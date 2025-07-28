<?php

namespace App\Http\Controllers;

use App\Models\Artists;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $artists = Artists::withCount('music')->get()->map(function ($item) {
            $item->artist_image = asset('storage/' . $item->artist_image);
            return $item;
        });

        return response()->json($artists);
    }

    public function getSongs($artistId)
    {
        $artist = Artists::find($artistId);

        if (!$artist) {
            return response()->json(['message' => 'Artist not found'], 404);
        }

        $songs = $artist->music;
        $songCount = $songs->count();

        $artistData = [
            'id' => $artist->id,
            'artist_name' => $artist->artist_name,
            'artist_image' => asset('storage/' . $artist->artist_image),
            'song_cover' => asset('storage/' . $artist->song_cover_path),
            'song_count' => $songCount,

        ];

        $songsData = $songs->map(function ($song) {
            return [
                'id' => $song->id,
                'title' => $song->title,
                'song_cover' => asset('storage/' . $song->song_cover_path),
                'file_path' => asset('storage/' . $song->file_path),
            ];
        });


        return response()->json([
            'artist' => $artistData,
            'songs' => $songsData
        ]);
    }
}
