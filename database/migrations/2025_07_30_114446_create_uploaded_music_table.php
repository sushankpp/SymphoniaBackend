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
        Schema::create('uploaded_music', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('music_id');
            $table->foreign('music_id')->references('id')->on('music')->onDelete('cascade');
            $table->string('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploaded_music');
    }
};
