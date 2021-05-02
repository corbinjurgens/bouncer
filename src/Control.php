<?php
// cj custom
namespace Corbinjurgens\Bouncer;
use Cache;
use Auth;
//use App\Models\Table;

use Corbinjurgens\Bouncer\Control\GetPermissions;

class Control
{
   
	public function as($user){
		return new GetPermissions($user);
	}
	
	public function for($user){
		return ( new GetPermissions( Auth::user() ) )->for($user);
	}
	
	
}