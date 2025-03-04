<?php

namespace Corbinjurgens\Bouncer;

use Illuminate\Database\Eloquent\Model;
use Corbinjurgens\Bouncer\Database\Queries\Abilities;

use Corbinjurgens\Bouncer\Control\Concerns\SpecialAbilities;

class Clipboard extends BaseClipboard
{
	use SpecialAbilities;
	
    /**
     * Determine if the given authority has the given ability, and return the ability ID.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return int|bool|null
     */
    public function checkGetId(Model $authority, $ability, $model = null)
    {	
		// Check if the ability is a function ability, and pass it as that instead
		if (in_array($ability, self::$function_abilities)){
			return $this->processFunctionAbility($authority, $ability, $model);
		}
		
        if ($this->isForbidden($authority, $ability, $model)) {
            return false;
        }

        $ability = $this->getAllowingAbility($authority, $ability, $model);

        $result = $ability ? $ability->getKey() : null;
		
		if (is_null($result) && isset(self::$special_ability_map[$ability])){
			// Ability failed, see if there is any special ability mapped to the ability
			// Such as user is trying to 'create' therefore we should see if they can '__claim'
			$special_ability = self::$special_ability_map[$ability];
			$ability = $this->getAllowingAbility($authority, $special_ability, $model);
			if ($ability){
				$ability = $this->applyPivot($ability, $authority);
				return $this->processSpecialAbility($ability, $authority, $model);
			}
			
		}
		
		return $result;
    }

    /**
     * Determine whether the given ability request is explicitely forbidden.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return bool
     */
    protected function isForbidden(Model $authority, $ability, $model = null)
    {
        return $this->getHasAbilityQuery(
            $authority, $ability, $model, $allowed = false
        )->exists();
    }

    /**
     * Get the ability model that allows the given ability request.
     *
     * Returns null if the ability is not allowed.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getAllowingAbility(Model $authority, $ability, $model = null)
    {
        return $this->getHasAbilityQuery(
            $authority, $ability, $model, $allowed = true
        )->first();
    }

    /**
     * Get the query for where the given authority has the given ability.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string $ability
     * @param  \Illuminate\Database\Eloquent\Model|string|null  $model
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getHasAbilityQuery($authority, $ability, $model, $allowed)
    {
        $query = Abilities::forAuthority($authority, $allowed);

        if (! $this->isOwnedBy($authority, $model)) {
            $query->where('only_owned', false);
        }

        if (is_null($model)) {
            return $this->constrainToSimpleAbility($query, $ability);
        }

        return $query->byName($ability)->forModel($model);
    }

    /**
     * Constrain the query to the given non-model ability.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $ability
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function constrainToSimpleAbility($query, $ability)
    {
        return $query->where(function ($query) use ($ability) {
            $query->where('name', $ability)->whereNull('entity_type');

            $query->orWhere(function ($query) use ($ability) {
                $query->where('name', '*')->where(function ($query) {
                    $query->whereNull('entity_type')->orWhere('entity_type', '*');
                });
            });
        });
    }
}
