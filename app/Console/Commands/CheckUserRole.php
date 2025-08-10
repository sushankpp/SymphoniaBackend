<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckUserRole extends Command
{
    protected $signature = 'app:check-user-role {user_id}';
    protected $description = 'Check user role directly in database';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return Command::FAILURE;
        }
        
        $this->info("=== User Role Check ===");
        $this->info("User ID: {$user->id}");
        $this->info("Name: {$user->name}");
        $this->info("Email: {$user->email}");
        $this->info("Role: {$user->role}");
        $this->info("Updated At: {$user->updated_at}");
        
        // Also check if there's an artist record
        $artist = $user->artist;
        if ($artist) {
            $this->info("Artist Record: Yes (ID: {$artist->id})");
        } else {
            $this->info("Artist Record: No");
        }
        
        return Command::SUCCESS;
    }
}
