<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // Login function
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!auth()->attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'redirect_url' => env('FRONTEND_URL', 'http://localhost:5173') . '/',
        ]);
    }

    // Register function
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->storeAs('images/profile_pictures/', $user->id . '_' . time() . '.jpg', 'public');
            $user->profile_picture = Storage::disk('public')->url($path);
            $user->save();
        }

        $user->sendEmailVerificationNotification();
        Auth::login($user, remember: true);

        return response()->json([
            'message' => 'User registered successfully',
            'redirect_url' => env('FRONTEND_URL', 'http://localhost:5173') . '/',
        ], 201);
    }

    // Logout function
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    // Get authenticated user
    public function getAuthenticatedUser(Request $request)
    {
        $user = auth()->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_picture' => $user->profile_picture,
            'role' => $user->role,
            'is_email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at->toDateTimeString(),
            'gender' => $user->gender,
            'dob' => $user->dob ? $user->dob->toDateString() : null,
            'phone' => $user->phone,
            'bio' => $user->bio,
            'address' => $user->address,
            'status' => $user->status,
        ], 200);
    }

    // Update user profile
    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'gender' => 'sometimes|string|in:male,female,other',
            'dob' => 'sometimes|date|before:today',
            'phone' => 'sometimes|string|max:20',
            'bio' => 'sometimes|string|max:500',
            'address' => 'sometimes|string|max:500',
            'profile_picture' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $updateData = $request->only([
            'name', 'email', 'gender', 'phone', 'bio', 'address'
        ]);

        // Handle date of birth
        if ($request->has('dob')) {
            $updateData['dob'] = $request->dob;
        }

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            // Delete old profile picture if exists
            if ($user->profile_picture) {
                $oldPath = str_replace('/storage/', '', $user->profile_picture);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Store new profile picture
            $path = $request->file('profile_picture')->storeAs(
                'images/profile_pictures/', 
                $user->id . '_' . time() . '.jpg', 
                'public'
            );
            $updateData['profile_picture'] = Storage::disk('public')->url($path);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture' => $user->profile_picture,
                'role' => $user->role,
                'is_email_verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at->toDateTimeString(),
                'gender' => $user->gender,
                'dob' => $user->dob ? $user->dob->toDateString() : null,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'address' => $user->address,
                'status' => $user->status,
            ]
        ], 200);
    }

    // Google OAuth redirect
    public function redirectToGoogle()
    {
        // Configure HTTP client based on environment
        $httpClientConfig = [
            'timeout' => 30,
        ];
        
        if (app()->environment('local')) {
            $httpClientConfig['verify'] = false;
        }
        
        $httpClient = new \GuzzleHttp\Client($httpClientConfig);
        
        return Socialite::driver('google')
            ->setHttpClient($httpClient)
            ->stateless()
            ->redirect();
    }

        // Google OAuth callback
    public function handleGoogleCallback()
    {
        try {
            
            $httpClientConfig = [
                'timeout' => 30,
            ];
            
            if (app()->environment('local')) {
                $httpClientConfig['verify'] = false;
            }
            
            $httpClient = new \GuzzleHttp\Client($httpClientConfig);
            
            $googleUser = Socialite::driver('google')
                ->setHttpClient($httpClient)
                ->stateless()
                ->user();
            $adminEmail = env('GOOGLE_ADMIN_EMAIL');

            $user = User::where('email', $googleUser->email)->first();
            $frontendPath = '/'; // Redirect to home page for all users

            if ($user) {
                $user->update([
                    'name' => $googleUser->name,
                    'google_id' => $googleUser->id,
                    'google_token' => $googleUser->token,
                    'google_refresh_token' => $googleUser->refreshToken,
                    'role' => $googleUser->email === $adminEmail ? 'admin' : 'user',
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'google_token' => $googleUser->token,
                    'google_refresh_token' => $googleUser->refreshToken,
                    'password' => bcrypt(Str::random(16)),
                    'role' => $googleUser->email === $adminEmail ? 'admin' : 'user',
                ]);
                // New users also go to home page
            }

            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->save();

            if ($googleUser->avatar) {
                $imageContents = file_get_contents($googleUser->avatar);
                if ($imageContents) {
                    $relativePath = 'images/profile_pictures/' . $user->id . '_' . time() . '.jpg';
                    Storage::disk('public')->put($relativePath, $imageContents);
                    $user->profile_picture = Storage::disk('public')->url($relativePath);
                    $user->save();
                }
            }

            Auth::login($user, remember: true);
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . $frontendPath);

        } catch (\Throwable $e) {
            Log::error('Google Login Failed: ' . $e->getMessage());
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=' . urlencode($e->getMessage()));
        }
    }

    // Email verification
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user || !hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/email-verification-failed');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/');
    }

    // Send verification email
    public function sendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            return response()->json(['message' => 'Verification link sent!', 'status' => 200]);
        }

        return response()->json(['message' => 'Email already verified or user not authenticated.', 'status' => 400], 400);
    }
}