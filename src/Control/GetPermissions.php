<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control;


use Corbinjurgens\Bouncer\Control as c;
use Corbinjurgens\Bouncer\Database\Models;


use Illuminate\Database\Eloquent\Model;

class GetPermissions
{
	use Concerns\DealsWithUsers, Concerns\DealsWithTables, Concerns\ProcessPermissions;
	
	
   	public function __construct($current_authority = null){
		$this->as($current_authority);
	}
	/**
	 * Who is modifying the permissions
	 * Based on tis value, it will restrict what permissions can be modified to
	 * prevent a user from given themself or others abilities they dont have
	 */
	public function as(Model $user = null){
		$this->loadCurrent($user);
		return $this;
	}
	
	/**
	 * Who is having  their abilities modified
	 * A user model, or a role as model or string
	 */
	public function for($authority){
		$this->loadTarget($authority);
		return $this;
	}
	
	
	/**
	 * Get all tables permission chart for specific user
	 * Pass a key as $old to point to your form input and apply old values if they exist
	 *
	[
		'table morph or full class name' => [
			'general_permissions' => [ // 'forbid_general_permissions' follows same format
				'list' => [ // List of abilities available for this table and permission type as declared in bouncercontrol config
					'create',
					'edit',
					'delete',
				],
				'presents' => [ // List of presets as declared in bouncercontrol, and the abilities that each would entail. To be used as you please in a purely UI sense, eg auto selecting a set of abilities based on preset
					'maintainer' => [
						'create',
						'edit',
					],
					...,
				],
				'permissions' => [
					[
						'name' => 'create',
						'checked' => bool, // whether the target user has this ability
						'disabled' => bool, // whether the current user (the one who is editing the abilities) can do this action themself. If they cannot, disabled will be true and you can disable the input to show they cannot change it. (If you set setStrict to false via Bouncer::for($user)->setStrict(false)... then disabled will always be false and the user can make any change)
						'pivot_options' => array | null,
					],
					[
						'name' => 'edit',
						'checked' => bool,
						'disabled' => bool,
						'pivot_options' => array | null,
					],
					[
						'name' => 'delete',
						'checked' => bool,
						'disabled' => bool,
						'pivot_options' => array | null,
					],
				],
				'level' => 1, // This specific table and permission types 'minimum' level. Use this feature if you only want certain permission types to only display to certain types of users. The level will display here if you want to show that this permission is any user, or only admin etc
			],
			
			'specific_permissions' => [ // 'forbid_specific_permissions' follows same format
				'list' => [ // List of abilities available for this table and permission type as declared in bouncercontrol config
					'create',
					'edit',
					'delete',
				],
				'presents' => [ // List of presets as declared in bouncercontrol, and the abilities that each would entail. To be used as you please in a purely UI sense, eg auto selecting a set of abilities based on preset
					'maintainer' => [
						'create',
						'edit',
					],
					...,
				],
				'permissions' => [
					100 => [ // id of the item
						[
							'name' => 'create',
							'checked' => bool, // whether the target user has this ability
							'disabled' => bool, // whether the current user (the one who is editing the abilities) can do this action themself. If they cannot, disabled will be true and you can disable the input to show they cannot change it. (If you set setStrict to false via Bouncer::for($user)->setStrict(false)... then disabled will always be false and the user can make any change)
							'pivot_options' => array | null,
						],
						[
							'name' => 'edit',
							'checked' => bool,
							'disabled' => bool,
							'pivot_options' => array | null,
						],
						[
							'name' => 'delete',
							'checked' => bool,
							'disabled' => bool,
							'pivot_options' => array | null,
						],
					]
				],
				'level' => 1, // This specific table and permission types 'minimum' level. Use this feature if you only want certain permission types to only display to certain types of users. The level will display here if you want to show that this permission is any user, or only admin etc
			],	
			'anything_permissions' => [ // all other permission types follow this layout such as 'claim'
				'list' => [],
				'presents' => [],
				'permissions' => [
						'name' => '*', // in the case of claim it will be "claim". The name here isn't use when returning form data, but is useful for display of form to get translation or display 
						'checked' => bool, // whether the target user has this ability
						'disabled' => bool, // whether the current user (the one who is editing the abilities) can do this action themself. If they cannot, disabled will be true and you can disable the input to show they cannot change it. (If you set setStrict to false via Bouncer::for($user)->setStrict(false)... then disabled will always be false and the user can make any change)
						'pivot_options' => array | null,
				],
				'level' => 1, // This specific table and permission types 'minimum' level. Use this feature if you only want certain permission types to only display to certain types of users. The level will display here if you want to show that this permission is any user, or only admin etc
			],	
			
		],
		...more tables,
	
	]
	 
	 *
	 */
	public function getTablePermissions($get_old = true, $old = 'permissions')
	{
		$previous = null;
		if ($get_old === true){
			$previous = request()->old($old);
		}
		
		$only = $this->getOnly();
		$permissions = [];
		$tables = self::getTableInfo();
		if ( is_array($only) ){
			$tables = $tables->intersectByKeys($only);
		}
		foreach($tables as $table_name => $table){
			if ( $permission = $this->getTablePermission($table_name, $table, $only, $previous) ){
				$permissions[$table_name] = $permission;
			}
		}
		return $permissions;
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
	]
	 * Ensure if you originally got the tablePermissions using an only() selection,
	 * you also use the same only() selection to put it back to avoid clearning
	 * a users entire permissions from the missing tables
	 */
	public function updateAbilitiesRequest($request){
		// In case all items from one section have been deleted and we need to add it back so it will be processed;
		// And remove items that should not be allowed based on only() settings
		$compare = $this->getTablePermissions(false);
		$request = array_intersect_key($request, $compare);
		foreach($compare as $table => $data){
			$request[$table] = array_intersect_key($request[$table] ?? [], $data);
			foreach($data as $mode => $values){
				if (!isset($request[$table][$mode])){
					$request[$table][$mode] = [];
				}
			}
		}
		
		
		// Process the request
		foreach($request as $table => $data){
			foreach($data as $mode => $values){
				
				// Only touch these abilities as they are the ones given from getTablePermission()
				// This prevents user hacking abilities or prevents abilities accidentally deleting
				// abilities that exist but aren't shown in ui
				$name_only = @$compare[$table][$mode]['list'];
				
				if ( in_array($mode, ['general_permissions', 'forbid_general_permissions']) ){
					$this->processGeneral($table, ($mode == 'forbid_general_permissions'), $values, $name_only);
				}else if ( in_array($mode, ['specific_permissions', 'forbid_specific_permissions']) ){
					$this->processSpecifics($table, ($mode == 'forbid_specific_permissions'), $values, $name_only);
				}else if ($mode == 'anything_permissions'){
					$this->processAnything($table, $values);
				}else{
					// Special. Only is allow not forbid
					$this->processSpecial($table, $values, $mode);
				}
			}
			
		}
		if ($this->target_type == 'user'){
			\Bouncer::refreshFor($this->target_authority);
		}else{
			\Bouncer::refresh();
		}
		
	}

	
	
	
	
	
	
}