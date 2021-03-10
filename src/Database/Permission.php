<?php

namespace Corbinjurgens\Bouncer\Database;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class Permission extends MorphPivot
{
	public $incrementing = true;
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
}
