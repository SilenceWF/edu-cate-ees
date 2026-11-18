<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Policy extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'title' => 'array', 'key_rules' => 'array', 'track_keys' => 'array',
        'effective_date' => 'date', 'is_mandatory_all' => 'boolean', 'is_active' => 'boolean',
    ];

    public function acknowledgments(): HasMany
    {
        return $this->hasMany(PolicyAcknowledgment::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Policies in the order of their codes, compared naturally: "Π Α5-2" before "Π Α5-10",
     * "Π Α5-4" before "Π Α6-1".
     */
    public static function sortByCode(Collection $policies): Collection
    {
        return $policies->sort(fn (self $a, self $b) => strnatcasecmp($a->code, $b->code))->values();
    }

    /** All matching policies, in code order. */
    public static function ordered(?Builder $query = null): Collection
    {
        return self::sortByCode(($query ?? static::query())->get());
    }

    public function appliesTo(?Track $track): bool
    {
        if (! $track || ! $track->requires_policies) {
            return false;
        }

        return $this->is_mandatory_all || in_array($track->key, $this->track_keys ?? [], true);
    }

    public function hasDocument(): bool
    {
        return filled($this->document_url);
    }

    public function documentIsExternal(): bool
    {
        return (bool) preg_match('#^https?://#i', (string) $this->document_url);
    }
}
