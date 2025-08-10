<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('music_upload_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('artist_id');
            $table->unsignedBigInteger('user_id');
            $table->string('song_title');
            $table->string('file_path');
            $table->string('song_cover_path');
            $table->unsignedBigInteger('artist_id_for_song');
            $table->unsignedBigInteger('album_id')->nullable();
            $table->string('genre')->nullable();
            $table->text('description')->nullable();
            $table->date('release_date')->nullable();
            $table->text('lyrics')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('artist_id_for_song')->references('id')->on('artists')->onDelete('cascade');
            $table->foreign('album_id')->references('id')->on('albums')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_upload_requests');
    }
};
