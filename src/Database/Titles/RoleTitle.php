<?php

namespace Corbinjurgens\Bouncer\Database\Titles;

use Illuminate\Database\Eloquent\model;

class RoleTitle extends Title
{
    public function __construct(Model $role)
    {
        $this->title = $this->humanize($role->name);
    }
}
