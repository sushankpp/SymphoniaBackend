<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlbumTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('albums')->insert([
            [
                'title' => 'After Hours',
                'cover_image_path' => 'albums_cover/weekend1.jpg',
                'artist_id' => 1,
                'release_date' => '2020-03-20',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Adele21',
                'cover_image_path' => 'albums_cover/adele30.jpg',
                'artist_id' => 2,
                'release_date' => '2011-01-24',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Adele30',
                'cover_image_path' => 'albums_cover/adele.webp',
                'artist_id' => 2,
                'release_date' => '2021-11-19',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
