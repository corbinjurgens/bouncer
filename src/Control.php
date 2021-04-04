<?php
// cj custom
namespace Corbinjurgens\Bouncer;
use Cache;
use Auth;
//use App\Models\Table;

use Corbinjurgens\Bouncer\Control\GetPermissions;

class Control
{
	public function getTableInfo($table_name = null){
		$tables = Cache::remember('table_info', 60 * 5, function(){
			$tables = config('bouncercontrol.tables', []);
			$table_defaults = config('bouncercontrol.table_defaults', []);
			foreach($tables as &$table){
				$table = array_replace($table_defaults, $table);
			}
			return collect($tables);
			//return Table::all()->keyBy('name');
		});
		return 
			!is_null($table_name) ? 
			( isset($tables[$table_name]) ? $tables[$table_name] : null ) :
			$tables;
	}
   
	public function as($user){
		return new GetPermissions($user);
	}
	
	public function for($user){
		return ( new GetPermissions( auth()->user() ) )->for($user);
	}
	
	public function getUserPermissions(){
		return ( new GetPermissions( auth()->user() ) )->for( auth()->user() )->getUserPermissions();
	}
	
	
}