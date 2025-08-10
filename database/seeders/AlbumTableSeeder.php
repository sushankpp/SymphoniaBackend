<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlbumTableSeeder extends Seeder
{
    public function run(): void
    {
        // First, create artist users if they don't exist
        $artistUsers = [
            [
                'name' => 'The Weeknd',
                'email' => 'theweeknd@example.com',
                'password' => bcrypt('password'),
                'role' => 'artist',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Adele',
                'email' => 'adele@example.com',
                'password' => bcrypt('password'),
                'role' => 'artist',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Post Malone',
                'email' => 'postmalone@example.com',
                'password' => bcrypt('password'),
                'role' => 'artist',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        // Insert artist users and get their IDs
        $artistUserIds = [];
        foreach ($artistUsers as $artistUser) {
            $userId = DB::table('users')->insertGetId($artistUser);
            $artistUserIds[] = $userId;
        }

        // Now create albums with artist user IDs
        DB::table('albums')->insert([
            [
                'title' => 'After Hours',
                'cover_image_path' => 'albums_cover/weekend1.jpg',
                'artist_id' => 1,
                'user_id' => $artistUserIds[0], // The Weeknd
                'release_date' => '2020-03-20',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Starboy',
                'cover_image_path' => 'albums_cover/weekend1.jpg',
                'artist_id' => 1,
                'user_id' => $artistUserIds[0], // The Weeknd
                'release_date' => '2016-11-25',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => '21',
                'cover_image_path' => 'albums_cover/adele.webp',
                'artist_id' => 2,
                'user_id' => $artistUserIds[1], // Adele
                'release_date' => '2011-01-24',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => '30',
                'cover_image_path' => 'albums_cover/adele30.jpg',
                'artist_id' => 2,
                'user_id' => $artistUserIds[1], // Adele
                'release_date' => '2021-11-19',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Hollywood\'s Bleeding',
                'cover_image_path' => 'albums_cover/adele30.jpg',
                'artist_id' => 3,
                'user_id' => $artistUserIds[2], // Post Malone
                'release_date' => '2019-09-06',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Beerbongs & Bentleys',
                'cover_image_path' => 'albums_cover/weekend1.jpg',
                'artist_id' => 3,
                'user_id' => $artistUserIds[2], // Post Malone
                'release_date' => '2018-04-27',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
