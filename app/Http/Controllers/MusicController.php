<?php

namespace App\Http\Controllers;

use App\Models\Music;
use App\Models\UploadedMusic;
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

                    // Add file size information
                    $relativePath = str_replace(asset('storage/'), '', $music->file_path);
                    if (File::exists(storage_path('app/public/' . $relativePath))) {
                        $fileSize = File::size(storage_path('app/public/' . $relativePath));
                        $music->file_size = $this->formatBytes($fileSize);
                        
                        // Try to find original file for compression stats
                        if (strpos($relativePath, 'audios/compressed/') !== false) {
                            // Extract the base filename without extension
                            $compressedFilename = basename($relativePath, '.m4a');
                            $compressedFilename = basename($compressedFilename, '.mp3');
                            
                            // Look for original file with timestamp pattern (new files)
                            $originalDir = storage_path('app/public/audios/original');
                            $originalFiles = glob($originalDir . '/' . $compressedFilename . '_*.*');
                            
                            // If not found, try exact same name (old files)
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
                            // File is not compressed, so no compression stats
                            $music->compression_stats = null;
                        }
                    }
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

            // Store with original filename but ensure uniqueness
            $originalFilename = $request->file('audio_file')->getClientOriginalName();
            $originalExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $originalName = pathinfo($originalFilename, PATHINFO_FILENAME);
            
            // Create unique filename with original name
            $uniqueFilename = $originalName . '_' . time() . '.' . $originalExtension;
            $originalAudioPath = $request->file('audio_file')->storeAs('audios/original', $uniqueFilename, 'public');
            
            $coverPath = $request->file('cover_image')->store('songs_cover', 'public');

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
                'artist_id' => $validated['artist_id'],
                'album_id' => $request->input('album_id') ?? null,
            ]);

            UploadedMusic::create([
                'music_id' => $music->id,
                'uploaded_by' => $request->input('uploaded_by', 'admin'),
            ]);

            // Get file sizes for response
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
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
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
        // Remove the timestamp suffix to get clean original name
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

        // Verify compression was successful
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
}




