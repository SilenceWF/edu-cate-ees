<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A filled-in toolkit form sent to the security officer. Kept as evidence: never edited or deleted. */
class ToolkitSubmission extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ToolkitItem::class, 'toolkit_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function storageDir(): string
    {
        return storage_path('app/toolkit-submissions');
    }

    public function path(): string
    {
        return self::storageDir().'/'.basename($this->file_path);
    }
}
