<?php

namespace App\Http\Controllers;

use App\Models\Music;
use App\Models\UploadedMusic;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\File;
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

    public function getUploadedMusic(Request $request)
    {
        try {
            $uploadedMusic = UploadedMusic::with([
                'music' => function ($query) {
                    $query->with(['artist', 'album']);
                }
            ])->get()->map(function ($uploadedItem) {
                $music = $uploadedItem->music;

                if ($music) {
                    $music->song_cover = asset('storage/' . $music->song_cover_path);

                    $music->file_path = asset('storage/' . $music->file_path);

                    if ($music->artist && $music->artist->artist_image) {
                        if (str_starts_with($music->artist->artist_image, 'http://')) {
                        } else if (str_starts_with($music->artist->artist_image, 'storage/')) {
                            $path = str_replace('storage/', '', $music->artist->artist_image);
                            $music->artist->artist_image = asset('storage/' . $path);
                        } else {
                            $music->artist->artist_image = asset('storage/' . $music->artist->artist_image);
                        }
                    }

                    if ($music->album) {
                        $music->album_title = $music->album->title;
                        $music->album_cover = asset('storage/' . $music->album->cover);
                    }

                    // Add uploaded music metadata
                    $music->uploaded_by = $uploadedItem->uploaded_by;
                    $music->uploaded_at = $uploadedItem->uploaded_at;
                }

                return $music;
            })->filter(); // Remove any null entries

            return response()->json($uploadedMusic);
        } catch (Exception $e) {
            Log::error('Error fetching uploaded music', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch uploaded music'], 500);
        }
    }

    public function uploadMusic(Request $request)
    {
        try {
            $validated = $request->validate([
                'audio_file' => 'required|file|mimes:mp3,wav',
                'song_title' => 'required|string|max:255',
                'artist_id' => 'required|exists:artists,id',
                'genre' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'release_date' => 'nullable|date',
                'lyrics' => 'nullable|string',
                'cover_image' => 'required|file|mimes:jpeg,png,jpg,gif',
            ], [], [], true);

            $originalAudioPath = $request->file('audio_file')->store('audios/original', 'public');
            $coverPath = $request->file('cover_image')->store('songs_cover', 'public');

            $compressedAudioPath = 'audios/compressed/' . basename($originalAudioPath);
            $ffmpeg = FFMpeg::create();
            $audio = $ffmpeg->open(storage_path('app/public/' . $originalAudioPath));
            $format = new Mp3();
            $format->setAudioKiloBitrate(128);
            $audio->save($format, storage_path('app/public/' . $compressedAudioPath));

            if (File::exists(storage_path('app/public/' . $compressedAudioPath))) {
                $bytes = File::size(storage_path('app/public/' . $compressedAudioPath));
                $audio->fileSize = $this->formatBytes($bytes);
            }

            $music = Music::create([
                'title' => $validated['song_title'],
                'file_path' => $compressedAudioPath,
                'song_cover_path' => $coverPath,
                'artist_id' => $validated['artist_id'],
                'album_id' => $request->input('album_id') ?? null,
            ]);

            UploadedMusic::create([
                'music_id' => $music->id,
                'uploaded_by' => $request->input('uploaded_by', 'admin'),
            ]);

            return response()->json(['message' => 'Music uploaded successfully', 'music' => $music], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        }
    }

    private
    function formatBytes($bytes, $precision = 2)
    {
        $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}




