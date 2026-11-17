<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingGroup extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['name' => 'array', 'description' => 'array'];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
