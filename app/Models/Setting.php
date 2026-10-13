<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** All settings, with config defaults filled in for anything not saved yet. */
    public static function allValues(): array
    {
        return array_merge(
            config('aetracker.defaults'),
            static::query()->pluck('value', 'key')->all(),
        );
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
