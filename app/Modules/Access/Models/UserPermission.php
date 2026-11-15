<?php

namespace App\Modules\Access\Models;

use Illuminate\Database\Eloquent\Model;

/** One extra permission given to a person on top of their role. Written by SaveUser. */
class UserPermission extends Model
{
    protected $table = 'user_permission';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['user_id', 'permission'];
}
