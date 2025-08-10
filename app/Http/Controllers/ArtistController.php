<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $artists = Artist::withCount('music')->get()->map(function ($item) {
            $item->artist_image = $this->generateAssetUrl($item->artist_image);
            return $item;
        });

        return response()->json($artists);
    }

    public function getSongs($artistId)
    {
        $artist = Artist::find($artistId);

        if (!$artist) {
            return response()->json(['message' => 'Artist not found'], 404);
        }

        $songs = $artist->music;
        $songCount = $songs->count();

        $artistData = [
            'id' => $artist->id,
            'artist_name' => $artist->artist_name,
                            'artist_image' => $this->generateAssetUrl($artist->artist_image),
            'song_cover' => asset('storage/' . $artist->song_cover_path),
            'song_count' => $songCount,
        ];

        $songsData = $songs->map(function ($song) {
            return [
                'id' => $song->id,
                'title' => $song->title,
                'song_cover' => asset('storage/' . $song->song_cover_path),
                'file_path' => asset('storage/' . $song->file_path),
                'views' => $song->views ?? 0,
                'genre' => $song->genre ?? '',
                'description' => $song->description ?? '',
                'lyrics' => $song->lyrics ?? '',
                'release_date' => $song->release_date ?? null,
            ];
        });


        return response()->json([
            'artist' => $artistData,
            'songs' => $songsData
        ]);
    }

    /**
     * Generate asset URL safely (handles both full URLs and relative paths)
     */
    private function generateAssetUrl($path)
    {
        if (!$path) {
            return null;
        }
        
        // Check if it's already a full URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        
        // Generate asset URL for relative path
        return asset('storage/' . $path);
    }
}
