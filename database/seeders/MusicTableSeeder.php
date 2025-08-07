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
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Shape of You',
                'song_cover_path' => 'songs_cover/Blinding_Lights.png',
                'file_path' => 'audios/radha-swoopna-suman-ft-abhigya-official-mv-128-ytshorts.savetube.me_1754446318.mp3',
                'artist_id' => 3,
                'album_id' => 5,
                'genre' => 'Pop',
                'description' => 'A tropical house song by Ed Sheeran.',
                'release_date' => '2017-01-06',
                'views' => 3000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Perfect',
                'song_cover_path' => 'songs_cover/Starboy.png',
                'file_path' => 'audios/raiya-chandiko-purna-bahadur-ko-sarangi-new-dohori-movie-song-ft-bijay-baral-anjana-baraili-128-ytshorts.savetube.me_1754416010.mp3',
                'artist_id' => 4,
                'album_id' => 6,
                'genre' => 'Pop',
                'description' => 'A romantic ballad by Taylor Swift.',
                'release_date' => '2017-03-03',
                'views' => 2800,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'God\'s Plan',
                'song_cover_path' => 'songs_cover/hello.jpg',
                'file_path' => 'audios/simsime-pani-ma-rekha-shah-new-nepali-song-128-ytshorts.savetube.me_1754414993.mp3',
                'artist_id' => 5,
                'album_id' => 7,
                'genre' => 'Hip Hop',
                'description' => 'A hip hop song by Drake.',
                'release_date' => '2018-01-19',
                'views' => 3500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Circles',
                'song_cover_path' => 'songs_cover/rolling_in_the_deep.png',
                'file_path' => 'audios/makhamali-from-oonko-sweater-the-woolen-sweater-128-ytshorts.savetube.me_1754407225.mp3.raw',
                'artist_id' => 6,
                'album_id' => 8,
                'genre' => 'Pop',
                'description' => 'A pop song by Post Malone.',
                'release_date' => '2019-08-30',
                'views' => 2700,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
