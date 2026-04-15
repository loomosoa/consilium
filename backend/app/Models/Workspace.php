<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'session_id',
        'state',
        'initial_prompt',
    ];

    protected $attributes = [
        'state' => 'initializing',
    ];

    public function columns(): HasMany
    {
        return $this->hasMany(ColumnConversation::class, 'workspace_id');
    }
}
