<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Music;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller
{
    /**
     * Get all albums (public)
     */
    public function index()
    {
        try {
            $albums = Album::with(['artists', 'songs'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            // Add cover image URLs
            $albums->getCollection()->transform(function ($album) {
                if ($album->cover_image_path) {
                    $album->cover_image_url = asset('storage/' . $album->cover_image_path);
                }
                return $album;
            });

            return response()->json([
                'success' => true,
                'albums' => $albums
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch albums',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get albums by artist
     */
    public function getAlbumsByArtist($artistId)
    {
        try {
            $albums = Album::where('artist_id', $artistId)
                ->with(['songs'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Add cover image URLs
            $albums->transform(function ($album) {
                if ($album->cover_image_path) {
                    $album->cover_image_url = asset('storage/' . $album->cover_image_path);
                }
                return $album;
            });

            return response()->json([
                'success' => true,
                'albums' => $albums
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch artist albums',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get artist's own albums (authenticated artist)
     */
    public function getMyAlbums()
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only artists can access their albums'
                ], 403);
            }

            $artist = Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist profile not found'
                ], 404);
            }

            $albums = Album::where('artist_id', $artist->id)
                ->with(['songs'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Add cover image URLs and song details
            $albums->transform(function ($album) {
                if ($album->cover_image_path) {
                    $album->cover_image_url = asset('storage/' . $album->cover_image_path);
                }
                
                // Add song URLs
                $album->songs->transform(function ($song) {
                    if ($song->file_path) {
                        $song->file_url = asset('storage/' . $song->file_path);
                    }
                    if ($song->song_cover_path) {
                        $song->song_cover_url = asset('storage/' . $song->song_cover_path);
                    }
                    return $song;
                });
                
                return $album;
            });

            return response()->json([
                'success' => true,
                'albums' => $albums
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your albums',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new album (artist only)
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only artists can create albums'
                ], 403);
            }

            // Log the incoming request data for debugging
            \Log::info('Album creation request', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'request_data' => $request->all(),
                'request_input' => $request->input(),
                'request_post' => $request->post(),
                'request_json' => $request->json(),
                'files' => $request->hasFile('cover_image') ? 'cover_image_present' : 'no_cover_image',
                'headers' => $request->headers->all()
            ]);

            // Handle song_ids that might come as string or array
            $songIds = $request->input('song_ids');
            if (is_string($songIds)) {
                // Try to decode JSON if it's a JSON string
                if (str_starts_with($songIds, '[') && str_ends_with($songIds, ']')) {
                    $songIds = json_decode($songIds, true);
                } else {
                    // Single ID as string
                    $songIds = [$songIds];
                }
            }
            
            // Replace the song_ids in request for validation
            $request->merge(['song_ids' => $songIds]);

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'release_date' => 'required|date',
                'cover_image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
                'song_ids' => 'nullable|array',
                'song_ids.*' => 'exists:music,id'
            ]);

            $artist = Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist profile not found'
                ], 404);
            }

            // Store cover image if provided
            $coverPath = null;
            if ($request->hasFile('cover_image')) {
                $coverPath = $request->file('cover_image')->store('albums_cover', 'public');
            }

            $album = Album::create([
                'title' => $validated['title'],
                'cover_image_path' => $coverPath,
                'artist_id' => $artist->id,
                'user_id' => $user->id,
                'release_date' => $validated['release_date'],
            ]);

            // Add songs to album if provided (AUTOMATIC)
            $addedSongs = [];
            if ($request->has('song_ids') && is_array($request->song_ids)) {
                $songIds = $request->song_ids;
                
                // Automatically verify songs belong to this artist
                $artistSongs = Music::whereIn('id', $songIds)
                    ->where('uploaded_by', $user->id)
                    ->pluck('id')
                    ->toArray();
                
                if (!empty($artistSongs)) {
                    // Automatically update songs with album_id
                    Music::whereIn('id', $artistSongs)->update(['album_id' => $album->id]);
                    $addedSongs = $artistSongs;
                    
                    \Log::info('Songs automatically added to album', [
                        'album_id' => $album->id,
                        'album_title' => $album->title,
                        'song_ids' => $artistSongs,
                        'artist_id' => $artist->id,
                        'user_id' => $user->id
                    ]);
                }
            }

            if ($coverPath) {
                $album->cover_image_url = asset('storage/' . $coverPath);
            }

            // Load songs for response
            $album->load('songs');

            return response()->json([
                'success' => true,
                'message' => 'Album created successfully' . (!empty($addedSongs) ? ' with ' . count($addedSongs) . ' songs' : ''),
                'album' => $album,
                'added_songs_count' => count($addedSongs)
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Album creation validation failed', [
                'user_id' => auth()->id(),
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Album creation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create album',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add songs to album (artist only)
     */
    public function addSongs(Request $request, $albumId)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only artists can add songs to albums'
                ], 403);
            }

            $validated = $request->validate([
                'song_ids' => 'required|array',
                'song_ids.*' => 'exists:music,id'
            ]);

            $artist = Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist profile not found'
                ], 404);
            }

            $album = Album::where('id', $albumId)
                ->where('artist_id', $artist->id)
                ->first();

            if (!$album) {
                return response()->json([
                    'success' => false,
                    'message' => 'Album not found or you do not have permission'
                ], 404);
            }

            // Update songs to belong to this album
            $updatedCount = Music::whereIn('id', $validated['song_ids'])
                ->where('uploaded_by', $user->id)
                ->update(['album_id' => $albumId]);

            return response()->json([
                'success' => true,
                'message' => "Added {$updatedCount} songs to album",
                'album' => $album->load('songs')
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add songs to album',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove songs from album (artist only)
     */
    public function removeSongs(Request $request, $albumId)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only artists can remove songs from albums'
                ], 403);
            }

            $validated = $request->validate([
                'song_ids' => 'required|array',
                'song_ids.*' => 'exists:music,id'
            ]);

            $artist = Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist profile not found'
                ], 404);
            }

            $album = Album::where('id', $albumId)
                ->where('artist_id', $artist->id)
                ->first();

            if (!$album) {
                return response()->json([
                    'success' => false,
                    'message' => 'Album not found or you do not have permission'
                ], 404);
            }

            // Remove songs from album (AUTOMATIC)
            $updatedCount = Music::whereIn('id', $validated['song_ids'])
                ->where('uploaded_by', $user->id)
                ->where('album_id', $albumId)
                ->update(['album_id' => null]);

            // Automatically check if album is now empty
            $remainingSongs = Music::where('album_id', $albumId)->count();
            
            if ($remainingSongs === 0) {
                // Automatically delete empty album
                $album->delete();
                
                \Log::info('Empty album automatically deleted', [
                    'album_id' => $albumId,
                    'album_title' => $album->title,
                    'artist_id' => $artist->id,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => "Removed {$updatedCount} songs from album. Album was empty and has been automatically deleted.",
                    'album_deleted' => true
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Removed {$updatedCount} songs from album",
                'album' => $album->load('songs'),
                'remaining_songs' => $remainingSongs
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove songs from album',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update album (artist only)
     */
    public function update(Request $request, $albumId)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only artists can update albums'
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'release_date' => 'sometimes|required|date',
                'cover_image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $artist = Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist profile not found'
                ], 404);
            }

            $album = Album::where('id', $albumId)
                ->where('artist_id', $artist->id)
                ->first();

            if (!$album) {
                return response()->json([
                    'success' => false,
                    'message' => 'Album not found or you do not have permission'
                ], 404);
            }

            // Handle cover image update
            if ($request->hasFile('cover_image')) {
                // Delete old cover image if exists
                if ($album->cover_image_path && Storage::disk('public')->exists($album->cover_image_path)) {
                    Storage::disk('public')->delete($album->cover_image_path);
                }
                
                $coverPath = $request->file('cover_image')->store('albums_cover', 'public');
                $validated['cover_image_path'] = $coverPath;
            }

            $album->update($validated);

            if (isset($validated['cover_image_path'])) {
                $album->cover_image_url = asset('storage/' . $validated['cover_image_path']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Album updated successfully',
                'album' => $album->load('songs')
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update album',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete album (artist only)
     */
    public function destroy($albumId)
    {
        try {
            $user = auth()->user();
            
            if ($user->role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only artists can delete albums'
                ], 403);
            }

            $artist = Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist profile not found'
                ], 404);
            }

            $album = Album::where('id', $albumId)
                ->where('artist_id', $artist->id)
                ->first();

            if (!$album) {
                return response()->json([
                    'success' => false,
                    'message' => 'Album not found or you do not have permission'
                ], 404);
            }

            // Automatically remove album_id from all songs in this album
            $songsUpdated = Music::where('album_id', $albumId)->update(['album_id' => null]);

            // Automatically delete cover image if exists
            if ($album->cover_image_path && Storage::disk('public')->exists($album->cover_image_path)) {
                Storage::disk('public')->delete($album->cover_image_path);
            }

            $album->delete();

            \Log::info('Album automatically deleted with cleanup', [
                'album_id' => $albumId,
                'album_title' => $album->title,
                'songs_updated' => $songsUpdated,
                'artist_id' => $artist->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Album deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete album',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get album details
     */
    public function show($albumId)
    {
        try {
            $album = Album::with(['artists', 'songs'])
                ->find($albumId);

            if (!$album) {
                return response()->json([
                    'success' => false,
                    'message' => 'Album not found'
                ], 404);
            }

            // Add URLs
            if ($album->cover_image_path) {
                $album->cover_image_url = asset('storage/' . $album->cover_image_path);
            }

            $album->songs->transform(function ($song) {
                if ($song->file_path) {
                    $song->file_url = asset('storage/' . $song->file_path);
                }
                if ($song->song_cover_path) {
                    $song->song_cover_url = asset('storage/' . $song->song_cover_path);
                }
                return $song;
            });

            return response()->json([
                'success' => true,
                'album' => $album
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch album details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
