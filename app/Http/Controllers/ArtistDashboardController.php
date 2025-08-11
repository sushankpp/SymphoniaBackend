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

            $artist = $user->artist;

            if (!$artist) {
                $artist = \App\Models\Artist::create([
                    'user_id' => $user->id,
                    'artist_name' => $user->name,
                    'artist_image' => $user->profile_picture,
                ]);
            }

            $musicCount = \App\Models\Music::where('uploaded_by', $user->id)->count();
            $totalViews = \App\Models\Music::where('uploaded_by', $user->id)->sum('views') ?? 0;

            $songRatings = \App\Models\Music::where('uploaded_by', $user->id)->get();
            $songRatingsCount = $songRatings->sum(function ($music) {
                return $music->getAllRatingsCount();
            });
            $songAvgRating = $songRatings->avg(function ($music) {
                return $music->getAllRatingsAvg();
            });


            $artistRatingsCount = \App\Models\Rating::where('rateable_id', $artist->id)
                ->where(function ($query) {
                    $query->where('rateable_type', 'App\Models\Artist')
                        ->orWhere('rateable_type', 'artist');
                })
                ->count();
            $artistAvgRating = \App\Models\Rating::where('rateable_id', $artist->id)
                ->where(function ($query) {
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

            if ($request->has('artist_name')) {
                $artist->update(['artist_name' => $request->artist_name]);
            }

            if ($request->has('bio')) {
                $user->update(['bio' => $request->bio]);
            }

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
            $this->fixSongOwnership();

            $uploadedMusic = Music::where('uploaded_by', $artistId)
                ->with(['artist', 'ratings'])
                ->get();

            $uploadedMusic->each(function ($music) {
                $music->ratings_count = $music->getAllRatingsCount();
                $music->avg_rating = $music->getAllRatingsAvg();
                $music->rating_details = [
                    'count' => $music->getAllRatingsCount(),
                    'average' => round($music->getAllRatingsAvg(), 2)
                ];


                $ratings = $music->getAllRatings()->with('user')->get();
                $music->ratings = $ratings->map(function ($rating) {
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


            $totalTracks = $uploadedMusic->count();
            $totalViews = $uploadedMusic->sum('views');

            $songRatings = $uploadedMusic->sum('ratings_count');

            $artist = $user->artist;
            $artistRatings = 0;
            if ($artist) {
                $artistRatings = Rating::where('rateable_id', $artist->id)
                    ->where(function ($query) {
                        $query->where('rateable_type', 'App\Models\Artist')
                            ->orWhere('rateable_type', 'artist');
                    })
                    ->count();
            }

            $totalSongRatings = $songRatings;
            $totalArtistRatings = $artistRatings;
            $songAvgRating = $uploadedMusic->avg('avg_rating');

            $artistAvgRating = 0;
            if ($artist) {
                $artistAvgRating = Rating::where('rateable_id', $artist->id)
                    ->where(function ($query) {
                        $query->where('rateable_type', 'App\Models\Artist')
                            ->orWhere('rateable_type', 'artist');
                    })
                    ->avg('rating') ?? 0;
            }

            $recentPlays = RecentlyPlayed::whereIn('song_id', $uploadedMusic->pluck('id'))
                ->with(['user', 'song'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $recentRatings = Rating::whereIn('rateable_id', $uploadedMusic->pluck('id'))
                ->where(function ($query) {
                    $query->where('rateable_type', 'App\Models\Music')
                        ->orWhere('rateable_type', 'song');
                })
                ->with(['user'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $recentArtistRatings = collect();
            if ($artist) {
                $recentArtistRatings = Rating::where('rateable_id', $artist->id)
                    ->where(function ($query) {
                        $query->where('rateable_type', 'App\Models\Artist')
                            ->orWhere('rateable_type', 'artist');
                    })
                    ->with(['user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            }

            $recentActivity = collect();

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

            foreach ($recentRatings as $rating) {
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

            $recentActivity = $recentActivity->sortByDesc('created_at')->take(10)->values();

            $topRatedTracks = $uploadedMusic->sortByDesc(function ($music) {
                return $music->ratings->avg('rating') ?? 0;
            })->take(5)->values();

            $mostViewedTracks = $uploadedMusic->sortByDesc('views')->take(5);

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
                        ->where(function ($query) {
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
                'all_songs' => $uploadedMusic->map(function ($song) {
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


            if ($sortBy === 'views') {
                $query->orderBy('views', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            $music = $query->paginate($perPage);

            $music->getCollection()->each(function ($musicItem) {
                $musicItem->ratings_count = $musicItem->getAllRatingsCount();
                $musicItem->avg_rating = $musicItem->getAllRatingsAvg();
                $musicItem->upload_status = 'approved';
                $musicItem->request_id = null;

                $ratings = $musicItem->getAllRatings()->with('user')->get();
                $musicItem->ratings = $ratings->map(function ($rating) {
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


            if ($sortBy === 'rating') {
                $sortedCollection = $music->getCollection()->sortBy('avg_rating');
                if ($sortOrder === 'desc') {
                    $sortedCollection = $sortedCollection->reverse();
                }
                $music->setCollection($sortedCollection);
            }


            $music->getCollection()->transform(function ($song) {
                $song->song_cover_url = $song->song_cover_path ? asset('storage/' . $song->song_cover_path) : null;
                $song->file_url = $song->file_path ? asset('storage/' . $song->file_path) : null;
                $song->average_rating = round($song->avg_rating ?? 0, 2);


                $song->recent_plays = RecentlyPlayed::where('song_id', $song->id)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count();

                return $song;
            });


            $uploadRequests = \App\Models\MusicUploadRequest::where('user_id', $artistId)
                ->with(['songArtist', 'album'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    $music = new \stdClass();
                    $music->id = 'request_' . $request->id;
                    $music->title = $request->song_title;
                    $music->song_cover_path = $request->song_cover_path;
                    $music->file_path = $request->file_path;
                    $music->genre = $request->genre ?? '';
                    $music->description = $request->description ?? '';
                    $music->lyrics = $request->lyrics ?? '';
                    $music->release_date = $request->release_date;
                    $music->views = 0;
                    $music->uploaded_by = $request->user_id;
                    $music->created_at = $request->created_at;
                    $music->upload_status = $request->status;
                    $music->request_id = $request->id;
                    $music->admin_notes = $request->admin_notes;
                    $music->ratings_count = 0;
                    $music->avg_rating = 0;
                    $music->ratings = [];
                    $music->average_rating = 0;
                    $music->recent_plays = 0;
                    $music->song_cover_url = $request->song_cover_path ? asset('storage/' . $request->song_cover_path) : null;
                    $music->file_url = $request->file_path ? asset('storage/' . $request->file_path) : null;

                    if ($request->songArtist) {
                        $music->artist = $request->songArtist;
                    }

                    if ($request->album) {
                        $music->album = $request->album;
                    }

                    return $music;
                });

            $allMusic = $music->getCollection()->concat($uploadRequests)
                ->sortByDesc('created_at')
                ->values();

            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedMusic = $allMusic->slice($offset, $perPage);

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

            // Debug: Check if user is authenticated
            if (!$artistId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                    'debug' => [
                        'authenticated' => auth()->check(),
                        'user_id' => $artistId
                    ]
                ], 401);
            }

            $query = Music::where('uploaded_by', $artistId);

            // Debug: Check total songs for this user
            $totalSongsForUser = Music::where('uploaded_by', $artistId)->count();
            $totalSongsInDB = Music::count();

            $music = $query->paginate($perPage);

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
                    'current_page' => $music->currentPage(),
                    'total_songs_for_user' => $totalSongsForUser,
                    'total_songs_in_db' => $totalSongsInDB,
                    'authenticated' => auth()->check(),
                    'user_email' => auth()->user() ? auth()->user()->email : null
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

            $ratingsBreakdown = Rating::where('rateable_id', $songId)
                ->where('rateable_type', 'App\Models\Music')
                ->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->orderBy('rating', 'desc')
                ->get();

            $recentPlays = RecentlyPlayed::where('song_id', $songId)
                ->with(['user'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

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

            if ($song->file_path && Storage::disk('public')->exists($song->file_path)) {
                Storage::disk('public')->delete($song->file_path);
            }

            if ($song->song_cover_path && Storage::disk('public')->exists($song->song_cover_path)) {
                Storage::disk('public')->delete($song->song_cover_path);
            }

            Rating::where('rateable_id', $songId)
                ->where('rateable_type', 'App\Models\Music')
                ->delete();

            RecentlyPlayed::where('song_id', $songId)->delete();

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
                'title',
                'genre',
                'description',
                'lyrics',
                'release_date'
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

            $query = Music::where('uploaded_by', $artistId)
                ->with(['artist', 'ratings', 'album'])
                ->withCount(['ratings']);

            $query->withAvg('ratings', 'rating');

            $allMusic = $query->get();

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
                    'sample_music' => $allMusic->take(3)->map(function ($music) {
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
            $this->linkArtistsToUsers();

            $artists = \App\Models\Artist::with('user')->get();
            $artistUserMap = [];
            foreach ($artists as $artist) {
                if ($artist->user) {
                    $artistUserMap[$artist->artist_name] = $artist->user->id;
                }
            }

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
                $matchingUser = $users->first(function ($user) use ($artist) {
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

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset('storage/' . $path);
    }
}