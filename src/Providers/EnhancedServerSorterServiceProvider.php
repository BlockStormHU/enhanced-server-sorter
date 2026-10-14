<?php

namespace Olivier\EnhancedServerSorter\Providers;

use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Models\Server;
use App\Models\User;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
<<<<<<< Updated upstream
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
=======
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Olivier\EnhancedServerSorter\Models\ServerFolderAssignment;
use Olivier\EnhancedServerSorter\Services\FolderService;
use Throwable;
>>>>>>> Stashed changes

class EnhancedServerSorterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
<<<<<<< Updated upstream
        Server::resolveRelationUsing('enhancedFolderAssignment', function (Server $server) {
            return $server->hasOne(\Olivier\EnhancedServerSorter\Models\ServerFolderAssignment::class, 'server_id', 'id')
                ->where('user_id', user()?->id)
                ->with('folder');
        });
=======
        $this->app->scoped(FolderService::class);

        Server::resolveRelationUsing('enhancedFolderAssignment', fn (Server $server) => $server
            ->hasOne(ServerFolderAssignment::class, 'server_id')
            ->where('user_id', user()?->id));
>>>>>>> Stashed changes
    }

    public function boot(): void
    {
<<<<<<< Updated upstream
        if (!\Illuminate\Support\Facades\Schema::hasTable('enhanced_server_folder_server')) {
            return;
        }
=======
        View::prependNamespace('filament-panels', plugin_path('enhanced-server-sorter', 'resources/views/filament-panels'));
>>>>>>> Stashed changes

        Table::configureUsing(function (Table $table): void {
            if (!$table->getLivewire() instanceof ListServers || !$this->app->make(FolderService::class)->isReady()) {
                return;
            }

            $table
<<<<<<< Updated upstream
                ->groups([
                    (function (): Group {
                        $group = Group::make('enhancedFolderAssignment.folder.name')
                            ->label(fn () => trans('enhanced-server-sorter::messages.folders'))
                            ->titlePrefixedWithLabel(false);

                        $group->getTitleFromRecordUsing(function (Server $server): string {
                            return $server->enhancedFolderAssignment?->folder?->name ?? trans('enhanced-server-sorter::messages.unassigned');
                        })
                        ->collapsible();

                        $group->orderQueryUsing(function (Builder $query, string $direction): Builder {
                            $userId = (int) (user()?->id ?? 0);
                            $serverIdColumn = Server::query()->qualifyColumn('id');
                            $folderSortSub = "(SELECT esf.sort FROM enhanced_server_folder_server esfs JOIN enhanced_server_folders esf ON esf.id = esfs.folder_id WHERE esfs.user_id = {$userId} AND esfs.server_id = {$serverIdColumn} LIMIT 1)";
                            $positionSub = "(SELECT esfs.position FROM enhanced_server_folder_server esfs WHERE esfs.user_id = {$userId} AND esfs.server_id = {$serverIdColumn} LIMIT 1)";

                            $directionSql = $direction === 'desc' ? 'DESC' : 'ASC';

                            return $query
                                ->orderByRaw("COALESCE({$folderSortSub}, 2147483647) {$directionSql}")
                                ->orderByRaw("COALESCE({$positionSub}, 2147483647) {$directionSql}");
                        });

                        return $group;
                    })(),
                ])
=======
                ->groups([$this->makeFolderGroup()])
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
=======

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
>>>>>>> Stashed changes
}
