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
            $user->profile_picture = $path;
            $user->save();
        }

        $user->sendEmailVerificationNotification();
        Auth::login($user, remember: true);

        return response()->json([
            'message' => 'User registered successfully',
            'redirect_url' => env('FRONTEND_URL', 'http://localhost:5173') . '/',
        ], 201);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function getAuthenticatedUser(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

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
        } catch (\Exception $e) {
            Log::error('Get authenticated user failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to get user data'], 500);
        }
    }



    public function updateProfile(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

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
                'name',
                'email',
                'gender',
                'phone',
                'bio',
                'address'
            ]);

            if ($request->has('dob')) {
                $updateData['dob'] = $request->dob;
            }

            if ($request->hasFile('profile_picture')) {
                if ($user->profile_picture) {
                    $oldPath = $user->profile_picture;
                    if (filter_var($oldPath, FILTER_VALIDATE_URL)) {
                        $oldPath = str_replace('/storage/', '', $oldPath);
                    }
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $path = $request->file('profile_picture')->storeAs(
                    'images/profile_pictures/',
                    $user->id . '_' . time() . '.jpg',
                    'public'
                );
                $updateData['profile_picture'] = $path;
            }

            $user->update($updateData);
            $user->refresh();

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
        } catch (\Exception $e) {
            Log::error('Profile update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Profile update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function redirectToGoogle()
    {
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

    public function handleGoogleCallback(Request $request)
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
            }

            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            if ($googleUser->avatar) {
                try {
                    $imageContents = file_get_contents($googleUser->avatar);
                    if ($imageContents) {
                        $relativePath = 'images/profile_pictures/' . $user->id . '_' . time() . '.jpg';
                        Storage::disk('public')->put($relativePath, $imageContents);
                        $user->profile_picture = $relativePath; // Store relative path, not full URL
                        $user->save();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to download Google avatar: ' . $e->getMessage());
                }
            }

            $token = $user->createToken('google_auth_token')->plainTextToken;

            return redirect()->away(env('FRONTEND_URL', 'http://localhost:5173') . '/auth/callback?success=true&token=' . urlencode($token));

        } catch (\Throwable $e) {
            Log::error('Google Login Failed: ' . $e->getMessage());
            return redirect()->away(env('FRONTEND_URL', 'http://localhost:5173') . '/auth/callback?error=' . urlencode($e->getMessage()));
        }
    }

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

    public function sendVerificationEmail(Request $request)
    {
        $user = auth()->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $user->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification email sent successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }


        $user->password = bcrypt($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully',
            'success' => true
        ]);
    }

    public function resetPassword(Request $request)
    {
        return response()->json(['message' => 'Use forgot-password endpoint instead'], 400);
    }

    public function verifyResetToken(Request $request)
    {
        return response()->json(['message' => 'Use forgot-password endpoint instead'], 400);
    }
}