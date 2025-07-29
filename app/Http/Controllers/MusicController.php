<?php

namespace App\Http\Controllers;

use App\Models\Music;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;

class MusicController extends Controller
{
    public function __invoke(Request $request)
    {
        $music = Music::with('artist')->get()->map(function ($item) {
            // Process song cover path
            $item->song_cover = asset('storage/' . $item->song_cover_path);

            // Process file path
            $item->file_path = asset('storage/' . $item->file_path);

            // Process artist image
            if ($item->artist && $item->artist->artist_image) {
                if (str_starts_with($item->artist->artist_image, 'http://')) {
                } else if (str_starts_with($item->artist->artist_image, 'storage/')) {
                    $path = str_replace('storage/', '', $item->artist->artist_image);
                    $item->artist->artist_image = asset('storage/' . $path);
                } else {
                    $item->artist->artist_image = asset('storage/' . $item->artist->artist_image);
                }
            }

            if ($item->album) {
                $item->album_title = $item->album->title;
                $item->album_cover = asset('storage/' . $item->album->cover);
            };

            return $item;
        });

        return response()->json($music);
    }

    public function uploadMusic(Request $request)
    {
        try {
            // Debug all request data
            Log::info('Full request data', [
                'all_data' => $request->all(),
                'files' => $request->allFiles(),
                'has_audio_file' => $request->hasFile('audio_file'),
                'has_cover_image' => $request->hasFile('cover_image'),
                'audio_file_details' => $request->hasFile('audio_file') ? [
                    'name' => $request->file('audio_file')->getClientOriginalName(),
                    'size' => $request->file('audio_file')->getSize(),
                    'mime_type' => $request->file('audio_file')->getMimeType(),
                    'extension' => $request->file('audio_file')->getClientOriginalExtension(),
                ] : 'No audio file received',
                'cover_image_details' => $request->hasFile('cover_image') ? [
                    'name' => $request->file('cover_image')->getClientOriginalName(),
                    'size' => $request->file('cover_image')->getSize(),
                    'mime_type' => $request->file('cover_image')->getMimeType(),
                    'extension' => $request->file('cover_image')->getClientOriginalExtension(),
                ] : 'No cover image received',
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
            ]);

            Log::info('Upload request received', [
                'has_audio_file' => $request->hasFile('audio_file'),
                'has_cover_image' => $request->hasFile('cover_image'),
                'song_title' => $request->input('song_title'),
                'artist_id' => $request->input('artist_id'),
            ]);

            $validated = $request->validate([
                'audio_file' => 'required|file|mimes:mp3,wav',
                'song_title' => 'required|string|max:255',
                'artist_id' => 'required|exists:artists,id',
                'genre' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'release_date' => 'nullable|date',
                'lyrics' => 'nullable|string',
                'cover_image' => 'required|file|mimes:jpeg,png,jpg,gif',
            ],[],[],true);

            $audioPath = $request->file('audio_file')->store('audios', 'public');
            $coverPath = $request->file('cover_image')->store('songs_cover', 'public');

            Log::info('Files stored successfully', [
                'audio_path' => $audioPath,
                'cover_path' => $coverPath,
            ]);

            $music = Music::create([
                'title' => $validated['song_title'],
                'file_path' => $audioPath,
                'song_cover_path' => $coverPath,
                'artist_id' => $validated['artist_id'],
                'album_id' => $request->input('album_id') ?? null,
            ]);

            return response()->json(['message' => 'Music uploaded successfully', 'music' => $music], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Music upload error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
