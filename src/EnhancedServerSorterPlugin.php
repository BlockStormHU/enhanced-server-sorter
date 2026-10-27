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

    public function register(Panel $panel): void
    {
        if ($panel->getId() !== 'app') {
            return;
        }

        ListServers::registerCustomHeaderActions(HeaderActionPosition::After, $this->makeManageFoldersAction());
    }

    public function boot(Panel $panel): void {}

    private function folders(): FolderService
    {
        return app(FolderService::class);
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
                    ];
                }

                $servers = (user()?->accessibleServers()->pluck('name', 'id') ?? collect())
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE);

                return [
                    Repeater::make('folders')
                        ->label(fn () => trans('enhanced-server-sorter::messages.folders'))
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
                                ->disabled(fn (Get $get) => $get('id') && $this->folders()->isFolderLocked($get('name')))
                                ->helperText(fn (Get $get) => $get('id') && $this->folders()->isFolderLocked($get('name'))
                                    ? trans('enhanced-server-sorter::messages.folder_locked')
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

                $folders = $data['folders'] ?? [];

                $duplicates = collect($folders)
                    ->flatMap(fn (array $folder) => $folder['server_ids'] ?? [])
                    ->duplicates()
                    ->unique()
                    ->all();

                if ($duplicates !== []) {
                    $serverNames = $user->accessibleServers()
                        ->whereIn('servers.id', $duplicates)
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

    public function getSettingsForm(): array
    {
        return [
            Repeater::make('default_folders')
                ->label(fn () => trans('enhanced-server-sorter::messages.default_folders'))
                ->helperText(fn () => trans('enhanced-server-sorter::messages.default_folders_help'))
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
                    Toggle::make('is_locked')
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
            'default_folders' => $this->folders()->defaultFoldersFormData(),
        ];
    }

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
}
