<?php

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get parameters
$file = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'audio';

// Validate file parameter
if (empty($file)) {
    http_response_code(400);
    echo 'File parameter is required';
    exit;
}

// Security: prevent directory traversal
$file = basename($file);

// Determine file path based on type
if ($type === 'cover') {
    $filePath = __DIR__ . '/storage/songs_cover/' . $file;
    $contentType = 'image/jpeg'; // Default to JPEG
} else {
    $filePath = __DIR__ . '/storage/audios/compressed/' . $file;
    $contentType = 'audio/mpeg'; // Default to MP3
}

// Check if file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Set appropriate content type based on file extension
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
switch ($extension) {
    case 'jpg':
    case 'jpeg':
        $contentType = 'image/jpeg';
        break;
    case 'png':
        $contentType = 'image/png';
        break;
    case 'gif':
        $contentType = 'image/gif';
        break;
    case 'mp3':
        $contentType = 'audio/mpeg';
        break;
    case 'm4a':
        $contentType = 'audio/mp4';
        break;
    case 'wav':
        $contentType = 'audio/wav';
        break;
    case 'ogg':
        $contentType = 'audio/ogg';
        break;
}

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=31536000');

// Output file
readfile($filePath);
