<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecentlyPlayed extends Model
{
    use HasFactory;

    protected $table = 'recently_played';
    protected $fillable = ['user_id', 'song_id'];

    public function song()
    {
        return $this->belongsTo(Music::class, 'song_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
