<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control;
use Cache;
use Auth;
use App\Models\Table;


use Corbinjurgens\Bouncer\Control as c;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Relations\Relation;

class GetPermissions
{
	use Tools;
	
	private $current_user = null;
	
   	public function __construct($current_user = null){
		$this->current_user = $current_user;
	}
	public function as($user){
		$this->current_user = $user;
		return $this;
	}
	
	private $target_user;
	
	public function for($user){
		$this->target_user = $user;
		return $this;
	}
	
	private $tables;
	private $modes;
	
	/**
	 * Pass null for all tables (default) or pass array of table names as listed in 
	 * bouncercontrol config to only show some
	 * Can pass values to further define restrictions on mode
	 * eg
	 [
		'table1',
		'table2' => ['general_permissions', 'forbid_general_permissions']
		'table3' => null // if you always passed key value like this line, ensure its null unless you want to limit modes too
	 ]
	 * Pass null for mode, or indexed array only. If tables have values, the tables values will be prioritized
	 */
	public function only($tables = null, $modes = null){
		$this->tables = is_string($tables) ? [$tables] : $tables;
		$this->modes = is_string($modes) ? [$modes] : $modes;
		return $this;
	}
	private function getOnly(){
		if (!is_array($this->tables)){
			return null;
		}
		$only = [];
		$modes = $this->modes;
		foreach($this->tables as $key => $value){
			$table = $value;
			$options = $modes;
			if (is_string($key)) {
				$table = $key;
				$options = $value;
			}
			$only[$table] = $options;
		}
		return $only;
	}
	/**
	 * Pass result of getOnly, with table string, and the mode you want to test (eg 'general_permissions', 'forbid_general_permissions')
	 * To check if should get/process the mode
	 */
	private function checkModeFromOnly($only, $table = null, $mode = null){
		if (is_null($only) || is_null($table)){
			return is_array($this->modes) ? in_array($mode, $this->modes) : true;
		}
		return array_key_exists($table, $only) ? (is_array($only[$table]) ? in_array($mode, $only[$table]) : true ) : false;
		
	}
	
	private $strict_user_mode = true;
	
	/**
	 * Allow anyone with access to the permission page to give any permissions
	 * even if they themselves cannot do the action
	 * In the case you have multiple levels of admins it may be best to leave this as low level admin could
	 * make any other user do somthing they cant, or even edit their own permissions if the ui allows it
	 * By default it will be strict true to prevent this, but you can set strict false
	 */
	public function setStrict(bool $strict_user_mode = true){
		$this->strict_user_mode = $strict_user_mode;
		return $this;
	}
	
	private function getUserAdmin(){
		return $this->target_user ? $this->target_user->admin : 0;
	}
	
	public function getUserPermissions(){
		$permissions = $this->target_user->getAllAbilities();
		return self::groupPermissions($permissions);
	}
	
	/**
	 * Get all tables permission chart for specific user
	 */
	public function getTablePermissions()
	{
		$only = $this->getOnly();
		$permissions = [];
		$tables = (new C())->getTableInfo();
		if ( is_array($only) ){
			$tables = $tables->intersectByKeys($only);
		}
		foreach($tables as $table_name => $table){
			if ( $permission = $this->getTablePermission($table_name, $table) ){
				$permissions[$table_name] = $permission;
			}
		}
		
		if (
			(
				$this->checkModeFromOnly($only, '*', 'anything_permissions')
			)
				&& 
			
				config('bouncercontrol.everything') && $this->userCanBasic($this->target_user, config('bouncercontrol.everything_minimum')) && $this->userCanBasic($this->current_user, config('bouncercontrol.everything_minimum'))
			)
			{
			$check_permission = $this->target_user ? $this->target_user->getAbilities()->where('entity_type', '*')->where('name', '*') : collect([]);
			$permissions['*'] = [
						'name' => '*',
						'checked' => $check_permission->isNotEmpty(),
						'disabled' => !$this->currentUserCan('*', '*'),
							'pivot_options' => $check_permission->isNotEmpty() ? $check_permission->first()->pivot->pivot_options : null,
					];
		}
		return $permissions;
	}
	protected function toTableInstance($table, $id = null){
		if (is_null($table)){
			return null;
		}
		if ($table == '*'){
			return $table;
		}
		if ($table instanceof Model){
			return $table;
		}
		if ( ! class_exists ( $table ) ){
			$morphMap = Relation::morphMap();
			if (! empty($morphMap) && isset($morphMap[$table])) {
				$table = $morphMap[$table];
			}else{
				throw new InvalidArgumentException(
					"Table $table not found, be sure it exists and you are giving either full class path string or morph string"
				);
			}
		}
		$instance = new $table();
		if (!is_null($id)){
			$instance->{$instance->getKeyName()} = $id;
			$instance->exists = true;
		}
		return $instance;
		
		
	}
	protected function userCan($user, $ability, $table, $id = null){
		$instance = $this->toTableInstance($table, $id);
		return $user->can($ability, $instance) == true;
	}
	protected function currentUserCan($ability, $table, $id = null){
		if ($this->strict_user_mode === false){
			return True;
		}
		// Only run if set to check if current user has the right (so they dont give rights for things they dont have themselves etc)
		return $this->userCan($this->current_user, $ability, $table, $id);
	}
	protected function targetUserCan($ability, $table, $id = null){
		return $this->userCan($this->target_user, $ability, $table, $id);
	}
	/**
	 * Checks if according to the bouncercontrol config, the users admin level is even one 
	 * That allows the permission types (ie, permissions, anything, forbid_permissions) at all
	 * Check auto passes if config minimum checks are null, or if authority admin passes null (eg if giving permissions to role)
	 */
	protected function userCanBasic($user, $level){
		if (is_null($level)){
			return true;
		}
		$admin_level = $user ? $user->admin : 0;
		return is_null($admin_level) ? true : $admin_level >= $level;
	}
	public function getTablePermission($table, $data){
		$permission = [];
		
		$run = [
			// 0 active			// 1 minimum		// 2 type	// 3 mode	// 4 closure
			['claim_permissions', 'claim_minimum', 'special', 'claim', function($user) use ($table){ return $user->getAbilities()->where('entity_type', $table)->whereNull('entity_id')->where('name', '__claim'); }],
			['general_permissions', 'general_minimum', 'general', 'general', function($user) use ($table){ return $user->getAbilities()->where('entity_type', $table)->whereNull('entity_id'); }],
			['anything_permissions', 'anything_minimum', 'anything', 'anything', function($user) use ($table){ return $user->getAbilities()->where('entity_type', $table)->where('name', '*'); }],
			['forbid_general_permissions', 'forbid_general_minimum', 'general', 'forbid_general', function($user) use ($table){ return $user->getForbiddenAbilities()->where('entity_type', $table)->whereNull('entity_id'); }],
			['specific_permissions', 'specific_minimum', 'specific', 'specific', function($user) use ($table){ return $user->getAbilities()->where('entity_type', $table)->whereNotNull('entity_id'); }],
			['forbid_specific_permissions', 'forbid_specific_minimum', 'specific', 'forbid_specific', function($user) use ($table){ return $user->getForbiddenAbilities()->where('entity_type', $table)->whereNotNull('entity_id'); }],
			
		];
		$only = $this->getOnly();
		foreach($run as $param){
			
			if ( $this->checkModeFromOnly($only, $table, $param[0]) && $data[$param[0]] && $this->userCanBasic($this->target_user, $data[$param[1]]) && $this->userCanBasic($this->current_user, $data[$param[1]])){
				$list = $this->getTableAbilities($table, $param[3] );
				$presets = $list ? $this->getTablePresets($list) : [];
				$permissions = $this->target_user ? $param[4]($this->target_user) : collect([]);
				if ($param[2] == 'special'){
					$permissions = [
						'name' => $param[3],
						'checked' => $permissions->isNotEmpty(),
						'disabled' => !$this->currentUserCan('__'.$param[3], $table),
							'pivot_options' => $permissions->isNotEmpty() ? $permissions->first()->pivot->pivot_options : null,
					];
				}
				else if ($param[2] == 'anything'){
					
					$permissions = [
						'name' => '*',
						'checked' => $permissions->isNotEmpty(),
						'disabled' => !$this->currentUserCan('*', $table),
							'pivot_options' => $permissions->isNotEmpty() ? $permissions->first()->pivot->pivot_options : null,
					];
				}
				else if ($param[2] == 'specific'){
							
							
							
					$ids = $permissions->pluck('entity_id')->unique();
					$permissions = $ids->mapWithKeys(function($id) use ($permissions, $table, $list){
						
						return [$id => array_map(function($item) use ($id, $permissions, $table){
							$permission = $permissions->where('name', $item)->where('entity_id', $id)->first();
			
							return [
								'name' => $item,
								'checked' => $permission == True,
								'disabled' => !$this->currentUserCan($item, $table, $id),
									'pivot_options' => $permission ? $permission->pivot->pivot_options : null,
							];
						}, $list)];
					})->all();
					
					
				}else{
					$permissions = array_map(function($item) use ($permissions, $table){
						$permission = $permissions->where('name', $item)->first();
						return [
							'name' => $item,
							'checked' => $permission == True,
							'disabled' => !$this->currentUserCan($item, $table),
								'pivot_options' => $permission ? $permission->pivot->pivot_options : null,
						];
					}, $list);
				}
				$permission[ $param[0] ] = compact('list', 'presets', 'permissions');
			}
		}
		
		
		return $permission;
		
	}
	/**
	 * Pass array from post or get with array or tables
	 * With structure
	[
		'table' => [
			'anything_permissions' => [
				'checked' => bool,
				'pivot_options' => []
			],
			
			'general_permissions' => [
				'create' => [
					'checked' => bool,
					'pivot_options' => []
				],
			],
			'forbid_general_permissions' => ...
			
			'specific_permissions' => [
				100 => [
					'create' => [
						'checked' => bool,
						'pivot_options' => []
					],
				],
			]
			'forbid_specific_permissions' => ...
		],
		...,
		'*' => [
			'checked' => bool,
			'pivot_options' => []
		]
	]
	 * Ensure if you originally got the tablePermissions using an only() selection,
	 * you also use the same only() selection to put it back to avoid clearning
	 * a users entire permissions the missing tables
	 */
	public function updateAbilitiesRequest($request){
		// In case all items from one section have been deleted and we need to add it back so it will be processed;
		// And remove items that should not be allowed based on only() settings
		$compare = $this->getTablePermissions();
		$request = array_intersect_key($request, $compare);
		foreach($compare as $table => $data){
			$request[$table] = array_intersect_key($request[$table] ?? [], $data);
			if ($table !== '*'){
				foreach($data as $mode => $values){
					if (!isset($request[$table][$mode])){
						$request[$table][$mode] = [];
					}
				}
			}
		}
		// Process
		foreach($request as $table => $data){
			if ($table == '*'){
				$this->processEverything($data);
			}else{
				foreach($data as $mode => $values){
					if ( in_array($mode, ['general_permissions', 'forbid_general_permissions']) ){
						$this->processGeneral($table, ($mode == 'forbid_general_permissions'), $values);
					}else if ( in_array($mode, ['specific_permissions', 'forbid_specific_permissions']) ){
						$this->processSpecifics($table, ($mode == 'forbid_specific_permissions'), $values);
					}else if ($mode == 'anything_permissions'){
						$this->processAnything($table, $values);
					}else if ($mode == 'claim_permissions'){
						$this->processSpecial($table, $values, $mode);
					}
				}
			}
		}
		\Bouncer::refreshFor($this->target_user);
	}
	protected function parseAbilities($table = null, $forbid = false, $data = [], $mode = 'general'){
		$target_user_abilities = collect([]);
		if ($this->target_user){
			$function = $forbid ? 'getForbiddenAbilities' : 'getAbilities';
			$target_user_abilities = $this->target_user->{$function}()->when((is_string($table) || is_null($table)), function($collection) use ($table){
				return $collection->where('entity_type', $table);
			})->when(($table instanceof Model), function($collection) use ($table){
				return $collection->where('entity_type', $table->getMorphClass());
			})->when(($table instanceof Model && $table->exists), function($collection) use ($table){
				return $collection->where('entity_id', $table->getKey());
			});
		}
		$permissing_config = $mode . '_minimum';
		if ($forbid) $permissing_config = 'forbid_' . $permissing_config;
		// for now it should only be receiving instances, but could also be used for *
		$morph_class = ($table instanceof Model) ? $table->getMorphClass() : $table;
		$table_info = (new C())->getTableInfo($morph_class);
		
		$level = @$table_info[$permissing_config];
		$sync = [];
		if (!$this->userCanBasic($this->current_user, $level)){
			// Current user should't be able to modify this at all
			return null;
		}
		if ($this->userCanBasic($this->target_user, $level)){
			foreach($data as $ability => $value){
				if ($this->currentUserCan($ability, $table)){
					if (@$value['checked'] == true){
						// make change as current user can
						$pivot = [];
						if ( array_key_exists('pivot_options', $value) ) $pivot['pivot_options'] = $value['pivot_options'];
						$sync[$ability] = ['pivot' => $pivot];
					}
				}else if ($target_user_abilities->firstWhere('name', $ability)){
					// current user cannot make change so set it back if it already existed
					$sync[] = $ability;
				}
			}
			$deleting = $target_user_abilities->whereNotIn('name', array_keys($data));
			foreach($deleting as $delete){
				if (!$this->currentUserCan($delete['name'], $table)){
					// current user dosn't have rights to the item so thereforce cannot delete another users, put it back
					$sync[] = $delete['name'];
				}
			}	
		}else{
			// force target users abilities empty as they should not have these abilities
			return [];
		}
		return $sync;
		
	}
	protected function processGeneral($table = null, $forbid = false, $data = []){
		$table = $this->toTableInstance($table);
		//dd($table);
		$sync = $this->parseAbilities( $table, $forbid, $data, 'general' );
		if (is_array($sync)){
			$mode = $forbid ? 'forbiddenAbilities' : 'abilities';
			// Using whereModelStrict with new instance to only sync items that are matching the model and have no entity_id
			\Bouncer::sync($this->target_user)->whereModelStrict($table)->$mode($sync);
		}
		
		
	}
	/**
	 * An ability that is a single check (single ability such as '__claim'), with options
	 */
	protected function processSpecial($table = null, $data = [], $mode = 'claim_permissions'){
		$table = $this->toTableInstance($table);
		
		$special = explode('_', $mode);
		array_pop($special);
		$special = implode('_', $special);
		
		// force to array of itselt to support parseAbilities
		$data = ['__'.$special => $data];
		$sync = $this->parseAbilities( $table, false, $data, $special );
		if (is_array($sync)){
			// Using whereModelStrict with new instance to only sync items that are matching the model and have no entity_id
			\Bouncer::sync($this->target_user)->whereModelStrict($table)->specialScope($special)->abilities($sync);
		}
		
		
	}
	protected function processSpecifics($table = null, $forbid = false, $data = []){
		// First fine ids missing and add them back so they can be checked if ok to delete
		$mode = $forbid ? 'forbiddenAbilities' : 'abilities';
		$all_table = $this->toTableInstance($table);
		$missing_user_abilities = collect([]);
		if ($this->target_user){
			$function = $forbid ? 'getForbiddenAbilities' : 'getAbilities';
			$missing_user_abilities = $this->target_user->{$function}()->where('entity_type', $all_table->getMorphClass())->whereNotNull('entity_id')->whereNotIn('entity_id', array_keys($data))->pluck('entity_id');
			
		}
		foreach($missing_user_abilities as $id){
			$data[$id] = [];
		}
		
		// Sync each table row
		foreach($data as $id => $abilities){
			$current_table = $this->toTableInstance($table, $id);
			$sync = $this->parseAbilities( $current_table, $forbid, $abilities, 'specific' );
			if (is_array($sync)){
				
				// Using whereModelStrict with existing model to only sync items that are matching the model and have same entity_id
				\Bouncer::sync($this->target_user)->whereModelStrict($current_table)->$mode($sync);
				
			}
		}
		
	}
	protected function processAnything($table, $value){
		$current_table = $this->toTableInstance($table);
		if ($this->currentUserCan('*', $current_table->getMorphClass())){
			$sync = [];
			if (@$value['checked'] == true){
				$pivot = [];
				if ( array_key_exists('pivot_options', $value) ) $pivot['pivot_options'] = $value['pivot_options'];
				$sync['*'] = ['pivot' => $pivot];
			}
			\Bouncer::sync($this->target_user)->whereModel($current_table)->whereCustom(function($query) use ($current_table){
				$query->where($query->qualifyColumn('name'), '*');
			})->abilities($sync);
		}
		
	}
	protected function processEverything($value){
		if ($this->currentUserCan('*', '*')){
			$sync = [];
			if (@$value['checked'] == true){
				$pivot = [];
				if ( array_key_exists('pivot_options', $value) ) $pivot['pivot_options'] = $value['pivot_options'];
				$sync['*'] = ['pivot' => $pivot];
			}
			
			\Bouncer::sync($this->target_user)->whereModel('*')->whereCustom(function($query){
				$query->where($query->qualifyColumn('name'), '*');
			})->abilities($sync);
		}
		
	}
	
	/**
	 * Get a tables abilities as declared in bouncercontrol config
	 */
	public function getTableAbilities($table, $mode = 'general'){
		$only_options = [
			'general' => true,
			'specific' => true,
			'forbid_general' => true,
			'forbid_specific' => true,
			'anything' => true,
			'claim' => true,
		];
		$default_options = $only_options;
		$abilities = $this->optionsArray( config('bouncercontrol.table_abilities', []), $default_options ) +
			$this->optionsArray( (new C())->getTableInfo($table)['abilities'] ?? [], $default_options );
			
		$comparison_options = $only_options;
		foreach($abilities as $key => $value){
			if (@$value[$mode] == false){
				unset( $abilities[$key] );
			}
		}
		
		return array_keys($abilities);
	}
	
	/**
	 * From a specific tables list as from getTableAbilities(), get the presets
	 */
	public function getTablePresets($list = []){
		$prepared = [];
		$presets = config('bouncercontrol.table_compound_abilities');
		foreach($presets as $preset => $abilities){
			$prepared[$preset] = array_intersect($abilities, $list);
		}
		return $prepared;
		
	}
	
	
	
	
	
}