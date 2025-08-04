<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MusicTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('music')->insert([
            [
                'title' => 'Blinding Lights',
                'song_cover_path' => 'songs_cover/Blinding_Lights.png',
                'file_path' => 'audios/Blinded By The Light-yt.savetube.me.mp3',
                'artist_id' => 1,
                'album_id' => 1,
                'genre' => 'Pop',
                'description' => 'A hit song by The Weeknd.',
                'release_date' => '2019-11-29',
                'cover_image_path' => 'images/Blinding_Lights_Cover.png',
                'views' => 0,
            ],
            [
                'title' => 'Star Boy',
                'song_cover_path' => 'songs_cover/Starboy.png',
                'file_path' => 'audios/The Weeknd - Starboy ft. Daft Punk (Official Video)-yt.savetube.me.mp3',
                'artist_id' => 1,
                'album_id' => 1,
                'genre' => 'R&B',
                'description' => 'A collaboration between The Weeknd and Daft Punk.',
                'release_date' => '2016-09-22',
                'cover_image_path' => 'images/Starboy_Cover.png',
                'views' => 0,
            ],
            [
                'title' => 'Hello',
                'song_cover_path' => 'songs_cover/hello.jpg',
                'file_path' => 'audios/Adele - Hello (Official Music Video)-yt.savetube.me.mp3',
                'artist_id' => 2,
                'album_id' => 2,
                'genre' => 'Soul',
                'description' => 'A soulful ballad by Adele.',
                'release_date' => '2015-10-23',
                'cover_image_path' => 'images/Hello_Cover.jpg',
                'views' => 0,
            ],
            [
                'title' => 'Rolling in the Deep',
                'song_cover_path' => 'songs_cover/rolling_in_the_deep.png',
                'file_path' => 'audios/Adele - Rolling in the Deep (Official Music Video)-yt.savetube.me.mp3',
                'artist_id' => 2,
                'album_id' => 3,
                'genre' => 'Blues',
                'description' => 'A powerful song by Adele.',
                'release_date' => '2010-11-29',
                'cover_image_path' => 'images/Rolling_in_the_Deep_Cover.png',
                'views' => 0,
            ],
        ]);
    }
}
