<?php

namespace Corbinjurgens\Bouncer\Conductors\Concerns;

use Corbinjurgens\Bouncer\Helpers;
use Illuminate\Support\Collection;
use Corbinjurgens\Bouncer\Conductors\Lazy;

trait ConductsAbilities
{
	/**
	 * Declare attributes for the pivot before running to() or other conducting function
	 * Pass an array, such as 
	 * ['pivot_options' => ['limit' => 2]]
	 * To change the pivots attributes when attatching / syncing
	 */
	protected $pivot_attributes = null;
	
	public function setPivot(array $pivot_attributes = null){
		$this->pivot_attributes = $pivot_attributes;
		return $this;
	}
	
	protected function getPivotAttributes(){
		$pivot_attributes = [];
		if (is_array($this->pivot_attributes)){
			$pivot_attributes = $this->pivot_attributes;
		}
		return $pivot_attributes;
		
	}
	
    /**
     * Allow/disallow all abilities on everything.
     *
     * @param  array  $attributes
     * @return mixed
     */
    public function everything(array $attributes = [])
    {
        return $this->to('*', '*', $attributes);
    }

    /**
     * Allow/disallow all abilities on the given model.
     *
     * @param  string|array|\Illuminate\Database\Eloquent\Model  $models
     * @param  array  $attributes
     * @return void
     */
    public function toManage($models, array $attributes = [])
    {
        if (is_array($models)) {
            foreach ($models as $model) {
                $this->to('*', $model, $attributes);
            }
        } else {
            $this->to('*', $models, $attributes);
        }
    }

    /**
     * Allow/disallow owning the given model.
     *
     * @param  string|object  $model
     * @param  array  $attributes
     * @return \Corbinjurgens\Bouncer\Conductors\Lazy\HandlesOwnership
     */
    public function toOwn($model, array $attributes = [])
    {
        return new Lazy\HandlesOwnership($this, $model, $attributes);
    }

    /**
     * Allow/disallow owning all models.
     *
     * @param  array  $attributes
     * @return \Corbinjurgens\Bouncer\Conductors\Lazy\HandlesOwnership
     */
    public function toOwnEverything(array $attributes = [])
    {
        return $this->toOwn('*', $attributes);
    }

    /**
     * Determines whether a call to "to" with the given parameters should be conducted lazily.
     *
     * @param  mixed  $abilities
     * @param  mixed  $model
     * @return bool
     */
    protected function shouldConductLazy($abilities)
    {
        // We'll only create a lazy conductor if we got a single
        // param, and that single param is either a string or
        // a numerically-indexed array (of simple strings).
        if (func_num_args() > 1) {
            return false;
        }

        if (is_string($abilities)) {
            return true;
        }

        if (! is_array($abilities) || ! Helpers::isIndexedArray($abilities)) {
            return false;
        }

        return (new Collection($abilities))->every('is_string');
    }

    /**
     * Create a lazy abilities conductor.
     *
     * @param  string|string[]  $ablities
     * @return \Corbinjurgens\Bouncer\Conductors\Lazy\ConductsAbilities
     */
    protected function conductLazy($abilities)
    {
        return new Lazy\ConductsAbilities($this, $abilities);
    }
}
