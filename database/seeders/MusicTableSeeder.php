<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Music;
use App\Models\Artist;
use App\Models\User;

class MusicTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing artists
        $theWeeknd = Artist::where('artist_name', 'The Weeknd')->first();
        $adele = Artist::where('artist_name', 'Adele')->first();
        $postMalone = Artist::where('artist_name', 'Post Malone')->first();

        $user2 = User::find(2);
        $user3 = User::find(3);
        $user4 = User::find(4);

        $musicData = [
            [
                'title' => 'Chaine jo Jinda gani ma',
                'artist_id' => $theWeeknd ? $theWeeknd->id : null,
                'genre' => 'Nepali Pop',
                'description' => 'A beautiful Nepali movie song',
                'lyrics' => 'Chaine jo jinda gani ma...',
                'file_path' => 'audios/simsime-pani-ma-rekha-shah-new-nepali-song-128-ytshorts.savetube.me_1754414993.mp3',
                'song_cover_path' => 'songs_cover/Blinding_Lights.png',
                'uploaded_by' => $user2 ? $user2->id : 2,
                'views' => 1,
                'release_date' => '2024-01-15',
            ],
            [
                'title' => 'John Denver - Take Me Home Country Roads',
                'artist_id' => $adele ? $adele->id : null,
                'genre' => 'Country',
                'description' => 'Classic country road song',
                'lyrics' => 'Almost heaven, West Virginia\nBlue Ridge Mountains, Shenandoah River...',
                'file_path' => 'audios/Blinded By The Light-yt.savetube.me.mp3',
                'song_cover_path' => 'albums_cover/adele30.jpg',
                'uploaded_by' => $user3 ? $user3->id : 3,
                'views' => 1,
                'release_date' => '1971-04-12',
            ],
            [
                'title' => 'Launa ni Bhaiya',
                'artist_id' => $postMalone ? $postMalone->id : null,
                'genre' => 'Nepali Folk',
                'description' => 'Traditional Nepali folk song',
                'lyrics' => 'Launa ni bhaiya laun ni bhaiyaa...',
                'file_path' => 'audios/makhamali-from-oonko-sweater-the-woolen-sweater-128-ytshorts.savetube.me.mp3',
                'song_cover_path' => 'albums_cover/weekend1.jpg',
                'uploaded_by' => $user4 ? $user4->id : 4,
                'views' => 1,
                'release_date' => '2024-08-15',
            ],
            [
                'title' => 'The Weeknd - Starboy',
                'artist_id' => $theWeeknd ? $theWeeknd->id : null,
                'genre' => 'R&B',
                'description' => 'A collaboration with Daft Punk',
                'lyrics' => 'I\'m tryna put you in the worst mood, ah\nP1 cleaner than your church shoes, ah...',
                'file_path' => 'audios/The Weeknd - Starboy ft. Daft Punk (Official Video)-yt.savetube.me.mp3',
                'song_cover_path' => 'songs_cover/Starboy.png',
                'uploaded_by' => $user2 ? $user2->id : 2,
                'views' => 1,
                'release_date' => '2016-09-21',
            ],
            [
                'title' => 'Adele - Hello',
                'artist_id' => $adele ? $adele->id : null,
                'genre' => 'Soul',
                'description' => 'A powerful ballad about reconnecting',
                'lyrics' => 'Hello, it\'s me\nI was wondering if after all these years...',
                'file_path' => 'audios/Adele - Hello (Official Music Video)-yt.savetube.me.mp3',
                'song_cover_path' => 'songs_cover/hello.jpg',
                'uploaded_by' => $user3 ? $user3->id : 3,
                'views' => 1,
                'release_date' => '2015-10-23',
            ],
            [
                'title' => 'Adele - Rolling in the Deep',
                'artist_id' => $adele ? $adele->id : null,
                'genre' => 'Soul',
                'description' => 'A soulful breakup anthem',
                'lyrics' => 'There\'s a fire starting in my heart\nReaching a fever pitch and it\'s bringing me out the dark...',
                'file_path' => 'audios/Adele - Rolling in the Deep (Official Music Video)-yt.savetube.me.mp3',
                'song_cover_path' => 'songs_cover/rolling_in_the_deep.png',
                'uploaded_by' => $user3 ? $user3->id : 3,
                'views' => 1,
                'release_date' => '2010-11-29',
            ],
        ];

        foreach ($musicData as $music) {
            Music::create($music);
        }

        $this->command->info('Music data seeded successfully!');
    }
}
