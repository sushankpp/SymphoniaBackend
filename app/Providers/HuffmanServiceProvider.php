<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AudioHuffmanCompressor;

class HuffmanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(AudioHuffmanCompressor::class, function ($app) {
            return new AudioHuffmanCompressor();
        });
    }
}