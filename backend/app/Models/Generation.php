<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'column_id',
        'user_message_id',
        'status',
        'partial_output',
        'prompt_tokens',
        'completion_tokens',
        'error_code',
        'error_message',
        'retryable',
        'started_at',
        'completed_at',
        'created_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'retryable' => false,
    ];

    protected $casts = [
        'status' => GenerationStatus::class,
        'retryable' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function column(): BelongsTo
    {
        return $this->belongsTo(ColumnConversation::class, 'column_id');
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'user_message_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'streaming']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
