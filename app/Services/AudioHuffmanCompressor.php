<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Optimized Audio Compression using Adaptive Delta Compression
 * More suitable for audio than Huffman coding
 */
class AudioHuffmanCompressor
{
    private $tempDir;
    private $chunkSize = 65536; // 64KB chunks
    private $maxExecutionTime = 30; // 30 seconds max

    public function __construct()
    {
        $this->tempDir = storage_path('app/temp/audio_compression');
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    public function compress($inputPath, $outputPath)
    {
        try {
            Log::info('Starting optimized audio compression', [
                'input' => $inputPath,
                'output' => $outputPath
            ]);

            $startTime = time();
            $fileSize = filesize($inputPath);
            $maxSize = 50 * 1024 * 1024; // 50MB max (increased limit)
            
            if ($fileSize > $maxSize) {
                Log::info('File too large for compression', [
                    'file_size' => $this->formatBytes($fileSize),
                    'max_size' => $this->formatBytes($maxSize)
                ]);
                return false;
            }

            // Step 1: Convert to raw PCM
            $rawPath = $this->convertToRawPCM($inputPath);
            
            if (time() - $startTime > $this->maxExecutionTime) {
                throw new \Exception('Compression timeout during PCM conversion');
            }

            // Step 2: Apply advanced compression algorithm
            Log::info('Applying compression algorithm...');
            $compressedData = $this->compressAudioData($rawPath);
            
            if (time() - $startTime > $this->maxExecutionTime) {
                throw new \Exception('Compression timeout during compression');
            }

            // Step 3: Save compressed data
            $this->saveCompressedData($compressedData, $outputPath);

            // Step 4: Convert to streamable format
            $this->createStreamableVersion($outputPath);

            // Cleanup
            unlink($rawPath);

            $originalSize = filesize($inputPath);
            $compressedSize = filesize($outputPath);
            $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 2);

            Log::info('Audio compression completed', [
                'original_size' => $this->formatBytes($originalSize),
                'compressed_size' => $this->formatBytes($compressedSize),
                'compression_ratio' => $compressionRatio . '%',
                'time_taken' => (time() - $startTime) . 's'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Audio compression failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function decompress($inputPath, $outputPath)
    {
        try {
            Log::info('Starting audio decompression');

            // Load compressed data
            $compressedData = $this->loadCompressedData($inputPath);

            // Decompress audio data
            $rawPath = $this->decompressAudioData($compressedData);

            // Convert to final audio format
            $this->convertRawToAudio($rawPath, $outputPath, $compressedData['metadata']);

            // Cleanup
            unlink($rawPath);

            return true;
        } catch (\Exception $e) {
            Log::error('Audio decompression failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function convertToRawPCM($inputPath)
    {
        $rawPath = $this->tempDir . '/temp_raw_' . uniqid() . '.raw';

        // Use optimal settings for compression
        $command = sprintf(
            'ffmpeg -i "%s" -f s16le -acodec pcm_s16le -ar 44100 -ac 2 "%s" -y 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($rawPath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0 || !file_exists($rawPath)) {
            throw new \Exception("Failed to convert to raw PCM: " . implode("\n", $output));
        }

        return $rawPath;
    }

    private function compressAudioData($rawPath)
    {
        $handle = fopen($rawPath, 'rb');
        if (!$handle) {
            throw new \Exception("Cannot open raw file for reading");
        }

        $compressedData = [
            'metadata' => [
                'sample_rate' => 44100,
                'channels' => 2,
                'bits_per_sample' => 16,
                'algorithm' => 'Adaptive Delta + RLE'
            ],
            'blocks' => []
        ];

        $totalBytes = filesize($rawPath);
        $processedBytes = 0;
        $lastProgress = 0;
        $previousSample = 0;

        Log::info('Starting audio data compression...');

        while (!feof($handle)) {
            $chunk = fread($handle, $this->chunkSize);
            if ($chunk === false || empty($chunk)) break;

            $compressedBlock = $this->compressBlock($chunk, $previousSample);
            $compressedData['blocks'][] = $compressedBlock;
            
            // Update previous sample for next block
            if (!empty($compressedBlock['samples'])) {
                $lastSamples = array_slice($compressedBlock['samples'], -1);
                if (!empty($lastSamples)) {
                    $previousSample = $this->reconstructSample($lastSamples[0], $previousSample);
                }
            }

            $processedBytes += strlen($chunk);
            $progress = intval(($processedBytes / $totalBytes) * 100);

            if ($progress >= $lastProgress + 10) {
                Log::info("Compression progress: {$progress}%");
                $lastProgress = $progress;
            }
        }

        fclose($handle);
        
        Log::info('Audio compression completed', [
            'blocks_created' => count($compressedData['blocks'])
        ]);

        return $compressedData;
    }

    private function compressBlock($chunk, $previousSample)
    {
        $samples = [];
        $deltas = [];
        
        // Convert bytes to 16-bit samples
        for ($i = 0; $i < strlen($chunk) - 1; $i += 2) {
            if ($i + 1 >= strlen($chunk)) break;
            
            $sample = unpack('s', substr($chunk, $i, 2))[1];
            $samples[] = $sample;
            
            // Calculate delta (difference from previous sample)
            $delta = $sample - $previousSample;
            $deltas[] = $delta;
            $previousSample = $sample;
        }

        // Apply Run Length Encoding to deltas
        $compressedDeltas = $this->runLengthEncode($deltas);
        
        return [
            'samples' => $compressedDeltas,
            'block_size' => count($samples)
        ];
    }

    private function runLengthEncode($data)
    {
        if (empty($data)) return [];
        
        $encoded = [];
        $currentValue = $data[0];
        $count = 1;
        
        for ($i = 1; $i < count($data); $i++) {
            if ($data[$i] === $currentValue && $count < 255) {
                $count++;
            } else {
                $encoded[] = ['value' => $currentValue, 'count' => $count];
                $currentValue = $data[$i];
                $count = 1;
            }
        }
        
        // Add the last run
        $encoded[] = ['value' => $currentValue, 'count' => $count];
        
        return $encoded;
    }

    private function runLengthDecode($encodedData)
    {
        $decoded = [];
        
        foreach ($encodedData as $run) {
            for ($i = 0; $i < $run['count']; $i++) {
                $decoded[] = $run['value'];
            }
        }
        
        return $decoded;
    }

    private function reconstructSample($deltaRun, $previousSample)
    {
        return $previousSample + $deltaRun['value'];
    }

    private function saveCompressedData($compressedData, $outputPath)
    {
        $handle = fopen($outputPath, 'wb');
        if (!$handle) {
            throw new \Exception("Cannot create compressed file");
        }

        // Create optimized binary format
        $binaryData = $this->serializeToBinary($compressedData);
        
        fwrite($handle, $binaryData);
        fclose($handle);
    }

    private function serializeToBinary($data)
    {
        // Use efficient binary serialization instead of JSON
        $binary = '';
        
        // Write metadata
        $metadata = $data['metadata'];
        $binary .= pack('N', $metadata['sample_rate']);
        $binary .= pack('n', $metadata['channels']);
        $binary .= pack('n', $metadata['bits_per_sample']);
        
        // Write number of blocks
        $binary .= pack('N', count($data['blocks']));
        
        // Write blocks
        foreach ($data['blocks'] as $block) {
            $binary .= pack('N', $block['block_size']);
            $binary .= pack('N', count($block['samples']));
            
            foreach ($block['samples'] as $run) {
                $binary .= pack('s', $run['value']); // 16-bit signed
                $binary .= pack('C', $run['count']); // 8-bit unsigned
            }
        }
        
        return $binary;
    }

    private function loadCompressedData($inputPath)
    {
        $handle = fopen($inputPath, 'rb');
        if (!$handle) {
            throw new \Exception("Cannot open compressed file");
        }

        $data = $this->deserializeFromBinary($handle);
        fclose($handle);
        
        return $data;
    }

    private function deserializeFromBinary($handle)
    {
        // Read metadata
        $sampleRate = unpack('N', fread($handle, 4))[1];
        $channels = unpack('n', fread($handle, 2))[1];
        $bitsPerSample = unpack('n', fread($handle, 2))[1];
        
        $metadata = [
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bits_per_sample' => $bitsPerSample
        ];
        
        // Read number of blocks
        $numBlocks = unpack('N', fread($handle, 4))[1];
        
        $blocks = [];
        for ($b = 0; $b < $numBlocks; $b++) {
            $blockSize = unpack('N', fread($handle, 4))[1];
            $numRuns = unpack('N', fread($handle, 4))[1];
            
            $samples = [];
            for ($r = 0; $r < $numRuns; $r++) {
                $value = unpack('s', fread($handle, 2))[1];
                $count = unpack('C', fread($handle, 1))[1];
                $samples[] = ['value' => $value, 'count' => $count];
            }
            
            $blocks[] = [
                'samples' => $samples,
                'block_size' => $blockSize
            ];
        }
        
        return [
            'metadata' => $metadata,
            'blocks' => $blocks
        ];
    }

    private function decompressAudioData($compressedData)
    {
        $rawPath = $this->tempDir . '/decompressed_raw_' . uniqid() . '.raw';
        $handle = fopen($rawPath, 'wb');
        
        if (!$handle) {
            throw new \Exception("Cannot create decompressed file");
        }

        $previousSample = 0;
        $totalBlocks = count($compressedData['blocks']);
        $processedBlocks = 0;

        Log::info('Starting audio decompression...');

        foreach ($compressedData['blocks'] as $block) {
            // Decompress deltas using RLE
            $deltas = $this->runLengthDecode($block['samples']);
            
            // Reconstruct original samples from deltas
            $binaryData = '';
            foreach ($deltas as $delta) {
                $sample = $previousSample + $delta;
                $sample = max(-32768, min(32767, $sample)); // Clamp to 16-bit range
                $binaryData .= pack('s', $sample);
                $previousSample = $sample;
            }
            
            fwrite($handle, $binaryData);
            
            $processedBlocks++;
            if ($processedBlocks % 10 === 0) {
                $progress = round(($processedBlocks / $totalBlocks) * 100);
                Log::info("Decompression progress: {$progress}%");
            }
        }

        fclose($handle);
        return $rawPath;
    }

    private function createStreamableVersion($compressedPath)
    {
        // For now, keep the compressed version as-is
        // In a real implementation, you might want to create a separate streamable format
        Log::info('Compressed file is ready for streaming');
    }

    private function convertRawToAudio($rawPath, $outputPath, $metadata)
    {
        $sampleRate = $metadata['sample_rate'] ?? 44100;
        $channels = $metadata['channels'] ?? 2;

        $command = sprintf(
            'ffmpeg -f s16le -ar %d -ac %d -i "%s" -c:a aac -b:a 192k "%s" -y 2>&1',
            $sampleRate,
            $channels,
            escapeshellarg($rawPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0 || !file_exists($outputPath)) {
            throw new \Exception("Failed to convert raw to audio: " . implode("\n", $output));
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

