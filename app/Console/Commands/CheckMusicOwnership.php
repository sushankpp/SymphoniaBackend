<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Music;

class CheckMusicOwnership extends Command
{
    protected $signature = 'app:check-music-ownership';
    protected $description = 'Check music ownership status';

    public function handle()
    {
        $this->info("=== Checking Music Ownership ===");
        
        // Check user 8
        $user = User::find(8);
        if (!$user) {
            $this->error("User 8 not found!");
            return Command::FAILURE;
        }
        
        $this->info("User: {$user->name} (ID: {$user->id}, Role: {$user->role})");
        
        // Check music owned by user 8
        $ownedMusic = Music::where('uploaded_by', 8)->get();
        $this->info("Music owned by user 8: {$ownedMusic->count()}");
        
        if ($ownedMusic->count() > 0) {
            $this->info("Sample music:");
            foreach ($ownedMusic->take(3) as $music) {
                $this->line("  - {$music->title} (ID: {$music->id})");
            }
        } else {
            $this->warn("No music owned by user 8!");
        }
        
        // Check all music
        $allMusic = Music::all();
        $this->info("\nTotal music in database: {$allMusic->count()}");
        
        $this->info("\nMusic ownership breakdown:");
        $ownership = $allMusic->groupBy('uploaded_by');
        foreach ($ownership as $userId => $music) {
            $owner = User::find($userId);
            $ownerName = $owner ? $owner->name : "Unknown User";
            $this->line("  User {$userId} ({$ownerName}): {$music->count()} tracks");
        }
        
        return Command::SUCCESS;
    }
}
