<?php

namespace Olivier\EnhancedServerSorter\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultFolderServerAssignment extends Model
{
    protected $table = 'enhanced_server_default_folder_servers';

    protected $fillable = [
        'default_folder_id',
        'server_id',
        'position',
    ];

    protected $casts = [
        'default_folder_id' => 'integer',
        'server_id' => 'integer',
        'position' => 'integer',
    ];

    public function defaultFolder(): BelongsTo
    {
        return $this->belongsTo(DefaultFolder::class, 'default_folder_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
