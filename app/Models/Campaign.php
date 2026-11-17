<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Campaign extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean'];

    public static function current(): self
    {
        return static::where('is_current', true)->first()
            ?? static::create(['name' => (string) now()->year, 'starts_on' => now()->startOfYear(), 'is_current' => true]);
    }

    public function makeCurrent(): void
    {
        DB::transaction(function () {
            static::where('id', '!=', $this->id)->update(['is_current' => false]);
            $this->update(['is_current' => true]);
        });
    }
}
