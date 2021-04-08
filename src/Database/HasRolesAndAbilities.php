<?php

namespace Corbinjurgens\Bouncer\Database;

use Illuminate\Database\Eloquent\Collection;

use Corbinjurgens\Bouncer\Database\Concerns\HasRoles;
use Corbinjurgens\Bouncer\Database\Concerns\HasAbilities;

trait HasRolesAndAbilities
{
    use HasRoles, HasAbilities;
	
}
