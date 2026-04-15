<?php

namespace App\Models;

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

    public function column(): BelongsTo
    {
        return $this->belongsTo(ColumnConversation::class, 'column_id');
    }
}
