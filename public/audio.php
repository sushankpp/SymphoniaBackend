<?php
// Simple file server with CORS headers for audio files, cover images, and artist images
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$filename = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'normal';

if (empty($filename)) {
    http_response_code(404);
    exit('File not found');
}

$basePath = '../storage/app/public/';
if ($type === 'compressed') {
    $basePath .= 'audios/compressed/';
} elseif ($type === 'audio') {
    $basePath .= 'audios/';
} elseif ($type === 'cover') {
    $basePath .= 'songs_cover/';
} elseif ($type === 'artist' || $type === 'artist_image') {
    $basePath .= 'artist_image/';
} else {
    $basePath .= 'audios/';
}

$filePath = $basePath . $filename;

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

$mimeType = mime_content_type($filePath);
$fileSize = filesize($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=31536000');

readfile($filePath);