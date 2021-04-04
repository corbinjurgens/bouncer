<?php

namespace Corbinjurgens\Bouncer\Conductors\Concerns;

use Corbinjurgens\Bouncer\Helpers;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

use Corbinjurgens\Bouncer\Database\Models;

trait ScopesModel
{
	/**
	 * 
	 * Custom Model to scope to certain model sync only. Model intance or model class string.
	 * Can also be array with at least 'class' (must be full, not morph) and 'id' (or whatever the key should be)
	 */
	protected $scoping_model = false;
	protected $scoping_model_mode = null;
    protected $scope_model = null;
	/**
	 * Basic scope to model. Automatically passes model when necessary, and scopes to the model
	 */
	public function whereModel($scope_model = null){
		$this->scoping_model = true;
		$this->scoping_model_mode = null;
		$this->scope_model = $scope_model;
		return $this;
	}
	/**
	 * If passing class string or new class model, it will ONLY scope models that are have empty entity_id,
	 * Passing a model that exists will have same affect as whereModel
	 * Good to use when you are trying to sync abilities which are only ones that ARE NOT restricted to entity_id
	 */
	public function whereModelStrict($scope_model = null){
		$this->scoping_model = true;
		$this->scoping_model_mode = 'strict';
		$this->scope_model = $scope_model;
		return $this;
	}
	/**
	 * If passing class string or new class model, it will ONLY scope models that have some entity_id,
	 * Passing an model that exists will have same affect as whereModel
	 * Good to use when you are trying to sync abilities which are only ones that ARE restricted to entity_id
	 */
	public function whereModelSpecific($scope_model = null){
		$this->scoping_model = true;
		$this->scoping_model_mode = 'specific';
		$this->scope_model = $scope_model;
		return $this;
	}
	/**
	 * Bare model. will not query, but will still be passed on and used when finding 
	 * abilities (eg from sync), so you can set model here, and then set a whereCustom() custom scope
	 */
	public function setModel($scope_model = null){
		$this->scoping_model = true;
		return $this;
	}
	/**
	 * Explicitly Passing null to whereModel() will look for abilities with no models.
	 * Use this to truly clear it.
	 */
	public function resetModel(){
		$this->scoping_model = false;
		$this->scoping_model_mode = null;
		$this->scope_model = null;
		return $this;
	}
	
	/**
	 * Custom scoper, applied when scopeModel() is applied so all situations where it is used should have no issue
	 * You can do anything unrelated to model like scope to only some abilities
	 * You should not use this for scoping to model as the ability will not correctly be found, unless you also set the model with setModel
	 * WARNING you will likely need to use $query->qualifyColumn('...') or other method to ensure your getting correct tables column
	 */
	private $custom_scoper = null;
	public function whereCustom(\Closure $closure){
		$this->custom_scoper = $closure;
		return $this;
	}
	public function resetCustom(){
		$this->custom_scoper = null;
		return $this;
	}
	
	private $special_scope = null;
	static $special_abilities = [
		'claim',
	];
	/**
	 * Eg set to 'claim' mode to only modify the special ability,
	 * Otherwise it will ignore all special abilities as declared in static $special_abilities
	 * Special abilities are actually stored with '__' prefix to not conflict with a users own abilities of the 
	 * same name. It will auto prefix upon query, so do not input here with prefix
	 */
	public function specialScope($string_or_array = null){
		if (!is_null($string_or_array)){
			$string_or_array = is_array($string_or_array) ? $string_or_array : [$string_or_array];
		}
		$this->special_scope = $string_or_array;
		return $this;
	}
	public function resetSpecialScope(){
		$this->special_scope = null;
		return $this;
	}
	/**
	 * Pass the current instance model scope settings to another instance that also uses this trait
	 * Eg, in SyncsRoleAndAbilities syncAbilities when creating new $associateClass
	 */
	public function passModelScope($instance){
		if ($this->scoping_model){
			if ($this->scoping_model_mode == 'strict'){
				$function = 'whereModelStrict';
			}else if ($this->scoping_model_mode == 'specific'){
				$function = 'whereModelSpecific';
			}else{
				$function = 'whereModel';
			}
			$instance->$function($this->scope_model);
		}else{
			$instance->setModel($this->scope_model);// In the case scope_model has been manually set via setModel
			$instance->resetModel();
		}
		if ($this->custom_scoper instanceof \Closure){
			$instance->whereCustom($this->custom_scoper);
		}else{
			$instance->resetCustom();
		}
		if (is_array($this->special_scope)){
			$instance->specialScope($this->special_scope);
		}else{
			$instance->resetSpecialScope();
		}
		return $instance;
	}
	/**
	 * Chaining off an ability query, Scope to a certain model
	 * so when syncing or modifying permissions only certain models will be affected
	 */
	protected function scopeModel($relation){
		$closure = $this->scopeModelClosure();
		return $closure($relation);
	}
	/**
	 * Scope model is stored as a closure in order to filter the results in other places too
	 */
	public function scopeModelClosure(){
		return function($relation){
			$scope_model = $this->scope_model;
			$table = Models::table('abilities');
			$relation->when($this->scoping_model, function($query) use ($scope_model, $table){
				if (is_null($scope_model)){
					$query->whereNull("$table.entity_type");
				}else{
					$model = $this->getScopeModel(false);
					if ($model instanceof Model && $model->exists){
						$query->where("$table.entity_id", $model->getKey());
					}else if ($this->scoping_model_mode == 'strict'){
						$query->whereNull("$table.entity_id");
					}else if ($this->scoping_model_mode == 'specific'){
						$query->whereNotNull("$table.entity_id");
					}
					$query->where("$table.entity_type", ($model instanceof Model) ? $model->getMorphClass() : $model);
				}
				 
			 });
			 
			if ($this->custom_scoper instanceof \Closure){
				$relation->where($this->custom_scoper);
			}
			
			if (is_array($this->special_scope)){
				$special_scope = array_map(function($item){
					return '__' . $item;
				}, $this->special_scope);
				$relation->whereIn("$table.name", $special_scope);
			}else{
				$special_scope = array_map(function($item){
					return '__' . $item;
				}, self::$special_abilities);
				$relation->whereNotIn("$table.name", $special_scope);
			}
		};
	}
	/**
	 * Turn scope_model propery to instance, unless user_mode is true
	 * in which case returns model as was originally given, and 
	 * ensure a instance that doesn't exist returns class name instead
	 * to prevent error at bouncer/src/Conductors/Concerns/FindsAndCreatesAbilities.php:215
	 */
	private function getScopeModel($user_mode = true){
		$scope_model = $this->scope_model;
		if ($user_mode === true){
			if ( is_array($scope_model) ){
				$scope_model = $this->hydrateScopeModel($scope_model);
			}
			if ($scope_model instanceof Model && !$scope_model->exists){
				$scope_model = get_class($scope_model);
			}
			return $scope_model;
		}
		
		$model = $scope_model;
		if (is_string($scope_model) && $scope_model !== '*'){
			$model = new $scope_model();
		}else if( is_array($scope_model) ){
			$model = $this->hydrateScopeModel($scope_model);
		}
		return $model;
	}
	private function hydrateScopeModel($array){
		$model = new $array['class']();
		unset($array['class']);
		$model->forceFill($array);
		$model->exists = isset($array[$model->getKey()]);
		return $model;
	}
}
