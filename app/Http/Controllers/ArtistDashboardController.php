<?php

namespace App\Http\Controllers;

use App\Models\Music;
use App\Models\Rating;
use App\Models\RecentlyPlayed;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArtistDashboardController extends Controller
{
    /**
     * Get artist profile information
     */
    public function getProfile()
    {
        try {
            $user = auth()->user();
            
            // Get or create artist record
            $artist = $user->artist;
            
            if (!$artist) {
                // Create artist record if it doesn't exist (for users who became artists)
                $artist = \App\Models\Artist::create([
                    'user_id' => $user->id,
                    'artist_name' => $user->name,
                    'artist_image' => $user->profile_picture,
                ]);
            }

            // Get artist statistics
            $musicCount = \App\Models\Music::where('uploaded_by', $user->id)->count();
            $totalViews = \App\Models\Music::where('uploaded_by', $user->id)->sum('views') ?? 0;
            
            // Get song ratings
            $songAvgRating = \App\Models\Rating::where('rateable_type', 'App\Models\Music')
                                             ->whereIn('rateable_id', \App\Models\Music::where('uploaded_by', $user->id)->pluck('id'))
                                             ->avg('rating') ?? 0;
            
            // Get artist ratings
            $artistAvgRating = \App\Models\Rating::where('rateable_id', $artist->id)
                ->where(function($query) {
                    $query->where('rateable_type', 'App\Models\Artist')
                          ->orWhere('rateable_type', 'artist');
                })
                ->avg('rating') ?? 0;
            
            // Combine averages (simple average for now)
            $avgRating = ($songAvgRating + $artistAvgRating) / 2;
            if ($songAvgRating == 0 && $artistAvgRating == 0) {
                $avgRating = 0;
            }

            return response()->json([
                'success' => true,
                'artist' => [
                    'id' => $artist->id,
                    'user_id' => $user->id,
                    'artist_name' => $artist->artist_name,
                    'artist_image' => $this->generateAssetUrl($artist->artist_image),
                    'email' => $user->email,
                    'bio' => $user->bio,
                    'created_at' => $user->created_at,
                    'stats' => [
                        'music_count' => $musicCount,
                        'total_views' => $totalViews,
                        'average_rating' => round($avgRating, 2),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch artist profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update artist profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = auth()->user();
            $artist = $user->artist;

            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist record not found'
                ], 404);
            }

            $request->validate([
                'artist_name' => 'sometimes|string|max:255',
                'bio' => 'sometimes|string|max:1000',
                'artist_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Update artist record
            if ($request->has('artist_name')) {
                $artist->update(['artist_name' => $request->artist_name]);
            }

            // Update user bio
            if ($request->has('bio')) {
                $user->update(['bio' => $request->bio]);
            }

            // Handle artist image upload
            if ($request->hasFile('artist_image')) {
                $image = $request->file('artist_image');
                $imageName = 'artist_' . $user->id . '_' . time() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('images/artists', $imageName, 'public');
                $artist->update(['artist_image' => $imagePath]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Artist profile updated successfully',
                'artist' => $artist->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update artist profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get artist dashboard stats
     */
    public function getDashboardStats()
    {
        try {
            $artistId = auth()->id();
            $user = auth()->user();

            // Get artist's uploaded music
            $uploadedMusic = Music::where('uploaded_by', $artistId)
                                ->with(['artist', 'ratings'])
                                ->get();

            // Add custom rating counts and averages
            $uploadedMusic->each(function($music) {
                $music->ratings_count = $music->getAllRatingsCount();
                $music->avg_rating = $music->getAllRatingsAvg();
            });

            // Calculate stats
            $totalTracks = $uploadedMusic->count();
            $totalViews = $uploadedMusic->sum('views');
            
            // Count song ratings using custom method
            $songRatings = $uploadedMusic->sum('ratings_count');
            
            // Count artist ratings (for the current artist)
            $artist = $user->artist;
            $artistRatings = 0;
            if ($artist) {
                $artistRatings = Rating::where('rateable_id', $artist->id)
                    ->where(function($query) {
                        $query->where('rateable_type', 'App\Models\Artist')
                              ->orWhere('rateable_type', 'artist');
                    })
                    ->count();
            }
            
            // Keep song and artist ratings separate
            $totalSongRatings = $songRatings;
            $totalArtistRatings = $artistRatings;
            
            // Calculate separate averages
            $songAvgRating = $uploadedMusic->avg('avg_rating');
            
            $artistAvgRating = 0;
            if ($artist) {
                $artistAvgRating = Rating::where('rateable_id', $artist->id)
                    ->where(function($query) {
                        $query->where('rateable_type', 'App\Models\Artist')
                              ->orWhere('rateable_type', 'artist');
                    })
                    ->avg('rating') ?? 0;
            }

            // Get recent activity
            $recentPlays = RecentlyPlayed::whereIn('song_id', $uploadedMusic->pluck('id'))
                                       ->with(['user', 'song'])
                                       ->orderBy('created_at', 'desc')
                                       ->limit(10)
                                       ->get();

            // Get top rated tracks
            $topRatedTracks = $uploadedMusic->sortByDesc(function ($music) {
                return $music->ratings->avg('rating') ?? 0;
            })->take(5)->values();

            // Get most viewed tracks
            $mostViewedTracks = $uploadedMusic->sortByDesc('views')->take(5);

            // Monthly stats (last 6 months)
            $monthlyStats = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthStart = $date->copy()->startOfMonth();
                $monthEnd = $date->copy()->endOfMonth();

                $monthViews = RecentlyPlayed::whereIn('song_id', $uploadedMusic->pluck('id'))
                                          ->whereBetween('created_at', [$monthStart, $monthEnd])
                                          ->count();

                $monthSongRatings = Rating::whereIn('rateable_id', $uploadedMusic->pluck('id'))
                                        ->where('rateable_type', 'App\Models\Music')
                                        ->whereBetween('created_at', [$monthStart, $monthEnd])
                                        ->count();
                
                $monthArtistRatings = 0;
                if ($artist) {
                    $monthArtistRatings = Rating::where('rateable_id', $artist->id)
                        ->where(function($query) {
                            $query->where('rateable_type', 'App\Models\Artist')
                                  ->orWhere('rateable_type', 'artist');
                        })
                        ->whereBetween('created_at', [$monthStart, $monthEnd])
                        ->count();
                }
                
                $monthRatings = $monthSongRatings + $monthArtistRatings;

                $monthlyStats[] = [
                    'month' => $date->format('M Y'),
                    'views' => $monthViews,
                    'ratings' => $monthRatings,
                ];
            }

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_tracks' => $totalTracks,
                    'total_views' => $totalViews,
                    'total_ratings' => $totalRatings,
                    'average_rating' => round($averageRating, 2),
                    'monthly_stats' => $monthlyStats,
                ],
                'recent_activity' => $recentPlays,
                'top_rated_tracks' => $topRatedTracks,
                'most_viewed_tracks' => $mostViewedTracks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get artist's uploaded music with detailed stats
     */
    public function getMyMusic(Request $request)
    {
        try {
            $artistId = auth()->id();
            $perPage = $request->get('per_page', 15);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $query = Music::where('uploaded_by', $artistId)
                         ->with(['artist', 'ratings', 'album']);

            // Apply sorting
            if ($sortBy === 'views') {
                $query->orderBy('views', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            $music = $query->paginate($perPage);

            // Add custom rating counts and averages
            $music->getCollection()->each(function($musicItem) {
                $musicItem->ratings_count = $musicItem->getAllRatingsCount();
                $musicItem->avg_rating = $musicItem->getAllRatingsAvg();
            });

            // Sort by rating if requested (after getting the data)
            if ($sortBy === 'rating') {
                $sortedCollection = $music->getCollection()->sortBy('avg_rating');
                if ($sortOrder === 'desc') {
                    $sortedCollection = $sortedCollection->reverse();
                }
                $music->setCollection($sortedCollection);
            }

            // Transform data to include additional stats
            $music->getCollection()->transform(function ($song) {
                $song->song_cover_url = $song->song_cover_path ? asset('storage/' . $song->song_cover_path) : null;
                $song->file_url = $song->file_path ? asset('storage/' . $song->file_path) : null;
                $song->average_rating = round($song->avg_rating ?? 0, 2);

                // Get recent plays count (last 30 days)
                $song->recent_plays = RecentlyPlayed::where('song_id', $song->id)
                                                  ->where('created_at', '>=', now()->subDays(30))
                                                  ->count();

                return $song;
            });

            return response()->json([
                'success' => true,
                'music' => $music,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch music',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get artist's uploaded music with detailed stats (Simplified version for testing)
     */
    public function getMyMusicSimple(Request $request)
    {
        try {
            $artistId = auth()->id();
            $perPage = $request->get('per_page', 15);
            
            // Simple query without complex relationships
            $query = Music::where('uploaded_by', $artistId);
            
            $music = $query->paginate($perPage);
            
            // Transform data to include URLs
            $music->getCollection()->transform(function ($song) {
                $song->song_cover_url = $song->song_cover_path ? asset('storage/' . $song->song_cover_path) : null;
                $song->file_url = $song->file_path ? asset('storage/' . $song->file_path) : null;
                return $song;
            });
            
            return response()->json([
                'success' => true,
                'music' => $music,
                'debug' => [
                    'artist_id' => $artistId,
                    'total_found' => $music->total(),
                    'current_page' => $music->currentPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch music',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get detailed stats for a specific song
     */
    public function getSongStats($songId)
    {
        try {
            $artistId = auth()->id();
            
            $song = Music::where('id', $songId)
                        ->where('uploaded_by', $artistId)
                        ->with(['artist', 'album'])
                        ->withCount(['ratings'])
                        ->withAvg('ratings', 'rating')
                        ->first();

            if (!$song) {
                return response()->json([
                    'success' => false,
                    'message' => 'Song not found or you do not have permission to view it'
                ], 404);
            }

            // Get ratings breakdown
            $ratingsBreakdown = Rating::where('rateable_id', $songId)
                                    ->where('rateable_type', 'App\Models\Music')
                                    ->selectRaw('rating, COUNT(*) as count')
                                    ->groupBy('rating')
                                    ->orderBy('rating', 'desc')
                                    ->get();

            // Get recent plays
            $recentPlays = RecentlyPlayed::where('song_id', $songId)
                                       ->with(['user'])
                                       ->orderBy('created_at', 'desc')
                                       ->limit(20)
                                       ->get();

            // Get daily plays for last 30 days
            $dailyPlays = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $plays = RecentlyPlayed::where('song_id', $songId)
                                     ->whereDate('created_at', $date)
                                     ->count();
                $dailyPlays[] = [
                    'date' => $date->format('Y-m-d'),
                    'plays' => $plays,
                ];
            }

            // Get all ratings with user info
            $allRatings = Rating::where('rateable_id', $songId)
                              ->where('rateable_type', 'App\Models\Music')
                              ->with(['user'])
                              ->orderBy('created_at', 'desc')
                              ->limit(50)
                              ->get();

            $song->song_cover_url = $song->song_cover_path ? asset('storage/' . $song->song_cover_path) : null;
            $song->file_url = $song->file_path ? asset('storage/' . $song->file_path) : null;

            return response()->json([
                'success' => true,
                'song' => $song,
                'ratings_breakdown' => $ratingsBreakdown,
                'recent_plays' => $recentPlays,
                'daily_plays' => $dailyPlays,
                'all_ratings' => $allRatings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch song stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete artist's own music
     */
    public function deleteMusic($songId)
    {
        try {
            $artistId = auth()->id();
            
            $song = Music::where('id', $songId)
                        ->where('uploaded_by', $artistId)
                        ->first();

            if (!$song) {
                return response()->json([
                    'success' => false,
                    'message' => 'Song not found or you do not have permission to delete it'
                ], 404);
            }

            // Delete files from storage
            if ($song->file_path && Storage::disk('public')->exists($song->file_path)) {
                Storage::disk('public')->delete($song->file_path);
            }

            if ($song->song_cover_path && Storage::disk('public')->exists($song->song_cover_path)) {
                Storage::disk('public')->delete($song->song_cover_path);
            }

            // Delete related records
            Rating::where('rateable_id', $songId)
                  ->where('rateable_type', 'App\Models\Music')
                  ->delete();

            RecentlyPlayed::where('song_id', $songId)->delete();

            // Delete the song record
            $song->delete();

            return response()->json([
                'success' => true,
                'message' => 'Song deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete song',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update song details
     */
    public function updateMusic(Request $request, $songId)
    {
        try {
            $artistId = auth()->id();
            
            $song = Music::where('id', $songId)
                        ->where('uploaded_by', $artistId)
                        ->first();

            if (!$song) {
                return response()->json([
                    'success' => false,
                    'message' => 'Song not found or you do not have permission to edit it'
                ], 404);
            }

            $request->validate([
                'title' => 'sometimes|string|max:255',
                'genre' => 'sometimes|string|max:100',
                'description' => 'sometimes|string|max:1000',
                'lyrics' => 'sometimes|string',
                'release_date' => 'sometimes|date',
            ]);

            $song->update($request->only([
                'title', 'genre', 'description', 'lyrics', 'release_date'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Song updated successfully',
                'song' => $song
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update song',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug method to check authentication and music ownership
     */
    public function debugMusicOwnership()
    {
        try {
            $artistId = auth()->id();
            $user = auth()->user();
            
            // Test the exact same query as getMyMusic
            $query = Music::where('uploaded_by', $artistId)
                         ->with(['artist', 'ratings', 'album'])
                         ->withCount(['ratings']);
            
            $query->withAvg('ratings', 'rating');
            
            // Test without pagination first
            $allMusic = $query->get();
            
            // Test with pagination
            $paginatedMusic = $query->paginate(15);
            
            return response()->json([
                'success' => true,
                'debug_info' => [
                    'authenticated_user_id' => $artistId,
                    'user_name' => $user ? $user->name : 'Not found',
                    'user_role' => $user ? $user->role : 'Not found',
                    'all_music_count' => $allMusic->count(),
                    'paginated_music_count' => $paginatedMusic->count(),
                    'paginated_total' => $paginatedMusic->total(),
                    'sample_music' => $allMusic->take(3)->map(function($music) {
                        return [
                            'id' => $music->id,
                            'title' => $music->title,
                            'uploaded_by' => $music->uploaded_by,
                            'artist_id' => $music->artist_id,
                            'album_id' => $music->album_id
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        
        // Check if it's already a full URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        
        // Generate asset URL for relative path
        return asset('storage/' . $path);
    }
}