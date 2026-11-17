<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['question_text' => 'array', 'options' => 'array', 'explanation' => 'array', 'is_active' => 'boolean'];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /** Options as letter => text in the current locale. */
    public function localizedOptions(): array
    {
        return collect($this->options ?? [])->map(fn ($o) => loc($o))->all();
    }
}
