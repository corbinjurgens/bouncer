<?php

namespace Corbinjurgens\Bouncer\Conductors\Concerns;

use Corbinjurgens\Bouncer\Helpers;
use Corbinjurgens\Bouncer\Database\Models;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

trait FindsAndCreatesAbilities
{
    /**
     * Get the IDs of the provided abilities.
     *
     * @param  \Illuminate\Database\Eloquent\model|array|int  $abilities
     * @param  \Illuminate\Database\Eloquent\Model|string|array|null  $model
     * @param  array  $attributes
     * @return array
     */
    protected function getAbilityIds($abilities, $model = null, array $attributes = [])
    {
        if ($abilities instanceof Model) {
            return [$abilities->getKey()];
        }

        if ( ! is_null($model)) {
            return $this->getModelAbilityKeys($abilities, $model, $attributes);
        }

        if (Helpers::isAssociativeArray($abilities)) {
            return $this->getAbilityIdsFromMap($abilities, $attributes);
        }

        if (! is_array($abilities) && ! $abilities instanceof Collection) {
            $abilities = [$abilities];
        }

        return $this->getAbilityIdsFromArray($abilities, $attributes);
    }
	
	/**
	 * Like getAbilityIds but keeps track of what you passed to it for later reference
	 * Can accept abilities as indexed array of arrays with abiliy name as 'ability' key (recommended) eg [['ability' => 'create', 'attributes' => []...], ...]
	 * OR associative array with ability name as key and array as value eg  ['create' => ['attributes' => []...], ...] Note this method will not allow you to pass multiple of the same ability for different models
	 * OR index array with ability name as value eg ['create', ...]
	 * OR a mixture of the above.
	 * 
	 * In the abilities array, 'ability', 'attributes' and 'model' have an actual function here, everything else is simply passed back
	 * - 'attributes' used to query ability columns
	 * - 'model' used to query ability by model
	 * Both take preference over anything passed as function parameter ($attributes is merged, $model is overwritten)
	 */
	protected function getFullAbilities(array $abilities = [], $model = null, array $attributes = []){
		if (!is_array($abilities)){
			$abilities = [$abilities];
		}
		
		// Add empty pivot default, so that when using array_column() to get pivot it will get same amount of elemens
		$prepared_abilities = Helpers::toOptionArray($abilities, ['pivot' => []]);
		
		$abilities = Collection::make($prepared_abilities);

        $models = Collection::make(is_array($model) ? $model : [$model]);

        return $abilities->map(function ($value, $ability) use ($models, $attributes) {
			// If ability was passed as a key use it, otherwise look to array key
			$ability = $value['ability'] ?? $ability;
			
			// If model was passed as a key use it, otherwise used $model parameter
			$target_models = isset($value['model']) ? ( Collection::make(is_array($value['model']) ? $value['model'] : [$value['model']]) ) : $models;
           
		   return $target_models->map(function ($model) use ($value, $ability, $attributes) {
				// Merge attributes
				$attributes = Helpers::combineArrays([], $attributes, @$value['attributes']);
				
				// Find ability
				$getModel = (is_null($model)) ? $this->abilitiesByName($ability, $attributes)->first() : $this->getModelAbility($ability, $model, $attributes);
				
				return [
					'ability' => $getModel,
					'model' => $model,
					'attributes' => $attributes
				]
				 + 
				array_diff_key($value, array_flip(['ability', 'model', 'attributes']) );
				;
            });
        })->collapse()->keyBy(function($item){
			return $item['ability']->getKey();
		})->all();
	}

    /**
     * Get the ability IDs for the given map.
     *
     * The map should use the ['ability-name' => Entity::class] format.
     *
     * @param  array  $map
     * @param  array  $attributes
     * @return array
     */
    protected function getAbilityIdsFromMap(array $map, array $attributes)
    {
        list($map, $list) = Helpers::partition($map, function ($value, $key) {
            return ! is_int($key);
        });

        return $map->map(function ($entity, $ability) use ($attributes) {
            return $this->getAbilityIds($ability, $entity, $attributes);
        })->collapse()->merge($this->getAbilityIdsFromArray($list, $attributes))->all();
    }

    /**
     * Get the ability IDs from the provided array, creating the ones that don't exist.
     *
     * @param  iterable  $abilities
     * @param  array  $attributes
     * @return array
     */
    protected function getAbilityIdsFromArray($abilities, array $attributes)
    {
        $groups = Helpers::groupModelsAndIdentifiersByType($abilities);

        $keyName = Models::ability()->getKeyName();

        $groups['strings'] = $this->abilitiesByName($groups['strings'], $attributes)
                                  ->pluck($keyName)->all();

        $groups['models'] = Arr::pluck($groups['models'], $keyName);

        return Arr::collapse($groups);
    }

    /**
     * Get the abilities for the given model ability descriptors.
     *
     * @param  array|string  $abilities
     * @param  \Illuminate\Database\Eloquent\Model|string|array  $model
     * @param  array  $attributes
     * @return array
     */
    protected function getModelAbilityKeys($abilities, $model, array $attributes)
    {
        $abilities = Collection::make(is_array($abilities) ? $abilities : [$abilities]);

        $models = Collection::make(is_array($model) ? $model : [$model]);

        return $abilities->map(function ($ability) use ($models, $attributes) {
            return $models->map(function ($model) use ($ability, $attributes) {
                return $this->getModelAbility($ability, $model, $attributes)->getKey();
            });
        })->collapse()->all();
    }

    /**
     * Get an ability for the given entity.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string  $entity
     * @param  array  $attributes
     * @return \Corbinjurgens\Bouncer\Database\Ability
     */
    protected function getModelAbility($ability, $entity, array $attributes)
    {
        $entity = $this->getEntityInstance($entity);

        $existing = $this->findAbility($ability, $entity, $attributes);

        return $existing ?: $this->createAbility($ability, $entity, $attributes);
    }

    /**
     * Find the ability for the given entity.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string  $entity
     * @param  array  $attributes
     * @return \Corbinjurgens\Bouncer\Database\Ability|null
     */
    protected function findAbility($ability, $entity, $attributes)
    {
        $onlyOwned = isset($attributes['only_owned']) ? $attributes['only_owned'] : false;

        $query = Models::ability()
                     ->where('name', $ability)
                     ->forModel($entity, true)
                     ->where('only_owned', $onlyOwned);
		
        return Models::scope()->applyToModelQuery($query)->first();
    }

    /**
     * Create an ability for the given entity.
     *
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string  $entity
     * @param  array  $attributes
     * @return \Corbinjurgens\Bouncer\Database\Ability
     */
    protected function createAbility($ability, $entity, $attributes)
    {
        return Models::ability()->createForModel($entity, $attributes + [
            'name' => $ability,
        ]);
    }

    /**
     * Get an instance of the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @return \Illuminate\Database\Eloquent\Model|string
     */
    protected function getEntityInstance($model)
    {
        if ($model === '*') {
            return '*';
        }

        if ( ! $model instanceof Model) {
            return new $model;
        }

        // Creating an ability for a non-existent model gives the authority that
        // ability on all instances of that model. If the developer passed in
        // a model instance that does not exist, it is probably a mistake.
        if ( ! $model->exists) {
            throw new InvalidArgumentException(
                'The model does not exist. To edit access to all models, use the class name instead'
            );
        }

        return $model;
    }

    /**
     * Get or create abilities by their name.
     *
     * @param  array|string  $abilities
     * @param  array  $attributes
     * @return \Illuminate\Support\Collection
     */
    protected function abilitiesByName($abilities, $attributes = [])
    {
        $abilities = array_unique(is_array($abilities) ? $abilities : [$abilities]);

        if (empty($abilities)) {
            return new Collection;
        }

        $existing = Models::ability()->simpleAbility()->whereIn('name', $abilities)->get();
        return $existing->merge($this->createMissingAbilities(
            $existing, $abilities, $attributes
        ));
    }

    /**
     * Create the non-existant abilities by name.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $existing
     * @param  string[]  $abilities
     * @param  array  $attributes
     * @return array
     */
    protected function createMissingAbilities($existing, array $abilities, $attributes = [])
    {
        $missing = array_diff($abilities, $existing->pluck('name')->all());

        return array_map(function ($ability) use ($attributes) {
            return Models::ability()->create($attributes + ['name' => $ability]);
        }, $missing);
    }
}
