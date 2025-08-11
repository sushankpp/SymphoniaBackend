<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MusicUploadRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'artist_id',
        'user_id',
        'song_title',
        'file_path',
        'song_cover_path',
        'artist_id_for_song',
        'album_id',
        'genre',
        'description',
        'release_date',
        'lyrics',
        'status',
        'admin_notes'
    ];

    protected $casts = [
        'release_date' => 'date',
    ];

    public function artist()
    {
        return $this->belongsTo(Artist::class, 'artist_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function songArtist()
    {
        return $this->belongsTo(Artist::class, 'artist_id_for_song');
    }

    public function album()
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    /**
     * Approve the music upload request
     */
    public function approve($adminNotes = null)
    {
        try {
            DB::transaction(function () use ($adminNotes) {
                if (!Storage::disk('public')->exists($this->file_path)) {
                    throw new \Exception("Audio file not found: {$this->file_path}");
                }
                if (!Storage::disk('public')->exists($this->song_cover_path)) {
                    throw new \Exception("Cover image not found: {$this->song_cover_path}");
                }

                $finalAudioPath = $this->moveFileToFinalLocation($this->file_path, 'audios/compressed');
                $finalCoverPath = $this->moveFileToFinalLocation($this->song_cover_path, 'songs_cover');

                if (!$finalAudioPath || !$finalCoverPath) {
                    throw new \Exception("Failed to move files to final location");
                }

                $musicData = [
                    'title' => $this->song_title,
                    'file_path' => $finalAudioPath,
                    'song_cover_path' => $finalCoverPath,
                    'artist_id' => $this->artist_id_for_song,
                    'album_id' => $this->album_id,
                    'genre' => $this->genre,
                    'description' => $this->description,
                    'lyrics' => $this->lyrics,
                    'views' => 0,
                    'uploaded_by' => $this->user_id,
                ];

                $music = Music::create($musicData);

                UploadedMusic::create([
                    'music_id' => $music->id,
                    'uploaded_by' => $this->user_id,
                ]);

                $this->update([
                    'status' => 'approved',
                    'admin_notes' => $adminNotes
                ]);

                \Log::info("Music upload request {$this->id} approved. Music ID: {$music->id}");
            });

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to approve music upload request {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject the music upload request
     */
    public function reject($adminNotes = null)
    {
        try {
            DB::transaction(function () use ($adminNotes) {
                if (Storage::disk('public')->exists($this->file_path)) {
                    Storage::disk('public')->delete($this->file_path);
                }
                if (Storage::disk('public')->exists($this->song_cover_path)) {
                    Storage::disk('public')->delete($this->song_cover_path);
                }

                $this->update([
                    'status' => 'rejected',
                    'admin_notes' => $adminNotes
                ]);

                \Log::info("Music upload request {$this->id} rejected");
            });

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to reject music upload request {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel the music upload request (by the artist)
     */
    public function cancel()
    {
        try {
            DB::transaction(function () {
                if (Storage::disk('public')->exists($this->file_path)) {
                    Storage::disk('public')->delete($this->file_path);
                }
                if (Storage::disk('public')->exists($this->song_cover_path)) {
                    Storage::disk('public')->delete($this->song_cover_path);
                }

                $this->delete();

                \Log::info("Music upload request {$this->id} cancelled by artist");
            });

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to cancel music upload request {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Move file from temporary location to final location
     */
    private function moveFileToFinalLocation($tempPath, $finalDirectory)
    {
        if (!$tempPath || !Storage::disk('public')->exists($tempPath)) {
            throw new \Exception("Temporary file not found: {$tempPath}");
        }

        $filename = basename($tempPath);

        $finalPath = $finalDirectory . '/' . $filename;

        if (Storage::disk('public')->move($tempPath, $finalPath)) {
            return $finalPath;
        } else {
            throw new \Exception("Failed to move file from {$tempPath} to {$finalPath}");
        }
    }
}
