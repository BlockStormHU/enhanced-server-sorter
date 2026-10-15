<?php

namespace Olivier\EnhancedServerSorter\Providers;

use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Models\Server;
use App\Models\User;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Olivier\EnhancedServerSorter\Models\ServerFolderAssignment;
use Olivier\EnhancedServerSorter\Services\FolderService;
use Throwable;

class EnhancedServerSorterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped('filament', fn(): FolderOrderedFilamentManager => new FolderOrderedFilamentManager());

        Server::resolveRelationUsing('enhancedFolderAssignment', function (Server $server) {
            return $server->hasOne(\Olivier\EnhancedServerSorter\Models\ServerFolderAssignment::class, 'server_id', 'id')
                ->where('user_id', user()?->id)
                ->with('folder');
        });
    }

    public function boot(): void
    {
        View::prependNamespace('filament-panels', plugin_path('enhanced-server-sorter', 'resources/views/filament-panels'));

        if (!\Illuminate\Support\Facades\Schema::hasTable('enhanced_server_folder_server')) {
            return;
        }

        Table::configureUsing(function (Table $table): void {
            if (!$table->getLivewire() instanceof ListServers || !$this->app->make(FolderService::class)->isReady()) {
                return;
            }

            $table
                ->groups([$this->makeFolderGroup()])
                ->defaultGroup('enhancedFolderAssignment.folder.name');
        });

        User::created(function (User $user): void {
            try {
                $this->app->make(FolderService::class)->syncDefaultFolders($user->id);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    private function makeFolderGroup(): Group
    {
        return Group::make('enhancedFolderAssignment.folder.name')
            ->label(fn () => trans('enhanced-server-sorter::messages.folders'))
            ->titlePrefixedWithLabel(false)
            ->getTitleFromRecordUsing(fn (Server $server): string => $server->enhancedFolderAssignment?->folder?->name
                ?? trans('enhanced-server-sorter::messages.unassigned'))
            ->collapsible()
            ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                $direction = $direction === 'desc' ? 'desc' : 'asc';
                $userId = (int) (user()?->id ?? 0);
                $serverId = $query->qualifyColumn('id');

                $this->unwrapDistinct($query, $serverId);

                $folderSort = "(SELECT esf.sort FROM enhanced_server_folder_server esfs JOIN enhanced_server_folders esf ON esf.id = esfs.folder_id WHERE esfs.user_id = ? AND esfs.server_id = {$serverId} LIMIT 1)";
                $position = "(SELECT esfs.position FROM enhanced_server_folder_server esfs WHERE esfs.user_id = ? AND esfs.server_id = {$serverId} LIMIT 1)";

                return $query
                    ->orderByRaw("COALESCE({$folderSort}, 2147483647) {$direction}", [$userId])
                    ->orderByRaw("COALESCE({$position}, 2147483647) {$direction}", [$userId])
                    ->orderBy($query->qualifyColumn('name'));
            });
    }

    private function unwrapDistinct(Builder $query, string $serverId): void
    {
        $base = $query->getQuery();

        if (!$base->distinct && empty($base->joins)) {
            return;
        }

        $ids = $base->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->select($serverId);

        $base->distinct = false;
        $base->joins = null;
        $base->wheres = [];
        $base->bindings['join'] = [];
        $base->bindings['where'] = [];
        $base->whereIn($serverId, $ids);
    }
}
