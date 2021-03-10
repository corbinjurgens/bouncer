<?php

namespace Corbinjurgens\Bouncer\Database\Concerns;

use Illuminate\Container\Container;

use Corbinjurgens\Bouncer\Helpers;
use Corbinjurgens\Bouncer\Database\Models;
use Corbinjurgens\Bouncer\Database\Ability;
use Corbinjurgens\Bouncer\Database\Permission;
use Corbinjurgens\Bouncer\Contracts\Clipboard;
use Corbinjurgens\Bouncer\Conductors\GivesAbilities;
use Corbinjurgens\Bouncer\Conductors\ForbidsAbilities;
use Corbinjurgens\Bouncer\Conductors\RemovesAbilities;
use Corbinjurgens\Bouncer\Conductors\UnforbidsAbilities;

trait HasAbilities
{
    /**
     * Boot the HasAbilities trait.
     *
     * @return void
     */
    public static function bootHasAbilities()
    {
        static::deleted(function ($model) {
            if (! Helpers::isSoftDeleting($model)) {
                $model->abilities()->detach();
            }
        });
    }

    /**
     * The abilities relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function abilities()
    {
        $relation = $this->morphToMany(
            Models::classname(Ability::class),
            'entity',
            Models::table('permissions')
        )->withPivot('forbidden', 'scope')->using( Models::classname(Permission::class) );

        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Get all of the model's allowed abilities.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAbilities()
    {
        return Container::getInstance()
            ->make(Clipboard::class)
            ->getAbilities($this);
    }

    /**
     * Get all of the model's allowed abilities.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForbiddenAbilities()
    {
        return Container::getInstance()
            ->make(Clipboard::class)
            ->getAbilities($this, false);
    }

    /**
     * Give an ability to the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Corbinjurgens\Bouncer\Conductors\GivesAbilities|$this
     */
    public function allow($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new GivesAbilities($this);
        }

        (new GivesAbilities($this))->to($ability, $model);

        return $this;
    }

    /**
     * Remove an ability from the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Corbinjurgens\Bouncer\Conductors\RemovesAbilities|$this
     */
    public function disallow($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new RemovesAbilities($this);
        }

        (new RemovesAbilities($this))->to($ability, $model);

        return $this;
    }

    /**
     * Forbid an ability to the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Corbinjurgens\Bouncer\Conductors\ForbidsAbilities|$this
     */
    public function forbid($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new ForbidsAbilities($this);
        }

        (new ForbidsAbilities($this))->to($ability, $model);

        return $this;
    }

    /**
     * Remove ability forbiddal from the model.
     *
     * @param  mixed  $ability
     * @param  mixed|null  $model
     * @return \Corbinjurgens\Bouncer\Conductors\UnforbidsAbilities|$this
     */
    public function unforbid($ability = null, $model = null)
    {
        if (is_null($ability)) {
            return new UnforbidsAbilities($this);
        }

        (new UnforbidsAbilities($this))->to($ability, $model);

        return $this;
    }
}
