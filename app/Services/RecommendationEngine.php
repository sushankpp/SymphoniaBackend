<?php

namespace App\Services;

use App\Models\Music;
use App\Models\RecentlyPlayed;
use App\Models\Rating;
use App\Models\Artist;
use Illuminate\Support\Facades\Cache;

class RecommendationEngine
{
    public function createSongVector($song)
    {
        try {
            $features = [
                'genre' => $song->genre ?? '',
                'artist_popularity' => $song->artist ? $song->artist->music()->count() : 0,
                'avg_rating' => $song->ratings ? $song->ratings->avg('rating') : 0,
                'view_count' => $song->views ?? 0,
                'era' => $this->extractEra($song->created_at)
            ];

            $tfidf_vector = $this->calculateTFIDF($features);

            return $this->normalizeVector($tfidf_vector);
        } catch (\Exception $e) {
            \Log::error('Error creating song vector', [
                'song_id' => $song->id ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function calculateTFIDF($features)
    {
        try {
            // Cache total songs count for better performance
            $total_songs = Cache::remember('total_songs_count', 3600, function () {
                return max(1, Music::count());
            });
            $tfidf = [];

            foreach ($features as $feature => $value) {
                try {
                    $tf = $this->normalizeValue($value);
                    $songs_with_feature = $this->countSongsWithFeature($feature, $value);

                    if ($songs_with_feature <= 0) {
                        $idf = 0;
                    } else {
                        $idf = log($total_songs / ($songs_with_feature + 1));

                        $idf = max(0, min($idf, 5));
                    }

                    $tfidf[$feature] = $tf * $idf;
                } catch (\Exception $e) {
                    $tfidf[$feature] = 0;
                }
            }
            return $tfidf;
        } catch (\Exception $e) {
            return array_fill_keys(array_keys($features), 0);
        }
    }

    public function getUserPreferenceVector($user_id)
    {
        $user_vector = [];

        $session_songs = RecentlyPlayed::where('user_id', $user_id)
            ->with(['song.artist', 'song.ratings'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $user_ratings = Rating::where('user_id', $user_id)
            ->where('rateable_type', 'App\Models\Music')
            ->with(['rateable.artist', 'rateable.ratings'])
            ->get();

        foreach ($session_songs as $sessionSong) {
            try {
                $song = $sessionSong->song;
                if (!$song)
                    continue;

                $song_vector = $this->createSongVector($song);
                $weight = $this->calculateTimeWeight($sessionSong->created_at);

                foreach ($song_vector as $feature => $value) {
                    $user_vector[$feature] = ($user_vector[$feature] ?? 0) + $value * $weight;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        foreach ($user_ratings as $rating) {
            try {
                $song = $rating->rateable;
                if (!$song)
                    continue;

                $song_vector = $this->createSongVector($song);
                $weight = $this->calculateRatingWeight($rating->rating);

                foreach ($song_vector as $feature => $value) {
                    $user_vector[$feature] = ($user_vector[$feature] ?? 0) + $value * $weight;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $this->normalizeVector($user_vector);
    }

    public function cosineSimilarity($user_vector, $song_vector)
    {
        $user_vector = $this->normalizeVector($user_vector);
        $song_vector = $this->normalizeVector($song_vector);

        $dot_product = 0;
        $user_magnitude = 0;
        $song_magnitude = 0;

        $all_features = array_unique(array_merge(array_keys($user_vector), array_keys($song_vector)));

        foreach ($all_features as $feature) {
            $user_value = $user_vector[$feature] ?? 0;
            $song_value = $song_vector[$feature] ?? 0;
            $dot_product += $user_value * $song_value;
            $user_magnitude += $user_value ** 2;
            $song_magnitude += $song_value ** 2;
        }

        $user_magnitude = sqrt($user_magnitude);
        $song_magnitude = sqrt($song_magnitude);

        if ($user_magnitude == 0 || $song_magnitude == 0) {
            return 0;
        }

        return $dot_product / ($user_magnitude * $song_magnitude);
    }

    public function getRecommendations($user_id = null, $limit = 10)
    {
        // Always return trending songs for now - simple and reliable
                return $this->getGlobalTrendingSongs($limit);
    }

    public function getTopRecommendations($user_id = null, $limit = 5)
    {
        return $this->getRecommendations($user_id, $limit);
    }

    public function getTopArtists($user_id, $limit = 5)
    {
        // Always return global top artists for now - simple and reliable
        return $this->getGlobalTopArtists($limit);
    }

    public function getGlobalTopArtists($limit = 5)
    {
        try {
            // Simple approach: just get artists with most songs
            $artists = Artist::with(['music'])
                ->get()
                ->sortByDesc(function ($artist) {
                    return $artist->music->count();
                })
                ->take($limit)
                ->map(function ($artist) {
                    return [
                        'artist' => $artist,
                        'score' => $artist->music->count()
                    ];
                })
                ->toArray();

            return $artists;
        } catch (\Exception $e) {
            // Ultimate fallback - just return any artists
            $artists = Artist::limit($limit)->get()->map(function ($artist) {
                return [
                    'artist' => $artist,
                    'score' => 1
                ];
            })->toArray();

            return $artists;
        }
    }

    public function getGlobalTrendingSongs($limit = 10)
    {
        // Cache global trending songs for 10 minutes
        $cache_key = "global_trending_songs_{$limit}";
        $cached_songs = Cache::get($cache_key);
        
        if ($cached_songs) {
            return $cached_songs;
        }

        try {
            // Simple and fast: just get songs ordered by views
            $songs = Music::with(['artist', 'ratings'])
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($song) {
                    return [
                        'song' => $song,
                        'trending_score' => ($song->views ?? 0) / 10.0 // Simple normalized score
                    ];
                })
                ->toArray();

            // Cache the results for 10 minutes
            Cache::put($cache_key, $songs, 600);
            
            return $songs;
        } catch (\Exception $e) {
            \Log::error('Global trending songs error: ' . $e->getMessage());
            // Ultimate fallback - just return any songs
            $songs = Music::with(['artist', 'ratings'])
                ->limit($limit)
                ->get()
                ->map(function ($song) {
                    return [
                        'song' => $song,
                        'trending_score' => 0.5
                    ];
                })
                ->toArray();

            Cache::put($cache_key, $songs, 600);
            return $songs;
        }
    }

    private function getExcludedSongIds($user_id)
    {
        $rated_songs = Rating::where('user_id', $user_id)
            ->where('rateable_type', 'App\Models\Music')
            ->pluck('rateable_id');

        $recently_played_songs = RecentlyPlayed::where('user_id', $user_id)
            ->pluck('song_id');

        return $rated_songs->merge($recently_played_songs)->unique()->toArray();
    }

    private function calculateRatingWeight($rating)
    {
        return $rating / 5.0 * 2.0;
    }

    private function normalizeValue($value)
    {
        if (is_string($value)) {
            $value = strlen($value);
        } elseif (is_null($value)) {
            $value = 0;
        } else {
            $value = (float) $value;
        }

        return min(1, max(0, $value / 100));
    }

    private function normalizeVector($vector)
    {
        $magnitude = sqrt(array_sum(array_map(function ($val) {
            return $val * $val;
        }, $vector)));

        if ($magnitude == 0) {
            return $vector;
        }

        return array_map(function ($val) use ($magnitude) {
            return $val / $magnitude;
        }, $vector);
    }

    private function calculateTimeWeight($created_at)
    {
        $hours_ago = now()->diffInHours($created_at);
        return exp(-$hours_ago / 24);
    }

    private function extractEra($date)
    {
        $year = $date->year;
        if ($year >= 2020)
            return '2020s';
        if ($year >= 2010)
            return '2010s';
        if ($year >= 2000)
            return '2000s';
        if ($year >= 1990)
            return '1990s';
        if ($year >= 1980)
            return '1980s';
        return 'older';
    }

    private function countSongsWithFeature($feature, $value)
    {
        if ($feature === 'genre' && is_string($value)) {
            $cache_key = "songs_with_genre_" . md5($value);
            return Cache::remember($cache_key, 3600, function () use ($value) {
                return Music::where('genre', $value)->count();
            });
        } elseif ($feature === 'era' && is_string($value)) {
            return 1;
        } else {
            $cache_key = "songs_with_{$feature}_{$value}";
            return Cache::remember($cache_key, 3600, function () use ($feature, $value) {
                return Music::where($feature, $value)->count();
            });
        }
    }

    private function getPopularSongs($limit = 10)
    {
        return Music::with(['artist', 'ratings'])
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($song) {
                return [
                    'song' => $song,
                    'similarity_score' => 0.5
                ];
            })
            ->toArray();
    }
}