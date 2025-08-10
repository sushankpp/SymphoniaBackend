<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'google_token',
        'google_refresh_token',
        'role',
        'profile_picture',
        'gender',
        'dob',
        'phone',
        'bio',
        'address',
        'status',
    ];



    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_token',
        'google_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
        ];
    }

    public function recentlyPlayed()
    {
        return $this->hasMany(RecentlyPlayed::class);
    }

    public function roleChangeRequests()
    {
        return $this->hasMany(RoleChangeRequest::class);
    }

    public function uploadedMusic()
    {
        return $this->hasMany(Music::class, 'uploaded_by');
    }

    public function artist()
    {
        return $this->hasOne(Artist::class);
    }

    // Role-based helper methods
    public function isArtist()
    {
        return $this->role === 'artist';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isRegularUser()
    {
        return $this->role === 'user';
    }

    public function canCreateAlbums()
    {
        return $this->isArtist() || $this->isAdmin();
    }

    public function canCreatePlaylists()
    {
        return $this->isRegularUser() || $this->isAdmin();
    }
}
