<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('enhanced_server_folder_server')
            && !Schema::hasIndex('enhanced_server_folder_server', 'esfs_user_server_unique')) {
            DB::table('enhanced_server_folder_server')
                ->select('user_id', 'server_id', DB::raw('MIN(id) as keep_id'))
                ->groupBy('user_id', 'server_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->each(fn (object $row) => DB::table('enhanced_server_folder_server')
                    ->where('user_id', $row->user_id)
                    ->where('server_id', $row->server_id)
                    ->where('id', '!=', $row->keep_id)
                    ->delete());

            Schema::table('enhanced_server_folder_server', function (Blueprint $table) {
                $table->unique(['user_id', 'server_id'], 'esfs_user_server_unique');
            });
        }

        if (Schema::hasTable('enhanced_server_folders')
            && !Schema::hasIndex('enhanced_server_folders', 'esf_user_sort_index')) {
            Schema::table('enhanced_server_folders', function (Blueprint $table) {
                $table->index(['user_id', 'sort'], 'esf_user_sort_index');
            });
        }
    }

    public function down(): void
    {
    }
};
