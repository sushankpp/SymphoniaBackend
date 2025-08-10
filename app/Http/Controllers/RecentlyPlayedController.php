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

        $recentlyPlayed = RecentlyPlayed::where('user_id', $user_id)
            ->where('song_id', $validated['song_id'])
            ->first();

        if ($recentlyPlayed) {
            $recentlyPlayed->touch();
        } else {
            RecentlyPlayed::create([
                'user_id' => $user_id,
                'song_id' => $validated['song_id'],
            ]);
        }

        return response()->json(['message' => 'Song added to recently played'], 201);
    }

    public function index()
    {
        try {
            $user_id = auth()->id();

            $recentlyPlayed = RecentlyPlayed::where('user_id', $user_id)
                ->with(['song', 'song.artist'])
                ->latest('updated_at')
                ->take(10)
                ->get()
                ->map(function ($item) {
                    if ($item->song) {
                        if ($item->song->song_cover_path) {
                            $item->song->song_cover_url = asset('storage/' . $item->song->song_cover_path);
                        }
                        if ($item->song->file_path) {
                            $item->song->file_url = asset('storage/' . $item->song->file_path);
                        }

                        if ($item->song->artist && $item->song->artist->artist_image) {
                            $item->song->artist->artist_image_url = $this->generateAssetUrl($item->song->artist->artist_image);
                        }
                    }
                    return $item;
                });

            return response()->json([
                'success' => true,
                'recently_played' => $recentlyPlayed
            ]);
        } catch (\Exception $e) {
            \Log::error('Recently played fetch error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recently played songs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate asset URL safely (handles both full URLs and relative paths)
     */
    private function generateAssetUrl($path)
    {
        if (!$path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset('storage/' . $path);
    }
}
