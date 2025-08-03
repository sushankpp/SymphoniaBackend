<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Debug PHP settings
Route::get('/debug-upload-settings', function () {
    return response()->json([
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
    ]);
});

Route::get('/music', \App\Http\Controllers\MusicController::class);
Route::post('/upload-music', [\App\Http\Controllers\MusicController::class, 'uploadMusic']);
Route::get('/uploaded-music', [\App\Http\Controllers\MusicController::class, 'getUploadedMusic']);

Route::get('/artists', [\App\Http\Controllers\ArtistController::class, 'index']);
Route::get('/artists/{artistId}/songs', [\App\Http\Controllers\ArtistController::class, 'getSongs']);

//recently played routes
Route::post('/recently-played', [\App\Http\Controllers\RecentlyPlayedController::class, 'store']);
Route::get('/recently-played', [\App\Http\Controllers\RecentlyPlayedController::class, 'index']);

//albums
Route::get('/albums', [\App\Http\Controllers\AlbumController::class, 'index']);
Route::post('/albums', [\App\Http\Controllers\AlbumController::class, 'store']);
Route::get('/artists/{artistId}/albums', [\App\Http\Controllers\AlbumController::class, 'getAlbumsByArtist']);

// Playlists
Route::get('/playlists', [\App\Http\Controllers\PlaylistController::class, 'index']);
Route::post('/playlists', [\App\Http\Controllers\PlaylistController::class, 'store']);
Route::post('/playlists/{playlist}/songs', [\App\Http\Controllers\PlaylistController::class, 'addSong']);
Route::get('/playlists/{playlist}/songs', [\App\Http\Controllers\PlaylistController::class, 'getSongs']);

//ratings
Route::post('/ratings', [\App\Http\Controllers\RatingController::class, 'store']);
Route::get('/ratings', [\App\Http\Controllers\RatingController::class, 'index']);
