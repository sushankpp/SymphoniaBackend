<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_role',
        'requested_role',
        'reason',
        'status',
        'reviewed_by',
        'admin_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

        public function approve($adminId, $notes = null)
    {
        return \DB::transaction(function () use ($adminId, $notes) {
            try {
                \Log::info('Starting role change approval', [
                    'request_id' => $this->id,
                    'user_id' => $this->user_id,
                    'requested_role' => $this->requested_role,
                    'admin_id' => $adminId
                ]);

                // Update the role change request first
                $this->update([
                    'status' => 'approved',
                    'reviewed_by' => $adminId,
                    'admin_notes' => $notes,
                    'reviewed_at' => now(),
                ]);

                \Log::info('Role change request updated to approved');

                // Update user role
                if ($this->user) {
                    $oldRole = $this->user->role;
                    $updated = $this->user->update(['role' => $this->requested_role]);
                    
                    if (!$updated) {
                        throw new \Exception('Failed to update user role');
                    }
                    
                    \Log::info('User role updated successfully', [
                        'user_id' => $this->user_id,
                        'old_role' => $oldRole,
                        'new_role' => $this->requested_role
                    ]);
                    
                    // If approved to become artist, create artist record and assign music
                    if ($this->requested_role === 'artist') {
                        $this->createArtistRecord();
                        $this->assignMusicToArtist();
                    }
                } else {
                    throw new \Exception('User not found for role change request');
                }
                
                \Log::info('Role change approval completed successfully', [
                    'request_id' => $this->id,
                    'user_id' => $this->user_id
                ]);
                
                return true;
                
            } catch (\Exception $e) {
                \Log::error('Role change approval failed', [
                    'request_id' => $this->id,
                    'user_id' => $this->user_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Re-throw the exception so the transaction is rolled back
                throw $e;
            }
        });
    }

    public function reject($adminId, $notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'admin_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }

    private function createArtistRecord()
    {
        // Use updateOrCreate to handle existing records gracefully
        $artist = \App\Models\Artist::updateOrCreate(
            ['user_id' => $this->user_id],
            [
                'artist_name' => $this->user->name,
                'artist_image' => $this->user->profile_picture ?? null,
            ]
        );
        
        \Log::info('Artist record ensured', [
            'user_id' => $this->user_id,
            'artist_id' => $artist->id,
            'artist_name' => $artist->artist_name,
            'was_created' => $artist->wasRecentlyCreated
        ]);
    }

    private function assignMusicToArtist()
    {
        // Assign all music to the new artist
        $musicCount = \App\Models\Music::where('uploaded_by', '!=', $this->user_id)->count();
        
        if ($musicCount > 0) {
            \App\Models\Music::query()->update(['uploaded_by' => $this->user_id]);
            
            \Log::info('Music assigned to new artist', [
                'user_id' => $this->user_id,
                'music_count' => $musicCount
            ]);
        } else {
            \Log::info('No music to assign - artist already owns all music', [
                'user_id' => $this->user_id
            ]);
        }
    }

    public static function validateUserArtistConsistency()
    {
        $inconsistencies = [];
        
        // Find users with 'artist' role but no artist record
        $usersWithArtistRole = \App\Models\User::where('role', 'artist')->get();
        foreach ($usersWithArtistRole as $user) {
            $artistRecord = \App\Models\Artist::where('user_id', $user->id)->first();
            if (!$artistRecord) {
                $inconsistencies[] = [
                    'type' => 'user_artist_role_no_record',
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'message' => 'User has artist role but no artist record'
                ];
            }
        }
        
        // Find artist records with users that don't have 'artist' role
        $artistRecords = \App\Models\Artist::all();
        foreach ($artistRecords as $artist) {
            $user = \App\Models\User::find($artist->user_id);
            if (!$user) {
                $inconsistencies[] = [
                    'type' => 'orphaned_artist_record',
                    'artist_id' => $artist->id,
                    'user_id' => $artist->user_id,
                    'message' => 'Artist record exists but user not found'
                ];
            } elseif ($user->role !== 'artist') {
                $inconsistencies[] = [
                    'type' => 'artist_record_user_not_artist',
                    'artist_id' => $artist->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_role' => $user->role,
                    'message' => 'Artist record exists but user role is not artist'
                ];
            }
        }
        
        return $inconsistencies;
    }

    public static function fixUserArtistInconsistencies()
    {
        $fixed = [];
        
        // Fix users with artist role but no artist record
        $usersWithArtistRole = \App\Models\User::where('role', 'artist')->get();
        foreach ($usersWithArtistRole as $user) {
            $artistRecord = \App\Models\Artist::where('user_id', $user->id)->first();
            if (!$artistRecord) {
                $artist = \App\Models\Artist::create([
                    'user_id' => $user->id,
                    'artist_name' => $user->name,
                    'artist_image' => $user->profile_picture,
                ]);
                $fixed[] = [
                    'type' => 'created_artist_record',
                    'user_id' => $user->id,
                    'artist_id' => $artist->id
                ];
            }
        }
        
        // Fix artist records with users that don't have artist role
        $artistRecords = \App\Models\Artist::all();
        foreach ($artistRecords as $artist) {
            $user = \App\Models\User::find($artist->user_id);
            if ($user && $user->role !== 'artist') {
                $user->update(['role' => 'artist']);
                $fixed[] = [
                    'type' => 'updated_user_role',
                    'user_id' => $user->id,
                    'old_role' => $user->getOriginal('role'),
                    'new_role' => 'artist'
                ];
            }
        }
        
        return $fixed;
    }
}