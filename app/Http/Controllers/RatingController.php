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

        $rating = Rating::create($validated);

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
