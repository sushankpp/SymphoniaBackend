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
        'artist_id',
        'album_id',
        'genre',
        'description',
        'lyrics',
        'views',
        'release_date',
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

    public function recentlyPlayed()
    {
        return $this->hasMany(RecentlyPlayed::class, 'song_id');
    }
}
