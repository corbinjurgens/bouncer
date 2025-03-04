<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control\Concerns;


use Corbinjurgens\Bouncer\Control as c;
use Corbinjurgens\Bouncer\Database\Models;

use Corbinjurgens\Bouncer\Clipboard;


use Corbinjurgens\Bouncer\Control\Tools;
use Illuminate\Database\Eloquent\Model;

trait ProcessPermissions
{
	
	
	protected function getTablePermission($table, $data, $only = null, $old = []){
		$permission = [];
		
		$run = [
			// 0 name				// 1 type		// 2 closure
			['claim',				'special',		function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)->whereNull('entity_id')		->where('name', '__claim'); }],
			
			['general',				'general',		function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)->whereNull('entity_id')		->whereIn('name', $list); }],
			['forbid_general',		'general',		function($user, $list) use ($table){ return $user['forbidden_abilities']->where('entity_type', $table)->whereNull('entity_id')		->whereIn('name', $list); }],
			['specific',			'specific',		function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)->whereNotNull('entity_id')	->whereIn('name', $list); }],
			['forbid_specific', 	'specific',		function($user, $list) use ($table){ return $user['forbidden_abilities']->where('entity_type', $table)->whereNotNull('entity_id')	->whereIn('name', $list); }],
			
			['anything',			'anything',		function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)								->where('name', '*'); }],
			
		];
		
		foreach($run as $param){
			
			$name = $param[0] . '_permissions';
			$mode = $param[0];
			$minimum = $param[0] . '_minimum';
			$type = $param[1];
			$closure = $param[2];
			
			if ( 
				$data[ $name ] && 
				$this->checkModeFromOnly($only, $table, $name) && 
				$this->userCanBasic($this->target_authority, $data[$minimum]) && 
				$this->userCanBasic($this->current_authority, $data[$minimum])
			){
				
				$current_old = $old[$table][$name] ?? null;
				
				$list = $this->getTableAbilities($table, $mode ); // List of abilities available for this table according to the bouncercontrol config
				$presets = $list ? $this->getTablePresets($list) : []; // bouncercontrol 'table_compound_abilities' fit with the abilities from list if you chose to impement them in your ui, such as checking a preset will auto check the abilities
				$level = $data[ $minimum ]; // each permission types minimum requirement for access as declared by bouncercontrol 'general_minimum' and simiar. You may use it in your ui to show if an ability is for anyone, or for admin only etc
				
				// Get users current permissions based on collecton filter, and lastly make sure to only get those abilities as declared by $list
				$permissions = $closure($this->target_permissions, $list);
				
				if ($type == 'special'){
					$permissions = [
						'name' => $mode,
						'checked' => $current_old['checked'] ?? $permissions->isNotEmpty(),
						'disabled' => !$this->currentUserCan('__'.$mode, $table),
							'pivot_options' => $current_old['pivot_options'] ?? ($permissions->isNotEmpty() ? $permissions->first()->pivot->pivot_options : null),
					];
				}
				else if ($type == 'anything'){
					
					$permissions = [
						'name' => '*',
						'checked' => $current_old['checked'] ?? $permissions->isNotEmpty(),
						'disabled' => !$this->currentUserCan('*', $table),
							'pivot_options' => $current_old['pivot_options'] ?? ($permissions->isNotEmpty() ? $permissions->first()->pivot->pivot_options : null),
					];
				}
				else if ($type == 'specific'){
							
					$ids = !is_null($current_old) ? collect(array_keys($current_old)) : $permissions->pluck('entity_id')->unique();
					$permissions = $ids->mapWithKeys(function($id) use ($permissions, $table, $list, $current_old){
						$current_old_id = @$current_old[$id];
						return [$id => array_map(function($item) use ($id, $permissions, $table, $current_old_id){
							$permission = $permissions->where('name', $item)->where('entity_id', $id)->first();
			
							return [
								'name' => $item,
								'checked' => $current_old_id[$item]['checked'] ?? $permission == True,
								'disabled' => !$this->currentUserCan($item, $table, $id),
									'pivot_options' => $current_old_id[$item]['pivot_options'] ?? ($permission ? $permission->pivot->pivot_options : null),
							];
						}, $list)];
					})->all();
					
					
				}else{
					// General
					$permissions = array_map(function($item) use ($permissions, $table, $current_old){
						$permission = $permissions->where('name', $item)->first();
						return [
							'name' => $item,
							'checked' => $current_old[$item]['checked'] ?? $permission == True,
							'disabled' => !$this->currentUserCan($item, $table),
								'pivot_options' => $current_old[$item]['pivot_options'] ?? ($permission ? $permission->pivot->pivot_options : null),
						];
					}, $list);
				}
				$permission[ $name ] = compact('list', 'presets', 'permissions', 'level');
			}
		}
		
		
		return $permission;
		
	}
	
	protected function getPermission($only = null, $old = []){
		$group = [];
		
		$run = [
			// 0 mode			// 1 get user abilities
			['allow',			function($user, $list) { return $user['abilities']				->whereNull('entity_type')		->whereIn('name', $list); }],
			['forbid',			function($user, $list) { return $user['forbidden_abilities']	->whereNull('entity_type')		->whereIn('name', $list); }],
			
		];
		foreach($run as $param){
			
				$mode = $param[0];
				$ability_closure = $param[1];
				
				$mode_old = $old[ $mode ] ?? [];
				
				$permission = [];
				
				$list = self::getAbilities( $mode, $this->target_type ); // List of abilities available according to the bouncercontrol config
				$permissions = $ability_closure($this->target_permissions, $list);
				$abilities_info = self::getAbilitiesInfo();
				
				
				foreach($list as $ability){
					$current_old = $mode_old[$ability] ?? [];
					$current_permission = $permissions->where('name', $ability);
					$level = $abilities_info[$ability]['minimum'] ?? null;
					if ($this->userCanBasic($this->target_authority, $level)){
						$permission[] = [
							'name' => $ability,
							'checked' => $current_old['checked'] ?? $current_permission->isNotEmpty(),
							'disabled' => !$this->currentUserCan($ability),
								'pivot_options' => $current_old['pivot_options'] ?? ($permissions->isNotEmpty() ? $permissions->first()->pivot->pivot_options : null),
						];
					}
					
				}
				$group[ $mode ] = $permission;
			
		}
		
		
		return $group;
		
	}
	
	
	
	protected function parseAbilities($table = null, $forbid = false, $data = [], $mode = 'general', bool $special_abilities = false, array $name_only = null){
		// Filter user abilities for current 
		$pipe = Clipboard::collectionPipe($table, true, null, $name_only, ($mode == 'anything') ? null : ['*'], true);
		$target_authority_abilities = $forbid ? $this->target_permissions['forbidden_abilities'] : $this->target_permissions['abilities'];
		$target_authority_abilities = $target_authority_abilities
			->pipe($pipe)
			->when($special_abilities, function($collection) use ($mode){
				// If is parsing a special ability, only look for it
				return $collection->whereIn('name', ['__'. $mode]);
			});
			
		
		if ($mode === 'simple'){
			// In simple mode get levels as array of abilities
			$abilities_info = self::getAbilitiesInfo();
			$level = null;// force allowed
			$levels = array_combine(array_keys($abilities_info), array_column($abilities_info, 'minimum'));
		}else{
			$minimum = (($forbid) ? 'forbid_' : '') . $mode . '_minimum';
			$table_info = self::getTableInfo($table);
			$level = @$table_info[$minimum];
		}
		
		$sync = [];
		
		if (!$this->userCanBasic($this->current_authority, $level)){
			// Current user should't be able to modify this at all. Return null to leave it untouched
			return null;
		}
		
		if ($this->userCanBasic($this->target_authority, $level)){
			foreach($data as $ability => $value){
				if ($mode === 'simple'){
					$current_level = $levels[$ability] ?? null;
					// target user shouldn't have access to this ability, force 
					if (!$this->userCanBasic($this->target_authority, $current_level)){
						continue;
					}
				}
				
				if (
					$this->currentUserCan($ability, $table)
				){
					if (@$value['checked'] == true){
						// make change as current user can
						$pivot = [];
						if ( array_key_exists('pivot_options', $value) ) $pivot['pivot_options'] = $value['pivot_options'];
						$sync[$ability] = ['pivot' => $pivot];
					}
				}else if ($target_authority_abilities->firstWhere('name', $ability)){
					// current user cannot make change so set it back if it already existed
					$sync[] = $ability;
				}
			}
			
			$deleting = $target_authority_abilities->whereNotIn('name', array_keys($data));
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
	
	protected function processGeneral($table = null, $forbid = false, $data = [], array $name_only = null){
		$table = $this->toTableInstance($table);
		$sync = $this->parseAbilities( $table, $forbid, $data, 'general', false, $name_only );
		
		if (is_array($sync)){
			$mode = $forbid ? 'forbiddenAbilities' : 'abilities';
			// Using whereModelStrict with new instance to only sync items that are matching the model and have no entity_id
			\Bouncer::sync($this->target_authority)->whereCustom(function($query) use ($name_only){
				if (is_array($name_only)){
					$query->whereIn($query->qualifyColumn('name'), $name_only);
				}
			})->whereModelStrict($table)->$mode($sync, ['scope' => true]);
		}
		
		
	}
	
	protected function processSpecifics($table = null, $forbid = false, $data = [], array $name_only = null){
		// First find ids missing and add them back so they can be checked if ok to delete
		$mode = $forbid ? 'forbiddenAbilities' : 'abilities';
		$all_table = $this->toTableInstance($table);
		
		$pipe = Clipboard::collectionPipe($all_table, true, 'specific', $name_only, null, true);
		$missing_user_abilities = $this->target_permissions[$forbid ? 'forbidden_abilities' : 'abilities']
			->pipe($pipe)
			->whereNotIn('entity_id', array_keys($data))
			->pluck('entity_id')
			->unique();
		
		foreach($missing_user_abilities as $id){
			$data[$id] = [];
		}
		
		// Sync each table row
		foreach($data as $id => $abilities){
			$current_table = $this->toTableInstance($table, $id);
			$sync = $this->parseAbilities( $current_table, $forbid, $abilities, 'specific', false, $name_only );
			if (is_array($sync)){
				
				// Using whereModelStrict with existing model to only sync items that are matching the model and have same entity_id
				\Bouncer::sync($this->target_authority)->whereCustom(function($query) use ($name_only){
					if (is_array($name_only)){
						$query->whereIn($query->qualifyColumn('name'), $name_only);
					}
				})->whereModelStrict($current_table)->$mode($sync, ['scope' => true]);
				
			}
		}
		
	}
	/**
	 * An ability that is a single check (single ability such as '__claim'), with options
	 * Special abilities are treated as separate modes and each have their own table setting in bouncercontrol
	 */
	protected function processSpecial($table = null, $data = [], $mode = 'claim_permissions'){
		$table = $this->toTableInstance($table);
		
		// Find special ability base name.
		$special = explode('_', $mode);
		array_pop($special);
		$special = implode('_', $special);
		
		// force to array of itself to support parseAbilities
		$data = ['__'.$special => $data];
		
		$sync = $this->parseAbilities( $table, false, $data, $special, true );
		if (is_array($sync)){
			// Using whereModelStrict with new instance to only sync items that are matching the model and have no entity_id
			\Bouncer::sync($this->target_authority)->whereModelStrict($table)->specialScope($special)->abilities($sync, ['scope' => true]);
		}
		
		
	}
	
	protected function processAnything($table, $value){
		$current_table = $this->toTableInstance($table);
		// force to array of itselt to support parseAbilities
		$data = ['*' => $value];
		
		$sync = $this->parseAbilities( $current_table, false, $data, 'anything' );
		if (is_array($sync)){
			\Bouncer::sync($this->target_authority)->whereModel($current_table)->whereCustom(function($query) use ($current_table){
				$query->where($query->qualifyColumn('name'), '*');
			})->abilities($sync, ['scope' => true]);
		}
		
	}
	
	protected function processSimple($forbid, $data = [], $name_only = []){
		$mode = $forbid ? 'forbiddenAbilities' : 'abilities';
		$data = array_intersect_key($data, array_flip($name_only));
		$sync = $this->parseAbilities( null, $forbid, $data, 'simple', false, $name_only);
		if (is_array($sync)){
			\Bouncer::sync($this->target_authority)->whereModelStrict(null)->$mode($sync, ['scope' => true]);
		}
	}
	
	
	/**
	 * Get simple abilities as declared in bouncercontrol config
	 */
	public static function getAbilitiesInfo(){
		$only_options = [
			'user' => true,
			'role' => true,
			'everyone' => true,
		];
		$restrictions = [
			'minimum' => null
		];
		$types = [
			'allow' => true,
			'forbid' => true,
		];
		$default_options = $only_options + $restrictions + $types;
		$abilities = self::optionsArray( config('bouncercontrol.abilities', []), $default_options );
		
		return $abilities;
	}
	
	public static function getAbilities($mode = 'allow', $authority_type = 'user'){
		$abilities = self::getAbilitiesInfo();
		foreach($abilities as $key => $value){
			if (@$value[$authority_type] == false){
				unset( $abilities[$key] );
			}
			if (@$value[$mode] == false){
				unset( $abilities[$key] );
			}
		}
		return array_keys($abilities);
	}
	
	
}