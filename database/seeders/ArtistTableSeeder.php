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
                'artist_name' => 'Ed Sheeran',
                'artist_image' => 'artist_image/ed-sheeran.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'artist_name' => 'Taylor Swift',
                'artist_image' => 'artist_image/taylor_swift.jpeg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'artist_name' => 'Drake',
                'artist_image' => 'artist_image/drake.webp',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'artist_name' => 'Post Malone',
                'artist_image' => 'artist_image/post-malone.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'artist_name' => 'Sushank Pandey',
                'artist_image' => 'artist_image/sushank-pandey.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
