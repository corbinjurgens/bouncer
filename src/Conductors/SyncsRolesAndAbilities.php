<?php

namespace Corbinjurgens\Bouncer\Conductors;

use Corbinjurgens\Bouncer\Helpers;
use Corbinjurgens\Bouncer\Database\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SyncsRolesAndAbilities
{
    use Concerns\FindsAndCreatesAbilities, Concerns\ScopesModel;

    /**
     * The authority for whom to sync roles/abilities.
     *
     * @var \Illuminate\Database\Eloquent\Model|string
     */
    protected $authority;

    /**
     * Constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model|string  $authority
     */
    public function __construct($authority)
    {
        $this->authority = $authority;
    }

    /**
     * Sync the provided roles to the authority.
     *
     * @param  iterable  $roles
     * @return void
     */
    public function roles($roles)
    {
        $this->sync(
            Models::role()->getRoleKeys($roles),
            $this->authority->roles()
        );
    }
    /**
     * Sync the provided abilities to the authority.
     *
     * @param  iterable  $abilities
     * @return void
     */
    public function abilities($abilities, $options = [])
    {
        $this->syncAbilities($abilities, array_replace(['forbidden' => false, 'scope' => false], $options));
    }

    /**
     * Sync the provided forbidden abilities to the authority.
     *
     * @param  iterable  $abilities
     * @return void
     */
    public function forbiddenAbilities($abilities, $options = [])
    {
        $this->syncAbilities($abilities, array_replace(['forbidden' => true, 'scope' => false], $options));
    }

    /**
     * Sync the given abilities for the authority.
     *
     * @param  iterable  $abilities
     * @param  array  $options
     * @return void
     */
    protected function syncAbilities($abilities, $options = ['forbidden' => false, 'scope' => false])
    {
		$associateClass = $options['forbidden'] ? ForbidsAbilities::class : GivesAbilities::class;
		if ($options['scope']){
			$abilityData = $this->getFullAbilities($abilities, $this->getScopeModel());
			$this->syncClearAbilities( array_keys($abilityData) , $options['forbidden'], True);
			$instance = new $associateClass($this->authority);
			$this->passModelScope($instance);
			$instance->associateAbilitiesDirectly($abilityData);
			
		}else{
			// Default method
			$abilityKeys = $this->getAbilityIds($abilities);
			$this->syncClearAbilities($abilityKeys, $options['forbidden']);

			(new $associateClass($this->authority))->to($abilityKeys);
		}
    }
	

    /**
     * Clear Sync the given abilities for the authority.
     *
     * @param  array  $keys
     * @param  bool  $forbidden
     * @return void
     */
    protected function syncClearAbilities($keys, $forbidden, $scope = false)
    {
		
		$associateClass = $forbidden ? ForbidsAbilities::class : GivesAbilities::class;
		if (!is_null($this->authority)){
			$authority = $this->getAuthority();
			$relation = $authority->abilities();
			
			$instance = new $associateClass($this->authority);
			
			if ($scope){
				$this->passModelScope($instance);
			}
			
			$ids = $instance->getAssociatedAbilityQuery()
				->whereNotIn($relation->getQualifiedRelatedKeyName(), $keys);
				
			$ids = $ids->get()->pluck('id')->all();
			
			// Apply scope to pivot so detach behaves
			Models::scope()->applyToPivot($relation);
			$relation->wherePivot('forbidden', $forbidden);
			$relation->detach($ids);
		}else{
			$instance = new $associateClass();
			
			if ($scope){
				$this->passModelScope($instance);
			}
			
			$query = $instance->getAbilityIdsAssociatedWithEveryoneQuery();
				$query->whereNotIn('ability_id', $keys);
				$query->delete();
		}

    }
	
	

    /**
     * Get (and cache) the authority for whom to sync roles/abilities.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getAuthority()
    {
        if (is_string($this->authority)) {
            $this->authority = Models::role()->firstOrCreate([
                'name' => $this->authority
            ]);
        }

        return $this->authority;
    }

    /**
     * Get the fully qualified column name for the abilities table's primary key.
     *
     * @return string
     */
    protected function getAbilitiesQualifiedKeyName()
    {
        $model = Models::ability();

        return $model->getTable().'.'.$model->getKeyName();
    }

    /**
     * Sync the given IDs on the pivot relation.
     *
     * This is a heavily-modified version of Eloquent's built-in
     * BelongsToMany@sync - which we sadly cannot use because
     * our scope sets a "closure where" on the pivot table.
     *
     * @param  array  $ids
     * @param  \Illuminate\Database\Eloquent\Relations\BelongsToMany  $relation
     * @return void
     */
    protected function sync(array $ids, BelongsToMany $relation)
    {
        $current = $this->pluck(
            $this->newPivotQuery($relation),
            $relation->getRelatedPivotKeyName()
        );
        $this->detach(array_diff($current, $ids), $relation);

        $relation->attach(
            array_diff($ids, $current),
            Models::scope()->getAttachAttributes($this->authority)
        );
    }

    /**
     * Detach the records with the given IDs from the relationship.
     *
     * @param  array  $ids
     * @param  \Illuminate\Database\Eloquent\Relations\BelongsToMany  $relation
     * @return void
     */
    public function detach(array $ids, BelongsToMany $relation)
    {
        if (empty($ids)) {
            return;
        }

        $this->newPivotQuery($relation)
             ->whereIn($relation->getRelatedPivotKeyName(), $ids)
             ->delete();
    }

    /**
     * Get a scoped query for the pivot table.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\BelongsToMany  $relation
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newPivotQuery(BelongsToMany $relation)
    {
        $query = $relation
            ->newPivotStatement()
            ->where('entity_type', $this->getAuthority()->getMorphClass())
            ->where(
                $relation->getForeignPivotKeyName(),
                $relation->getParent()->getKey()
            );

        return Models::scope()->applyToRelationQuery(
            $query, $relation->getTable()
        );
    }

    /**
     * Pluck the values of the given column using the provided query.
     *
     * @param  mixed  $query
     * @param  string $column
     * @return string[]
     */
    protected function pluck($query, $column)
    {
        return Arr::pluck($query->get([$column]), last(explode('.', $column)));
    }
}
