<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AudioController extends Controller
{
    /**
     * Handle OPTIONS requests for CORS preflight
     */
    public function handleOptions()
    {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->header('Access-Control-Max-Age', '86400');
    }

    /**
     * Serve audio files with CORS headers
     */
    public function serveAudio($filename)
    {
        $path = 'audios/' . $filename;
        
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        
        $file = Storage::disk('public')->get($path);
        $type = Storage::disk('public')->mimeType($path);
        
        return response($file, 200)
            ->header('Content-Type', $type)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->header('Cache-Control', 'public, max-age=31536000');
    }

    /**
     * Serve compressed audio files with CORS headers
     */
    public function serveCompressedAudio($filename)
    {
        $path = 'audios/compressed/' . $filename;
        
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        
        $file = Storage::disk('public')->get($path);
        $type = Storage::disk('public')->mimeType($path);
        
        return response($file, 200)
            ->header('Content-Type', $type)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->header('Cache-Control', 'public, max-age=31536000');
    }
} 