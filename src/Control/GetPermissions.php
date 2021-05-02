<?php

namespace Corbinjurgens\Bouncer\Control;

use Corbinjurgens\Bouncer\Control as c;
use Corbinjurgens\Bouncer\Database\Models;
use Corbinjurgens\Bouncer\Database\Role;

use Illuminate\Database\Eloquent\Model;

class GetPermissions
{
	use Concerns\DealsWithUsers;
	use Concerns\DealsWithTables;
	use Concerns\ProcessPermissions;
	
	
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
				'presets' => [ // List of presets as declared in bouncercontrol, and the abilities that each would entail. To be used as you please in a purely UI sense, eg auto selecting a set of abilities based on preset
					'maintainer' => [
						'create',
						'edit',
					],
					//...,
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
				'presets' => [ // List of presets as declared in bouncercontrol, and the abilities that each would entail. To be used as you please in a purely UI sense, eg auto selecting a set of abilities based on preset
					'maintainer' => [
						'create',
						'edit',
					],
					//...,
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
				'list' => [], // 'anything' by default has empty list of abilities, but claim and other will have list
				'presets' => [],
				'permissions' => [
						'name' => '*', // in the case of claim it will be "claim". The name here isn't use when returning form data, but is useful for display of form to get translation or display 
						'checked' => bool, // whether the target user has this ability
						'disabled' => bool, // whether the current user (the one who is editing the abilities) can do this action themself. If they cannot, disabled will be true and you can disable the input to show they cannot change it. (If you set setStrict to false via Bouncer::for($user)->setStrict(false)... then disabled will always be false and the user can make any change)
						'pivot_options' => array | null,
				],
				'level' => 1, // This specific table and permission types 'minimum' level. Use this feature if you only want certain permission types to only display to certain types of users. The level will display here if you want to show that this permission is any user, or only admin etc
			],	
			
		],
		//...more tables,
	
	]
	 
	 *
	 */
	public function getTablePermissions($get_old = true, $old = 'table_permissions')
	{
		$previous = [];
		if ($get_old === true){
			$previous = request()->old($old, []);
		}
		
		$only = $this->getTablesOnly();
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
	 * Get simple permissions
	 */ 
	public function getPermissions($get_old = true, $old = 'permissions'){
		$previous = [];
		if ($get_old === true){
			$previous = request()->old($old, []);
		}
		
		return $this->getPermission(null, $previous);
		
	}
	
	public function getRoles($get_old = true, $old = 'roles'){
		$previous = false;
		if ($get_old === true){
			$previous = request()->old($old, false);
		}
		
		if (is_null($this->target_authority)){
			throw new \Exception("You cannot use roles without an authority");
		}
		
		$current_authority_effective_roles = $this->current_authority->getEffectiveRoles()->pluck('id')->all();
		$target_authority_roles = $this->target_authority->roles->pluck('id')->all();
		$all_roles = Models::role()->pluck('title', 'id')->all();
		
		$roles = [];
		foreach($all_roles as $id => $title){
			$role = [];
			$role['name'] = $title;
			$role['disabled'] = !in_array($id, $current_authority_effective_roles);
			$role['checked'] = ($previous !== false)
				? ( is_array($previous) ? in_array($id, $previous) : false )
				: in_array($id, $target_authority_roles);
			$roles[$id] = $role;
		}
		
		return $roles;
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
	 * Ensure if you originally got the tablePermissions using an tablesOnly() selection,
	 * you also use the same tablesOnly() selection to put it back to avoid clearning
	 * a users entire permissions from the missing tables
	 */
	public function updateTablePermissions($request){
		// In case all items from one section have been deleted and we need to add it back so it will be processed;
		// And remove items that should not be allowed based on tablesOnly() settings
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
		
	}
	/**
	 * Update simple permissions from request
	 */
	public function updatePermissions($request){
		// In case all items from one section have been deleted and we need to add it back so it will be processed;
		// And remove items that should not be allowed based on only() settings
		$compare = $this->getPermissions(false);
		$request = array_intersect_key($request, $compare);
		foreach($compare as $mode => $data){
			if (!isset($request[$mode])){
				$request[$mode] = [];
			}
		}
		
		// Process the request
		foreach($request as $mode => $data){
			// Only touch these abilities as they are the ones given from getPermission()
			// This prevents user hacking abilities or prevents abilities accidentally deleting
			// abilities that exist but aren't shown in ui
			$name_only = array_intersect(self::getAbilities($mode, $this->target_type), array_column($compare[$mode] ?? [], 'name'));
			$this->processSimple($mode == 'forbid', $data, $name_only);
		}
	}
	
	public function updateRoles($request){
		$target_authority_roles = $this->target_authority->roles()->get();
		
		$current_user_roles = $this->current_authority->getEffectiveRoles();
		
		$result = [];
		
		foreach($request as $role_id){
			if ($current_user_roles->where('id', $role_id)->first()){
				// Current user can change
				$result[] = $role_id;
			}else if ($target_authority_roles->where('id', $role_id)->first()){
				// Current user cannot change, and the user already had it, so put it back
				$result[] = $role_id;
			}
		}
		
		$to_delete = $target_authority_roles->whereNotIn('id', $request)->pluck('id')->all();
		foreach($to_delete as $role_id){
			if (!$current_user_roles->where('id', $role_id)->first()){
				// Current user cannot change, put it back
				$result[] = $role_id;
			}
		}
		
		\Bouncer::sync($this->target_authority)->roles($result);
		
	}
	
	public function refresh(){
		if ($this->target_type == 'user'){
			\Bouncer::refreshFor($this->target_authority);
		}else{
			\Bouncer::refresh();
		}
	}
	
	/**
	 * Use and return this function inside a view controller. 
	 * For example 
	 * return \Bouncer::control()->for(User::first())->formExample( route('bouncer_form_post_example') );
	 */
	public function formExample(string $post_url = null){
		$authority = $this->target_authority;
		$mode = $this->target_type;
		
		$table_permissions = $this->getTablePermissions();
		$permissions = $this->getPermissions();
		
		$roles = null;
		
		if ($mode == 'user'){
			$roles = $this->getRoles();
		}
		
		
		return view('bouncer::example', compact('authority', 'table_permissions', 'permissions', 'roles', 'post_url' ,'mode'));
	}
	
	/**
	 * Use directly in routes to handle the submission from formExample()
	 * Current user will always be Auth::user(), target user will be decided from the form
	 */
	public function formExamplePost(\Illuminate\Http\Request $request){
		$form = $request->all();
		$authority = null;
		if ($form['mode'] == 'user'){
			$authority = Models::user()->find($form['id']);
		}else if ($form['mode'] == 'role'){
			$authority = Models::role()->find($form['id']);
		}
		$this->loadCurrent();
		$this->for($authority);
		
		$this->updateTablePermissions($form['table_permissions'] ?? []);
		$this->updatePermissions($form['permissions'] ?? []);
		
		if ($form['mode'] == 'user'){
			$this->updateRoles($form['roles'] ?? []);
		}
		
		$this->refresh();
		
		return back();
	}
	
	
	
	
}