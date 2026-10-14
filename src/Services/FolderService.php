<?php

namespace Olivier\EnhancedServerSorter\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Olivier\EnhancedServerSorter\Models\DefaultFolder;
use Olivier\EnhancedServerSorter\Models\DefaultFolderServerAssignment;
use Olivier\EnhancedServerSorter\Models\ServerFolder;
use Olivier\EnhancedServerSorter\Models\ServerFolderAssignment;
use Throwable;

class FolderService
{
    private const TABLES = [
        'enhanced_server_folder_server',
        'enhanced_server_default_folder_servers',
    ];

    private ?bool $ready = null;

    private ?EloquentCollection $defaultFolders = null;

    public function isReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        try {
            return $this->ready = collect(self::TABLES)->every(fn (string $table) => Schema::hasTable($table));
        } catch (Throwable) {
            return $this->ready = false;
        }
    }

    public function defaultFolders(): EloquentCollection
    {
        return $this->defaultFolders ??= DefaultFolder::query()
            ->with(['serverAssignments' => fn ($query) => $query->orderBy('position')])
            ->orderBy('sort')
            ->get();
    }

    public function lockedFolderNames(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        return $this->defaultFolders()->where('is_locked', true)->pluck('name')->all();
    }

    public function isFolderLocked(?string $name): bool
    {
        return filled($name) && in_array($name, $this->lockedFolderNames(), true);
    }

    public function foldersForUser(int $userId, array $accessibleServerIds): array
    {
        if (!$this->isReady()) {
            return [];
        }

        $this->syncDefaultFolders($userId);

        return ServerFolder::query()
            ->with(['assignments' => fn ($query) => $query->orderBy('position')])
            ->where('user_id', $userId)
            ->orderBy('sort')
            ->get()
            ->map(fn (ServerFolder $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'server_ids' => $folder->assignments
                    ->pluck('server_id')
                    ->intersect($accessibleServerIds)
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    public function saveUserFolders(int $userId, array $folders, array $accessibleServerIds): void
    {
        if (!$this->isReady()) {
            return;
        }

        $accessible = array_flip($accessibleServerIds);
        $lockedFolders = $this->defaultFolders()->where('is_locked', true)->keyBy('name');

        $lockedServerIds = $lockedFolders
            ->flatMap(fn (DefaultFolder $folder) => $folder->serverAssignments->pluck('server_id'))
            ->flip()
            ->all();

        DB::transaction(function () use ($userId, $folders, $accessible, $lockedFolders, $lockedServerIds) {
            $existing = ServerFolder::query()->where('user_id', $userId)->get()->keyBy('id');

            $keptIds = [];
            $assignedServerIds = [];
            $rows = [];

            foreach (array_values($folders) as $index => $data) {
                $folder = $existing->get((int) ($data['id'] ?? 0)) ?? new ServerFolder(['user_id' => $userId]);

                $lockedFolder = $folder->exists ? $lockedFolders->get($folder->name) : null;

                $name = $lockedFolder ? $folder->name : trim((string) ($data['name'] ?? ''));

                $folder->name = $name !== '' ? $name : 'Folder';
                $folder->sort = $index * 10;
                $folder->save();

                $keptIds[] = $folder->id;

                $serverIds = $lockedFolder
                    ? $lockedFolder->serverAssignments->pluck('server_id')->all()
                    : array_filter(
                        array_map('intval', $data['server_ids'] ?? []),
                        fn (int $serverId) => !isset($lockedServerIds[$serverId]),
                    );

                $position = 0;
                foreach ($serverIds as $serverId) {
                    if (!isset($accessible[$serverId]) || isset($assignedServerIds[$serverId])) {
                        continue;
                    }

                    $assignedServerIds[$serverId] = true;
                    $rows[] = $this->assignmentRow($folder->id, $userId, $serverId, $position++);
                }
            }

            $removedIds = $existing->keys()
                ->diff($keptIds)
                ->filter(fn (int $id) => !$lockedFolders->has($existing[$id]->name))
                ->values()
                ->all();

            ServerFolderAssignment::query()
                ->where('user_id', $userId)
                ->where(fn ($query) => $query
                    ->whereIn('folder_id', [...$keptIds, ...$removedIds])
                    ->orWhereIn('server_id', array_keys($assignedServerIds)))
                ->delete();

            ServerFolder::query()->whereKey($removedIds)->delete();

            $this->insertAssignments($rows);
        });
    }

    public function syncDefaultFolders(?int $userId = null): void
    {
        if (!$this->isReady()) {
            return;
        }

        $defaults = $this->defaultFolders();

        if ($defaults->isEmpty()) {
            return;
        }

        $userIds = $userId !== null
            ? collect([$userId])
            : User::query()->orderBy('id')->pluck('id');

        DB::transaction(function () use ($defaults, $userIds) {
            foreach ($userIds->chunk(200) as $chunk) {
                $this->syncDefaultFoldersForChunk($defaults, $chunk->values()->all());
            }
        });
    }

    public function saveDefaultFolders(array $folders): void
    {
        if (!$this->isReady()) {
            return;
        }

        DB::transaction(function () use ($folders) {
            DefaultFolderServerAssignment::query()->delete();
            DefaultFolder::query()->delete();

            $assignedServerIds = [];
            $rows = [];

            foreach (array_values($folders) as $index => $data) {
                $name = trim((string) ($data['name'] ?? ''));

                $folder = DefaultFolder::query()->create([
                    'name' => $name !== '' ? $name : 'Folder',
                    'sort' => $index * 10,
                    'is_locked' => (bool) ($data['is_locked'] ?? false),
                ]);

                $position = 0;
                foreach (array_map('intval', $data['server_ids'] ?? []) as $serverId) {
                    if (isset($assignedServerIds[$serverId])) {
                        continue;
                    }

                    $assignedServerIds[$serverId] = true;
                    $rows[] = [
                        'default_folder_id' => $folder->id,
                        'server_id' => $serverId,
                        'position' => $position++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DefaultFolderServerAssignment::query()->insert($chunk);
            }
        });

        $this->defaultFolders = null;

        $this->syncDefaultFolders();
    }

    public function defaultFoldersFormData(): array
    {
        if (!$this->isReady()) {
            return [];
        }

        return $this->defaultFolders()
            ->map(fn (DefaultFolder $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'server_ids' => $folder->serverAssignments->pluck('server_id')->all(),
                'is_locked' => $folder->is_locked,
            ])
            ->all();
    }

    public function sortTenants(array $tenants, ?int $userId): array
    {
        if (count($tenants) < 2 || !$userId || !$this->isReady()) {
            return $tenants;
        }

        try {
            $rank = DB::table('enhanced_server_folder_server as esfs')
                ->join('enhanced_server_folders as esf', 'esf.id', '=', 'esfs.folder_id')
                ->where('esfs.user_id', $userId)
                ->get(['esfs.server_id', 'esf.sort', 'esfs.position'])
                ->mapWithKeys(fn (object $row) => [(int) $row->server_id => [(int) $row->sort, (int) $row->position]])
                ->all();
        } catch (Throwable) {
            return $tenants;
        }

        usort($tenants, function (Model $a, Model $b) use ($rank): int {
            $rankA = $rank[$a->getKey()] ?? null;
            $rankB = $rank[$b->getKey()] ?? null;

            return match (true) {
                $rankA === null && $rankB === null => strcasecmp((string) $a->name, (string) $b->name),
                $rankA === null => 1,
                $rankB === null => -1,
                default => $rankA <=> $rankB,
            };
        });

        return $tenants;
    }

    private function syncDefaultFoldersForChunk(EloquentCollection $defaults, array $userIds): void
    {
        $names = $defaults->pluck('name')->all();

        $foldersByUser = ServerFolder::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('name', $names)
            ->get()
            ->groupBy('user_id');

        $maxSortByUser = ServerFolder::query()
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(sort) as max_sort')
            ->pluck('max_sort', 'user_id');

        $assignmentsByUser = ServerFolderAssignment::query()
            ->whereIn('user_id', $userIds)
            ->orderBy('position')
            ->get(['id', 'user_id', 'folder_id', 'server_id'])
            ->groupBy('user_id');

        $rows = [];
        $staleAssignmentIds = [];

        foreach ($userIds as $userId) {
            $folders = ($foldersByUser->get($userId) ?? collect())->keyBy('name');

            $assignments = $assignmentsByUser->get($userId) ?? collect();
            $assignedServerIds = $assignments->pluck('server_id')->flip()->all();
            $nextSort = $maxSortByUser->has($userId) ? (int) $maxSortByUser->get($userId) + 10 : null;

            foreach ($defaults as $default) {
                $serverIds = $default->serverAssignments->pluck('server_id')->all();
                $folder = $folders->get($default->name);

                if (!$folder) {
                    $folder = ServerFolder::query()->create([
                        'user_id' => $userId,
                        'name' => $default->name,
                        'sort' => $nextSort ?? $default->sort,
                    ]);

                    if ($nextSort !== null) {
                        $nextSort += 10;
                    }

                    if (!$default->is_locked) {
                        $position = 0;
                        foreach ($serverIds as $serverId) {
                            if (isset($assignedServerIds[$serverId])) {
                                continue;
                            }

                            $assignedServerIds[$serverId] = true;
                            $rows[] = $this->assignmentRow($folder->id, $userId, $serverId, $position++);
                        }

                        continue;
                    }
                }

                if (!$default->is_locked) {
                    continue;
                }

                $current = $assignments->where('folder_id', $folder->id)->pluck('server_id')->values()->all();
                $elsewhere = $assignments
                    ->where('folder_id', '!=', $folder->id)
                    ->whereIn('server_id', $serverIds);

                if ($current === $serverIds && $elsewhere->isEmpty()) {
                    continue;
                }

                $staleAssignmentIds = [
                    ...$staleAssignmentIds,
                    ...$assignments->where('folder_id', $folder->id)->pluck('id')->all(),
                    ...$elsewhere->pluck('id')->all(),
                ];

                foreach ($serverIds as $position => $serverId) {
                    $assignedServerIds[$serverId] = true;
                    $rows[] = $this->assignmentRow($folder->id, $userId, $serverId, $position);
                }
            }
        }

        foreach (array_chunk($staleAssignmentIds, 500) as $chunk) {
            ServerFolderAssignment::query()->whereKey($chunk)->delete();
        }

        $this->insertAssignments($rows);
    }

    private function assignmentRow(int $folderId, int $userId, int $serverId, int $position): array
    {
        return [
            'folder_id' => $folderId,
            'user_id' => $userId,
            'server_id' => $serverId,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function insertAssignments(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            ServerFolderAssignment::query()->insert($chunk);
        }
    }
}
