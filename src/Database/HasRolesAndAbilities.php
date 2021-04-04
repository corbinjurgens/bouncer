<?php

namespace Corbinjurgens\Bouncer\Database;

use Illuminate\Database\Eloquent\Collection;

use Corbinjurgens\Bouncer\Database\Concerns\HasRoles;
use Corbinjurgens\Bouncer\Database\Concerns\HasAbilities;

trait HasRolesAndAbilities
{
    use HasRoles, HasAbilities;
	
	public function getAllAbilities(){
		$direct_abilities = $this->abilities;
		$role_abilities = new Collection();
		foreach($this->roles as $roles){
			$role_abilities = $role_abilities->merge($roles->abilities);
		}
		return $role_abilities->merge($direct_abilities);
	}
}
