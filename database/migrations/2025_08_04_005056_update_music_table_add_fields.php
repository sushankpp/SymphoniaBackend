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
        Schema::table('music', function (Blueprint $table) {
            if (!Schema::hasColumn('music', 'genre')) {
                $table->string('genre')->nullable();
            }
            if (!Schema::hasColumn('music', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('music', 'release_date')) {
                $table->date('release_date')->nullable();
            }
            if (!Schema::hasColumn('music', 'lyrics')) {
                $table->text('lyrics')->nullable();
            }
            if (!Schema::hasColumn('music', 'cover_image_path')) {
                $table->string('cover_image_path')->nullable();
            }
            if (!Schema::hasColumn('music', 'views')) {
                $table->unsignedBigInteger('views')->default(0);
            }
            if (!Schema::hasColumn('music', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music', function (Blueprint $table) {
            $table->dropColumn(['genre', 'description', 'release_date', 'lyrics', 'cover_image_path', 'views', 'uploaded_by']);
        });
    }
};
