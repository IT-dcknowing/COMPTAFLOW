<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderCredential extends Model
{
    protected $fillable = ['provider', 'model', 'api_key', 'is_active', 'notes', 'updated_by'];
    protected $hidden = ['api_key'];
    protected $casts = ['api_key' => 'encrypted', 'is_active' => 'boolean'];
}
