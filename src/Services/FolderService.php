<?php

namespace Olivier\EnhancedServerSorter\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    private const SYNC_VERSION = 2;

    private const SYNC_CACHE_PREFIX = 'enhanced-server-sorter:synced:';

    private const SYNC_LOCK_PREFIX = 'enhanced-server-sorter:sync-lock:';

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
            ->with(['assignments' => fn ($query) => $query->orderBy('position')->orderBy('id')])
            ->where('user_id', $userId)
            ->orderBy('sort')
            ->orderBy('id')
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

            $this->applyFolderOrder(
                ServerFolder::query()->where('user_id', $userId)->get(['id', 'user_id', 'name', 'sort']),
                $this->defaultRanks($this->defaultFolders()),
            );
        });
    }

    public function duplicateFolderNames(array $folders): array
    {
        return collect($folders)
            ->map(fn (array $folder) => trim((string) ($folder['name'] ?? '')))
            ->filter(fn (string $name) => $name !== '')
            ->groupBy(fn (string $name) => mb_strtolower($name))
            ->filter(fn (Collection $names) => $names->count() > 1)
            ->map(fn (Collection $names) => $names->first())
            ->values()
            ->all();
    }

    public function ensureUserSynced(?int $userId): void
    {
        if (!$userId || !$this->isReady()) {
            return;
        }

        try {
            $signature = $this->defaultsSignature();
            $key = self::SYNC_CACHE_PREFIX . $userId;

            if (Cache::get($key) === $signature) {
                return;
            }

            Cache::lock(self::SYNC_LOCK_PREFIX . $userId, 30)->get(function () use ($userId, $key, $signature) {
                if (Cache::get($key) === $signature) {
                    return;
                }

                $this->syncDefaultFolders($userId);

                Cache::forever($key, $signature);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
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

        $this->ensureUserSynced($userId);

        try {
            $rank = DB::table('enhanced_server_folder_server as esfs')
                ->join('enhanced_server_folders as esf', 'esf.id', '=', 'esfs.folder_id')
                ->where('esfs.user_id', $userId)
                ->get(['esfs.server_id', 'esf.sort', 'esf.id as folder_id', 'esfs.position'])
                ->mapWithKeys(fn (object $row) => [(int) $row->server_id => [
                    (int) $row->sort,
                    (int) $row->folder_id,
                    (int) $row->position,
                ]])
                ->all();
        } catch (Throwable) {
            return $tenants;
        }

        usort($tenants, function (Model $a, Model $b) use ($rank): int {
            $rankA = $rank[$a->getKey()] ?? null;
            $rankB = $rank[$b->getKey()] ?? null;

            return match (true) {
                $rankA === null && $rankB === null => strcasecmp((string) $a->name, (string) $b->name)
                    ?: $a->getKey() <=> $b->getKey(),
                $rankA === null => 1,
                $rankB === null => -1,
                default => ($rankA <=> $rankB) ?: $a->getKey() <=> $b->getKey(),
            };
        });

        return $tenants;
    }

    private function syncDefaultFoldersForChunk(EloquentCollection $defaults, array $userIds): void
    {
        $ranks = $this->defaultRanks($defaults);

        $foldersByUser = ServerFolder::query()
            ->whereIn('user_id', $userIds)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'user_id', 'name', 'sort'])
            ->groupBy('user_id');

        $assignmentsByUser = ServerFolderAssignment::query()
            ->whereIn('user_id', $userIds)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'user_id', 'folder_id', 'server_id'])
            ->groupBy('user_id');

        $rows = [];
        $staleAssignmentIds = [];
        $foldersToOrder = [];

        foreach ($userIds as $userId) {
            $userFolders = ($foldersByUser->get($userId) ?? new EloquentCollection())->values();
            $folders = $userFolders->unique('name')->keyBy('name');
            $createdIds = [];

            $assignments = $assignmentsByUser->get($userId) ?? collect();
            $assignedServerIds = $assignments->pluck('server_id')->flip()->all();

            foreach ($defaults as $default) {
                $serverIds = $default->serverAssignments->pluck('server_id')->all();
                $folder = $folders->get($default->name);

                if (!$folder) {
                    $folder = ServerFolder::query()->create([
                        'user_id' => $userId,
                        'name' => $default->name,
                        'sort' => $default->sort,
                    ]);

                    $folders->put($default->name, $folder);
                    $userFolders->push($folder);
                    $createdIds[$folder->id] = true;

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

            $foldersToOrder[] = [$userFolders, $createdIds];
        }

        foreach (array_chunk($staleAssignmentIds, 500) as $chunk) {
            ServerFolderAssignment::query()->whereKey($chunk)->delete();
        }

        $this->insertAssignments($rows);

        foreach ($foldersToOrder as [$userFolders, $createdIds]) {
            $this->applyFolderOrder($userFolders, $ranks, $createdIds);
        }
    }

    private function defaultRanks(EloquentCollection $defaults): array
    {
        $ranks = [];

        foreach ($defaults->values() as $index => $default) {
            $ranks[$default->name] ??= $index;
        }

        return $ranks;
    }

    private function defaultsSignature(): string
    {
        return hash('sha256', json_encode([
            self::SYNC_VERSION,
            $this->defaultFolders()
                ->map(fn (DefaultFolder $folder) => [
                    $folder->name,
                    (int) $folder->sort,
                    (bool) $folder->is_locked,
                    $folder->serverAssignments->pluck('server_id')->map(fn ($id) => (int) $id)->all(),
                ])
                ->values()
                ->all(),
        ]));
    }

    private function applyFolderOrder(Collection $folders, array $ranks, array $createdIds = []): void
    {
        $byId = $folders->keyBy('id');

        $entries = $byId
            ->map(fn (ServerFolder $folder) => [
                'id' => (int) $folder->id,
                'name' => (string) $folder->name,
                'sort' => (int) $folder->sort,
                'created' => isset($createdIds[$folder->id]),
            ])
            ->values()
            ->all();

        foreach ($this->orderFolders($entries, $ranks) as $index => $folderId) {
            $folder = $byId->get($folderId);
            $sort = $index * 10;

            if ((int) $folder->sort === $sort) {
                continue;
            }

            ServerFolder::query()->whereKey($folderId)->update(['sort' => $sort]);
            $folder->sort = $sort;
        }
    }

    private function orderFolders(array $entries, array $ranks): array
    {
        $existing = array_values(array_filter($entries, fn (array $entry) => !$entry['created']));
        $created = array_values(array_filter($entries, fn (array $entry) => $entry['created']));

        usort($existing, fn (array $a, array $b) => [$a['sort'], $a['id']] <=> [$b['sort'], $b['id']]);

        $rankById = [];
        foreach ($existing as $entry) {
            if (isset($ranks[$entry['name']]) && !in_array($ranks[$entry['name']], $rankById, true)) {
                $rankById[$entry['id']] = $ranks[$entry['name']];
            }
        }

        $slots = [];
        $defaults = [];
        foreach ($existing as $index => $entry) {
            if (isset($rankById[$entry['id']])) {
                $slots[] = $index;
                $defaults[] = $entry;
            }
        }

        usort($defaults, fn (array $a, array $b) => $rankById[$a['id']] <=> $rankById[$b['id']]);

        foreach ($slots as $slot => $index) {
            $existing[$index] = $defaults[$slot];
        }

        usort($created, fn (array $a, array $b) => [$ranks[$a['name']] ?? PHP_INT_MAX, $a['id']]
            <=> [$ranks[$b['name']] ?? PHP_INT_MAX, $b['id']]);

        $ordered = $existing;

        foreach ($created as $entry) {
            $rank = $ranks[$entry['name']] ?? PHP_INT_MAX;
            $after = null;
            $before = null;

            foreach ($ordered as $index => $placed) {
                $placedRank = $rankById[$placed['id']] ?? null;

                if ($placedRank === null) {
                    continue;
                }

                if ($placedRank < $rank) {
                    $after = $index;
                } elseif ($before === null) {
                    $before = $index;
                }
            }

            $position = $after !== null ? $after + 1 : ($before ?? count($ordered));
            array_splice($ordered, $position, 0, [$entry]);

            if ($rank !== PHP_INT_MAX) {
                $rankById[$entry['id']] = $rank;
            }
        }

        return array_column($ordered, 'id');
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
