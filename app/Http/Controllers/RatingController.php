<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rateable_id' => 'required|integer',
            'rateable_type' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $typeMap = [
            'song' => 'App\Models\Music',
            'artist' => 'App\Models\Artist',
            'album' => 'App\Models\Album'
        ];

        $rateableType = $typeMap[$validated['rateable_type']] ?? $validated['rateable_type'];

        $existingRating = Rating::where('user_id', auth()->id())
            ->where('rateable_id', $validated['rateable_id'])
            ->where(function ($query) use ($rateableType, $validated) {
                $query->where('rateable_type', $rateableType)
                    ->orWhere('rateable_type', $validated['rateable_type']);
            })
            ->first();

        if ($existingRating) {
            $existingRating->update(['rating' => $validated['rating']]);
            $rating = $existingRating;
        } else {
            $rating = Rating::create([
                'user_id' => auth()->id(),
                'rateable_id' => $validated['rateable_id'],
                'rateable_type' => $rateableType,
                'rating' => $validated['rating'],
            ]);
        }

        return response()->json([
            'message' => 'Rating created successfully',
            'rating' => $rating
        ], 201);
    }

    public function index(Request $request)
    {
        $validated = $request->validate(
            [
                'rateable_id' => 'required|integer',
                'rateable_type' => 'required|string',
            ]
        );

        $ratings = Rating::where('rateable_id', $validated['rateable_id'])
            ->where('rateable_type', $validated['rateable_type'])
            ->get();

        return response()->json(['ratings' => $ratings], 200);
    }

    /**
     * Get rating for a specific item (supports route parameters)
     */
    public function show($id, Request $request)
    {
        try {
            $type = $request->get('type', 'song');

            $typeMap = [
                'song' => 'App\Models\Music',
                'artist' => 'App\Models\Artist',
                'album' => 'App\Models\Album'
            ];

            $rateableType = $typeMap[$type] ?? 'App\Models\Music';
            $simpleType = $type;

            $userRating = null;
            if (auth()->check()) {
                $userRating = Rating::where('user_id', auth()->id())
                    ->where('rateable_id', $id)
                    ->where(function ($query) use ($rateableType, $simpleType) {
                        $query->where('rateable_type', $rateableType)
                            ->orWhere('rateable_type', $simpleType);
                    })
                    ->first();
            }

            $ratings = Rating::where('rateable_id', $id)
                ->where(function ($query) use ($rateableType, $simpleType) {
                    $query->where('rateable_type', $rateableType)
                        ->orWhere('rateable_type', $simpleType);
                })
                ->with('user:id,name,email')
                ->get();

            $averageRating = $ratings->avg('rating') ?? 0;
            $totalRatings = $ratings->count();

            // Calculate rating distribution
            $ratingDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $ratingDistribution[$i] = $ratings->where('rating', $i)->count();
            }

            return response()->json([
                'success' => true,
                'user_rating' => $userRating ? $userRating->rating : null,
                'average_rating' => round($averageRating, 2),
                'total_ratings' => $totalRatings,
                'rating_distribution' => $ratingDistribution,
                'ratings' => $ratings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'user' => [
                            'id' => $rating->user->id,
                            'name' => $rating->user->name,
                            'email' => $rating->user->email
                        ],
                        'created_at' => $rating->created_at,
                        'updated_at' => $rating->updated_at
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all ratings by the authenticated user
     */
    public function getUserRatings(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $ratings = Rating::where('user_id', $user->id)
                ->with(['rateable'])
                ->orderBy('created_at', 'desc')
                ->get();

            $groupedRatings = [
                'songs' => [],
                'artists' => [],
                'albums' => []
            ];

            foreach ($ratings as $rating) {
                $item = $rating->rateable;
                if (!$item) continue;

                $ratingData = [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'created_at' => $rating->created_at,
                    'updated_at' => $rating->updated_at
                ];

                if ($rating->rateable_type === 'App\Models\Music') {
                    $ratingData['song'] = [
                        'id' => $item->id,
                        'title' => $item->title,
                        'artist' => $item->artist ? $item->artist->artist_name : null,
                        'genre' => $item->genre,
                        'file_path' => $item->file_path,
                        'song_cover_path' => $item->song_cover_path
                    ];
                    $groupedRatings['songs'][] = $ratingData;
                } elseif ($rating->rateable_type === 'App\Models\Artist') {
                    $ratingData['artist'] = [
                        'id' => $item->id,
                        'artist_name' => $item->artist_name,
                        'bio' => $item->bio,
                        'profile_picture' => $item->profile_picture
                    ];
                    $groupedRatings['artists'][] = $ratingData;
                } elseif ($rating->rateable_type === 'App\Models\Album') {
                    $ratingData['album'] = [
                        'id' => $item->id,
                        'title' => $item->title,
                        'artist' => $item->artist ? $item->artist->artist_name : null,
                        'cover_image' => $item->cover_image
                    ];
                    $groupedRatings['albums'][] = $ratingData;
                }
            }

            return response()->json([
                'success' => true,
                'ratings' => $groupedRatings,
                'total_ratings' => $ratings->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ratings for items created by the authenticated artist
     */
    public function getArtistItemRatings(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist access required'
                ], 403);
            }

            $artist = \App\Models\Artist::where('user_id', $user->id)->first();
            if (!$artist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artist profile not found'
                ], 404);
            }

            // Get ratings for artist's songs
            $songRatings = Rating::where('rateable_type', 'App\Models\Music')
                ->whereIn('rateable_id', $artist->music()->pluck('id'))
                ->with(['rateable', 'user:id,name,email'])
                ->get();

            // Get ratings for the artist profile
            $artistRatings = Rating::where('rateable_type', 'App\Models\Artist')
                ->where('rateable_id', $artist->id)
                ->with(['user:id,name,email'])
                ->get();

            // Get ratings for artist's albums
            $albumRatings = Rating::where('rateable_type', 'App\Models\Album')
                ->whereIn('rateable_id', $artist->albums()->pluck('id'))
                ->with(['rateable', 'user:id,name,email'])
                ->get();

            $allRatings = $songRatings->merge($artistRatings)->merge($albumRatings);

            $groupedRatings = [
                'songs' => $songRatings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'user' => [
                            'id' => $rating->user->id,
                            'name' => $rating->user->name,
                            'email' => $rating->user->email
                        ],
                        'song' => [
                            'id' => $rating->rateable->id,
                            'title' => $rating->rateable->title,
                            'genre' => $rating->rateable->genre
                        ],
                        'created_at' => $rating->created_at
                    ];
                }),
                'artist_profile' => $artistRatings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'user' => [
                            'id' => $rating->user->id,
                            'name' => $rating->user->name,
                            'email' => $rating->user->email
                        ],
                        'created_at' => $rating->created_at
                    ];
                }),
                'albums' => $albumRatings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'user' => [
                            'id' => $rating->user->id,
                            'name' => $rating->user->name,
                            'email' => $rating->user->email
                        ],
                        'album' => [
                            'id' => $rating->rateable->id,
                            'title' => $rating->rateable->title
                        ],
                        'created_at' => $rating->created_at
                    ];
                })
            ];

            // Calculate summary statistics
            $summary = [
                'total_ratings' => $allRatings->count(),
                'average_rating' => round($allRatings->avg('rating'), 2),
                'rating_distribution' => []
            ];

            for ($i = 1; $i <= 5; $i++) {
                $summary['rating_distribution'][$i] = $allRatings->where('rating', $i)->count();
            }

            return response()->json([
                'success' => true,
                'ratings' => $groupedRatings,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch artist ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
