<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Artist;

class FixExistingArtists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-existing-artists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create artist records for existing users with artist role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Fixing existing artist users ===');

        // Find all users with artist role
        $artistUsers = User::where('role', 'artist')->get();

        $this->info("Found {$artistUsers->count()} users with artist role");

        $created = 0;
        $existing = 0;

        foreach ($artistUsers as $user) {
            // Check if artist record already exists
            $existingArtist = Artist::where('user_id', $user->id)->first();
            
            if (!$existingArtist) {
                // Create artist record
                $artist = Artist::create([
                    'user_id' => $user->id,
                    'artist_name' => $user->name,
                    'artist_image' => $user->profile_picture,
                ]);
                
                $this->info("✅ Created artist record for user: {$user->name} (ID: {$user->id})");
                $this->line("   Artist ID: {$artist->id}");
                $created++;
            } else {
                $this->warn("⚠️  Artist record already exists for user: {$user->name} (ID: {$user->id})");
                $existing++;
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Created: {$created} new artist records");
        $this->info("Existing: {$existing} artist records");
        $this->info("=== Fix complete ===");

        return Command::SUCCESS;
    }
}