<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tables & Permissions
    |--------------------------------------------------------------------------
    |
    | All info about each tables abilities
	| 
	| In the 'tables' array, you can key each table by its morph, or by full class string,
	| but it must match whatever you are using in your ability morphs
    |
    */

	
	'table_defaults' => [
		
		'general_permissions' => false, // whether it is table that has specific permissions for all items, eg can delete any post unrestricted by id
		'general_minimum' => 1, // 0,1,2... minium admin level to be able to access this at all, 
						// eg by default its only open to those atleast admin as declared on User model 'admin' attribute, as it would be strange to allow normal users to manage anything
						// To use this feature set a getAdminAttribute() function on user model to return 0 for user,1 admin, 2 super admin for example, or directly use database column with integer
						// If you dont want this checked and always pass, set to null
		
		'forbid_general_permissions' => false, // whether table allows forbiding actions to all items eg user cannot edit any post regardless of id. Forbids take preference over allow. Kind of unnessary to forbid generally
		'forbid_general_minimum' => 1, //
		
		'specific_permissions' => false, // whether it is a table that allows permissions for specific entries, eg post of id 143 
		'specific_minimum' => 0,	// 0,1,2. minium admin level to be able to access specfic permissions
									//
		
		'forbid_specific_permissions' => false, // whether table allows forbiding actions to specific items eg user cannot edit post of id 143. Forbids take preference over allow.
		'forbid_specific_minimum' => 0,	// 
		
		'anything_permissions' => false, // whether it is a table that has permission to do any action to any specific table, eg can create, delete or other action to any post unrestricted by id
		'anything_minimum' => 1, //
		
		// Special
		'claim_permissions' => false,	// Whether it can make use of the special 'claim' feature TODO. Used to create an item and then auto set the user
								// to have permissions to it, and also check if the user has a claim limit and if they reached it deny further claim
		'claim_minimum' => 0, //
		
		// 
		'abilities' => [
			// 'publish',
			// 'duplcate' => ['specific' => false]
		],// extra permission abiltiies outside of usual 'edit' and 'delete', which can be added below inside 'table_abilities'
	
	],
	
	'everything' => true,// whether there can be an option for every table as *
	'everything_minimum' => 1,
	
    'tables' => [
		// Declare each table, and state only differences from the table_defaults array
		/* 
		'posts' => [
			
			'permissions' => True,
			'abilities' => [
				'publish', // eg the user has ability to Set a post to public after a change 
			],
		],
		...,
		...,
		*/
    ],
	
    /*
    |--------------------------------------------------------------------------
    | General Table Abilities
    |--------------------------------------------------------------------------
    |
    | Declare abilties such as create, edit
	| Table specific abilitis should be declared in tables above
	| Options can be specific as array
	| By default an ability is used for any mode of ability (ability for any item, or specfic item etc)
	| but you can specify certain ones as false
	|
	|	general bool
	| 	specific bool
	|	forbid_general bool
	|	forbid_specific bool
	|	anything bool
	|	claim bool
    |
    */
	'table_abilities' => [
		'create' => ['specific' => false, 'forbid_specific' => false, 'claim' => false], // Because you cant create an item thats already created and so it should only be an option for general
		//'view', // not really used for me as permissions are exclusively for admin page in my situation but if you want to
		'edit',
		'delete',
	],
	
	// Preset pseudo "Roles" that can do multiple other things, or describe a set of abilities
	// I couldn't conceive a simple way to break roles up into table specific (eg post maintainer, manager, admin), and then group roles within roles (eg admin role is a post and comment admin)
	// So this is my solution which can be used purely in a ui sense as shortcut to select the preset abilities
	'table_compound_abilities' => [
		'maintainer' => [
			'create',
			'view',
			'edit'
		],
		'manager' => [
			'create',
			'view',
			'edit',
			
			'publish',
		],
		'admin' => [
			'create',
			'view',
			'edit',
			'delete',
			
			'publish',
			
		]
	],
	
	// Free abilties not connected to a table or an item TODO
	'abilities' => [
		// 'make_user_admin',
	],

];
