<?php

namespace App\Modules\Access\Models;

use Illuminate\Database\Eloquent\Model;

/** One ticked permission on a role. Read through Role::permissionRows; written by SaveRole. */
class RolePermission extends Model
{
    protected $table = 'role_permission';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['role_id', 'permission'];
}
