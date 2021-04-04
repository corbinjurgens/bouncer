<?php

namespace Corbinjurgens\Bouncer\Database;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

use Corbinjurgens\Bouncer\Database\Models;
use Corbinjurgens\Bouncer\Database\Ability;

class Permission extends MorphPivot
{
	public $incrementing = true;
	const CREATED_AT = null;
	const UPDATED_AT = null;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    //protected $fillable = ['name', 'title', 'level'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'pivot_options' => 'array'
    ];

    /**
     * Constructor.
     *
     * @param array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('permissions');

        parent::__construct($attributes);
    }
	
	public function ability(){
		return $this->belongsTo( Models::classname(Ability::class) );
	}
}
