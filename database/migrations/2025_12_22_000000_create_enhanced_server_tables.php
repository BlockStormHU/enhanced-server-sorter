<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('enhanced_server_folders')) {
            Schema::create('enhanced_server_folders', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('name');
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('enhanced_server_folder_server')) {
            Schema::create('enhanced_server_folder_server', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('folder_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('server_id');
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->foreign('folder_id')->references('id')->on('enhanced_server_folders')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_server_folder_server');
        Schema::dropIfExists('enhanced_server_folders');
    }
};

