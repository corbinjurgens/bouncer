# Introduction

This is a fork of bouncer made to add features that seem to be missing in most roles/permissions like packages. 
 - Declare each tables available abilities.
 - Based on declared table abilities, get array output that can be used to build html forms for a specific authority (user, role, or everyone)
 - If you follow a certain form name formula to build your form, you can input the form data as is and it will apply the abilities to the authority
 - Based on who is currently editing an authorities abilities, limit what they can change based on what they themselves can do (ie. prevent a user from allowing another user to do something they cant, which would be useful in the case of admins that have different abilities)
 - Scope items based on what a user has access to by using Corbinjurgens\Bouncer\Database\Concerns\CanScope scopeWhereCan. [Untested and very messy code]
 - Pivot options. Up until now the only pivot column that mattered was 'forbidden' and 'scope'. Now upon allow/forbid you can declare pivot columns such as 'pivot_options', and any existing abilities will have their pivot updated. This also means getAbilities() function had to be updated to get the best matching pivot. Pivot columns are free to be customized, but they are inteded to be used for special abilities like 'claim' below
 - Special abilities (saved with "__" prefix) such as the "claim" ability which allows a user to "create" an item, and upon creation, automatically apply a set of permissions to it (permissions are declared in the pivot "pivot options" json column as 'abilities' array)
 - When syncing a users abilities, sync only a specific model (eg abilities for Posts and any post) or only a specific result (eg abilities for a specific post). [TODO allow syncing of the same ability name ie for all Posts abilities that have any specific id]


# Sync with UI

## Preparation

First, see bouncercontrol config file and edit your tables as shown in the config

```
php artisan vendor:publish --tag="bouncer.controlconfig"
```

## Example templates

Two functions have been added to aid you in displaying example pages. To view properly you must be a logged in user, otherwise all abilities will be disabled and you wont be able to edit them. Set yourself as able to do anything via `\Bouncer::allow($user)->everything()` and maybe assign yourself some roles. You can only edit a users abilities or roles that you yourself have, so trying to edit your own permissions and roles will behave strangely. If you want to make use of the permisssions template, publish it with `php artisan vendor:publish --tag="bouncer.templates"`

Add something like the following to your routes to quickly get an idea for how to display your permissions

```php
Route::get('/test', 					function(){
	$authority = null;// Test permissions for everyone
	$authority = App\Models\User::first();// Test permissions for a user
	//$authority = \Corbinjurgens\Bouncer\Database\Models::role()->first();// Test permissions for a role
	return \Bouncer::control()->for($authority)->formExample(route('test2'));// the formExample function inside control area returns views for you. Note this example doesn't support changing "as()", it will be curren logged in user

})->name('test');

// Use the formExamplePost function directly as follows. It will automatically 
Route::post('/test2', 					[\Corbinjurgens\Bouncer\Control\GetPermissions::class, 'formExamplePost']	)->name('test2');
```

## Get form array output

Basic usage
```php
$user = User::first(); // Can also be a Role as Role::first() or 'admin' or null for everyone
$form_data = Bouncer::control()->for($user)->getTablePermissions();
```
For specific tables and permission types, use 'only' [IF YOU USE ONLY, BE SURE TO USE SAME ONLY LATER WHEN INPUTTING DATA TO updateAbilitiesRequest($form_data)]
```php
$only_tables = ['posts', 'comments']; // or NULL or all tables
$only_types = ['general_permissions', 'specific_permissions']; // see bouncercontrol config to types, or NULL for all types
$form_data = Bouncer::control()->for($user)->only($only_tables, $only_types)->getTablePermissions();
```
$only_tables can also be a key with a value of types eg in the following example comments will show 'general_permissions', 'specific_permissions' but posts will show only 'general_permissions'
```php
$only_tables = ['posts' => ['general_permissions'], 'comments']; // or NULL or all tables
$only_types = ['general_permissions', 'specific_permissions']; // see bouncercontrol config to types, or NULL for all types
$form_data = Bouncer::control()->for($user)->only($only_tables, $only_types)->getTablePermissions();
```

By default getTablePermissions() will also get and apply old() values. You can change this behaviour by setting
```php
$form_data = Bouncer::control()->for($user)->getTablePermissions(false);
```
Or if you create your form with a base name prefix you can pass that like
```php
$form_data = Bouncer::control()->for($user)->getTablePermissions(true, 'permissions');
```

The output array will look like the following

```php
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
				'presets' => [ // List of presets as declared in bouncercontrol, and the abilities that each would entail. To be used as you please in a purely UI sense, eg auto selecting a set of abilities based on preset
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
		...more tables,
	
	]
```

## Set form array input

The input array must look like the following, in this example we have 'anything_permissions', 'general_permissions', 'forbid_general_permissions', 'specific_permissions' permissions types as generated by bouncercontrol config
If you create a form that doesn't result in the following layout as is, you must modify it before you input it. 
If entire tables are missing, or table permission types are missing this is OK as they will be added back based on what was expected from getTablePermissions()

WARNING

If you used an only() when getting the output array, you must use the same only() setting when inputting otherwise any missing tables or permission types will be assumed as deleted

```php
	[
		'table morph or full class name' => [
			'anything_permissions' => [
				'checked' => bool, // true or false, if null or if checked column doesn't exist, will be assumed false
				'pivot_options' => [] // array with contents, or null
			],
			
			'general_permissions' => [
				'create' => [
					'checked' => bool,
					'pivot_options' => []
				],
			],
			'forbid_general_permissions' => ...same as previous
			
			'specific_permissions' => [
				100 => [
					'create' => [
						'checked' => bool,
						'pivot_options' => []
					],
					...,
				],
				...,
			]
			'forbid_specific_permissions' => ...same as previous
		],
		...more tables,
	]
```

Basic usage
```
Bouncer::control()->for($model)->updateAbilitiesRequest($permissions);
```
If you used a prefix in your form be sure to pass inside that eg 
```
Bouncer::control()->for($model)->updateAbilitiesRequest($request['permissions'] ?? []);
```

## Further

There is a similar function to edit and update simple abilities, and roles.

See

```php
$permissions = Bouncer::control()->for($model)->getPermissions();
//...
Bouncer::control()->for($model)->updatePermissions(request()->input('permissions'));

```

and 

```php
$roles = Bouncer::control()->for($model)->getRoles();
//...
Bouncer::control()->for($model)->updateRoles(request()->input('roles'));

```

# Scope Items

## Preparation

If you are using the bouncer toOwn() feature and are using a closure to check whether a item is owned by the user (rather than simply a 'user_id' or a string column name),
you will need to pass a third parameter as a query closure.
eg
```php
// Post items
Bouncer::ownedVia(Post::class, function($post, $user){
	return $post->team_id == $user->team_id;
});
// All items
Bouncer::ownedVia(function($item, $user){
	return $item->team_id == $user->team_id;
});
``` 
Will need to be replaced with something like

```php
// Post items
Bouncer::ownedVia(Post::class, function($post, $user){
	return $post->team_id == $user->team_id;
}, function($query, $user){
	$query->where('team_id', $user->team_id)
});
// All items
Bouncer::ownedVia(function($item, $user){
	return $item->team_id == $user->team_id;
}, null, function($query, $user){
	$query->where('team_id', $user->team_id)
});
``` 


## Basic usage

In the Model, be sure to include the CanScope trait
```php
...
use Corbinjurgens\Bouncer\Database\Concerns\CanScope;

class Post extends Model {
	use CanScope;
}
```

When querying a model, get only items where the current user (as by Auth::user()) can do the given action
```php
$posts = Post::whereCan('view')->get();
```

Pass array to get items where user can do ANY of the abilities. If one is blocked but others aren't, it will still get those items
```php
$posts = Post::whereCan(['view', 'edit'])->get();
```

## Extended usage

You can declare what user you are searching as via the second parameter. Passing null will default to Auth::user()
```php
$user = User::find(100);
$posts = Post::whereCan(['view', 'edit'], $user)->get();
```

You can pass a closure that you can use to bypass all checks. If you registed a bypass closure at Gate::before, you can use the same one here.
If the closure passes true, it will not apply any queries essentially allowing user to view all items unless you apply other queries.
```php
$bypass = function($user, $ability, $attributes){
	if ($user->isAdmin()){
		return True;
	}
	// Model can be found at $attributes[0]
	// It will loop over each ability if you pass multiple and if any pass true it will break
};
$posts = Post::whereCan(['view', 'edit'], null, $bypass)->get(); // In this example passing null as user, so it will default to current user via Auth::user()

```

You can directly access the whereCan closure via Post::whereCanClosure(...) and it accepts same variables as whereCan scope, which allows you to do such as the following.
This example will get comments that are not private, or where the user can edit the post they are attached to
```php
$comments = Comment::where(function($query){
	$query->orWhere('private', false);
	
	// This is a manual query, but it could also be replaced with
	// $query->orWhereHas('post', Post::whereCanClosure(['edit']));
	// if your relationships are mapped
	$query->orWhereIn('post_id', function($query){
		$query->select('id');
		$query->from('posts');
				
		$query->where(Post::whereCanClosure(['edit']));
				
	});
	
			
});
```

# Pivot columns

## Basic usage

Use setPivot() as follows.
You can set any pivot column or custom column you may add, but pivot_options is the intended usage.
If the ability already exists, the pivot will be updated
```php
Bouncer::allow( auth()->user() )->setPivot(['pivot_options' => ['test' => true]])->to(['publish'], Post::first() );
```

## When syncing

If you sync via the alternative method such as
```php
Bouncer::sync($user)->whereModel( Post::first() )->abilities($abilities, ['scope' => true]);
Bouncer::sync($user)->whereModel( Post::first() )->forbiddenAbilities($abilities, ['scope' => true]);
```
You can pass array of with options such as 

```php
$abilities = [
	'create',
	'edit' => ['pivot' => ['pivot_options' => ['key' => 'example']]],
	'delete'
];
```

# Scoped syncing

## Basic usage
Pass ['scope' => true] as an option to abilities or forbiddenAbilities to use the alternative scoped syncing method.
In the below example, only abilities connected to the specific Post will be detached if they are missing from the $abilities array
```php
$abilities = [
	'create',
	'edit',
	'delete'
];
Bouncer::sync($user)->whereModelStrict( Post::first() )->abilities($abilities, ['scope' => true]);
Bouncer::sync($user)->whereModelStrict( Post::first() )->forbiddenAbilities($abilities, ['scope' => true]);
```

To only sync a users abilities relating to all items (eg where user can do action to regardless of item ie entity_id is empty)
use class instead
```php
$abilities = [
	'create',
	'edit',
	'delete'
];
Bouncer::sync($user)->whereModelStrict( Post::class )->abilities($abilities, ['scope' => true]);
Bouncer::sync($user)->whereModelStrict( Post::class )->forbiddenAbilities($abilities, ['scope' => true]);
```

## Extended usage

There are three model scope types
 - whereModel() when passing a class name or non-existing model, it will search abilities of the model that has specific entity_id or empty entity_id. Passing an existing model will search abilities only on that model
 - whereModelStrict() when passing class name or non-existing model, it will only search for abilities with empty entity_id. Passing an existing model will search abilities only on that model
 - whereModelSpecific() when passing class name or non-existing model, it will only search for abilities with a specific entity_id. Passing an existing model will search abilities only on that model

To create a custom model scope, pass a query closure to whereCustom() and be sure to pass the model to setModel() whether its class, model or '*' to ensure the correct abilities are found 
```php
$post = Post::first();
Bouncer::sync($user)->setModel($post)->whereCustom(function($query) use ($post){
	$query->where('entity_type', $post->getMorphClass());
})->forbiddenAbilities($abilities, ['scope' => true]);
```

Custom scope
You can also use whereCustom() to scope anything outside of the model such as ability name
```php
// Only scope to the * manage post ability
$abilities = [
	'*'
];
Bouncer::sync($user)->whereModelStrict( Post::class )->whereCustom(function($query){
	$query->where('name', '*');
})->abilities($abilities, ['scope' => true]);

// This second step will only detach ability where name is * and is for all Post
$abilities = [
	
];
Bouncer::sync($user)->whereModelStrict( Post::class )->whereCustom(function($query){
	$query->where('name', '*');
})->abilities($abilities, ['scope' => true]);
```

## Special abilities

By default, syncing with the scope method, will always look for abilities that ARE NOT special abilties (see trait ScopesModel static $special_abilities)

Special ability names have a __ prefix. Howevever specialScope() can take special ability names with or without __ prefix

If you instead want to sync special abilities
```php

// an array of only special abilities
$abilities = [
	'__claim',
	'__example2'
	'__example3',
];
// Sync specific special abilities only, in this case example3 is ignored. claim and example2 are added or updated
Bouncer::sync($user)->whereModelStrict( Post::class )->specialScope(['claim', 'example2'])->abilities($abilities, ['scope' => true]);
// Sync all available special abilities as defined in trait ScopesModel static $special_abilities
Bouncer::sync($user)->whereModelStrict( Post::class )->specialScope(true)->abilities($abilities, ['scope' => true]);
// Turn off special sync mode, any abilities that are in static $special_abilities will be ignored and not synced and any non-special abilities will be detatched
Bouncer::sync($user)->whereModelStrict( Post::class )->specialScope(false)->abilities($abilities, ['scope' => true]); // Can also use null instead of false
```

# Claim ability

Allows users to create an item, and upon creation automatically allow a set of abilities. For each table you want to support the claim ability, ensure it is set inside bouncercontrol.tables and set claim_permissions to true


By giving a user the claim ability, for example in the following
```php
\Bouncer::allow($user)->setPivot(['pivot_options' => ['max' = 2, 'abilities' => ['edit']]])->to('__claim', Post::class);
```
and ensuring the Post class is using the trait Corbinjurgens\Bouncer\Database\Concerns\CanScope. The following behavour will be exhibeted.


- Code checks if user can create a post. If user can expiliciy create, then nothing special happens. If user cannot create, it will then check if they have the 'claim' ability. If 'max' is set in the pivot as shown in the example, it will count the other post items the user has specific permissions for. If its over max, they cannot claim.
- If they can claim, it will set a property on the model so that after the model is saved, it will give the user the abilities as set in the pivot_options. If abilities is not set, or is null, it will give the user all abilities as available in bouncercontrol for the Post table

Be sure that you have a new model, check if user can create, and then if so edit and save the same model.

Usage example

```php

$post = new Post();
if (!auth()->user()->can('create', $post)){
	abort(403);
}
// $post model now is primed to add permissions after creation.
$post->title = 'Post Title';
$post->body = 'Blah blah';
$post->save();

```