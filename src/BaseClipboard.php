<?php

namespace Corbinjurgens\Bouncer;

use Corbinjurgens\Bouncer\Database\Models;
use Corbinjurgens\Bouncer\Database\Queries\Abilities;

use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Access\Gate;

abstract class BaseClipboard implements Contracts\Clipboard
{
    /**
     * Determine if the given authority has the given ability.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return bool
     */
    public function check(Model $authority, $ability, $model = null)
    {
        return (bool) $this->checkGetId($authority, $ability, $model);
    }

    /**
     * Check if an authority has the given roles.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  array|string  $roles
     * @param  string  $boolean
     * @return bool
     */
    public function checkRole(Model $authority, $roles, $boolean = 'or')
    {
        $count = $this->countMatchingRoles($authority, $roles);

        if ($boolean == 'or') {
            return $count > 0;
        } elseif ($boolean === 'not') {
            return $count === 0;
        }

        return $count == count((array) $roles);
    }

    /**
     * Count the authority's roles matching the given roles.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  array|string  $roles
     * @return int
     */
    protected function countMatchingRoles($authority, $roles)
    {
        $lookups = $this->getRolesLookup($authority);

        return count(array_filter($roles, function ($role) use ($lookups) {
            switch (true) {
                case is_string($role):
                    return $lookups['names']->has($role);
                case is_numeric($role):
                    return $lookups['ids']->has($role);
                case $role instanceof Model:
                    return $lookups['ids']->has($role->getKey());
            }

            throw new InvalidArgumentException('Invalid model identifier');
        }));
    }

    /**
     * Get the given authority's roles' IDs and names.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return array
     */
    public function getRolesLookup(Model $authority)
    {
        $roles = $authority->roles()->get([
            'name', Models::role()->getQualifiedKeyName()
        ])->pluck('name', Models::role()->getKeyName());

        return ['ids' => $roles, 'names' => $roles->flip()];
    }

    /**
     * Get the given authority's roles' names.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return \Illuminate\Support\Collection
     */
    public function getRoles(Model $authority)
    {
        return $this->getRolesLookup($authority)['names']->keys();
    }

    /**
     * Get a list of the authority's abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAbilities(?Model $authority, $allowed = true)
    {
        $abilities = Abilities::forAuthority($authority, $allowed)->get();
		return $this->applyPivot($abilities, $authority, $allowed);
    }

    /**
     * Get a list of the authority's direct abilities. 
	 * Does not cache, is meant only for editing an authorities explicit abilities
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDirectAbilities(?Model $authority, $allowed = true)
    {
        $abilities = Abilities::forAuthority($authority, $allowed, true)->get();
		return $this->applyPivot($abilities, $authority, $allowed);
    }
	
	/**
	 * Return the closure used to filter a permissions collection
	 *
	 * @param \Illuminate\Database\Eloquent\Model|string|null  $model // null, or "*" accepted
	 * @param bool $model_strict // Whether it includes * entity_type and null entity_id
	 * @param string|null $model_mode // 'strict', 'specific' or null
	 * @param array|null $abilities
	 * @param array|null $abilities_except
	 * @param bool $ability_strict
	 * @param bool|null $special_abilities
	 */
	public static function collectionPipe($model, bool $model_strict = false, string $model_mode = null, array $abilities = null, array $abilities_except = null, bool $ability_strict = false, bool $special_abilities = null){
		
		return function($collection) use ($model, $model_strict, $model_mode, $abilities, $abilities_except, $ability_strict, $special_abilities){
			
			// Get name same as abilities, and * if not strict
			if ($ability_strict === false && is_array($abilities) && !in_array("*", $abilities)) $abilities[] = "*";
						
			return $collection
				// Model is null, only look for simple abilities similar to trait IsAbility scopeSimpleAbility
				->when(is_null($model), function($collection){
					return $collection->whereNull('entity_type');
				})
				// Otherwise, find by prepared $entity_types
				->unless(is_null($model), function($collection) use ($model, $model_strict){
					// Get entity type same as model, and * if not strict
					$entity_types = [];
					$entity_types[] = $model instanceof Model ? $model->getMorphClass() : $model;
					if ($model_strict === false && $model !== "*") $entity_types[] = "*";
					
					return $collection->whereIn('entity_type', $entity_types);
				})
				
				
				
				// Model exists, find by ID
				->when(($model instanceof Model && $model->exists), function($collection) use ($model, $model_strict){
					return $collection->filter(function($item) use ($model, $model_strict){
						return 
							($item->entity_id == $model->getKey())
							//	||
							//($model_strict === false && $item->entity_id === null)
							;
					});
				})
				// Otherwise, apply $model_mode
				->unless(($model instanceof Model && $model->exists), function($collection) use ($model_mode){
					
					return $collection
						->when($model_mode == 'strict', function($collection){
							return $collection->whereNull('entity_id');
						})
						->when($model_mode == 'specific', function($collection){
							return $collection->whereNotNull('entity_id');
						})
						;
				})
				
				
				
				
				// Special abilities should be filtered in or out
				->when(is_bool($special_abilities), function($collection) use ($special_abilities){
					return $collection->filter(function($collection) use ($special_abilities){
						// Special abilities start with __
						return (\Str::startsWith($item->name, '__') == $special_abilities);
					});
				})
				
				
				
				// Abilities in filter
				->when(is_array($abilities), function($collection) use ($abilities){
					return $collection->whereIn('name', $abilities);
				})
				->when(is_array($abilities_except), function($collection) use ($abilities_except){
					return $collection->whereNotIn('name', $abilities_except);
				});
		};
		
	}
	/**
	 * Apply pivot to abilities so we can get pivot info
	 *
	 * @param \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model $collection
	 * @param \Illuminate\Database\Eloquent\Model|null $authority
	 * @param bool $allowed
	 * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
	 */
	protected function applyPivot($collection, ?Model $authority, $allowed = true){
		$single_item = false;
		if (!$collection instanceof EloquentCollection){
			$single_item = true;
			$collection = new EloquentCollection([$collection]);
		}
		$ability_ids = $collection->pluck('id');
		
		$permissions = Models::table('permissions');
		
		$pivots = Abilities::forAuthorityPivot($authority, $allowed)->get()->groupBy('ability_id');
		
		foreach($collection as &$item){
			if ($pivots->has($item->getKey())){
				$target = $pivots->get( $item->getKey() )->sort(function($a, $b){
					// Sort role_level to top so itll be found first
					return $a->role_level === $b->role_level ? $b->id <=> $a->id : $b->role_level <=> $a->role_level;
				});
				$find = null;
				
				// Incase a user has the same permission by multiple ways, priorize user, role then everyone
				if ( 
					( $authority instanceof Model && $find = $target->firstWhere('entity_type', $authority->getMorphClass() ) )
						||
					( $authority instanceof Model && $authority->getTable() != Models::table('roles') && $find = $target->firstWhere('entity_type', Models::role()->getMorphClass() ) )
						|| 
					( $find = $target->first() )
				){
					$item->setRelation('pivot', $find);
				}
				
			}
		}
		if ($single_item === true){
			return $collection->first();
		}
		return $collection;
		
	}

    /**
     * Get a list of the authority's forbidden abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForbiddenAbilities(?Model $authority)
    {
		return $this->getAbilities($authority, false);
    }

    /**
     * Determine whether the authority owns the given model.
     *
     * @return bool
     */
    public function isOwnedBy($authority, $model)
    {
        return $model instanceof Model && Models::isOwnedBy($authority, $model);
    }
}
