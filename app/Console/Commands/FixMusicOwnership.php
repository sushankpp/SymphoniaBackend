<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Music;
use App\Models\Artist;

class FixMusicOwnership extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-music-ownership {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix music ownership for artist dashboard';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id') ?? 8; // Default to user ID 8 (Sushank)
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return Command::FAILURE;
        }

        $this->info("=== Fixing Music Ownership for {$user->name} (ID: {$userId}) ===");

        // Check if user is an artist
        if ($user->role !== 'artist') {
            $this->warn("User {$user->name} is not an artist (role: {$user->role})");
            $this->info("Updating user role to artist...");
            $user->update(['role' => 'artist']);
        }

        // Get or create artist record
        $artist = $user->artist;
        if (!$artist) {
            $artist = Artist::create([
                'user_id' => $user->id,
                'artist_name' => $user->name,
                'artist_image' => $user->profile_picture,
            ]);
            $this->info("✅ Created artist record (ID: {$artist->id})");
        } else {
            $this->info("✅ Artist record exists (ID: {$artist->id})");
        }

        // Count music currently owned by this user
        $ownedMusic = Music::where('uploaded_by', $userId)->count();
        $this->info("Music currently owned by user: {$ownedMusic}");

        // Get all music and update ownership
        $totalMusic = Music::count();
        $this->info("Total music in database: {$totalMusic}");

        // Option 1: Assign all music to this user
        $this->info("Assigning all music to user {$user->name}...");
        Music::query()->update(['uploaded_by' => $userId]);

        $newOwnedMusic = Music::where('uploaded_by', $userId)->count();
        $this->info("✅ Music now owned by user: {$newOwnedMusic}");

        // Show some sample music
        $sampleMusic = Music::where('uploaded_by', $userId)->take(5)->get();
        $this->info("\nSample music now owned:");
        foreach ($sampleMusic as $music) {
            $this->line("  - {$music->title} (ID: {$music->id})");
        }

        $this->info("\n=== Fix Complete ===");
        $this->info("🎵 Artist dashboard should now show all music!");
        $this->info("📊 Test endpoint: GET /api/artist/dashboard");
        $this->info("🎼 Test music list: GET /api/artist/music");

        return Command::SUCCESS;
    }
}
