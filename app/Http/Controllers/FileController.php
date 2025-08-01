<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    /**
     * Serve audio files with CORS headers
     */
    public function serveAudio($filename)
    {
        Log::info('Audio file request received: ' . $filename);
        
        // Check if file exists in storage
        if (!Storage::disk('public')->exists('audios/' . $filename)) {
            Log::error('File not found: audios/' . $filename);
            abort(404);
        }
        
        $file = Storage::disk('public')->get('audios/' . $filename);
        $type = Storage::disk('public')->mimeType('audios/' . $filename);
        
        Log::info('Serving file: audios/' . $filename . ' with type: ' . $type);
        
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
        Log::info('Compressed audio file request received: ' . $filename);
        
        // Check if file exists in storage
        if (!Storage::disk('public')->exists('audios/compressed/' . $filename)) {
            Log::error('File not found: audios/compressed/' . $filename);
            abort(404);
        }
        
        $file = Storage::disk('public')->get('audios/compressed/' . $filename);
        $type = Storage::disk('public')->mimeType('audios/compressed/' . $filename);
        
        Log::info('Serving file: audios/compressed/' . $filename . ' with type: ' . $type);
        
        return response($file, 200)
            ->header('Content-Type', $type)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->header('Cache-Control', 'public, max-age=31536000');
    }

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
} 