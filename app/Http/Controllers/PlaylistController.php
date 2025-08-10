<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function index()
    {
        $user_id = auth()->id();
        $playlists = Playlist::with('songs')->where('user_id', $user_id)->get();
        return response()->json($playlists, 200);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'playlist_name' => 'required|string|max:255',
            ]);

            $user = auth()->user();
            if (!$user->canCreatePlaylists()) {
                return response()->json(['error' => 'Artists cannot create playlists'], 403);
            }

            $playlist = Playlist::create([
                'user_id' => $user->id,
                'playlist_name' => $validated['playlist_name'],
            ]);

            return response()->json($playlist, 201);
        } catch (\Exception $e) {
            \Log::error('Playlist creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Failed to create playlist',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addSong(Request $request, Playlist $playlist)
    {
        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'song_id' => 'required|exists:music,id',
        ]);

        $playlist->songs()->syncWithoutDetaching($validated['song_id']);
        return response()->json(['message' => 'Song added to playlist successfully'], 200);
    }

    public function getSongs(Playlist $playlist)
    {
        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $songs = $playlist->songs;

        foreach ($songs as $song) {
            $song->song_cover_path = asset('storage/' . $song->song_cover_path);
        }

        return response()->json($songs, 200);
    }
}
