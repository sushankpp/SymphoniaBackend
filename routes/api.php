<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage; // Added for image upload test
use Illuminate\Support\Facades\Log; // Added for database update test

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

Route::get('/test-role-requests', function () {
    try {
        $controller = new \App\Http\Controllers\RoleChangeRequestController();

        return response()->json([
            'success' => true,
            'message' => 'Role requests endpoint is working',
            'note' => 'The actual /role-requests endpoint requires authentication',
            'auth_required' => true,
            'controller_available' => true,
            'endpoint_info' => [
                'authenticated_endpoints' => [
                    'GET /role-requests',
                    'POST /role-requests',
                    'GET /role-requests/{id}',
                    'PATCH /role-requests/{id}/cancel'
                ],
                'admin_endpoints' => [
                    'GET /admin/role-requests',
                    'PATCH /admin/role-requests/{id}/approve',
                    'PATCH /admin/role-requests/{id}/reject'
                ]
            ],
            'instructions' => [
                '1. Login first: POST /auth/login',
                '2. Use token: Authorization: Bearer YOUR_TOKEN',
                '3. Then access: GET /role-requests'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

//Auth Routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Recommendations and trending content (work for both authenticated and non-authenticated users)
Route::get('/recommendations', [\App\Http\Controllers\MusicController::class, 'getRecommendations']);
Route::get('/top-recommendations', [\App\Http\Controllers\MusicController::class, 'getTopRecommendations']);
Route::get('/top-artists', [\App\Http\Controllers\MusicController::class, 'getTopArtists']);

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

    // Albums (store requires authentication)
    Route::post('/albums', [\App\Http\Controllers\AlbumController::class, 'store']);

    // Role change requests (require authentication)
    Route::get('/role-requests', [\App\Http\Controllers\RoleChangeRequestController::class, 'index']);
    Route::post('/role-requests', [\App\Http\Controllers\RoleChangeRequestController::class, 'store']);
    Route::get('/role-requests/{id}', [\App\Http\Controllers\RoleChangeRequestController::class, 'show']);
    Route::patch('/role-requests/{id}/cancel', [\App\Http\Controllers\RoleChangeRequestController::class, 'cancel']);
});

// Test admin access
Route::get('/test-admin-access', function () {
    $user = auth()->user();
    return response()->json([
        'success' => true,
        'message' => 'Admin access test',
        'authenticated' => auth()->check(),
        'user' => $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_admin' => $user->isAdmin()
        ] : null,
        'correct_admin_urls' => [
            'GET /api/admin/dashboard',
            'GET /api/admin/users',
            'GET /api/admin/role-requests'
        ]
    ]);
})->middleware('auth:sanctum');

// Admin Routes (require admin role)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\AdminController::class, 'getDashboardStats']);
    Route::get('/users', [\App\Http\Controllers\AdminController::class, 'getUsers']);
    Route::get('/users/{id}', [\App\Http\Controllers\AdminController::class, 'getUserDetails']);
    Route::patch('/users/{id}', [\App\Http\Controllers\AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [\App\Http\Controllers\AdminController::class, 'deleteUser']);

    Route::get('/role-requests', [\App\Http\Controllers\AdminController::class, 'getRoleChangeRequests']);
    Route::patch('/role-requests/{id}/approve', [\App\Http\Controllers\AdminController::class, 'approveRoleChangeRequest']);
    Route::patch('/role-requests/{id}/reject', [\App\Http\Controllers\AdminController::class, 'rejectRoleChangeRequest']);
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
});

Route::get('/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/auth/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])->name('verification.send');

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

// Test image upload endpoint
Route::post('/test-image-upload', function (Request $request) {
    try {
        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'No image file provided'], 400);
        }

        $file = $request->file('image');

        // Validate file
        if (!$file->isValid()) {
            return response()->json(['error' => 'Invalid file upload'], 400);
        }

        // Check file size (max 2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json(['error' => 'File size too large (max 2MB)'], 400);
        }

        // Validate mime type
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json(['error' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'], 400);
        }

        // Get original extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return response()->json(['error' => 'Invalid file extension'], 400);
        }

        // Generate unique filename
        $filename = 'test_' . time() . '.' . $extension;

        // Store file
        $path = $file->storeAs('images/test/', $filename, 'public');

        // Verify file was stored correctly
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['error' => 'Failed to store file'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'file_info' => [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
                'stored_path' => $path,
                'url' => Storage::disk('public')->url($path)
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Upload failed: ' . $e->getMessage()
        ], 500);
    }
});

// Simple test endpoint
Route::get('/test', function () {
    return response()->json([
        'message' => 'Server is running!',
        'timestamp' => now(),
        'status' => 'ok'
    ]);
});

// FFmpeg test endpoint
Route::get('/test-ffmpeg', function () {
    try {
        // Test FFmpeg command
        $command = 'ffmpeg -version 2>&1';
        $output = shell_exec($command);

        // Test a simple conversion
        $testInput = storage_path('app/public/audios/original');
        $testOutput = storage_path('app/temp/test_output.raw');

        // Check if we have any audio files to test with
        $audioFiles = glob($testInput . '/*.mp3');
        $testFile = !empty($audioFiles) ? $audioFiles[0] : null;

        $conversionResult = null;
        if ($testFile) {
            $convertCommand = sprintf(
                'ffmpeg -i %s -f s16le -acodec pcm_s16le -ar 44100 -ac 2 %s -y 2>&1',
                escapeshellarg($testFile),
                escapeshellarg($testOutput)
            );

            $conversionOutput = shell_exec($convertCommand);
            $conversionResult = [
                'command' => $convertCommand,
                'output' => $conversionOutput,
                'success' => file_exists($testOutput),
                'test_file' => basename($testFile)
            ];

            // Cleanup
            if (file_exists($testOutput)) {
                unlink($testOutput);
            }
        }

        return response()->json([
            'ffmpeg_version' => $output,
            'conversion_test' => $conversionResult,
            'available_test_files' => array_map('basename', $audioFiles)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

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
    Route::get('/admin/music-upload-requests', [\App\Http\Controllers\MusicUploadRequestController::class, 'index']);
    Route::get('/admin/music-upload-requests/{id}', [\App\Http\Controllers\MusicUploadRequestController::class, 'show']);
    Route::post('/admin/music-upload-requests/{id}/approve', [\App\Http\Controllers\MusicUploadRequestController::class, 'approve']);
    Route::post('/admin/music-upload-requests/{id}/reject', [\App\Http\Controllers\MusicUploadRequestController::class, 'reject']);
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

// Test database update endpoint
Route::post('/test-db-update', function (Request $request) {
    try {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Test simple update
        $testData = [
            'name' => 'Test Update ' . time(),
            'bio' => 'Test bio ' . time()
        ];

        Log::info('Testing database update with data:', $testData);

        $user->update($testData);
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Database update test successful',
            'user_data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'gender' => $user->gender,
                'dob' => $user->dob,
                'phone' => $user->phone,
                'address' => $user->address,
                'profile_picture' => $user->profile_picture
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Database update test failed:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => 'Database update test failed: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

// Test login endpoint for debugging
Route::post('/test-login-api', function (Request $request) {
    try {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!auth()->attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();
        $token = $user->createToken('test_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Test authentication debug endpoint
Route::get('/debug-auth', function (Request $request) {
    try {
        $headers = $request->headers->all();
        $bearerToken = $request->bearerToken();
        $authorization = $request->header('Authorization');

        return response()->json([
            'success' => true,
            'debug_info' => [
                'has_bearer_token' => !empty($bearerToken),
                'bearer_token_length' => $bearerToken ? strlen($bearerToken) : 0,
                'authorization_header' => $authorization,
                'all_headers' => $headers,
                'auth_check' => auth()->check(),
                'sanctum_auth_check' => auth('sanctum')->check(),
                'user_id' => auth()->id(),
                'sanctum_user_id' => auth('sanctum')->id(),
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Test current user data
Route::get('/test-user-data', function (Request $request) {
    try {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Get fresh data from database
        $user->refresh();

        return response()->json([
            'success' => true,
            'user_data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'gender' => $user->gender,
                'dob' => $user->dob,
                'phone' => $user->phone,
                'address' => $user->address,
                'profile_picture' => $user->profile_picture,
                'updated_at' => $user->updated_at,
                'created_at' => $user->created_at
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('User data test failed:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => 'User data test failed: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

// Test Google OAuth configuration
Route::get('/test-google-config', function () {
    try {
        $config = [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
            'admin_email' => env('GOOGLE_ADMIN_EMAIL'),
            'environment' => app()->environment(),
            'app_url' => env('APP_URL'),
            'frontend_url' => env('FRONTEND_URL')
        ];

        // Check if required config is present
        $missing = [];
        if (empty($config['client_id']))
            $missing[] = 'GOOGLE_CLIENT_ID';
        if (empty($config['client_secret']))
            $missing[] = 'GOOGLE_CLIENT_SECRET';
        if (empty($config['redirect_uri']))
            $missing[] = 'GOOGLE_REDIRECT_URI';

        return response()->json([
            'success' => empty($missing),
            'config' => $config,
            'missing_config' => $missing,
            'message' => empty($missing) ? 'Google OAuth configuration looks good' : 'Missing required Google OAuth configuration'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Google config test failed: ' . $e->getMessage()
        ], 500);
    }
});

// Session check route that works with both session and token auth
Route::middleware(['web'])->group(function () {
    Route::get('/auth/check', function (Request $request) {
        try {
            // First try session-based authentication
            if (auth()->check()) {
                $user = auth()->user();
                return response()->json([
                    'authenticated' => true,
                    'method' => 'session',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'profile_picture' => $user->profile_picture,
                        'role' => $user->role,
                        'is_email_verified' => $user->hasVerifiedEmail(),
                    ],
                    'session_id' => $request->session()->getId(),
                ]);
            }

            // Then try token-based authentication
            $token = $request->bearerToken();
            if ($token) {
                $user = auth('sanctum')->user();
                if ($user) {
                    return response()->json([
                        'authenticated' => true,
                        'method' => 'token',
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'profile_picture' => $user->profile_picture,
                            'role' => $user->role,
                            'is_email_verified' => $user->hasVerifiedEmail(),
                        ],
                    ]);
                }
            }

            // User is not authenticated - this is normal, not an error
            return response()->json([
                'authenticated' => false,
                'message' => 'User not logged in',
                'session_id' => $request->session()->getId(),
            ]);
        } catch (\Exception $e) {
            Log::error('Auth check failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'authenticated' => false,
                'error' => 'Authentication check failed: ' . $e->getMessage()
            ], 500);
        }
    });
});

// Test session functionality
Route::middleware(['web'])->group(function () {
    Route::get('/test-session', function (Request $request) {
        try {
            $sessionId = $request->session()->getId();
            $sessionData = $request->session()->all();

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'session_data' => $sessionData,
                'auth_check' => auth()->check(),
                'user' => auth()->user() ? auth()->user()->only(['id', 'name', 'email']) : null,
                'session_driver' => config('session.driver'),
                'session_lifetime' => config('session.lifetime'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    });
});

// Test session login
Route::middleware(['web'])->group(function () {
    Route::post('/test-login', function (Request $request) {
        try {
            $credentials = $request->only(['email', 'password']);

            if (auth()->attempt($credentials)) {
                $user = auth()->user();
                $request->session()->regenerate();

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'session_id' => $request->session()->getId(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

