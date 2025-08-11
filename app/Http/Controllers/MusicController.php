<?php

namespace App\Http\Controllers;

use App\Models\Music;
use App\Models\UploadedMusic;
use App\Models\Artist;
use App\Services\RecommendationEngine;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Aac;
use FFMpeg\Coordinate\TimeCode;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MusicController extends Controller
{
    public function __invoke(Request $request)
    {
        $music = Music::with('artist')->get()->map(function ($item) {
            $item->song_cover = asset('storage/' . $item->song_cover_path);

            $item->file_path = asset('storage/' . $item->file_path);

            if ($item->artist && $item->artist->artist_image) {
                $item->artist->artist_image = $this->generateAssetUrl($item->artist->artist_image);
            }

            if ($item->album) {
                $item->album_title = $item->album->title;
                $item->album_cover = asset('storage/' . $item->album->cover);
            };

            $item->views = $item->views ?? 0;
            $item->genre = $item->genre ?? '';
            $item->description = $item->description ?? '';
            $item->lyrics = $item->lyrics ?? '';
            $item->release_date = $item->release_date ?? null;

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
                        $music->artist->artist_image = $this->generateAssetUrl($music->artist->artist_image);
                    }

                    if ($music->album) {
                        $music->album_title = $music->album->title;
                        $music->album_cover = asset('storage/' . $music->album->cover);
                    }

                    $music->uploaded_by = $uploadedItem->uploaded_by;
                    $music->uploaded_at = $uploadedItem->uploaded_at;
                    $music->upload_status = 'approved';
                    $music->request_id = null;

                    $music->views = $music->views ?? 0;
                    $music->genre = $music->genre ?? '';
                    $music->description = $music->description ?? '';
                    $music->lyrics = $music->lyrics ?? '';
                    $music->release_date = $music->release_date ?? null;

                    $relativePath = str_replace(asset('storage/'), '', $music->file_path);
                    if (File::exists(storage_path('app/public/' . $relativePath))) {
                        $fileSize = File::size(storage_path('app/public/' . $relativePath));
                        $music->file_size = $this->formatBytes($fileSize);

                        if (strpos($relativePath, 'audios/compressed/') !== false) {
                            $compressedFilename = basename($relativePath, '.m4a');
                            $compressedFilename = basename($compressedFilename, '.mp3');

                            $originalDir = storage_path('app/public/audios/original');
                            $originalFiles = glob($originalDir . '/' . $compressedFilename . '_*.*');

                            if (empty($originalFiles)) {
                                $originalFiles = glob($originalDir . '/' . $compressedFilename . '.*');
                            }

                            Log::info('Looking for original file', [
                                'compressed_filename' => $compressedFilename,
                                'original_files_found' => count($originalFiles),
                                'files' => $originalFiles
                            ]);

                            if (!empty($originalFiles)) {
                                $originalPath = $originalFiles[0];
                                $originalSize = File::size($originalPath);
                                $compressionRatio = round(($originalSize - $fileSize) / $originalSize * 100, 2);

                                $music->compression_stats = [
                                    'original_size' => $this->formatBytes($originalSize),
                                    'compressed_size' => $music->file_size,
                                    'compression_ratio' => $compressionRatio,
                                    'space_saved' => $this->formatBytes($originalSize - $fileSize)
                                ];

                                Log::info('Compression stats found', $music->compression_stats);
                            } else {
                                Log::info('No original file found for compression stats', [
                                    'compressed_filename' => $compressedFilename,
                                    'relative_path' => $relativePath
                                ]);
                            }
                        } else {
                            $music->compression_stats = null;
                        }
                    }
                }

                return $music;
            })->filter();

            $uploadRequests = \App\Models\MusicUploadRequest::with(['user', 'artist', 'songArtist', 'album'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    $music = new \stdClass();
                    $music->id = 'request_' . $request->id;
                    $music->title = $request->song_title;
                    $music->song_cover = asset('storage/' . $request->song_cover_path);
                    $music->file_path = asset('storage/' . $request->file_path);
                    $music->genre = $request->genre ?? '';
                    $music->description = $request->description ?? '';
                    $music->lyrics = $request->lyrics ?? '';
                    $music->release_date = $request->release_date;
                    $music->views = 0;
                    $music->uploaded_by = $request->user_id;
                    $music->uploaded_at = $request->created_at;
                    $music->upload_status = $request->status;
                    $music->request_id = $request->id;
                    $music->admin_notes = $request->admin_notes;

                    if ($request->songArtist) {
                        $music->artist = $request->songArtist;
                        if ($music->artist->artist_image) {
                            $music->artist->artist_image = $this->generateAssetUrl($music->artist->artist_image);
                        }
                    }

                    if ($request->album) {
                        $music->album = $request->album;
                        $music->album_title = $request->album->title;
                        $music->album_cover = asset('storage/' . $request->album->cover);
                    }

                    $music->uploader = $request->user;

                    $relativePath = str_replace(asset('storage/'), '', $music->file_path);
                    if (File::exists(storage_path('app/public/' . $relativePath))) {
                        $fileSize = File::size(storage_path('app/public/' . $relativePath));
                        $music->file_size = $this->formatBytes($fileSize);
                    }

                    return $music;
                });

            $allMusic = $uploadedMusic->concat($uploadRequests)
                ->sortByDesc('uploaded_at')
                ->values();

            return response()->json($allMusic);
        } catch (Exception $e) {
            Log::error('Error fetching uploaded music', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch uploaded music'], 500);
        }
    }

    public function uploadMusic(Request $request)
    {
        try {
            $user = auth()->user();

            if ($user && $user->role === 'artist') {
                $uploadRequestController = new \App\Http\Controllers\MusicUploadRequestController();
                return $uploadRequestController->submit($request);
            }

            $validated = $request->validate([
                'audio_file' => 'required|file|mimes:mp3,wav',
                'song_title' => 'required|string|max:255',
                'artist_id' => 'nullable|exists:artists,id',
                'genre' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'release_date' => 'nullable|date',
                'lyrics' => 'nullable|string',
                'cover_image' => 'required|file|mimes:jpeg,png,jpg,gif',
            ], [], [], true);

            $originalFilename = $request->file('audio_file')->getClientOriginalName();
            $originalExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $originalName = pathinfo($originalFilename, PATHINFO_FILENAME);

            $uniqueFilename = $originalName . '_' . time() . '.' . $originalExtension;
            $originalAudioPath = $request->file('audio_file')->storeAs('audios/original', $uniqueFilename, 'public');

            $coverPath = $request->file('cover_image')->store('albums_cover', 'public');

            $artistId = $validated['artist_id'] ?? null;
            if (!$artistId && auth()->check()) {
                $user = auth()->user();
                if ($user->role === 'artist') {
                    $artist = Artist::where('user_id', $user->id)->first();
                    if ($artist) {
                        $artistId = $artist->id;
                    }
                }
            }

            if (!$artistId) {
                $artistId = $validated['artist_id'] ?? Artist::first()->id ?? 1;
            }

            $artist = Artist::find($artistId);
            $uploadedByUserId = $artist ? $artist->user_id : (auth()->id() ?? 1);

            $this->ensureCompressedDirectoryExists();

            $compressedAudioPath = $this->generateCompressedFilePath($originalAudioPath);

            $this->compressAudioFile($originalAudioPath, $compressedAudioPath);

            if (File::exists(storage_path('app/public/' . $compressedAudioPath))) {
                $originalSize = File::size(storage_path('app/public/' . $originalAudioPath));
                $compressedSize = File::size(storage_path('app/public/' . $compressedAudioPath));

                Log::info('Audio compression completed', [
                    'original_size' => $this->formatBytes($originalSize),
                    'compressed_size' => $this->formatBytes($compressedSize),
                    'compression_ratio' => round(($originalSize - $compressedSize) / $originalSize * 100, 2) . '%'
                ]);
            } else {
                Log::error('Compressed file was not created', ['path' => $compressedAudioPath]);
                throw new Exception('Failed to create compressed audio file');
            }

            $music = Music::create([
                'title' => $validated['song_title'],
                'file_path' => $compressedAudioPath,
                'song_cover_path' => $coverPath,
                'artist_id' => $artistId,
                'album_id' => $request->input('album_id') ?? null,
                'genre' => $validated['genre'] ?? null,
                'description' => $validated['description'] ?? null,
                'release_date' => $validated['release_date'] ?? null,
                'lyrics' => $validated['lyrics'] ?? null,
                'views' => 0,
                'uploaded_by' => $uploadedByUserId,
            ]);

            UploadedMusic::create([
                'music_id' => $music->id,
                'uploaded_by' => $request->input('uploaded_by', 'admin'),
            ]);

            $originalSize = File::size(storage_path('app/public/' . $originalAudioPath));
            $compressedSize = File::size(storage_path('app/public/' . $compressedAudioPath));

            $originalSizeFormatted = $this->formatBytes($originalSize);
            $compressedSizeFormatted = $this->formatBytes($compressedSize);
            $compressionRatio = round(($originalSize - $compressedSize) / $originalSize * 100, 2);

            return response()->json([
                'message' => 'Music uploaded successfully',
                'music' => $music,
                'compression_stats' => [
                    'original_size' => $originalSizeFormatted,
                    'compressed_size' => $compressedSizeFormatted,
                    'compression_ratio' => $compressionRatio,
                    'space_saved' => $this->formatBytes($originalSize - $compressedSize)
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
                'message' => 'Please check that all required fields are provided: audio_file, song_title, artist_id, and cover_image'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Music upload failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    private function ensureCompressedDirectoryExists()
    {
        $compressedDir = storage_path('app/public/audios/compressed');
        if (!File::exists($compressedDir)) {
            File::makeDirectory($compressedDir, 0755, true);
        }
    }


    private function generateCompressedFilePath($originalPath)
    {
        $filename = basename($originalPath, '.' . pathinfo($originalPath, PATHINFO_EXTENSION));
        $originalName = preg_replace('/_\d+$/', '', $filename);
        return 'audios/compressed/' . $originalName . '.m4a';
    }


    private function compressAudioFile($originalPath, $compressedPath)
    {
        $inputPath = storage_path('app/public/' . $originalPath);
        $outputPath = storage_path('app/public/' . $compressedPath);

        $ffmpegCommand = "ffmpeg -i \"$inputPath\" -c:a aac -b:a 64k -ar 44100 -ac 2 \"$outputPath\" 2>&1";

        Log::info('Starting audio compression', [
            'input' => $originalPath,
            'output' => $compressedPath,
            'command' => $ffmpegCommand
        ]);

        $output = shell_exec($ffmpegCommand);

        if (!File::exists($outputPath)) {
            Log::error('Audio compression failed', ['output' => $output]);
            throw new Exception('Failed to compress audio file: ' . $output);
        }

        Log::info('Audio compression completed successfully');
    }


    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function playSong($id)
    {
        $music = Music::findOrFail($id);
        $music->increment('views');
        return response()->json([
            'message' => 'Song is being played',
            'music' => $music
        ], 200);

    }

    public function getRecommendations(Request $request)
    {
        try {
            $user_id = auth()->id();
            $limit = $request->get('limit', 10);

            $recommendationEngine = new RecommendationEngine();
            $recommendations = $recommendationEngine->getRecommendations($user_id, $limit);

            $processed_recommendations = [];
            foreach ($recommendations as $rec) {
                $song = $rec['song'];
                $processed_recommendations[] = [
                    'song' => [
                        'id' => $song->id,
                        'title' => $song->title,
                        'genre' => $song->genre,
                        'views' => $song->views,
                        'file_path' => $song->file_path,
                        'song_cover_path' => $song->song_cover_path,
                        'artist' => $song->artist ? [
                            'id' => $song->artist->id,
                            'artist_name' => $song->artist->artist_name,
                            'artist_image' => $this->generateAssetUrl($song->artist->artist_image),
                        ] : null,
                        'album' => $song->album ? [
                            'id' => $song->album->id,
                            'title' => $song->album->title,
                            'cover_image_path' => $song->album->cover_image_path,
                        ] : null,
                    ],
                    'similarity_score' => $rec['similarity_score'] ?? $rec['trending_score'] ?? 0,
                    'is_trending' => !$user_id
                ];
            }

            return response()->json([
                'success' => true,
                'recommendations' => $processed_recommendations,
                'is_authenticated' => (bool) $user_id
            ]);
        } catch (\Exception $e) {
            \Log::error('Recommendation API error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTopRecommendations(Request $request)
    {
        try {
            $user_id = auth()->id();
            $limit = $request->get('limit', 5);

            $recommendationEngine = new RecommendationEngine();
            $recommendations = $recommendationEngine->getRecommendations($user_id, $limit);

            $processed_recommendations = [];
            foreach ($recommendations as $rec) {
                $song = $rec['song'];
                $processed_recommendations[] = [
                    'song' => [
                        'id' => $song->id,
                        'title' => $song->title,
                        'genre' => $song->genre,
                        'views' => $song->views,
                        'file_path' => $song->file_path,
                        'song_cover_path' => $song->song_cover_path,
                        'artist' => $song->artist ? [
                            'id' => $song->artist->id,
                            'artist_name' => $song->artist->artist_name,
                            'artist_image' => $this->generateAssetUrl($song->artist->artist_image),
                        ] : null,
                        'album' => $song->album ? [
                            'id' => $song->album->id,
                            'title' => $song->album->title,
                            'cover_image_path' => $song->album->cover_image_path,
                        ] : null,
                    ],
                    'similarity_score' => $rec['similarity_score'] ?? $rec['trending_score'] ?? 0,
                    'is_trending' => !$user_id
                ];
            }

            return response()->json([
                'success' => true,
                'top_recommendations' => $processed_recommendations,
                'is_authenticated' => (bool) $user_id
            ]);
        } catch (\Exception $e) {
            \Log::error('Top Recommendation API error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get top recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTopArtists(Request $request)
    {
        try {
            $user_id = auth()->id();
            $limit = $request->get('limit', 5);

            $recommendationEngine = new RecommendationEngine();

            if ($user_id) {
                $top_artists = $recommendationEngine->getTopArtists($user_id, $limit);
            } else {
                $top_artists = $recommendationEngine->getGlobalTopArtists($limit);
            }

            $processed_artists = [];
            foreach ($top_artists as $artist_data) {
                $artist = $artist_data['artist'];
                $processed_artists[] = [
                    'artist' => [
                        'id' => $artist->id,
                        'artist_name' => $artist->artist_name,
                        'artist_image' => $this->generateAssetUrl($artist->artist_image),
                        'music_count' => $artist->music ? $artist->music->count() : 0,
                    ],
                    'score' => $artist_data['score'],
                    'is_personalized' => (bool) $user_id
                ];
            }

            return response()->json([
                'success' => true,
                'top_artists' => $processed_artists,
                'is_authenticated' => (bool) $user_id
            ]);
        } catch (\Exception $e) {
            \Log::error('Top Artists API error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get top artists',
                'message' => $e->getMessage()
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



