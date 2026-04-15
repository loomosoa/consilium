<?php

namespace App\Models;

use App\Enums\ColumnStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ColumnConversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'model_code',
        'title',
        'position',
        'status',
        'last_generation_id',
        'last_error_code',
        'last_error_message',
    ];

    protected $attributes = [
        'status' => 'idle',
    ];

    protected $casts = [
        'status' => ColumnStatus::class,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'column_id');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class, 'column_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['waiting', 'streaming']);
    }
}
