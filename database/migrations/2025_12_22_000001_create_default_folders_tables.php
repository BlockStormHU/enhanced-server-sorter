<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enhanced_server_default_folders')) {
            Schema::create('enhanced_server_default_folders', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('sort')->default(0);
                $table->boolean('is_locked')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('enhanced_server_default_folder_servers')) {
            Schema::create('enhanced_server_default_folder_servers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('default_folder_id');
                $table->unsignedInteger('server_id');
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->foreign('default_folder_id')->references('id')->on('enhanced_server_default_folders')->onDelete('cascade');
                $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_server_default_folder_servers');
        Schema::dropIfExists('enhanced_server_default_folders');
    }
};
