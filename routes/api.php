<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/music', \App\Http\Controllers\MusicController::class);
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
