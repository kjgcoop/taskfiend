<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'filename',
        'created_by',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'template_id');
    }

    /**
     * The most recent date this template was used to create a project.
     * Returns null if it has never been used.
     */
    public function getLastUsedAtAttribute(): ?string
    {
        return $this->projects()->max('created_at');
    }
}
