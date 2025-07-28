<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function index()
    {
        $playlists = Playlist::with('songs')->where('user_id', 1)->get();
        return response()->json($playlists, 200);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'playlist_name' => 'required|string|max:255',
        ]);

        $playlist = Playlist::create([
            'user_id' => 1,
            'playlist_name' => $validated['playlist_name'],
        ]);

        return response()->json($playlist, 201);
    }

    public function addSong(Request $request, Playlist $playlist)
    {
        $validated = $request->validate([
            'song_id' => 'required|exists:music,id',
        ]);

        $playlist->songs()->syncWithoutDetaching($validated['song_id']);
        return response()->json(['message' => 'Song added to playlist successfully'], 200);
    }

    public function getSongs(Playlist $playlist)
    {
        $songs = $playlist->songs;

        foreach ($songs as $song) {
            $song->song_cover_path = asset('storage/' . $song->song_cover_path);
        }

        return response()->json($songs, 200);
    }
}
