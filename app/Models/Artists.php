<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Artists extends Model
{
    use HasFactory;

    protected $fillable = [
        'artist_name',
        'artist_image'
    ];

    public function music(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Music::class, 'artist_id', 'id');
    }

    public function albums(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Album::class, 'artist_id');
    }
}
