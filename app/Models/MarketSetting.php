<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $hidden = ['value'];
}
