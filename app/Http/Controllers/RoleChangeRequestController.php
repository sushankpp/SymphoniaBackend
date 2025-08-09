<?php

namespace App\Http\Controllers;

use App\Models\RoleChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleChangeRequestController extends Controller
{
    /**
     * Submit a role change request
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();

            // Check if user already has a pending request
            $existingRequest = RoleChangeRequest::where('user_id', $user->id)
                                              ->where('status', 'pending')
                                              ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending role change request'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'requested_role' => 'required|in:artist',
                'reason' => 'required|string|min:10|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Users can only request to become artists
            if ($request->requested_role !== 'artist') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only artist role requests are allowed'
                ], 400);
            }

            // Check if user is already an artist or admin
            if ($user->role !== 'user') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only regular users can request role changes'
                ], 400);
            }

            $roleRequest = RoleChangeRequest::create([
                'user_id' => $user->id,
                'current_role' => $user->role,
                'requested_role' => $request->requested_role,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role change request submitted successfully. An admin will review your request.',
                'request' => $roleRequest
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit role change request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's role change requests
     */
    public function index()
    {
        try {
            $user = auth()->user();

            $requests = RoleChangeRequest::where('user_id', $user->id)
                                       ->with(['reviewer'])
                                       ->orderBy('created_at', 'desc')
                                       ->get();

            return response()->json([
                'success' => true,
                'requests' => $requests
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
     * Get a specific role change request
     */
    public function show($id)
    {
        try {
            $user = auth()->user();

            $request = RoleChangeRequest::where('id', $id)
                                      ->where('user_id', $user->id)
                                      ->with(['reviewer'])
                                      ->first();

            if (!$request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role change request not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'request' => $request
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch role change request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a pending role change request
     */
    public function cancel($id)
    {
        try {
            $user = auth()->user();

            $request = RoleChangeRequest::where('id', $id)
                                      ->where('user_id', $user->id)
                                      ->where('status', 'pending')
                                      ->first();

            if (!$request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pending role change request not found'
                ], 404);
            }

            $request->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Role change request cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel role change request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}