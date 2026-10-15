<?php

namespace Olivier\EnhancedServerSorter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerFolder extends Model
{
    protected $table = 'enhanced_server_folders';

    protected $fillable = [
        'user_id',
        'name',
        'sort',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'sort' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ServerFolderAssignment::class, 'folder_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
