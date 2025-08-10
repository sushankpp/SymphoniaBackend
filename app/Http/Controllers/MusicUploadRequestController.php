<?php

namespace App\Http\Controllers;

use App\Models\MusicUploadRequest;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MusicUploadRequestController extends Controller
{
    /**
     * Submit a music upload request (for artists)
     */
    public function submit(Request $request)
    {
        try {
            $validated = $request->validate([
                'audio_file' => 'required|file|mimes:mp3,wav',
                'song_title' => 'required|string|max:255',
                'artist_id' => 'nullable|exists:artists,id',
                'genre' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'release_date' => 'nullable|date',
                'lyrics' => 'nullable|string',
                'cover_image' => 'required|file|mimes:jpeg,png,jpg,gif',
            ]);

            $user = auth()->user();
            
            // Ensure user is an artist
            if ($user->role !== 'artist') {
                return response()->json([
                    'error' => 'Only artists can submit music upload requests'
                ], 403);
            }

            // Get artist record
            $artist = Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'error' => 'Artist profile not found'
                ], 404);
            }

            // Auto-detect artist ID for the song
            $artistIdForSong = $validated['artist_id'] ?? $artist->id;

            // Store audio file temporarily
            $audioFile = $request->file('audio_file');
            $audioPath = $audioFile->store('temp_uploads/audio', 'public');

            // Store cover image temporarily
            $coverFile = $request->file('cover_image');
            $coverPath = $coverFile->store('temp_uploads/covers', 'public');

            // Validate that files were stored successfully
            if (!Storage::disk('public')->exists($audioPath)) {
                throw new \Exception('Failed to store audio file');
            }
            if (!Storage::disk('public')->exists($coverPath)) {
                throw new \Exception('Failed to store cover image');
            }

            // Create upload request
            $uploadRequest = MusicUploadRequest::create([
                'artist_id' => $artist->id,
                'user_id' => $user->id,
                'song_title' => $validated['song_title'],
                'file_path' => $audioPath,
                'song_cover_path' => $coverPath,
                'artist_id_for_song' => $artistIdForSong,
                'album_id' => $request->input('album_id'),
                'genre' => $validated['genre'] ?? null,
                'description' => $validated['description'] ?? null,
                'release_date' => $validated['release_date'] ?? null,
                'lyrics' => $validated['lyrics'] ?? null,
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Music upload request submitted successfully. Waiting for admin approval.',
                'request_id' => $uploadRequest->id
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Music upload request failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to submit upload request',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get artist's upload requests
     */
    public function myRequests(Request $request)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'artist') {
                return response()->json([
                    'error' => 'Only artists can view their upload requests'
                ], 403);
            }

            $requests = MusicUploadRequest::where('user_id', $user->id)
                ->with(['songArtist', 'album'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'requests' => $requests
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch artist upload requests', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch upload requests'
            ], 500);
        }
    }

    /**
     * Cancel an upload request (by artist)
     */
    public function cancel($id)
    {
        try {
            $user = auth()->user();
            $uploadRequest = MusicUploadRequest::where('id', $id)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$uploadRequest) {
                return response()->json([
                    'error' => 'Upload request not found or cannot be cancelled'
                ], 404);
            }

            if ($uploadRequest->cancel()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Upload request cancelled successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to cancel upload request'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to cancel upload request', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to cancel upload request'
            ], 500);
        }
    }

    /**
     * Get all upload requests (for admin)
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'error' => 'Only admins can view all upload requests'
                ], 403);
            }

            $query = MusicUploadRequest::with(['user', 'artist', 'songArtist', 'album']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by artist
            if ($request->has('artist_id')) {
                $query->where('artist_id', $request->artist_id);
            }

            $requests = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'requests' => $requests
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch upload requests', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch upload requests'
            ], 500);
        }
    }

    /**
     * Approve an upload request (for admin)
     */
    public function approve($id, Request $request)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'error' => 'Only admins can approve upload requests'
                ], 403);
            }

            $uploadRequest = MusicUploadRequest::where('id', $id)
                ->where('status', 'pending')
                ->first();

            if (!$uploadRequest) {
                return response()->json([
                    'error' => 'Upload request not found or already processed'
                ], 404);
            }

            $adminNotes = $request->input('admin_notes');

            if ($uploadRequest->approve($adminNotes)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Upload request approved successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to approve upload request'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to approve upload request', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to approve upload request'
            ], 500);
        }
    }

    /**
     * Reject an upload request (for admin)
     */
    public function reject($id, Request $request)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'error' => 'Only admins can reject upload requests'
                ], 403);
            }

            $uploadRequest = MusicUploadRequest::where('id', $id)
                ->where('status', 'pending')
                ->first();

            if (!$uploadRequest) {
                return response()->json([
                    'error' => 'Upload request not found or already processed'
                ], 404);
            }

            $adminNotes = $request->input('admin_notes');

            if ($uploadRequest->reject($adminNotes)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Upload request rejected successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to reject upload request'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to reject upload request', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to reject upload request'
            ], 500);
        }
    }

    /**
     * Get upload request details
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            $uploadRequest = MusicUploadRequest::with(['user', 'artist', 'songArtist', 'album'])
                ->find($id);

            if (!$uploadRequest) {
                return response()->json([
                    'error' => 'Upload request not found'
                ], 404);
            }

            // Check permissions
            if ($user->role !== 'admin' && $uploadRequest->user_id !== $user->id) {
                return response()->json([
                    'error' => 'Unauthorized access'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'request' => $uploadRequest
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch upload request details', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch upload request details'
            ], 500);
        }
    }
}
