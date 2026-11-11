<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiScanSetting extends Model
{
    protected $fillable = ['quotas_enabled', 'limits', 'updated_by'];

    protected $casts = [
        'quotas_enabled' => 'boolean',
        'limits' => 'array',
    ];
}
