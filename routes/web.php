<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

// Test route to see if routing is working
Route::get('test', function () {
    Log::info('Test route hit');
    return response('Test route working', 200)
        ->header('Access-Control-Allow-Origin', '*');
});

// Simple audio file serving - NO COMPLEX LOGIC
Route::get('audio/{filename}', function ($filename) {
    $filePath = storage_path('app/public/audios/' . $filename);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    $file = file_get_contents($filePath);
    $mimeType = mime_content_type($filePath);
    
    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
});

// Simple compressed audio file serving
Route::get('audio/compressed/{filename}', function ($filename) {
    $filePath = storage_path('app/public/audios/compressed/' . $filename);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    $file = file_get_contents($filePath);
    $mimeType = mime_content_type($filePath);
    
    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
});

// OPTIONS routes for CORS preflight - must come first
Route::options('audio/{filename}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
        ->header('Access-Control-Max-Age', '86400');
});

Route::options('audio/compressed/{filename}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
        ->header('Access-Control-Max-Age', '86400');
});

// Session-based authentication routes (moved from api.php for proper session support)
Route::prefix('api')->group(function () {
    Route::post('/auth/session/login', [AuthController::class, 'sessionLogin']);
    Route::post('/auth/session/logout', [AuthController::class, 'logout']);
    Route::get('/auth/session/user', [AuthController::class, 'getAuthenticatedUser']);
    Route::get('/auth/session/check', [AuthController::class, 'checkSessionAuth']);
    Route::put('/auth/session/user', [AuthController::class, 'updateProfile']);
    Route::post('/auth/session/user', [AuthController::class, 'updateProfile']);
    
    // Alternative routes without CSRF
    Route::get('/auth/session/user-data', [AuthController::class, 'getAuthenticatedUser']);
    Route::post('/auth/session/update-profile', [AuthController::class, 'updateProfile']);
});

// Google OAuth routes
Route::get('/api/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/api/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Test session functionality
Route::get('/api/test-session', function (Request $request) {
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

// Test user data in database
Route::get('/api/test-user-data', function () {
    try {
        $user = \App\Models\User::first();
        if ($user) {
            return response()->json([
                'success' => true,
                'user_data' => $user->toArray(),
                'database_columns' => \Schema::getColumnListing('users')
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No users found'
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

