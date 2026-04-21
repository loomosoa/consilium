<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'column_id',
        'role',
        'content',
        'sequence',
        'generation_id',
        'created_at',
    ];

    protected $casts = [
        'role' => MessageRole::class,
    ];

    public function column(): BelongsTo
    {
        return $this->belongsTo(ColumnConversation::class, 'column_id');
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('generation_id')
            ->whereHas('generation', fn ($q) => $q->where('status', 'completed'));
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class, 'generation_id');
    }
}
