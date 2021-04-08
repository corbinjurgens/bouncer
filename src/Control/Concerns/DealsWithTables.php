<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control\Concerns;


use Corbinjurgens\Bouncer\Control as c;
use Corbinjurgens\Bouncer\Database\Models;


use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

use Corbinjurgens\Bouncer\Control\Tools;
use Illuminate\Database\Eloquent\Relations\Relation;
use Cache;

trait DealsWithTables
{
	use Tools;
	
	public static function getTableInfo($table_name = null){
		$fetch = function(){
			$tables = config('bouncercontrol.tables', []);
			$table_defaults = config('bouncercontrol.table_defaults', []);
			foreach($tables as &$table){
				$table = array_replace($table_defaults, $table);
			}
			return collect($tables);
			//return Table::all()->keyBy('name');
		};
		//$tables = Cache::remember('table_info', 60 * 1, $fetch);
		$tables = $fetch();
		return 
			!is_null($table_name) ? 
			( isset($tables[$table_name]) ? $tables[$table_name] : null ) :
			$tables;
	}
	
	protected static function toTableInstance($table, $id = null){
		if (is_null($table)){
			return null;
		}
		if ($table == '*'){
			return $table;
		}
		if ($table instanceof Model){
			return $table;
		}
		if ( ! class_exists ( $table ) ){
			$morphMap = Relation::morphMap();
			if (! empty($morphMap) && isset($morphMap[$table])) {
				$table = $morphMap[$table];
			}else{
				throw new InvalidArgumentException(
					"Table $table not found, be sure it exists and you are giving either full class path string or morph string"
				);
			}
		}
		$instance = new $table();
		if (!is_null($id)){
			$instance->{$instance->getKeyName()} = $id;
			$instance->exists = true;
		}
		return $instance;
		
		
	}
	
	
	/**
	 * Get a tables abilities as declared in bouncercontrol config
	 */
	public static function getTableAbilities($table, $mode = 'general'){
		$only_options = [
			'general' => true,
			'specific' => true,
			'forbid_general' => true,
			'forbid_specific' => true,
			
			'anything' => false,
			'claim' => false,
		];
		$default_options = $only_options;
		$table_config = self::getTableInfo($table);
		$abilities = self::optionsArray( config('bouncercontrol.table_abilities', []), $default_options ) +
			self::optionsArray( $table_config['abilities'] ?? [], $default_options );
			
		// If the table has a abilities_except array, exlude these abilities
		$abilities = array_diff_key($abilities, array_flip($table_config['abilities_except'] ?? []));
		
		
		
		$comparison_options = $only_options;
		foreach($abilities as $key => $value){
			if (@$value[$mode] == false){
				unset( $abilities[$key] );
			}
		}
		
		return array_keys($abilities);
	}
	
	/**
	 * From a specific tables list as from getTableAbilities(), get the presets
	 */
	public function getTablePresets($list = []){
		$prepared = [];
		$presets = config('bouncercontrol.table_compound_abilities');
		foreach($presets as $preset => $abilities){
			$prepared[$preset] = array_intersect($abilities, $list);
		}
		return $prepared;
		
	}
	
	
	
	private $tables;
	private $modes;
	
	/**
	 * Pass null for all tables (default) or pass array of table names as listed in 
	 * bouncercontrol config to only show some
	 * Can pass values to further define restrictions on mode
	 * eg
	 [
		'table1',
		'table2' => ['general_permissions', 'forbid_general_permissions']
		'table3' => null // if you always passed key value like this line, ensure its null unless you want to limit modes too
	 ]
	 * Pass null for mode, or indexed array only. If tables have values, the tables values will be prioritized
	 */
	public function only($tables = null, $modes = null){
		$this->tables = is_string($tables) ? [$tables] : $tables;
		$this->modes = is_string($modes) ? [$modes] : $modes;
		return $this;
	}
	private function getOnly(){
		if (!is_array($this->tables)){
			return null;
		}
		$only = [];
		$modes = $this->modes;
		foreach($this->tables as $key => $value){
			$table = $value;
			$options = $modes;
			if (is_string($key)) {
				$table = $key;
				$options = $value;
			}
			$only[$table] = $options;
		}
		return $only;
	}
	/**
	 * Pass result of getOnly, with table string, and the mode you want to test (eg 'general_permissions', 'forbid_general_permissions')
	 * To check if should get/process the mode
	 */
	private function checkModeFromOnly($only, $table = null, $mode = null){
		if (is_null($only) || is_null($table)){
			return is_array($this->modes) ? in_array($mode, $this->modes) : true;
		}
		return array_key_exists($table, $only) ? (is_array($only[$table]) ? in_array($mode, $only[$table]) : true ) : false;
		
	}
	
	
	
	
}