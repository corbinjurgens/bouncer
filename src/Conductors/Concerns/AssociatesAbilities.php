<?php

namespace Corbinjurgens\Bouncer\Conductors\Concerns;

use Corbinjurgens\Bouncer\Helpers;
use Illuminate\Support\Arr;
use Corbinjurgens\Bouncer\Database\Models;
use Illuminate\Database\Eloquent\Model;

use InvalidArgumentException;

trait AssociatesAbilities
{
    use ConductsAbilities, FindsAndCreatesAbilities;
	use ScopesModel;// used here only for associateAbilitiesDirectly(), should not be used together with to()

    /**
     * Associate the abilities with the authority.
     *
     * @param  \Illuminate\Database\Eloquent\model|array|int  $abilities
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @param  array  $attributes
     * @return \Corbinjurgens\Bouncer\Conductors\Lazy\ConductsAbilities|null
     */
    public function to($abilities, $model = null, array $attributes = [])
    {
		if ($this->scoping_model){
			 throw new InvalidArgumentException(
                'You cannot use ' . __FUNCTION__ .' method when scoping a model'
            );
		}
        if ($this->shouldConductLazy(...func_get_args())) {
            return $this->conductLazy($abilities);
        }

        $ids = $this->getAbilityIds($abilities, $model, $attributes);

        $this->associateAbilities($ids, $this->getAuthority());
    }
	/**
     * Get the authority, creating a role authority if necessary.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getAuthority()
    {
        if (is_null($this->authority)) {
            return null;
        }

        if ($this->authority instanceof Model) {
            return $this->authority;
        }

        return Models::role()->firstOrCreate(['name' => $this->authority]);
    }

    /**
     * Get the IDs of the associated abilities.
	 * Or Retrieve the query
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $authority
     * @param  array  $abilityIds
     * @return array
     */
    protected function getAssociatedAbilityIds($authority, array $abilityIds = null, $return_raw = false, $scope = true)
    {
        if (is_null($authority)) {
            return $this->getAbilityIdsAssociatedWithEveryone($abilityIds, $return_raw, $scope);
        }

        $relation = $authority->abilities();
        $table = Models::table('abilities');

		if (!is_null($abilityIds)) $relation->whereIn("{$table}.id", $abilityIds);
        $relation->wherePivot('forbidden', '=', $this->forbidding);
		
		if ($scope === true) $this->scopeModel($relation);

        Models::scope()->applyToRelation($relation);
		if ($return_raw === true) return $relation;
        return $relation->get(["{$table}.id"])->pluck('id')->all();
    }
	public function getAssociatedAbilityQuery(array $abilityIds = null){
		$authority = $this->getAuthority();
		return $this->getAssociatedAbilityIds($authority, $abilityIds, True);
	}

    /**
     * Get the IDs of the abilities associated with everyone.
	 * Or Retrieve the query
     *
     * @param  array  $abilityIds
     * @return array
     */
    protected function getAbilityIdsAssociatedWithEveryone(array $abilityIds = null, $return_raw = false, $scope = true)
    {
        $query = Models::permission()->query()
            ->whereNull('entity_id')
			->when(is_array($abilityIds), function($query) use ($abilityIds){
				$query->whereIn('ability_id', $abilityIds);
			})
            ->where('forbidden', '=', $this->forbidding);
		
		if ($scope === true) {
			$query->whereHas('ability', function($relation){
				$this->scopeModel($relation);
			});
		}
		
		
        Models::scope()->applyToRelationQuery($query, Models::permission()->getTable());
		if ($return_raw === true) return $query;
        return Arr::pluck($query->get(['ability_id']), 'ability_id');
    }
	public function getAbilityIdsAssociatedWithEveryoneQuery(array $abilityIds = null){
		return $this->getAbilityIdsAssociatedWithEveryone($abilityIds, True);
	}

    /**
     * Associate the given ability IDs on the permissions table.
     *
     * @param  array  $ids
     * @param  \Illuminate\Database\Eloquent\Model|null  $authority
     * @return void
     */
    protected function associateAbilities(array $ids, Model $authority = null)
    {
		$existing_ids = $this->getAssociatedAbilityIds($authority, $ids, true)->get();
        $ids = array_diff($ids, $existing_ids->pluck( is_null($authority) ? 'ability_id' : 'id')->all());
		
        if (is_null($authority)) {
            $this->associateAbilitiesToEveryone($ids, $existing_ids);
        } else {
            $this->associateAbilitiesToAuthority($ids, $existing_ids, $authority);
        }
    }

    /**
     * Associate these abilities with the given authority.
     *
     * @param  array  $ids
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return void
     */
    protected function associateAbilitiesToAuthority(array $ids, $existing_ids, Model $authority)
    {
        $attributes = Models::scope()->getAttachAttributes(get_class($authority)) + $this->getPivotAttributes();
		$prepared_ids = Helpers::toOptionArray($ids, ['forbidden' => $this->forbidding] + $attributes);
        $authority
            ->abilities()
            ->attach($prepared_ids);// Custom editeds
		
		foreach($existing_ids as $item){
			if ($item->pivot){
				$item->pivot->fill($attributes);
				$item->pivot->save();
			}
		}
    }

    /**
     * Associate these abilities with everyone.
     *
     * @param  array  $ids
     * @return void
     */
    protected function associateAbilitiesToEveryone(array $ids, $existing_ids)
    {
        $attributes = ['forbidden' => $this->forbidding];

        $attributes += Models::scope()->getAttachAttributes() + $this->getPivotAttributes();
		
		$prepared_ids = Helpers::toOptionArray($ids, $attributes);
		$records = $this->toRecords($prepared_ids);
		foreach($records as $record){
			Models::permission()->create($record);
		}
        
		
		foreach($existing_ids as $item){
			if ($item){
				$item->fill($attributes);
				$item->save();
			}
		}
    }
	/** 
	 * Used to be only in associateAbilitiesToEveryone function,
	 * Now shared
	 */
	protected function toRecords($prepared_ids){
		$records = array_map(function ($id, $values){
            return ['ability_id' => $id] + $values;// edited
        }, array_keys($prepared_ids), $prepared_ids);
		return $records;
	}
	
	
	/**
	 * When entering an array of abilities already prepared, ie from syncAbilities()
	 */
	public function associateAbilitiesDirectly(array $abilities){
		$authority = $this->getAuthority();
		
		$attributes = ['forbidden' => $this->forbidding];
		$attributes += is_null($authority) ? Models::scope()->getAttachAttributes() : Models::scope()->getAttachAttributes(get_class($authority));
		$attributes += $this->getPivotAttributes();
		
		$ids = array_column($abilities, 'id');
		$prepared_ids = Helpers::toOptionArray(
			array_combine( $ids, array_column($abilities, 'pivot')),
			$attributes
		);
		if (is_null($authority)){
			$query = $this->getAbilityIdsAssociatedWithEveryone($ids, true);
			$exists = $query->get();
			$records = $this->toRecords($prepared_ids);
			
			foreach($records as $record){
				if ( $find = $exists->firstWhere('ability_id', $record['ability_id']) ){
					$find->fill($record);
					$find->save();
				}else{
					Models::permission()->create($record);
				}
				
			}
		}else{
			$table = Models::table('abilities');
			
			// Get ids that currently exists with model scoping applied
			$relation = $this->getAssociatedAbilityIds($authority, null, true);
			
			// Macroed in BouncerServiceProvider. $this->scopeModelClosure() is added to reuse same queries 
			// and check the prepared ids match and should be added
			$relation->filtered_sync($prepared_ids, true, $this->scopeModelClosure());
		}
		
	}
}
