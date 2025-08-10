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
                'description' => 'A hit song by The Weeknd with synthwave influences.',
                'release_date' => '2019-11-29',
                'views' => 1500,
                'uploaded_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Starboy',
                'song_cover_path' => 'songs_cover/Starboy.png',
                'file_path' => 'audios/The Weeknd - Starboy ft. Daft Punk (Official Video)-yt.savetube.me.mp3',
                'artist_id' => 1,
                'album_id' => 2,
                'genre' => 'R&B',
                'description' => 'A collaboration between The Weeknd and Daft Punk.',
                'release_date' => '2016-09-22',
                'views' => 2200,
                'uploaded_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Hello',
                'song_cover_path' => 'songs_cover/hello.jpg',
                'file_path' => 'audios/Adele - Hello (Official Music Video)-yt.savetube.me.mp3',
                'artist_id' => 2,
                'album_id' => 3,
                'genre' => 'Soul',
                'description' => 'A soulful ballad by Adele.',
                'release_date' => '2015-10-23',
                'views' => 1800,
                'uploaded_by' => 2,
                'created_at' => now(),
                'updated_at' => now(),
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
                'views' => 2500,
                'uploaded_by' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Circles',
                'song_cover_path' => 'songs_cover/rolling_in_the_deep.png',
                'file_path' => 'audios/makhamali-from-oonko-sweater-the-woolen-sweater-128-ytshorts.savetube.me.mp3',
                'artist_id' => 3,
                'album_id' => 5,
                'genre' => 'Pop',
                'description' => 'A pop song by Post Malone.',
                'release_date' => '2019-08-30',
                'views' => 2700,
                'uploaded_by' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Sunflower',
                'song_cover_path' => 'songs_cover/hello.jpg',
                'file_path' => 'audios/simsime-pani-ma-rekha-shah-new-nepali-song-128-ytshorts.savetube.me_1754414993.mp3',
                'artist_id' => 3,
                'album_id' => 6,
                'genre' => 'Pop',
                'description' => 'A hit song by Post Malone featuring Swae Lee.',
                'release_date' => '2018-10-18',
                'views' => 3200,
                'uploaded_by' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
