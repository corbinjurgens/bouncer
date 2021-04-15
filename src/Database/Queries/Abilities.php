<?php

namespace Corbinjurgens\Bouncer\Database\Queries;

use Corbinjurgens\Bouncer\Database\Models;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;

class Abilities
{
    /**
     * Get a query for the authority's abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function forAuthority(?Model $authority, $allowed = true, $direct_only = false)
    {
        return Models::ability()->where(function ($query) use ($authority, $allowed, $direct_only) {
			if (!is_null($authority)){
				if ($authority->getTable() != Models::table('roles') && $direct_only === false) $query->orWhereExists(static::getRoleConstraint($authority, $allowed));
				$query->orWhereExists(static::getAuthorityConstraint($authority, $allowed));
				
			}
			if ($direct_only === false  || is_null($authority)){
				$query->orWhereExists(static::getEveryoneConstraint($allowed));
			}
        });
    }
	
    /**
     * Get a query for the expected pivots to match with forAuthority results
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function forAuthorityPivot(?Model $authority, $allowed = true, $direct_only = false)
    {
		$abilities   = Models::table('abilities');
		$permissions = Models::table('permissions');
		$roles       = Models::table('roles');
		
        return Models::permission()
			->select("$permissions.*", "$roles.level as role_level")
			->leftJoin($roles, function($join) use ($roles, $permissions){
				$join->on("$roles.id", '=', "$permissions.entity_id")
					->where("$permissions.entity_type", Models::role()->getMorphClass());
			})
			->join($abilities, $abilities.'.id', '=', $permissions.'.ability_id')
			->where(function ($query) use ($authority, $allowed, $direct_only, $permissions) {
				if (!is_null($authority)){
					if ($authority->getTable() != Models::table('roles') && $direct_only === false){
						$query->orWhereIn("$permissions.id", function($query) use ($authority, $allowed, $permissions){
							$query->select("$permissions.id");
							static::getRoleConstraint($authority, $allowed)($query);
						});
					} 
					$query->orWhereIn("$permissions.id", function($query) use ($authority, $allowed, $permissions){
						$query->select("$permissions.id");
						static::getAuthorityConstraint($authority, $allowed)($query);
					});
					
				}
				if ($direct_only === false  || is_null($authority)){
					$query->orWhere(function($query) use ($allowed, $permissions){
						static::getEveryoneConstraint($allowed, true)($query);
						Models::scope()->applyToRelationQuery($query, $permissions);
					});
				}
        });
    }

    /**
     * Get a query for the authority's forbidden abilities.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function forbiddenForAuthority(?Model $authority, $direct_only = false)
    {
        return static::forAuthority($authority, false, $direct_only);
    }

    /**
     * Get a constraint for abilities that have been granted to the given authority through a role.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Closure
     */
    protected static function getRoleConstraint(Model $authority, $allowed)
    {
        return function ($query) use ($authority, $allowed) {
            $permissions = Models::table('permissions');
            $abilities   = Models::table('abilities');
            $roles       = Models::table('roles');

            $query->from($roles)
                  ->join($permissions, $roles.'.id', '=', $permissions.'.entity_id')
                  ->whereColumn("{$permissions}.ability_id", "{$abilities}.id")
                  ->where($permissions.".forbidden", ! $allowed)
                  ->where($permissions.".entity_type", Models::role()->getMorphClass());

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $permissions);

            $query->where(function ($query) use ($roles, $authority, $allowed) {
                $query->whereExists(static::getAuthorityRoleConstraint($authority));

                if ($allowed) {
                    static::addRoleInheritCondition($query, $authority, $roles);
                }
            });
        };
    }

    /**
     * Add the role inheritence "where" clause to the given query.
	 * Shared with Corbinjurgens\Bouncer\Database\Queries\Roles
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  string  $roles
     * @return \Closure
     */
    public static function addRoleInheritCondition(Builder $query, Model $authority, $roles)
    {
        $query->orWhere('level', '<', function ($query) use ($authority, $roles) {
            $query->selectRaw('max(level)')
                  ->from($roles)
                  ->whereExists(static::getAuthorityRoleConstraint($authority));

            Models::scope()->applyToModelQuery($query, $roles);
        });
    }

    /**
     * Get a constraint for roles that are assigned to the given authority.
	 * Shared with Corbinjurgens\Bouncer\Database\Queries\Roles
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @return \Closure
     */
    public static function getAuthorityRoleConstraint(Model $authority)
    {
        return function ($query) use ($authority) {
            $pivot  = Models::table('assigned_roles');
            $roles  = Models::table('roles');
            $table  = $authority->getTable();

            $query->from($table)
                  ->join($pivot, "{$table}.{$authority->getKeyName()}", '=', $pivot.'.entity_id')
                  ->whereColumn("{$pivot}.role_id", "{$roles}.id")
                  ->where($pivot.'.entity_type', $authority->getMorphClass())
                  ->where("{$table}.{$authority->getKeyName()}", $authority->getKey());

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $pivot);
        };
    }

    /**
     * Get a constraint for abilities that have been granted to the given authority.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $authority
     * @param  bool  $allowed
     * @return \Closure
     */
    protected static function getAuthorityConstraint(Model $authority, $allowed)
    {
        return function ($query) use ($authority, $allowed) {
            $permissions = Models::table('permissions');
            $abilities   = Models::table('abilities');
            $table       = $authority->getTable();

            $query->from($table)
                  ->join($permissions, "{$table}.{$authority->getKeyName()}", '=', $permissions.'.entity_id')
                  ->whereColumn("{$permissions}.ability_id", "{$abilities}.id")
                  ->where("{$permissions}.forbidden", ! $allowed)
                  ->where("{$permissions}.entity_type", $authority->getMorphClass())
                  ->where("{$table}.{$authority->getKeyName()}", $authority->getKey());

            Models::scope()->applyToModelQuery($query, $abilities);
            Models::scope()->applyToRelationQuery($query, $permissions);
        };
    }

    /**
     * Get a constraint for abilities that have been granted to everyone.
     *
     * @param  bool  $alloweds
	 * @param  bool  $direct
     * @return \Closure
     */
    protected static function getEveryoneConstraint($allowed, $direct = false)
    {
        return function ($query) use ($allowed, $direct) {
            $permissions = Models::table('permissions');
            $abilities   = Models::table('abilities');
			
			if ($direct === false){
				$query = $query->from($permissions)
                  ->whereColumn("{$permissions}.ability_id", "{$abilities}.id");
			}
			$query
                  ->where("{$permissions}.forbidden", ! $allowed)
                  ->whereNull("{$permissions}.entity_id");
			
			if ($direct === false){
				Models::scope()->applyToRelationQuery($query, $permissions);
			}
            
        };
    }
}
