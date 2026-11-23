<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One entry of the ISMS toolkit: a form to fill in, a procedure to follow, or a guide /
 * presentation. The master template file lives in storage/app/pack/toolkit and is never
 * modified: people download a fresh copy.
 */
class ToolkitItem extends Model
{
    public const TYPES = ['form', 'procedure', 'guide'];

    /** Template files an admin may upload (and a pack may ship). */
    public const TEMPLATE_EXTENSIONS = ['docx', 'xlsx', 'pdf', 'pptx', 'doc', 'xls'];

    protected $guarded = ['id'];

    protected $casts = [
        'title' => 'array', 'summary' => 'array', 'when_to_use' => 'array',
        'roles_guidance' => 'array', 'example_usage' => 'array', 'related_codes' => 'array',
        'is_active' => 'boolean', 'sort_order' => 'integer',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(ToolkitSubmission::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Items in the order of their codes, compared naturally (like Policy::sortByCode). */
    public static function sortByCode(Collection $items): Collection
    {
        return $items->sort(fn (self $a, self $b) => strnatcasecmp($a->code, $b->code))->values();
    }

    /** All matching items, in code order. */
    public static function ordered(?Builder $query = null): Collection
    {
        return self::sortByCode(($query ?? static::query())->get());
    }

    public static function storageDir(): string
    {
        return storage_path('app/pack/toolkit');
    }

    public function filePath(): ?string
    {
        return filled($this->file_path) ? self::storageDir().'/'.basename($this->file_path) : null;
    }

    public function hasFile(): bool
    {
        return ($path = $this->filePath()) !== null && is_file($path);
    }

    public function extension(): string
    {
        return strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION));
    }

    /** A clean, dated name for a downloaded copy, e.g. "e-a5-10-2026-11-23.xlsx". */
    public function downloadName(): string
    {
        $slug = Str::slug($this->code) ?: 'template';

        return $slug.'-'.now()->timezone(setting('timezone', 'Europe/Athens'))->format('Y-m-d').'.'.$this->extension();
    }

    public function acceptsSubmissions(): bool
    {
        return $this->type === 'form';
    }

    /** Text used by the search box: code and the localised texts, folded for accent-free matching. */
    public function searchText(): string
    {
        return self::fold(implode(' ', [
            $this->code, loc($this->title), loc($this->summary), loc($this->when_to_use),
            loc($this->roles_guidance), loc($this->example_usage), implode(' ', $this->related_codes ?? []),
        ]));
    }

    /** The same filter the page applies in the browser, so a link with ?type=…&q=… opens filtered. */
    public function matches(?string $type, ?string $query): bool
    {
        if ($type && $type !== $this->type) {
            return false;
        }
        $query = self::fold((string) $query);

        return $query === '' || collect(preg_split('/\s+/u', $query))->every(fn ($word) => str_contains($this->searchText(), $word));
    }

    /** Lowercase without accents ("Διαδικασία" → "διαδικασια"), mirroring the JavaScript side. */
    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = class_exists(\Normalizer::class)
            ? (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($text, \Normalizer::FORM_D))
            : strtr($text, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω', 'ϊ' => 'ι', 'ϋ' => 'υ', 'ΐ' => 'ι', 'ΰ' => 'υ']);

        return str_replace('ς', 'σ', $text);
    }
}
