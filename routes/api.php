<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test-connection', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Backend is reachable',
        'timestamp' => now()->toISOString(),
        'cors_working' => true
    ]);
});

//Auth Routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Password Change Route (no authentication required)
Route::post('/auth/change-password', [AuthController::class, 'forgotPassword']);

// Recommendations and trending content (require authentication for personalized recommendations)
Route::get('/recommendations', [\App\Http\Controllers\MusicController::class, 'getRecommendations'])->middleware('auth:sanctum');
Route::get('/top-recommendations', [\App\Http\Controllers\MusicController::class, 'getTopRecommendations'])->middleware('auth:sanctum');
Route::get('/top-artists', [\App\Http\Controllers\MusicController::class, 'getTopArtists'])->middleware('auth:sanctum');

// Public routes for non-authenticated users (shows global trending only)
Route::get('/public/recommendations', [\App\Http\Controllers\MusicController::class, 'getRecommendations']);
Route::get('/public/top-recommendations', [\App\Http\Controllers\MusicController::class, 'getTopRecommendations']);
Route::get('/public/top-artists', [\App\Http\Controllers\MusicController::class, 'getTopArtists']);

// Public ratings (viewable by everyone)
Route::get('/public/ratings/{id}', [\App\Http\Controllers\RatingController::class, 'show']);

//authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'getAuthenticatedUser']);
    Route::put('/auth/user', [AuthController::class, 'updateProfile']);
    Route::post('/auth/user', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/send-verification-email', [AuthController::class, 'sendVerificationEmail']);

    //recently played routes
    Route::post('/recently-played', [\App\Http\Controllers\RecentlyPlayedController::class, 'store']);
    Route::get('/recently-played', [\App\Http\Controllers\RecentlyPlayedController::class, 'index']);

    // Playlists (require authentication)
    Route::get('/playlists', [\App\Http\Controllers\PlaylistController::class, 'index']);
    Route::post('/playlists', [\App\Http\Controllers\PlaylistController::class, 'store']);
    Route::post('/playlists/{playlist}/songs', [\App\Http\Controllers\PlaylistController::class, 'addSong']);
    Route::get('/playlists/{playlist}/songs', [\App\Http\Controllers\PlaylistController::class, 'getSongs']);

    // Ratings (require authentication)
    Route::post('/ratings', [\App\Http\Controllers\RatingController::class, 'store']);
    Route::get('/ratings', [\App\Http\Controllers\RatingController::class, 'index']);
    Route::get('/ratings/{id}', [\App\Http\Controllers\RatingController::class, 'show']);
    Route::get('/user/ratings', [\App\Http\Controllers\RatingController::class, 'getUserRatings']);

    // Albums (store requires authentication)
    Route::post('/albums', [\App\Http\Controllers\AlbumController::class, 'store']);

    // Role change requests (require authentication)
    Route::get('/role-requests', [\App\Http\Controllers\RoleChangeRequestController::class, 'index']);
    Route::post('/role-requests', [\App\Http\Controllers\RoleChangeRequestController::class, 'store']);
    Route::get('/role-requests/{id}', [\App\Http\Controllers\RoleChangeRequestController::class, 'show']);
    Route::patch('/role-requests/{id}/cancel', [\App\Http\Controllers\RoleChangeRequestController::class, 'cancel']);
});

// Admin Routes (require admin role)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\AdminController::class, 'getDashboardStats']);
    Route::get('/users', [\App\Http\Controllers\AdminController::class, 'getUsers']);
    Route::get('/users/{id}', [\App\Http\Controllers\AdminController::class, 'getUserDetails']);
    Route::patch('/users/{id}', [\App\Http\Controllers\AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [\App\Http\Controllers\AdminController::class, 'deleteUser']);

    Route::get('/role-requests', [\App\Http\Controllers\AdminController::class, 'getRoleChangeRequests']);
    Route::patch('/role-requests/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveRoleChangeRequest']);
    Route::post('/role-requests/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveRoleChangeRequest']);
    Route::patch('/role-requests/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectRoleChangeRequest']);
    Route::post('/role-requests/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectRoleChangeRequest']);
    Route::get('/music-upload-requests', [\App\Http\Controllers\AdminController::class, 'getMusicUploadRequests']);
    Route::get('/test-role-change/{userId}', [\App\Http\Controllers\AdminController::class, 'testRoleChange']);
});

// Artist Routes (require artist role)
Route::middleware(['auth:sanctum', 'artist'])->prefix('artist')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ArtistDashboardController::class, 'getProfile']);
    Route::patch('/profile', [\App\Http\Controllers\ArtistDashboardController::class, 'updateProfile']);
    Route::get('/dashboard', [\App\Http\Controllers\ArtistDashboardController::class, 'getDashboardStats']);
    Route::get('/music', [\App\Http\Controllers\ArtistDashboardController::class, 'getMyMusic']);
    Route::get('/music-simple', [\App\Http\Controllers\ArtistDashboardController::class, 'getMyMusicSimple']);
    Route::get('/music/{id}/stats', [\App\Http\Controllers\ArtistDashboardController::class, 'getSongStats']);
    Route::patch('/music/{id}', [\App\Http\Controllers\ArtistDashboardController::class, 'updateMusic']);
    Route::delete('/music/{id}', [\App\Http\Controllers\ArtistDashboardController::class, 'deleteMusic']);
    Route::get('/debug-music', [\App\Http\Controllers\ArtistDashboardController::class, 'debugMusicOwnership']);
    Route::get('/ratings', [\App\Http\Controllers\RatingController::class, 'getArtistItemRatings']);
});

Route::get('/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/auth/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])->name('verification.send');

Route::get('/music', \App\Http\Controllers\MusicController::class);
Route::post('/music/{id}/play', [\App\Http\Controllers\MusicController::class, 'playSong']);
Route::post('/upload-music', [\App\Http\Controllers\MusicController::class, 'uploadMusic'])->middleware('auth:sanctum');

// Music upload request routes (for artists)
Route::middleware(['auth:sanctum', 'artist'])->group(function () {
    Route::post('/music-upload-requests', [\App\Http\Controllers\MusicUploadRequestController::class, 'submit']);
    Route::get('/music-upload-requests', [\App\Http\Controllers\MusicUploadRequestController::class, 'myRequests']);
    Route::delete('/music-upload-requests/{id}', [\App\Http\Controllers\MusicUploadRequestController::class, 'cancel']);
    Route::get('/music-upload-requests/{id}', [\App\Http\Controllers\MusicUploadRequestController::class, 'show']);
});

// Music upload request admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/music-upload-requests', [\App\Http\Controllers\AdminController::class, 'getMusicUploadRequests']);
    Route::post('/admin/music-upload-requests/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveMusicUploadRequest']);
    Route::post('/admin/music-upload-requests/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectMusicUploadRequest']);
});

Route::get('/uploaded-music', [\App\Http\Controllers\MusicController::class, 'getUploadedMusic']);
Route::get('/artists', [\App\Http\Controllers\ArtistController::class, 'index']);
Route::get('/artists/{artistId}/songs', [\App\Http\Controllers\ArtistController::class, 'getSongs']);

//albums
Route::get('/albums', [\App\Http\Controllers\AlbumController::class, 'index']);
Route::get('/artists/{artistId}/albums', [\App\Http\Controllers\AlbumController::class, 'getAlbumsByArtist']);

// Artist album management routes
Route::middleware(['auth:sanctum', 'artist'])->group(function () {
    Route::get('/artist/albums', [\App\Http\Controllers\AlbumController::class, 'getMyAlbums']);
    Route::post('/artist/albums', [\App\Http\Controllers\AlbumController::class, 'store']);
    Route::get('/artist/albums/{id}', [\App\Http\Controllers\AlbumController::class, 'show']);
    Route::put('/artist/albums/{id}', [\App\Http\Controllers\AlbumController::class, 'update']);
    Route::delete('/artist/albums/{id}', [\App\Http\Controllers\AlbumController::class, 'destroy']);
    Route::post('/artist/albums/{id}/add-songs', [\App\Http\Controllers\AlbumController::class, 'addSongs']);
    Route::post('/artist/albums/{id}/remove-songs', [\App\Http\Controllers\AlbumController::class, 'removeSongs']);
});

// Audio file serving route
Route::get('/audio/{filename}', function ($filename) {
    $path = storage_path('app/public/audios/' . $filename);
    
    if (!file_exists($path)) {
        abort(404, 'Audio file not found');
    }
    
    return response()->file($path, [
        'Content-Type' => 'audio/mpeg',
        'Accept-Ranges' => 'bytes'
    ]);
});

// Alternative audio route with query parameter
Route::get('/audio-file', function (Request $request) {
    $filename = $request->get('file');
    $type = $request->get('type', 'audio');
    
    if (!$filename) {
        abort(400, 'Filename is required');
    }
    
    $path = storage_path('app/public/audios/' . $filename);
    
    if (!file_exists($path)) {
        abort(404, 'Audio file not found');
    }
    
    return response()->file($path, [
        'Content-Type' => 'audio/mpeg',
        'Accept-Ranges' => 'bytes'
    ]);
});

// Image file serving route (for covers, album art, etc.)
Route::get('/image-file', function (Request $request) {
    $filename = $request->get('file');
    $type = $request->get('type', 'cover');
    
    if (!$filename) {
        abort(400, 'Filename is required');
    }
    
    // Check both possible image directories
    $paths = [
        storage_path('app/public/songs_cover/' . $filename),
        storage_path('app/public/audios/' . $filename),
        storage_path('app/public/images/' . $filename)
    ];
    
    $imagePath = null;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $imagePath = $path;
            break;
        }
    }
    
    if (!$imagePath) {
        abort(404, 'Image file not found: ' . $filename);
    }
    
    // Determine content type based on file extension
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $contentType = match($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => 'image/jpeg'
    };
    
    return response()->file($imagePath, [
        'Content-Type' => $contentType,
        'Cache-Control' => 'public, max-age=31536000' // Cache for 1 year
    ]);
});
