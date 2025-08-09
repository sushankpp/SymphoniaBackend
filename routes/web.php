<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth routes  
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Emergency route for role fixes (keep for production use if needed)
Route::get('/fix-user-role/{userId}', function ($userId) {
    try {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found']);
        }

        $roleBefore = $user->role;
        $updated = $user->update(['role' => 'artist']);
        $user->refresh();
        
        return response()->json([
            'success' => true,
            'message' => "Role update attempted for user $userId",
            'user_id' => $userId,
            'role_before' => $roleBefore,
            'role_after' => $user->role,
            'update_result' => $updated,
            'user_updated_at' => $user->updated_at,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Route for adding your specific songs (can be removed after use)
Route::get('/add-real-songs-user-9', function () {
    try {
        // Find or create user 9
        $user = \App\Models\User::find(9);
        if (!$user) {
            $user = \App\Models\User::where('email', 'sushankpandey23@gmail.com')->first();
            if (!$user) {
                $user = \App\Models\User::create([
                    'name' => 'Sushank Pandey',
                    'email' => 'sushankpandey23@gmail.com',
                    'password' => bcrypt('password'),
                    'role' => 'artist',
                ]);
            }
        }

        // Songs using files that exist in your storage
        $songs = [
            [
                'title' => 'Blinding Lights',
                'song_cover_path' => 'songs_cover/Blinding_Lights.png',
                'file_path' => 'audios/Blinded By The Light-yt.savetube.me.mp3',
                'artist_id' => 7,
                'genre' => 'Pop',
                'description' => 'Synthwave-influenced pop song',
                'uploaded_by' => $user->id,
                'release_date' => '2024-01-15',
                'views' => 0,
            ],
            [
                'title' => 'Starboy',
                'song_cover_path' => 'songs_cover/Starboy.png',
                'file_path' => 'audios/The Weeknd - Starboy ft. Daft Punk (Official Video)-yt.savetube.me.mp3',
                'artist_id' => 7,
                'genre' => 'R&B',
                'description' => 'Collaboration with Daft Punk',
                'uploaded_by' => $user->id,
                'release_date' => '2024-02-10',
                'views' => 0,
            ],
            [
                'title' => 'Hello',
                'song_cover_path' => 'songs_cover/hello.jpg',
                'file_path' => 'audios/Adele - Hello (Official Music Video)-yt.savetube.me.mp3',
                'artist_id' => 7,
                'genre' => 'Soul',
                'description' => 'Powerful ballad',
                'uploaded_by' => $user->id,
                'release_date' => '2024-03-05',
                'views' => 0,
            ],
            [
                'title' => 'Rolling in the Deep',
                'song_cover_path' => 'songs_cover/rolling_in_the_deep.png',
                'file_path' => 'audios/Adele - Rolling in the Deep (Official Music Video)-yt.savetube.me.mp3',
                'artist_id' => 7,
                'genre' => 'Soul',
                'description' => 'Soulful track with blues influences',
                'uploaded_by' => $user->id,
                'release_date' => '2024-04-20',
                'views' => 0,
            ],
            [
                'title' => 'Radha Swoopna Suman',
                'song_cover_path' => 'songs_cover/hello.jpg',
                'file_path' => 'audios/radha-swoopna-suman-ft-abhigya-official-mv-128-ytshorts.savetube.me_1754446318.mp3',
                'artist_id' => 7,
                'genre' => 'Folk',
                'description' => 'Traditional Nepali folk song',
                'uploaded_by' => $user->id,
                'release_date' => '2024-05-12',
                'views' => 0,
            ]
        ];

        $createdSongs = [];
        foreach ($songs as $songData) {
            $song = \App\Models\Music::create($songData);
            $createdSongs[] = $song;
        }

        return response()->json([
            'success' => true,
            'message' => 'Songs created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'songs_created' => count($createdSongs),
            'songs' => collect($createdSongs)->map(function($song) {
                return [
                    'id' => $song->id,
                    'title' => $song->title,
                    'file_path' => $song->file_path,
                    'cover_path' => $song->song_cover_path,
                    'genre' => $song->genre,
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
