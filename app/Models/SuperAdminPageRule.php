<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAdminPageRule extends Model
{
    protected $fillable = ['route_name', 'company_id', 'is_visible', 'reason', 'updated_by'];
    protected $casts = ['is_visible' => 'boolean'];
}
