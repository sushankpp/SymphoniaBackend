<?php

namespace App\Http\Controllers;

use App\Models\RecentlyPlayed;
use Illuminate\Http\Request;

class RecentlyPlayedController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'song_id' => 'required|exists:music,id',
        ]);

        $user_id = auth()->id();

        // Check if user already played this song
        $recentlyPlayed = RecentlyPlayed::where('user_id', $user_id)
            ->where('song_id', $validated['song_id'])
            ->first();

        if ($recentlyPlayed) {
            // Update timestamp if already exists
            $recentlyPlayed->touch();
        } else {
            // Create new entry
            RecentlyPlayed::create([
                'user_id' => $user_id,
                'song_id' => $validated['song_id'],
            ]);
        }

        return response()->json(['message' => 'Song added to recently played'], 201);
    }

    public function index()
    {
        $user_id = auth()->id();
        
        $recentlyPlayed = RecentlyPlayed::where('user_id', $user_id)
            ->with(['song', 'song.artist'])
            ->latest('updated_at')
            ->take(10)
            ->get()
            ->map(function ($item) {
                if ($item->song) {
                    $item->song->song_cover_path = asset('storage/' . $item->song->song_cover_path);
                    $item->song->file_path = asset('storage/' . $item->song->file_path);

                    if ($item->song->artist) {
                        $artist = $item->song->artist;
                    }
                }
                return $item;
            });

        return response()->json($recentlyPlayed);
    }
}
