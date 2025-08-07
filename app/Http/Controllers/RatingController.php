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

        // Check if user already rated this item
        $existingRating = Rating::where('user_id', auth()->id())
            ->where('rateable_id', $validated['rateable_id'])
            ->where('rateable_type', $validated['rateable_type'])
            ->first();

        if ($existingRating) {
            // Update existing rating
            $existingRating->update(['rating' => $validated['rating']]);
            $rating = $existingRating;
        } else {
            // Create new rating
            $rating = Rating::create([
                'user_id' => auth()->id(),
                'rateable_id' => $validated['rateable_id'],
                'rateable_type' => $validated['rateable_type'],
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
        $validated = $request->validate([
                'rateable_id' => 'required|integer',
                'rateable_type' => 'required|string',
            ]
        );

        $ratings = Rating::where('rateable_id', $validated['rateable_id'])
            ->where('rateable_type', $validated['rateable_type'])
            ->get();

        return response()->json(['ratings' => $ratings], 200);
    }
}
