<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderCall extends Model
{
    protected $guarded = [];

    protected $casts = ['is_test' => 'boolean'];
}
