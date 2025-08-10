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
                ->get();

            $averageRating = $ratings->avg('rating') ?? 0;
            $totalRatings = $ratings->count();

            return response()->json([
                'success' => true,
                'user_rating' => $userRating ? $userRating->rating : null,
                'average_rating' => round($averageRating, 2),
                'total_ratings' => $totalRatings,
                'ratings' => $ratings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
