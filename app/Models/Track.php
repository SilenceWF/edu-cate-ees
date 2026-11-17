<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['name' => 'array', 'requires_policies' => 'boolean', 'kiosk_enabled' => 'boolean'];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(TrainingModule::class, 'track_module')
            ->withPivot('sort_order')->orderBy('track_module.sort_order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    /** Active questions this track draws from (its own bank, or another track's via quiz_from). */
    public function questionPool()
    {
        $sourceId = $this->quiz_from ? (static::where('key', $this->quiz_from)->value('id') ?? $this->id) : $this->id;

        return QuizQuestion::where('track_id', $sourceId)->where('is_active', true)->orderBy('sort_order');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TrainingGroup::class);
    }
}
