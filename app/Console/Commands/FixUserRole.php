<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\RoleChangeRequest;

class FixUserRole extends Command
{
    protected $signature = 'app:fix-user-role {user_id}';
    protected $description = 'Fix user role after role change request approval';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return Command::FAILURE;
        }
        
        $this->info("=== Fixing User Role ===");
        $this->info("User: {$user->name} (ID: {$userId}, Current Role: {$user->role})");
        
        // Check for approved role change requests
        $approvedRequest = RoleChangeRequest::where('user_id', $userId)
                                          ->where('status', 'approved')
                                          ->latest()
                                          ->first();
        
        if ($approvedRequest) {
            $this->info("Found approved role change request:");
            $this->line("  From: {$approvedRequest->current_role} → To: {$approvedRequest->requested_role}");
            $this->line("  Status: {$approvedRequest->status}");
            
            // Update user role
            $user->update(['role' => $approvedRequest->requested_role]);
            $this->info("✅ Updated user role to: {$approvedRequest->requested_role}");
            
            // Create artist record if needed
            if ($approvedRequest->requested_role === 'artist') {
                $existingArtist = \App\Models\Artist::where('user_id', $userId)->first();
                if (!$existingArtist) {
                    $artist = \App\Models\Artist::create([
                        'user_id' => $userId,
                        'artist_name' => $user->name,
                        'artist_image' => $user->profile_picture,
                    ]);
                    $this->info("✅ Created artist record (ID: {$artist->id})");
                } else {
                    $this->info("✅ Artist record already exists (ID: {$existingArtist->id})");
                }
            }
        } else {
            $this->warn("No approved role change request found for user {$userId}");
        }
        
        // Show updated user info
        $user->refresh();
        $this->info("\nUpdated User Info:");
        $this->line("  Name: {$user->name}");
        $this->line("  Email: {$user->email}");
        $this->line("  Role: {$user->role}");
        
        return Command::SUCCESS;
    }
}
