<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder|static create(array $attributes = [])
 */
class Album extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'cover_image_path', 'artist_id', 'release_date', 'user_id'];

    public function artists()
    {
        return $this->belongsTo(Artist::class, 'artist_id');
    }

    public function songs()
    {
        return $this->hasMany(Music::class, 'album_id');
    }

    public function ratings(){
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
