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
        Schema::create('music', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('song_cover_path');
            $table->string('file_path');
            $table->unsignedBigInteger('artist_id');
            $table->string('genre')->nullable();
            $table->text('description')->nullable();
            $table->string('lyrics')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
            $table->foreignId('album_id')->nullable()->constrained('albums')->onDelete('cascade');
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music');
    }
};
