<?php

namespace Olivier\EnhancedServerSorter\Filament;

use Filament\FilamentManager;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FolderOrderedFilamentManager extends FilamentManager
{
    public function getUserTenants(HasTenants|Model|Authenticatable $user): array
    {
        $tenants = parent::getUserTenants($user);

        try {
            return $this->sortByFolder($tenants, $user);
        } catch (Throwable) {
            return $tenants;
        }
    }

    protected function sortByFolder(array $tenants, mixed $user): array
    {
        if ($tenants === []) {
            return $tenants;
        }

        if (!Schema::hasTable('enhanced_server_folder_server') || !Schema::hasTable('enhanced_server_folders')) {
            return $tenants;
        }

        $userId = $user->id ?? null;

        if (!$userId) {
            return $tenants;
        }

        $order = DB::table('enhanced_server_folder_server as esfs')
            ->join('enhanced_server_folders as esf', 'esf.id', '=', 'esfs.folder_id')
            ->where('esfs.user_id', $userId)
            ->orderBy('esf.sort')
            ->orderBy('esfs.position')
            ->get(['esfs.server_id', 'esf.sort as folder_sort', 'esfs.position']);

        if ($order->isEmpty()) {
            return $tenants;
        }

        $rank = [];

        foreach ($order as $row) {
            $rank[$row->server_id] = [(int)$row->folder_sort, (int)$row->position];
        }

        usort($tenants, function (Model $a, Model $b) use ($rank): int {
            $rankA = $rank[$a->getKey()] ?? null;
            $rankB = $rank[$b->getKey()] ?? null;

            if ($rankA === null && $rankB === null) {
                return strcasecmp((string)$a->name, (string)$b->name);
            }

            if ($rankA === null) {
                return 1;
            }

            if ($rankB === null) {
                return -1;
            }

            return [$rankA[0], $rankA[1]] <=> [$rankB[0], $rankB[1]];
        });

        return $tenants;
    }
}