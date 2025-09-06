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

    /**
     * Main entry point for recommendations
     */
    public function getRecommendations($user_id = null, $limit = 10)
    {
        if ($user_id) {
            $personalized = $this->getPersonalizedRecommendations($user_id, $limit);

            // Lower threshold: if we have any personalized results, use them
            if (count($personalized) > 0) {
                // If we have fewer personalized results than requested, mix with trending
                if (count($personalized) < $limit) {
                    $trending = $this->getGlobalTrendingSongs($limit);
                    $remaining = $limit - count($personalized);
                    return array_merge(
                        $personalized,
                        array_slice($trending, 0, $remaining)
                    );
                }
                return $personalized;
            }

            // Fallback to trending if no personalized results
            return $this->getGlobalTrendingSongs($limit);
        }

        return $this->getGlobalTrendingSongs($limit);
    }

    private function getPersonalizedRecommendations($user_id, $limit = 10)
    {
        try {
            $user_vector = $this->getUserPreferenceVector($user_id);
            $excluded_ids = $this->getExcludedSongIds($user_id);

            // Candidate pool = all available songs (except excluded)
            $candidates = Music::with(['artist', 'ratings'])
                ->whereNotIn('id', $excluded_ids)
                ->get();

            $scored = [];
            foreach ($candidates as $song) {
                $song_vector = $this->createSongVector($song);
                $similarity = $this->cosineSimilarity($user_vector, $song_vector);

                if ($similarity > 0) {
                    $scored[] = [
                        'song' => $song,
                        'similarity_score' => $similarity
                    ];
                }
            }

            usort($scored, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

            return array_slice($scored, 0, $limit);
        } catch (\Exception $e) {
            \Log::error("Personalized recommendation error: " . $e->getMessage());
            return [];
        }
    }


    public function getTopRecommendations($user_id = null, $limit = 5)
    {
        return $this->getRecommendations($user_id, $limit);
    }

    public function getTopArtists($user_id, $limit = 5)
    {
        return $this->getGlobalTopArtists($limit);
    }

    public function getGlobalTopArtists($limit = 5)
    {
        try {
            $artists = Artist::with(['music'])
                ->get()
                ->sortByDesc(fn($artist) => $artist->music->count())
                ->take($limit)
                ->map(fn($artist) => [
                    'artist' => $artist,
                    'score' => $artist->music->count()
                ])
                ->toArray();

            return $artists;
        } catch (\Exception $e) {
            $artists = Artist::limit($limit)->get()->map(fn($artist) => [
                'artist' => $artist,
                'score' => 1
            ])->toArray();

            return $artists;
        }
    }

    public function getGlobalTrendingSongs($limit = 10)
    {
        // Reduce cache time to 2 minutes for more responsive updates
        $cache_key = "global_trending_songs_{$limit}";
        $cached_songs = Cache::get($cache_key);

        if ($cached_songs) {
            return $cached_songs;
        }

        try {
            $songs = Music::with(['artist', 'ratings'])
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($song) => [
                    'song' => $song,
                    'trending_score' => ($song->views ?? 0) / 10.0
                ])
                ->toArray();

            // Reduced cache time from 600s (10 min) to 120s (2 min)
            Cache::put($cache_key, $songs, 120);
            return $songs;
        } catch (\Exception $e) {
            \Log::error('Global trending songs error: ' . $e->getMessage());

            $songs = Music::with(['artist', 'ratings'])
                ->limit($limit)
                ->get()
                ->map(fn($song) => [
                    'song' => $song,
                    'trending_score' => 0.5
                ])
                ->toArray();

            Cache::put($cache_key, $songs, 120);
            return $songs;
        }
    }

    private function getExcludedSongIds($user_id)
    {
        // Only exclude songs played in the last 30 minutes (reduced from 1 hour)
        $recently_played_songs = RecentlyPlayed::where('user_id', $user_id)
            ->where('created_at', '>', now()->subMinutes(30))
            ->pluck('song_id');

        // Exclude low-rated songs (1-3 stars), but allow 4-5★ to reappear
        $rated_songs = Rating::where('user_id', $user_id)
            ->where('rateable_type', Music::class)
            ->where('rating', '<', 4) // Changed from 5 to 4
            ->pluck('rateable_id');

        return $rated_songs->merge($recently_played_songs)->unique()->toArray();
    }

    private function calculateRatingWeight($rating)
    {
        return pow(2, $rating); // 1★=2, 5★=32
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
        $magnitude = sqrt(array_sum(array_map(fn($val) => $val * $val, $vector)));

        if ($magnitude == 0) {
            return $vector;
        }

        return array_map(fn($val) => $val / $magnitude, $vector);
    }

    private function calculateTimeWeight($created_at)
    {
        $hours_ago = now()->diffInHours($created_at);

        // Slower decay: last 24h counts strongly
        return 1 / (1 + $hours_ago / 12);
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
            return Cache::remember($cache_key, 3600, fn() => Music::where('genre', $value)->count());
        } elseif ($feature === 'era' && is_string($value)) {
            return 1;
        } else {
            $cache_key = "songs_with_{$feature}_{$value}";
            return Cache::remember($cache_key, 3600, fn() => Music::where($feature, $value)->count());
        }
    }

    private function getPopularSongs($limit = 10)
    {
        return Music::with(['artist', 'ratings'])
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($song) => [
                'song' => $song,
                'similarity_score' => 0.5
            ])
            ->toArray();
    }

    /**
     * Clear recommendation cache when user actions occur
     */
    public function clearRecommendationCache($user_id = null)
    {
        // Clear global trending cache
        Cache::forget('global_trending_songs_5');
        Cache::forget('global_trending_songs_10');
        Cache::forget('global_trending_songs_20');
        
        // Clear user-specific cache if provided
        if ($user_id) {
            Cache::forget("user_recommendations_{$user_id}");
            Cache::forget("user_preference_vector_{$user_id}");
        }
        
        // Clear total songs count cache
        Cache::forget('total_songs_count');
    }
}
