<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control\Concerns;

use Corbinjurgens\Bouncer\Clipboard;
use Corbinjurgens\Bouncer\Database\Concerns\CanScope;
use Corbinjurgens\Bouncer\Control\GetPermissions;

use Illuminate\Database\Eloquent\Model;


trait SpecialAbilities
{
	
	
	
	static $special_abilities = [
		'__claim',
	];
	
	static $special_ability_map = [
		'create' => '__claim',
	];
	
	/**
	 * Should return false to reject, or an ability id if successful;
	 */
	function processSpecialAbility(Model $ability, Model $authority, $model = null){
		$ability_name = $ability->name;
		$closures = [
			'__claim' => function($ability, $authority, $model){
				if (is_null($model)) return false; // can only claim model
				$action = true;
				if (!($model instanceof Model)){
					$action = false;
					$model = new $model;
				}
				
				$class_trats = class_uses($model);
				if ( !isset( $class_trats[CanScope::class] ) ){ // class must be using CanScope
					return false;
				}
				
				$max_claims = null;
				$abilities = GetPermissions::getTableAbilities($model->getMorphClass(), 'specific');
				if ($ability->pivot && is_array($ability->pivot->pivot_options)){
					if (isset($ability->pivot->pivot_options['abilities']) && is_array($ability->pivot->pivot_options['abilities'])){
						$abilities = $ability->pivot->pivot_options['abilities'];
					}
					if (isset($ability->pivot->pivot_options['max']) && is_numeric($ability->pivot->pivot_options['max'])){
						$max_claims = $ability->pivot->pivot_options['max'];
					}
				}
				if ( !is_null($max_claims) ){
					$pipe = Clipboard::collectionPipe($model->newInstance(), true, 'specific');
					$current_claimed = $authority->getAbilities()->pipe($pipe)->pluck('entity_id')->unique()->count();
					if ($current_claimed >= $max_claims){
						return false;
					}
				}
				if ($action === True){
					$model->permisssion_claim = true;
					$model->permission_authority = $authority;
					$model->permission_abilities = $abilities;
				}
				
				return $ability->getKey();
			}
		];
		if (!isset($closures[$ability_name])){
			return false;
		}
		
		return $closures[$ability_name]($ability, $authority, $model);
	}
	
	
}