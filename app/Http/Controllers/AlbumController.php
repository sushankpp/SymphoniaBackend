<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $albums = Album::with(['artists', 'songs'])->get()->map(function ($album) {
            return [
                'id' => $album->id,
                'title' => $album->title,
                'cover_image' => asset('storage/' . $album->cover_image_path),
                'artist_name' => $album->artists->artist_name,
                'artist_id'=> $album->artists->id,
                'release_date' => $album->release_date,
                'songs' => $album->songs->map(function ($song) {
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
                }),
            ];
        });

        return response()->json($albums);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'cover_image_path' => 'nullable|image',
            'artist_id' => 'required|exists:artists,id',
            'release_date' => 'required|date',
        ]);

        $album = Album::create($validated);

        return response()->json(['message' => 'Album created successfully', 'album' => $album], 201);
    }

    public function getAlbumsByArtist($artistId): \Illuminate\Http\JsonResponse
    {
        $artist = Artist::with('albums.songs')->findOrFail($artistId);

        $albums = $artist->albums->map(function ($album) {
            return [
                'id' => $album->id,
                'title' => $album->title,
                'cover_image' => asset('storage/' . $album->cover_image_path),
                'release_date' => $album->release_date,
                'songs' => $album->songs->map(function ($song) {
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
                }),
            ];
        });

        return response()->json([
            'artist' => $artist->artist_name,
            'albums' => $albums,
        ]);
    }
}
