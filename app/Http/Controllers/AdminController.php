<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RoleChangeRequest;
use App\Models\Music;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Get all users with pagination and filtering
     */
    public function getUsers(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $role = $request->get('role');
            $search = $request->get('search');

            $query = User::query();

            // Filter by role
            if ($role && in_array($role, ['user', 'artist', 'admin'])) {
                $query->where('role', $role);
            }

            // Search by name or email
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->withCount(['recentlyPlayed'])
                          ->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            // Add profile picture URLs
            $users->getCollection()->transform(function ($user) {
                if ($user->profile_picture) {
                    $user->profile_picture_url = asset('storage/' . $user->profile_picture);
                }
                return $user;
            });

            return response()->json([
                'success' => true,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user details by ID
     */
    public function getUserDetails($userId)
    {
        try {
            $user = User::with(['recentlyPlayed.song', 'recentlyPlayed.song.artist'])
                       ->withCount(['recentlyPlayed'])
                       ->find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get user's ratings
            $ratings = Rating::where('user_id', $userId)
                           ->where('rateable_type', 'App\Models\Music')
                           ->with(['rateable.artist'])
                           ->orderBy('created_at', 'desc')
                           ->limit(10)
                           ->get();

            // Get user's uploaded music if they're an artist
            $uploadedMusic = [];
            if ($user->isArtist()) {
                $uploadedMusic = Music::where('uploaded_by', $userId)
                                    ->with(['artist', 'ratings'])
                                    ->withCount(['ratings'])
                                    ->get();
            }

            if ($user->profile_picture) {
                $user->profile_picture_url = asset('storage/' . $user->profile_picture);
            }

            return response()->json([
                'success' => true,
                'user' => $user,
                'ratings' => $ratings,
                'uploaded_music' => $uploadedMusic,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user details
     */
    public function updateUser(Request $request, $userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $userId,
                'role' => 'sometimes|in:user,artist,admin',
                'status' => 'sometimes|in:active,inactive,suspended',
                'bio' => 'sometimes|string|max:1000',
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($request->only([
                'name', 'email', 'role', 'status', 'bio', 'phone', 'address'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all role change requests
     */
    public function getRoleChangeRequests(Request $request)
    {
        try {
            $status = $request->get('status');
            $perPage = $request->get('per_page', 15);

            $query = RoleChangeRequest::with(['user', 'reviewer'])
                                    ->orderBy('created_at', 'desc');

            if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
                $query->where('status', $status);
            }

            $requests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'requests' => $requests,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch role change requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve role change request
     */
    public function approveRoleChangeRequest(Request $request, $requestId)
    {
        try {
            $roleRequest = RoleChangeRequest::find($requestId);

            if (!$roleRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role change request not found'
                ], 404);
            }

            if ($roleRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This request has already been processed'
                ], 400);
            }

            $adminNotes = $request->get('admin_notes');
            $roleRequest->approve(auth()->id(), $adminNotes);

            return response()->json([
                'success' => true,
                'message' => 'Role change request approved successfully',
                'request' => $roleRequest->load(['user', 'reviewer'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve role change request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject role change request
     */
    public function rejectRoleChangeRequest(Request $request, $requestId)
    {
        try {
            $roleRequest = RoleChangeRequest::find($requestId);

            if (!$roleRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role change request not found'
                ], 404);
            }

            if ($roleRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This request has already been processed'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'admin_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin notes are required for rejection',
                    'errors' => $validator->errors()
                ], 422);
            }

            $roleRequest->reject(auth()->id(), $request->admin_notes);

            return response()->json([
                'success' => true,
                'message' => 'Role change request rejected',
                'request' => $roleRequest->load(['user', 'reviewer'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject role change request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin dashboard statistics
     */
    public function getDashboardStats()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'total_artists' => User::where('role', 'artist')->count(),
                'total_admins' => User::where('role', 'admin')->count(),
                'pending_role_requests' => RoleChangeRequest::where('status', 'pending')->count(),
                'total_music' => Music::count(),
                'total_ratings' => Rating::count(),
                'recent_users' => User::orderBy('created_at', 'desc')->limit(5)->get(),
                'recent_role_requests' => RoleChangeRequest::with(['user'])
                                                           ->where('status', 'pending')
                                                           ->orderBy('created_at', 'desc')
                                                           ->limit(5)
                                                           ->get(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user (soft delete)
     */
    public function deleteUser($userId)
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Prevent deleting other admins
            if ($user->isAdmin() && $user->id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete other admin users'
                ], 403);
            }

            // Prevent self-deletion
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 403);
            }

            $user->update(['status' => 'inactive']);

            return response()->json([
                'success' => true,
                'message' => 'User deactivated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}