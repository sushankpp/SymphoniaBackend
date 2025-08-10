<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Music extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'song_cover_path',
        'file_path',
        'compressed_file_path',
        'cover_image',
        'cover_image_path',
        'artist_id',
        'album_id',
        'genre',
        'description',
        'lyrics',
        'views',
        'release_date',
        'uploaded_by',
    ];


    public function artist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function album(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    /**
     * Get all ratings for this music (handles both full class name and simple type)
     */
    public function getAllRatings()
    {
        return Rating::where('rateable_id', $this->id)
            ->where(function($query) {
                $query->where('rateable_type', 'App\Models\Music')
                      ->orWhere('rateable_type', 'song');
            });
    }

    /**
     * Get the count of all ratings for this music
     */
    public function getAllRatingsCount()
    {
        return $this->getAllRatings()->count();
    }

    /**
     * Get the average rating for this music
     */
    public function getAllRatingsAvg()
    {
        return $this->getAllRatings()->avg('rating') ?? 0;
    }

    public function recentlyPlayed()
    {
        return $this->hasMany(RecentlyPlayed::class, 'song_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
