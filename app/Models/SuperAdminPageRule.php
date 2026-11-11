<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAdminPageRule extends Model
{
    // Nom explicite : évite l'incohérence entre superadmin_page_rules et super_admin_page_rules.
    protected $table = 'super_admin_page_rules';
    protected $fillable = ['route_name', 'company_id', 'is_visible', 'reason', 'updated_by'];
    protected $casts = ['is_visible' => 'boolean'];
}
