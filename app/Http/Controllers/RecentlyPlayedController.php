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

        $recentlyPlayed = RecentlyPlayed::where('song_id', $validated['song_id'])->first();

        if ($recentlyPlayed) {
            $recentlyPlayed->touch();
        } else {
            RecentlyPlayed::create([
                'song_id' => $validated['song_id'],
            ]);
        }

        return response()->json(['message' => 'Song added to recently played'], 201);
    }

    public function index()
    {
        $recentlyPlayed = RecentlyPlayed::with(['song', 'song.artist', 'song.artist.music'])->latest('updated_at')->take(10)->get()->map(function ($item) {
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
