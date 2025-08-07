<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'playlist_name'];

    public function songs()
    {
        return $this->belongsToMany(Music::class, 'playlist_song', 'playlist_id', 'song_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
