<?php

namespace Olivier\EnhancedServerSorter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DefaultFolder extends Model
{
    protected $table = 'enhanced_server_default_folders';

    protected $fillable = [
        'name',
        'sort',
        'is_locked',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function serverAssignments(): HasMany
    {
        return $this->hasMany(DefaultFolderServerAssignment::class, 'default_folder_id');
    }
}
