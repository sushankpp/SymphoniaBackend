<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArtistTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('artists')->insert([
            [
                'artist_name' => 'The Weeknd',
                'artist_image' => 'artist_image/the-weekend.webp',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'artist_name' => 'Adele',
                'artist_image' => 'artist_image/adele.webp',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'artist_name' => 'Post Malone',
                'artist_image' => 'artist_image/post-malone.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
