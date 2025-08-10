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
            $songRatings = \App\Models\Music::where('uploaded_by', $user->id)->get();
            $songRatingsCount = $songRatings->sum(function($music) {
                return $music->getAllRatingsCount();
            });
            $songAvgRating = $songRatings->avg(function($music) {
                return $music->getAllRatingsAvg();
            });
            
            // Get artist ratings
            $artistRatingsCount = \App\Models\Rating::where('rateable_id', $artist->id)
                ->where(function($query) {
                    $query->where('rateable_type', 'App\Models\Artist')
                          ->orWhere('rateable_type', 'artist');
                })
                ->count();
            $artistAvgRating = \App\Models\Rating::where('rateable_id', $artist->id)
                ->where(function($query) {
                    $query->where('rateable_type', 'App\Models\Artist')
                          ->orWhere('rateable_type', 'artist');
                })
                ->avg('rating') ?? 0;

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
                        'song_ratings' => [
                            'count' => $songRatingsCount,
                            'average' => round($songAvgRating, 2)
                        ],
                        'artist_ratings' => [
                            'count' => $artistRatingsCount,
                            'average' => round($artistAvgRating, 2)
                        ],
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
            
            // Automatically fix song ownership if needed
            $this->fixSongOwnership();

            // Get artist's uploaded music
            $uploadedMusic = Music::where('uploaded_by', $artistId)
                                ->with(['artist', 'ratings'])
                                ->get();

            // Add individual song ratings to each song
            $uploadedMusic->each(function($music) {
                $music->ratings_count = $music->getAllRatingsCount();
                $music->avg_rating = $music->getAllRatingsAvg();
                $music->rating_details = [
                    'count' => $music->getAllRatingsCount(),
                    'average' => round($music->getAllRatingsAvg(), 2)
                ];
                
                // Load actual rating data
                $ratings = $music->getAllRatings()->with('user')->get();
                $music->ratings = $ratings->map(function($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'user' => $rating->user ? [
                            'id' => $rating->user->id,
                            'name' => $rating->user->name
                        ] : null,
                        'created_at' => $rating->created_at
                    ];
                });
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

            // Get recent activity (plays and ratings)
            $recentPlays = RecentlyPlayed::whereIn('song_id', $uploadedMusic->pluck('id'))
                                       ->with(['user', 'song'])
                                       ->orderBy('created_at', 'desc')
                                       ->limit(10)
                                       ->get();
            
            // Get recent ratings for the artist's songs
            $recentRatings = Rating::whereIn('rateable_id', $uploadedMusic->pluck('id'))
                                  ->where(function($query) {
                                      $query->where('rateable_type', 'App\Models\Music')
                                            ->orWhere('rateable_type', 'song');
                                  })
                                  ->with(['user'])
                                  ->orderBy('created_at', 'desc')
                                  ->limit(10)
                                  ->get();
            
            // Get recent artist ratings
            $recentArtistRatings = collect();
            if ($artist) {
                $recentArtistRatings = Rating::where('rateable_id', $artist->id)
                                            ->where(function($query) {
                                                $query->where('rateable_type', 'App\Models\Artist')
                                                      ->orWhere('rateable_type', 'artist');
                                            })
                                            ->with(['user'])
                                            ->orderBy('created_at', 'desc')
                                            ->limit(10)
                                            ->get();
            }
            
            // Combine and sort all recent activity
            $recentActivity = collect();
            
            // Add plays
            foreach ($recentPlays as $play) {
                $recentActivity->push([
                    'type' => 'play',
                    'id' => $play->id,
                    'user' => $play->user ? ['id' => $play->user->id, 'name' => $play->user->name] : null,
                    'song' => $play->song ? ['id' => $play->song->id, 'title' => $play->song->title] : null,
                    'created_at' => $play->created_at,
                    'action' => 'played'
                ]);
            }
            
            // Add song ratings
            foreach ($recentRatings as $rating) {
                // Get song information manually
                $song = Music::find($rating->rateable_id);
                $recentActivity->push([
                    'type' => 'rating',
                    'id' => $rating->id,
                    'user' => $rating->user ? ['id' => $rating->user->id, 'name' => $rating->user->name] : null,
                    'song' => $song ? ['id' => $song->id, 'title' => $song->title] : null,
                    'rating' => $rating->rating,
                    'created_at' => $rating->created_at,
                    'action' => 'rated'
                ]);
            }
            
            // Add artist ratings
            foreach ($recentArtistRatings as $rating) {
                $recentActivity->push([
                    'type' => 'artist_rating',
                    'id' => $rating->id,
                    'user' => $rating->user ? ['id' => $rating->user->id, 'name' => $rating->user->name] : null,
                    'rating' => $rating->rating,
                    'created_at' => $rating->created_at,
                    'action' => 'rated artist'
                ]);
            }
            
            // Sort by created_at and take the most recent 10
            $recentActivity = $recentActivity->sortByDesc('created_at')->take(10)->values();

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
                
                $monthlyStats[] = [
                    'month' => $date->format('M Y'),
                    'views' => $monthViews,
                    'song_ratings' => $monthSongRatings,
                    'artist_ratings' => $monthArtistRatings,
                ];
            }

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_tracks' => $totalTracks,
                    'total_views' => $totalViews,
                    'song_ratings' => [
                        'count' => $totalSongRatings,
                        'average' => round($songAvgRating, 2)
                    ],
                    'artist_ratings' => [
                        'count' => $totalArtistRatings,
                        'average' => round($artistAvgRating, 2)
                    ],
                    'monthly_stats' => $monthlyStats,
                ],
                'recent_activity' => $recentActivity,
                'top_rated_tracks' => $topRatedTracks,
                'most_viewed_tracks' => $mostViewedTracks,
                'all_songs' => $uploadedMusic->map(function($song) {
                    return [
                        'id' => $song->id,
                        'title' => $song->title,
                        'views' => $song->views,
                        'ratings' => $song->rating_details,
                        'artist' => $song->artist ? [
                            'id' => $song->artist->id,
                            'name' => $song->artist->artist_name
                        ] : null
                    ];
                })
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
                $musicItem->upload_status = 'approved'; // Mark as approved
                $musicItem->request_id = null; // No request ID for approved music
                
                // Load actual rating data
                $ratings = $musicItem->getAllRatings()->with('user')->get();
                $musicItem->ratings = $ratings->map(function($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'user' => $rating->user ? [
                            'id' => $rating->user->id,
                            'name' => $rating->user->name
                        ] : null,
                        'created_at' => $rating->created_at
                    ];
                });
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

            // Get upload requests for this artist
            $uploadRequests = \App\Models\MusicUploadRequest::where('user_id', $artistId)
                ->with(['songArtist', 'album'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    // Create a music-like object from the request
                    $music = new \stdClass();
                    $music->id = 'request_' . $request->id; // Unique ID for requests
                    $music->title = $request->song_title;
                    $music->song_cover_path = $request->song_cover_path;
                    $music->file_path = $request->file_path;
                    $music->genre = $request->genre ?? '';
                    $music->description = $request->description ?? '';
                    $music->lyrics = $request->lyrics ?? '';
                    $music->release_date = $request->release_date;
                    $music->views = 0; // No views for pending requests
                    $music->uploaded_by = $request->user_id;
                    $music->created_at = $request->created_at;
                    $music->upload_status = $request->status; // pending, approved, rejected
                    $music->request_id = $request->id;
                    $music->admin_notes = $request->admin_notes;
                    $music->ratings_count = 0; // No ratings for requests
                    $music->avg_rating = 0;
                    $music->ratings = [];
                    $music->average_rating = 0;
                    $music->recent_plays = 0;
                    $music->song_cover_url = $request->song_cover_path ? asset('storage/' . $request->song_cover_path) : null;
                    $music->file_url = $request->file_path ? asset('storage/' . $request->file_path) : null;

                    // Artist information
                    if ($request->songArtist) {
                        $music->artist = $request->songArtist;
                    }

                    // Album information
                    if ($request->album) {
                        $music->album = $request->album;
                    }

                    return $music;
                });

            // Combine approved music and upload requests
            $allMusic = $music->getCollection()->concat($uploadRequests)
                ->sortByDesc('created_at')
                ->values();

            // Create a new paginator with the combined data
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedMusic = $allMusic->slice($offset, $perPage);

            // Create pagination metadata
            $pagination = [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $allMusic->count(),
                'last_page' => ceil($allMusic->count() / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $allMusic->count()),
            ];

            return response()->json([
                'success' => true,
                'music' => [
                    'data' => $paginatedMusic,
                    'current_page' => $pagination['current_page'],
                    'per_page' => $pagination['per_page'],
                    'total' => $pagination['total'],
                    'last_page' => $pagination['last_page'],
                    'from' => $pagination['from'],
                    'to' => $pagination['to'],
                ]
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
    /**
     * Automatically fix song ownership by assigning songs to their correct artists
     */
    private function fixSongOwnership()
    {
        try {
            // First, link artists to their corresponding users if not already linked
            $this->linkArtistsToUsers();
            
            // Get all artists with their user IDs
            $artists = \App\Models\Artist::with('user')->get();
            $artistUserMap = [];
            foreach ($artists as $artist) {
                if ($artist->user) {
                    $artistUserMap[$artist->artist_name] = $artist->user->id;
                }
            }
            
            // Fix songs that are assigned to wrong users
            $songs = \App\Models\Music::with('artist')->get();
            foreach ($songs as $song) {
                if ($song->artist) {
                    $artistName = $song->artist->artist_name;
                    $correctUserId = $artistUserMap[$artistName] ?? null;
                    
                    if ($correctUserId && $song->uploaded_by != $correctUserId) {
                        $song->update(['uploaded_by' => $correctUserId]);
                        \Log::info("Fixed song ownership: {$song->title} -> User {$correctUserId}");
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error fixing song ownership: ' . $e->getMessage());
        }
    }
    
    /**
     * Link artists to their corresponding users
     */
    private function linkArtistsToUsers()
    {
        try {
            $users = \App\Models\User::where('role', 'artist')->get();
            $artists = \App\Models\Artist::all();
            
            foreach ($artists as $artist) {
                // Find matching user by name
                $matchingUser = $users->first(function($user) use ($artist) {
                    return strtolower($user->name) === strtolower($artist->artist_name);
                });
                
                if ($matchingUser && !$artist->user_id) {
                    $artist->update(['user_id' => $matchingUser->id]);
                    \Log::info("Linked artist {$artist->artist_name} to user {$matchingUser->name}");
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error linking artists to users: ' . $e->getMessage());
        }
    }

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