<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control\Concerns;


use Corbinjurgens\Bouncer\Control as c;
use Corbinjurgens\Bouncer\Database\Models;


use Corbinjurgens\Bouncer\Control\Tools;
use Illuminate\Database\Eloquent\Model;

trait ProcessPermissions
{
	
	
	private function getTablePermission($table, $data, $only = null, $old = null){
		$permission = [];
		
		$run = [
			// 0 active						// 1 minimum					// 2 type		// 3 mode				// 4 closure
			['claim_permissions',			'claim_minimum',				'special',		'claim',				function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)->whereNull('entity_id')		->where('name', '__claim'); }],
			
			['general_permissions',			'general_minimum',				'general',		'general',				function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)->whereNull('entity_id')		->whereIn('name', $list); }],
			['forbid_general_permissions',	'forbid_general_minimum',		'general',		'forbid_general',		function($user, $list) use ($table){ return $user['forbidden_abilities']->where('entity_type', $table)->whereNull('entity_id')		->whereIn('name', $list); }],
			['specific_permissions',		'specific_minimum',				'specific',		'specific',				function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)->whereNotNull('entity_id')	->whereIn('name', $list); }],
			['forbid_specific_permissions', 'forbid_specific_minimum',		'specific',		'forbid_specific',		function($user, $list) use ($table){ return $user['forbidden_abilities']->where('entity_type', $table)->whereNotNull('entity_id')	->whereIn('name', $list); }],
			
			['anything_permissions',		'anything_minimum',				'anything',		'anything',				function($user, $list) use ($table){ return $user['abilities']			->where('entity_type', $table)								->where('name', '*'); }],
			
		];
		foreach($run as $param){
			
			if ( $this->checkModeFromOnly($only, $table, $param[0]) && $data[$param[0]] && $this->userCanBasic($this->target_authority, $data[$param[1]]) && $this->userCanBasic($this->current_authority, $data[$param[1]])){
				
				$current_old = @$old[$table][$param[0]];
				
				$list = $this->getTableAbilities($table, $param[3] ); // List of abilities available for this table according to the bouncercontrol config
				$presets = $list ? $this->getTablePresets($list) : []; // bouncercontrol 'table_compound_abilities' fit with the abilities from list if you chose to impement them in your ui, such as checking a preset will auto check the abilities
				$level = $data[ $param[1] ]; // each permission types minimum requirement for access as declared by bouncercontrol 'general_minimum' and simiar. You may use it in your ui to show if an ability is for anyone, or for admin only etc
				
				// Get users current permissions based on collecton filter, and lastly make sure to only get those abilities as declared by $list
				$permissions = $param[4]($this->target_permissions, $list);
				
				if ($param[2] == 'special'){
					$permissions = [
						'name' => $param[3],
						'checked' => $current_old['checked'] ?? $permissions->isNotEmpty(),
						'disabled' => !$this->currentUserCan('__'.$param[3], $table),
							'pivot_options' => $current_old['pivot_options'] ?? ($permissions->isNotEmpty() ? $permissions->first()->pivot->pivot_options : null),
					];
				}
				else if ($param[2] == 'anything'){
					
					$permissions = [
						'name' => '*',
						'checked' => $current_old['checked'] ?? $permissions->isNotEmpty(),
						'disabled' => !$this->currentUserCan('*', $table),
							'pivot_options' => $current_old['pivot_options'] ?? ($permissions->isNotEmpty() ? $permissions->first()->pivot->pivot_options : null),
					];
				}
				else if ($param[2] == 'specific'){
							
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
				$permission[ $param[0] ] = compact('list', 'presets', 'permissions', 'level');
			}
		}
		
		
		return $permission;
		
	}
	
	
	
	
	protected function parseAbilities($table = null, $forbid = false, $data = [], $mode = 'general', bool $special_abilities = false, array $name_only = null){
		$target_authority_abilities = $forbid ? $this->target_permissions['forbidden_abilities'] : $this->target_permissions['abilities'];
		
		$anything_mode = ($mode == 'anything');
		$table_is_model = ($table instanceof Model);
		
		$target_authority_abilities = $target_authority_abilities->unless($table_is_model, function($collection) use ($table){
			return $collection->where('entity_type', $table)->whereNull('entity_id');
		})->when($table_is_model, function($collection) use ($table){
			return $collection->where('entity_type', $table->getMorphClass());
		})->when(($table_is_model && $table->exists), function($collection) use ($table){
			return $collection->where('entity_id', $table->getKey());
		})->when(($table_is_model && !$table->exists), function($collection) use ($table){
			return $collection->whereNull('entity_id');
		})->when($anything_mode, function($collection){
			return $collection->whereIn('name', ['*']);
		})->unless($anything_mode, function($collection){
			return $collection->whereNotIn('name', ['*']);
		})->filter(function($item) use ($special_abilities){
			// Special abilities start with __
			return (\Str::startsWith($item->name, '__') == $special_abilities);
		})->when($special_abilities, function($collection) use ($mode){
			// If is parsing a special ability, only look for it
			return $collection->whereIn('name', ['__'. $mode]);
		})->when(!is_null($name_only), function($collection) use ($name_only){
			// To ensure only abilities declared can be removed
			return $collection->whereIn('name', $name_only);
		});
		
		$permissing_config = $mode . '_minimum';
		if ($forbid) $permissing_config = 'forbid_' . $permissing_config;
		// for now it should only be receiving instances, but could also be used for *
		$morph_class = ($table instanceof Model) ? $table->getMorphClass() : $table;
		$table_info = self::getTableInfo($morph_class);
		
		$level = @$table_info[$permissing_config];
		$sync = [];
		if (!$this->userCanBasic($this->current_authority, $level)){
			// Current user should't be able to modify this at all. Return null to leave it untouched
			return null;
		}
		if ($this->userCanBasic($this->target_authority, $level)){
			foreach($data as $ability => $value){
				if ($this->currentUserCan($ability, $table)){
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
		$missing_user_abilities = collect([]);
		if ($this->target_authority){
			$function = $forbid ? 'getForbiddenAbilities' : 'getAbilities';
			$missing_user_abilities = $this->target_authority->{$function}()
			->where('entity_type', $all_table->getMorphClass())
			->whereNotNull('entity_id')
			->whereNotIn('entity_id', array_keys($data))
			->when(is_array($name_only), function($collection) use ($name_only){
				return $collection->whereIn('name', $name_only);
			})
			->pluck('entity_id')
			->unique();
			
		}
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
			})->abilities($sync);
		}
		
	}
	
	
	
}