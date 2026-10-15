<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A personal setting (badges, projection...) belonging to one account. */
class UserSetting extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];
}
