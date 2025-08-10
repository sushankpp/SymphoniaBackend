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
            return $tfidf_vector;
        } catch (\Exception $e) {
            \Log::error('Error creating song vector', [
                'song_id' => $song->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function calculateTFIDF($features)
    {
        try {
            $total_songs = Music::count();
            $tfidf = [];

            foreach ($features as $feature => $value) {
                try {
                    $tf = $this->normalizeValue($value);
                    $songs_with_feature = $this->countSongsWithFeature($feature, $value);

                    if ($songs_with_feature <= 0) {
                        $idf = 0;
                    } else {
                        $idf = log($total_songs / ($songs_with_feature + 1));
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
        $dot_product = 0;
        $user_magnitude = 0;
        $song_magnitude = 0;

        foreach ($user_vector as $feature => $user_value) {
            $song_value = $song_vector[$feature] ?? 0;
            $dot_product += $user_value * $song_value;
            $user_magnitude += $user_value * $user_value;
            $song_magnitude += $song_value * $song_value;
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
        if (!$user_id) {
            return $this->getGlobalTrendingSongs($limit);
        }

        try {
            $user_vector = $this->getUserPreferenceVector($user_id);

            if (empty($user_vector)) {
                return $this->getGlobalTrendingSongs($limit);
            }

            $excluded_song_ids = $this->getExcludedSongIds($user_id);

            $all_songs = Music::with(['artist', 'ratings'])
                ->whereNotIn('id', $excluded_song_ids)
                ->get();

            $recommendations = [];

            foreach ($all_songs as $song) {
                try {
                    $song_vector = $this->createSongVector($song);
                    $similarity = $this->cosineSimilarity($user_vector, $song_vector);

                    $recommendations[] = [
                        'song' => $song,
                        'similarity_score' => $similarity
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }

            usort($recommendations, function ($a, $b) {
                return $b['similarity_score'] <=> $a['similarity_score'];
            });

            return array_slice($recommendations, 0, $limit);
        } catch (\Exception $e) {
            return $this->getGlobalTrendingSongs($limit);
        }
    }

    public function getTopRecommendations($user_id = null, $limit = 5)
    {
        return $this->getRecommendations($user_id, $limit);
    }

    public function getTopArtists($user_id, $limit = 5)
    {
        try {
            $user_ratings = Rating::where('user_id', $user_id)
                ->where('rateable_type', 'App\Models\Music')
                ->with(['rateable.artist'])
                ->get();

            $recently_played = RecentlyPlayed::where('user_id', $user_id)
                ->with(['song.artist'])
                ->get();

            $artist_scores = [];

            foreach ($user_ratings as $rating) {
                if ($rating->rateable && $rating->rateable->artist) {
                    $artist_id = $rating->rateable->artist->id;
                    $artist_scores[$artist_id] = ($artist_scores[$artist_id] ?? 0) + $rating->rating;
                }
            }

            foreach ($recently_played as $played) {
                if ($played->song && $played->song->artist) {
                    $artist_id = $played->song->artist->id;
                    $artist_scores[$artist_id] = ($artist_scores[$artist_id] ?? 0) + 1;
                }
            }

            arsort($artist_scores);
            $top_artist_ids = array_slice(array_keys($artist_scores), 0, $limit);

            $top_artists = Artist::whereIn('id', $top_artist_ids)
                ->with(['music'])
                ->get()
                ->sortBy(function ($artist) use ($top_artist_ids) {
                    return array_search($artist->id, $top_artist_ids);
                });

            return $top_artists->map(function ($artist) use ($artist_scores) {
                return [
                    'artist' => $artist,
                    'score' => $artist_scores[$artist->id] ?? 0
                ];
            })->toArray();

        } catch (\Exception $e) {
            return $this->getGlobalTopArtists($limit);
        }
    }

    public function getGlobalTopArtists($limit = 5)
    {
        try {
            $artists = Artist::with(['music.ratings'])
                ->get()
                ->map(function ($artist) {
                    $total_views = $artist->music->sum('views');
                    $avg_rating = $artist->music->flatMap->ratings->avg('rating') ?? 0;
                    $song_count = $artist->music->count();

                    $score = ($total_views * 0.4) + ($avg_rating * 20) + ($song_count * 10);

                    return [
                        'artist' => $artist,
                        'score' => $score,
                        'total_views' => $total_views,
                        'avg_rating' => $avg_rating,
                        'song_count' => $song_count
                    ];
                })
                ->sortByDesc('score')
                ->take($limit)
                ->values()
                ->toArray();

            return $artists;
        } catch (\Exception $e) {
            return Artist::with(['music'])
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
        }
    }

    public function getGlobalTrendingSongs($limit = 10)
    {
        try {
            $songs = Music::with(['artist', 'ratings'])
                ->get()
                ->map(function ($song) {
                    $views = $song->views ?? 0;
                    $avg_rating = $song->ratings->avg('rating') ?? 0;
                    $rating_count = $song->ratings->count();

                    $trending_score = ($views * 0.3) + ($avg_rating * 15) + ($rating_count * 5);

                    return [
                        'song' => $song,
                        'trending_score' => $trending_score,
                        'views' => $views,
                        'avg_rating' => $avg_rating,
                        'rating_count' => $rating_count
                    ];
                })
                ->sortByDesc('trending_score')
                ->take($limit)
                ->values()
                ->toArray();

            return $songs;
        } catch (\Exception $e) {
            return Music::with(['artist', 'ratings'])
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($song) {
                    return [
                        'song' => $song,
                        'trending_score' => $song->views ?? 0
                    ];
                })
                ->toArray();
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