<?php

namespace Corbinjurgens\Bouncer\Database\Queries;

use Corbinjurgens\Bouncer\Helpers;
use Corbinjurgens\Bouncer\Database\Models;

use Illuminate\Database\Eloquent\Model;

class Roles
{
	
    /**
     * Get roles for the authority. Essentially same as relationship 
	 * but also allows retrieving effective roles, ie. roles that are levels below
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function forAuthority(Model $authority, $indirect = false)
    {
        return Models::role()->where(function ($query) use ($authority, $indirect) {
			$roles  = Models::table('roles');
			
			$query->orWhereIn("$roles.id", function($query) use ($roles, $authority){
				$query->select("$roles.id");
				Abilities::getAuthorityRoleConstraint($authority)($query);
				
			});
			
			if ($indirect === true){
				Abilities::addRoleInheritCondition($query->getQuery(), $authority, $roles);
			}
        });
    }
	 
	 
	 
    /**
     * Constrain the given query by the provided role.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  ...$roles
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function constrainWhereIs($query, ...$roles)
    {
        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->whereIn('name', $roles);
        });
    }

    /**
     * Constrain the given query by all provided roles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  ...$roles
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function constrainWhereIsAll($query, ...$roles)
    {
        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->whereIn('name', $roles);
        }, '=', count($roles));
    }

    /**
     * Constrain the given query by the provided role.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  ...$roles
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function constrainWhereIsNot($query, ...$roles)
    {
        return $query->whereDoesntHave('roles', function ($query) use ($roles) {
            $query->whereIn('name', $roles);
        });
    }

    /**
     * Constrain the given roles query to those that were assigned to the given authorities.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection  $model
     * @param  array  $keys
     * @return void
     */
    public function constrainWhereAssignedTo($query, $model, array $keys = null)
    {
        list($model, $keys) = Helpers::extractModelAndKeys($model, $keys);

        $query->whereExists(function ($query) use ($model, $keys) {
            $table  = $model->getTable();
            $key    = "{$table}.{$model->getKeyName()}";
            $pivot  = Models::table('assigned_roles');
            $roles  = Models::table('roles');

            $query->from($table)
                  ->join($pivot, $key, '=', $pivot.'.entity_id')
                  ->whereColumn("{$pivot}.role_id", "{$roles}.id")
                  ->where("{$pivot}.entity_type", $model->getMorphClass())
                  ->whereIn($key, $keys);

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $pivot);
        });
    }
}
