<?php

namespace App\Http\Controllers;

use App\Models\Music;
use Illuminate\Http\Request;

class MusicController extends Controller
{
    public function __invoke(Request $request)
    {
        $music = Music::with('artist')->get()->map(function ($item) {
            // Process song cover path
            $item->song_cover = asset('storage/' . $item->song_cover_path);

            // Process file path
            $item->file_path = asset('storage/' . $item->file_path);

            // Process artist image
            if ($item->artist && $item->artist->artist_image) {
                if (str_starts_with($item->artist->artist_image, 'http://')) {
                } else if (str_starts_with($item->artist->artist_image, 'storage/')) {
                    $path = str_replace('storage/', '', $item->artist->artist_image);
                    $item->artist->artist_image = asset('storage/' . $path);
                } else {
                    $item->artist->artist_image = asset('storage/' . $item->artist->artist_image);
                }
            }

            if ($item->album) {
                $item->album_title = $item->album->title;
                $item->album_cover = asset('storage/' . $item->album->cover);
            };

            return $item;
        });

        return response()->json($music);
    }
}
