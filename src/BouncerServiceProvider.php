<?php

namespace Corbinjurgens\Bouncer;

use Illuminate\Support\Arr;
use Corbinjurgens\Bouncer\Database\Models;
use Corbinjurgens\Bouncer\Console\CleanCommand;

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model as EloquentModel;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BouncerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBouncer();
        $this->registerCommands();
		
		$this->registerRelationMacros();
		
		// Control
		$this->mergeControlConfig();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerMorphs();
        $this->setUserModel();

        $this->registerAtGate();

        if ($this->app->runningInConsole()) {
            $this->publishMiddleware();
            $this->publishMigrations();
			
			// Control
            $this->publishControlConfig();
        }
    }
	protected function registerRelationMacros(){
		BelongsToMany::macro('filtered_sync', $this->relationMacro());
	}
	/**
	 * Use just as normal sync(). It will automatically only detach items that are missing match the filtered results.
	 * Optionally, use a query closure as $compare to double check that the $ids you are trying to sync are matching. For best results, use
	 * the same query closure for both synching and comparing (leave out any pivot related queries for comparing). This is a workaround as achieving this properly would require heavily modifying
	 * laravel classes
	 */
	protected function relationMacro(){
		return function($ids, $detaching = true, \Closure $compare = null)
		{
			$changes = [
				'attached' => [], 'detached' => [], 'updated' => [],
			];
			
			$records = $this->formatRecordsList($this->parseIds($ids));
			
			// If $compare closure is given, apply it to a new related query and confirm these items to be added or updated match the filters
			if (!is_null($compare)){
				$compare_ids = $this->related->where($compare)->whereIn($this->getRelatedKeyName(), array_keys($records))->get([$this->getRelatedKeyName()])->pluck($this->getRelatedKeyName())->all();
				$records = array_intersect_key($records, array_flip($compare_ids));
			}
			
			// Unlike the sync method, this also takes into account all 'where' and other filtering
			// to decide which to delete and which are new
			$current = $this->get([$this->related->getTable() . '.' . $this->getRelatedKeyName()])->pluck( $this->getRelatedKeyName() )->all();
			
			// From here on is same as normal sync() function
			$detach = array_diff($current, array_keys(
				$records
			));
			
			if ($detaching && count($detach) > 0) {
				$this->detach($detach);

				$changes['detached'] = $this->castKeys($detach);
			}
			
			$changes = array_merge(
				$changes, $this->attachNew($records, $current, false)
			);
			
			if (count($changes['attached']) ||
				count($changes['updated'])) {
				$this->touchIfTouching();
			}

			return $changes;
		};
	}

    /**
     * Register Bouncer as a singleton.
     *
     * @return void
     */
    protected function registerBouncer()
    {
        $this->app->singleton(Bouncer::class, function () {
            return Bouncer::make()
                ->withClipboard(new CachedClipboard(new ArrayStore))
                ->withGate($this->app->make(Gate::class))
                ->create();
        });
    }

    /**
     * Register Bouncer's commands with artisan.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands(CleanCommand::class);
    }

    /**
     * Register Bouncer's models in the relation morph map.
     *
     * @return void
     */
    protected function registerMorphs()
    {
        Models::updateMorphMap();
    }

    /**
     * Publish the package's middleware.
     *
     * @return void
     */
    protected function publishMiddleware()
    {
        $stub = __DIR__.'/../middleware/ScopeBouncer.php';

        $target = app_path('Http/Middleware/ScopeBouncer.php');

        $this->publishes([$stub => $target], 'bouncer.middleware');
    }

    /**
     * Publish the package's migrations.
     *
     * @return void
     */
    protected function publishMigrations()
    {
        if (class_exists('CreateBouncerTables')) {
            return;
        }

        $timestamp = date('Y_m_d_His', time());

        $stub = __DIR__.'/../migrations/create_bouncer_tables.php';

        $target = $this->app->databasePath().'/migrations/'.$timestamp.'_create_bouncer_tables.php';

        $this->publishes([$stub => $target], 'bouncer.migrations');
    }

    /**
     * Publish the config for Control
     *
     * @return void
     */
    protected function publishControlConfig()
    {

        $stub = __DIR__.'/control/bouncercontrol.php';

        $target = config_path('bouncercontrol' . '.php');

        $this->publishes([$stub => $target], 'bouncer.controlconfig');
    }

    /**
     * Merge config for Control
     *
     * @return void
     */
    protected function mergeControlConfig()
    {

        $stub = __DIR__.'/control/bouncercontrol.php';

        $target = 'bouncercontrol';

        $this->mergeConfigFrom($stub, $target);
    }

    /**
     * Set the classname of the user model to be used by Bouncer.
     *
     * @return void
     */
    protected function setUserModel()
    {
        if ($model = $this->getUserModel()) {
            Models::setUsersModel($model);
        }
    }

    /**
     * Get the user model from the application's auth config.
     *
     * @return string|null
     */
    protected function getUserModel()
    {
        $config = $this->app->make('config');

        if (is_null($guard = $config->get('auth.defaults.guard'))) {
            return null;
        }

        if (is_null($provider = $config->get("auth.guards.{$guard}.provider"))) {
            return null;
        }

        $model = $config->get("auth.providers.{$provider}.model");

        // The standard auth config that ships with Laravel references the
        // Eloquent User model in the above config path. However, users
        // are free to reference anything there - so we check first.
        if (is_subclass_of($model, EloquentModel::class)) {
            return $model;
        }
    }

    /**
     * Register the bouncer's clipboard at the gate.
     *
     * @return void
     */
    protected function registerAtGate()
    {
        // When creating a Bouncer instance thru the Factory class, it'll
        // auto-register at the gate. We already registered Bouncer in
        // the container using the Factory, so now we'll resolve it.
        $this->app->make(Bouncer::class);
    }
}
