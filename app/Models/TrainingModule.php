<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrainingModule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['title' => 'array', 'subtitle' => 'array', 'slides' => 'array', 'is_active' => 'boolean'];

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'track_module')->withPivot('sort_order');
    }

    /** Slides resolved to the current locale, with icons rendered, ready for the browser. */
    public function slidesForDisplay(): array
    {
        return collect($this->slides ?? [])->map(fn ($s) => [
            'title' => loc($s['title'] ?? ''),
            'icon' => icon_svg($s['icon'] ?? null, 'size-10'),
            'summary' => loc($s['summary'] ?? ''),
            'bullets' => collect($s['bullets'] ?? [])->map(fn ($b) => loc($b))->values()->all(),
            'callout' => loc($s['callout'] ?? ($s['callout_box'] ?? '')),
        ])->values()->all();
    }
}
