<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadedMusic extends Model
{
    use HasFactory;

    protected $fillable = [
        'music_id',
        'uploaded_by',
        'uploaded_at',
    ];

    public function music()
    {
        return $this->belongsTo(Music::class);
    }
}
