<?php

namespace Olivier\EnhancedServerSorter;

use App\Contracts\Plugins\HasPluginSettings;
use App\Enums\HeaderActionPosition;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Utilities\Get;
use Olivier\EnhancedServerSorter\Services\FolderService;
use Throwable;

class EnhancedServerSorterPlugin implements HasPluginSettings, Plugin
{
    public function getId(): string
    {
        return 'enhanced-server-sorter';
    }

    public function getId(): string
    {
        return 'enhanced-server-sorter';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() !== 'app') {
            return;
        }

        ListServers::registerCustomHeaderActions(HeaderActionPosition::After, $this->makeManageFoldersAction());
    }

<<<<<<< Updated upstream
    public function boot(Panel $panel): void
    {
        //
    }

    public function getServiceProviders(): array
    {
        return [
            EnhancedServerSorterServiceProvider::class,
        ];
=======
    public function boot(Panel $panel): void {}

    private function folders(): FolderService
    {
        return app(FolderService::class);
>>>>>>> Stashed changes
    }

    private function makeManageFoldersAction(): Action
    {
        return Action::make('manageFolders')
            ->label(fn () => trans('enhanced-server-sorter::messages.manage_folders'))
            ->icon('tabler-folders')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel(fn () => trans('enhanced-server-sorter::messages.save'))
            ->modalCancelActionLabel(fn () => trans('enhanced-server-sorter::messages.cancel'))
            ->visible(fn () => user() !== null)
<<<<<<< Updated upstream
            ->form(function () {
                if (!Schema::hasTable('enhanced_server_folders')) {
                    return [
                        \Filament\Forms\Components\Placeholder::make('migration_required')
                            ->label(fn () => trans('enhanced-server-sorter::messages.migration_required'))
                            ->content(fn () => trans('enhanced-server-sorter::messages.migration_required_message'))
=======
            ->fillForm(function () {
                $user = user();

                if (!$user || !$this->folders()->isReady()) {
                    return [];
                }

                $serverIds = $user->accessibleServers()->pluck('id')->all();

                return ['folders' => $this->folders()->foldersForUser($user->id, $serverIds)];
            })
            ->schema(function () {
                if (!$this->folders()->isReady()) {
                    return [
                        TextEntry::make('migration_required')
                            ->label(fn () => trans('enhanced-server-sorter::messages.migration_required'))
                            ->state(fn () => trans('enhanced-server-sorter::messages.migration_required_message')),
>>>>>>> Stashed changes
                    ];
                }

                $servers = (user()?->accessibleServers()->pluck('name', 'id') ?? collect())
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE);

                return [
                    Repeater::make('folders')
                        ->label(fn () => trans('enhanced-server-sorter::messages.folders'))
<<<<<<< Updated upstream
                        ->default(fn () => $this->loadFolders())
                        ->live()
=======
>>>>>>> Stashed changes
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')
                                ->label(fn () => trans('enhanced-server-sorter::messages.folder_name'))
                                ->required()
                                ->maxLength(255)
                                ->readOnly(fn (Get $get) => $get('id') && $this->folders()->isFolderLocked($get('name'))),
                            Select::make('server_ids')
                                ->label(fn () => trans('enhanced-server-sorter::messages.servers'))
                                ->multiple()
                                ->options($servers)
                                ->searchable()
                                ->preload()
                                ->live()
<<<<<<< Updated upstream
                                ->disabled(fn ($get) => $this->isFolderLocked($get('name')))
                                ->helperText(fn ($get) => $this->isFolderLocked($get('name')) 
                                    ? trans('enhanced-server-sorter::messages.folder_locked') 
=======
                                ->disabled(fn (Get $get) => $get('id') && $this->folders()->isFolderLocked($get('name')))
                                ->helperText(fn (Get $get) => $get('id') && $this->folders()->isFolderLocked($get('name'))
                                    ? trans('enhanced-server-sorter::messages.folder_locked')
>>>>>>> Stashed changes
                                    : null)
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        ])
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->collapsed()
                        ->reorderableWithButtons()
                        ->addActionLabel(fn () => trans('enhanced-server-sorter::messages.add_folder')),
                ];
            })
            ->action(function (array $data) {
                $user = user();

                if (!$user || !$this->folders()->isReady()) {
                    return;
                }

<<<<<<< Updated upstream
                $folders = collect($data['folders'] ?? []);
                
                $allServerIds = [];
                $duplicates = [];
                
                foreach ($folders as $folder) {
                    $serverIds = $folder['server_ids'] ?? [];
                    foreach ($serverIds as $serverId) {
                        if (in_array($serverId, $allServerIds)) {
                            $duplicates[] = $serverId;
                        }
                        $allServerIds[] = $serverId;
                    }
                }
                
                if (!empty($duplicates)) {
                    $serverNames = user()?->accessibleServers()
                        ->whereIn('servers.id', array_unique($duplicates))
=======
                $folders = $data['folders'] ?? [];

                $duplicates = collect($folders)
                    ->flatMap(fn (array $folder) => $folder['server_ids'] ?? [])
                    ->duplicates()
                    ->unique()
                    ->all();

                if ($duplicates !== []) {
                    $serverNames = $user->accessibleServers()
                        ->whereIn('servers.id', $duplicates)
>>>>>>> Stashed changes
                        ->pluck('name')
                        ->join(', ');
                    
                    Notification::make()
                        ->title(trans('enhanced-server-sorter::messages.duplicate_servers'))
                        ->body(trans('enhanced-server-sorter::messages.duplicate_servers_message', ['servers' => $serverNames]))
                        ->danger()
                        ->send();
                    
                    return;
                }

                $this->folders()->saveUserFolders($user->id, $folders, $user->accessibleServers()->pluck('id')->all());

                Notification::make()
                    ->title(trans('enhanced-server-sorter::messages.folders_updated'))
                    ->success()
                    ->send();
            });
    }

<<<<<<< Updated upstream
    private function loadFolders(): array
    {
        $user = user();

        if (!$user) {
            return [];
        }

        if (!Schema::hasTable('enhanced_server_folders')) {
            return [];
        }

        try {
            $this->syncDefaultFoldersForUser($user->id);
            $lockedFolderNames = $this->getLockedFolderNames();

            return ServerFolder::query()
                ->with('assignments')
                ->where('user_id', $user->id)
                ->orderBy('sort')
                ->get()
                ->map(fn (ServerFolder $folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'server_ids' => $folder->assignments->pluck('server_id')->all(),
                    'is_locked' => in_array($folder->name, $lockedFolderNames),
                ])
                ->all();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function persistFolders(Collection $folders, int $userId): void
    {
        if (!Schema::hasTable('enhanced_server_folders')) {
            return;
        }

        $accessibleServerIds = user()?->accessibleServers()?->pluck('id')->all() ?? [];
        $existingIds = [];
        $lockedFolderNames = $this->getLockedFolderNames();

        foreach ($folders as $index => $folderData) {
            $folder = isset($folderData['id'])
                ? ServerFolder::query()
                    ->where('user_id', $userId)
                    ->whereKey($folderData['id'])
                    ->first()
                : null;

            if (!$folder) {
                $folder = new ServerFolder(['user_id' => $userId]);
            }

            $folderName = $folderData['name'] ?? 'Folder';
            $isLocked = in_array($folderName, $lockedFolderNames);

            $folder->name = $folderName;
            $folder->sort = ($index * 10);
            $folder->save();

            $existingIds[] = $folder->id;

            if ($isLocked) {
                $defaultFolder = DefaultFolder::query()
                    ->where('name', $folderName)
                    ->where('is_locked', true)
                    ->with('serverAssignments')
                    ->first();

                if ($defaultFolder) {
                    $serverIds = $defaultFolder->serverAssignments
                        ->sortBy('position')
                        ->pluck('server_id')
                        ->intersect($accessibleServerIds)
                        ->values()
                        ->all();
                } else {
                    $serverIds = [];
                }
            } else {
                $serverIds = collect($folderData['server_ids'] ?? [])
                    ->unique()
                    ->intersect($accessibleServerIds)
                    ->values()
                    ->all();
            }

            $this->syncAssignments($folder, $serverIds, $userId);
        }

        ServerFolder::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $existingIds)
            ->whereNotIn('name', $lockedFolderNames)
            ->each(fn (ServerFolder $folder) => $folder->delete());
    }

    private function isFolderLocked(?string $folderName): bool
    {
        if (!$folderName) {
            return false;
        }

        $lockedFolderNames = $this->getLockedFolderNames();
        return in_array($folderName, $lockedFolderNames);
    }

    private function getLockedFolderNames(): array
    {
        try {
            if (!Schema::hasTable('enhanced_server_default_folders')) {
                return [];
            }

            return DefaultFolder::query()
                ->where('is_locked', true)
                ->pluck('name')
                ->all();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function syncAssignments(ServerFolder $folder, array $serverIds, int $userId): void
    {
        if (!Schema::hasTable('enhanced_server_folder_server')) {
            return;
        }

        ServerFolderAssignment::query()
            ->where('folder_id', $folder->id)
            ->where('user_id', $userId)
            ->delete();

        if (empty($serverIds)) {
            return;
        }

        $rows = [];

        foreach ($serverIds as $position => $serverId) {
            $rows[] = [
                'folder_id' => $folder->id,
                'server_id' => $serverId,
                'user_id' => $userId,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ServerFolderAssignment::query()->insert($rows);
    }

=======
>>>>>>> Stashed changes
    public function getSettingsForm(): array
    {
        return [
            Repeater::make('default_folders')
                ->label(fn () => trans('enhanced-server-sorter::messages.default_folders'))
                ->helperText(fn () => trans('enhanced-server-sorter::messages.default_folders_help'))
<<<<<<< Updated upstream
                ->default(fn () => $this->loadDefaultFolders())
=======
>>>>>>> Stashed changes
                ->schema([
                    Hidden::make('id'),
                    TextInput::make('name')
                        ->label(fn () => trans('enhanced-server-sorter::messages.folder_name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('server_ids')
                        ->label(fn () => trans('enhanced-server-sorter::messages.servers'))
                        ->multiple()
                        ->options(fn () => Server::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->helperText(fn () => trans('enhanced-server-sorter::messages.select_servers_help'))
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
<<<<<<< Updated upstream
                    \Filament\Forms\Components\Toggle::make('is_locked')
=======
                    Toggle::make('is_locked')
>>>>>>> Stashed changes
                        ->label(fn () => trans('enhanced-server-sorter::messages.lock_folder'))
                        ->helperText(fn () => trans('enhanced-server-sorter::messages.lock_folder_help'))
                        ->default(false)
                        ->inline(false),
                ])
                ->itemLabel(fn (array $state) => $state['name'] ?? null)
                ->collapsible()
                ->collapsed()
                ->reorderableWithButtons()
                ->addActionLabel(fn () => trans('enhanced-server-sorter::messages.add_default_folder'))
                ->columns(2),
        ];
    }

    public function getSettingsFormData(): array
    {
        return [
<<<<<<< Updated upstream
            'default_folders' => $this->loadDefaultFolders(),
        ];
    }

    protected function loadDefaultFolders(): array
    {
        try {
            if (!Schema::hasTable('enhanced_server_default_folders')) {
                return [];
            }

            return DefaultFolder::query()
                ->with('serverAssignments')
                ->orderBy('sort')
                ->get()
                ->map(fn (DefaultFolder $folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'server_ids' => $folder->serverAssignments->pluck('server_id')->all(),
                    'is_locked' => $folder->is_locked ?? false,
                ])
                ->all();
        } catch (\Exception $e) {
            return [];
        }
    }

=======
            'default_folders' => $this->folders()->defaultFoldersFormData(),
        ];
    }

>>>>>>> Stashed changes
    public function saveSettings(array $data): void
    {
        try {
            $this->folders()->saveDefaultFolders($data['default_folders'] ?? []);

            Notification::make()
                ->title(trans('enhanced-server-sorter::messages.default_folders_saved'))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(trans('enhanced-server-sorter::messages.error_saving'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
<<<<<<< Updated upstream

    public function syncDefaultFoldersForUser(int $userId): void
    {
        try {
            if (!Schema::hasTable('enhanced_server_default_folders') || !Schema::hasTable('enhanced_server_folders')) {
                return;
            }

            $defaultFolders = DefaultFolder::query()
                ->with('serverAssignments')
                ->orderBy('sort')
                ->get();

            foreach ($defaultFolders as $index => $defaultFolder) {
                $folder = ServerFolder::query()
                    ->where('user_id', $userId)
                    ->where('name', $defaultFolder->name)
                    ->first();

                if (!$folder) {
                    $folder = new ServerFolder([
                        'user_id' => $userId,
                        'name' => $defaultFolder->name,
                        'sort' => $defaultFolder->sort,
                    ]);
                    $folder->save();
                } else {
                    $folder->sort = $defaultFolder->sort;
                    $folder->save();
                }

                $serverIds = $defaultFolder->serverAssignments
                    ->sortBy('position')
                    ->pluck('server_id')
                    ->all();

                if (!empty($serverIds)) {
                    ServerFolderAssignment::query()
                        ->where('folder_id', $folder->id)
                        ->where('user_id', $userId)
                        ->delete();

                    $rows = [];
                    foreach ($serverIds as $position => $serverId) {
                        $rows[] = [
                            'folder_id' => $folder->id,
                            'server_id' => $serverId,
                            'user_id' => $userId,
                            'position' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    ServerFolderAssignment::query()->insert($rows);
                }
            }
        } catch (\Exception $e) {
        }
    }

    public function syncDefaultFoldersForAllUsers(): void
    {
        try {
            if (!Schema::hasTable('enhanced_server_default_folders')) {
                return;
            }

            $userModelClass = config('panel.auth.models.user', \App\Models\User::class);
            
            if (!class_exists($userModelClass)) {
                return;
            }

            $users = $userModelClass::query()->get();

            foreach ($users as $user) {
                $this->syncDefaultFoldersForUser($user->id);
            }
        } catch (\Exception $e) {
        }
    }

=======
>>>>>>> Stashed changes
}
