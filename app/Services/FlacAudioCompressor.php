<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FlacAudioCompressor
{
    private $tempDir;
    private $blockSize = 1024;
    private $maxLpcOrder = 4;
    private $maxExecutionTime = 30;

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
            Log::info('Starting FLAC-inspired compression', [
                'input' => $inputPath,
                'output' => $outputPath
            ]);

            $startTime = time();
            $fileSize = filesize($inputPath);
            $maxSize = 25 * 1024 * 1024;

            if ($fileSize > $maxSize) {
                Log::info('File too large for compression', [
                    'file_size' => $this->formatBytes($fileSize),
                    'max_size' => $this->formatBytes($maxSize)
                ]);
                return false;
            }

            $rawPath = $this->convertToRawPCM($inputPath);

            if (time() - $startTime > $this->maxExecutionTime) {
                throw new \Exception('Compression timeout during PCM conversion');
            }

            Log::info('Applying FLAC-inspired compression...');
            $compressedData = $this->compressAudioBlocks($rawPath);

            if (time() - $startTime > $this->maxExecutionTime) {
                throw new \Exception('Compression timeout during compression');
            }

            $this->saveCompressedData($compressedData, $outputPath);

            unlink($rawPath);

            $originalSize = filesize($inputPath);
            $compressedSize = filesize($outputPath);
            $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 2);

            Log::info('FLAC-inspired compression completed', [
                'original_size' => $this->formatBytes($originalSize),
                'compressed_size' => $this->formatBytes($compressedSize),
                'compression_ratio' => $compressionRatio . '%',
                'time_taken' => (time() - $startTime) . 's'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FLAC-inspired compression failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function decompress($inputPath, $outputPath)
    {
        try {
            Log::info('Starting FLAC-inspired decompression');

            $compressedData = $this->loadCompressedData($inputPath);
            $rawPath = $this->decompressAudioBlocks($compressedData);
            $this->convertRawToAudio($rawPath, $outputPath, $compressedData['metadata']);

            unlink($rawPath);
            return true;
        } catch (\Exception $e) {
            Log::error('FLAC-inspired decompression failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function convertToRawPCM($inputPath)
    {
        $rawPath = $this->tempDir . '/temp_raw_' . uniqid() . '.raw';

        $command = sprintf(
            'ffmpeg -i %s -f s16le -acodec pcm_s16le -ar 44100 -ac 2 %s -y 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($rawPath)
        );

        Log::info('FFmpeg command', ['command' => $command]);

        exec($command, $output, $returnVar);

        if ($returnVar !== 0 || !file_exists($rawPath)) {
            Log::error('FFmpeg conversion failed', [
                'return_code' => $returnVar,
                'output' => $output,
                'input_path' => $inputPath,
                'output_path' => $rawPath
            ]);
            throw new \Exception("Failed to convert to raw PCM: " . implode("\n", $output));
        }

        Log::info('FFmpeg conversion successful', [
            'input_size' => $this->formatBytes(filesize($inputPath)),
            'output_size' => $this->formatBytes(filesize($rawPath))
        ]);

        return $rawPath;
    }

    private function compressAudioBlocks($rawPath)
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
                'block_size' => $this->blockSize,
                'algorithm' => 'FLAC-inspired Linear Prediction + Rice Coding'
            ],
            'blocks' => []
        ];

        $totalBytes = filesize($rawPath);
        $processedBytes = 0;
        $lastProgress = 0;
        $blockCount = 0;
        $startTime = time(); // Add start time for timeout checking

        Log::info('Starting block-based compression...');

        while (!feof($handle)) {
            // Check timeout
            if (time() - $startTime > $this->maxExecutionTime) {
                Log::warning('Compression timeout, stopping early');
                break;
            }

            // Read block of samples
            $blockBytes = $this->blockSize * 2 * 2; // block_size * 2_bytes_per_sample * 2_channels
            $chunk = fread($handle, $blockBytes);

            if ($chunk === false || empty($chunk))
                break;

            // Convert to samples
            $samples = $this->bytesToSamples($chunk);

            if (count($samples) === 0)
                break;

            // Compress this block using FLAC-inspired method
            $compressedBlock = $this->compressBlock($samples, $blockCount);
            $compressedData['blocks'][] = $compressedBlock;

            $processedBytes += strlen($chunk);
            $blockCount++;

            $progress = intval(($processedBytes / $totalBytes) * 100);
            if ($progress >= $lastProgress + 5) { // More frequent progress updates
                Log::info("Compression progress: {$progress}% (Block {$blockCount})");
                $lastProgress = $progress;
            }
        }

        fclose($handle);

        Log::info('Block compression completed', [
            'blocks_created' => count($compressedData['blocks'])
        ]);

        return $compressedData;
    }

    private function bytesToSamples($chunk)
    {
        $samples = [];
        $length = strlen($chunk);

        for ($i = 0; $i < $length - 1; $i += 2) {
            if ($i + 1 >= $length)
                break;

            $sample = unpack('s', substr($chunk, $i, 2))[1];
            $samples[] = $sample;
        }

        return $samples;
    }

    private function compressBlock($samples, $blockIndex)
    {
        $sampleCount = count($samples);

        if ($sampleCount === 0) {
            return ['method' => 'empty', 'data' => [], 'sample_count' => 0];
        }

        // Use only constant prediction for speed
        $chosen = $this->tryConstantPrediction($samples);

        // Apply Rice coding to residuals
        $riceEncoded = $this->riceEncode($chosen['residuals']);

        return [
            'method' => 'constant',
            'data' => $riceEncoded,
            'coefficients' => $chosen['coefficients'] ?? [],
            'sample_count' => $sampleCount,
            'original_size' => $sampleCount * 2, // 2 bytes per sample
            'compressed_size' => strlen(serialize($riceEncoded))
        ];
    }

    private function tryConstantPrediction($samples)
    {
        // Simple: predict each sample as the previous sample
        $residuals = [];
        $previous = 0;

        foreach ($samples as $sample) {
            $residuals[] = $sample - $previous;
            $previous = $sample;
        }

        return ['residuals' => $residuals, 'coefficients' => []];
    }

    private function tryLinearPrediction($samples)
    {
        // Linear prediction: predict based on linear combination of previous samples
        $residuals = [];
        $order = min(4, count($samples) - 1);

        for ($i = 0; $i < count($samples); $i++) {
            if ($i < $order) {
                $residuals[] = $samples[$i]; // No prediction for first samples
            } else {
                // Simple linear prediction: average of previous 'order' samples
                $prediction = 0;
                for ($j = 1; $j <= $order; $j++) {
                    $prediction += $samples[$i - $j];
                }
                $prediction = intval($prediction / $order);
                $residuals[] = $samples[$i] - $prediction;
            }
        }

        return ['residuals' => $residuals, 'coefficients' => ['order' => $order]];
    }

    private function tryLpcPrediction($samples)
    {
        // Simplified LPC: Use fixed coefficients for efficiency
        $residuals = [];
        $order = min($this->maxLpcOrder, count($samples) - 1);

        // Use simple fixed coefficients (in real FLAC these are calculated)
        $coefficients = [];
        for ($i = 0; $i < $order; $i++) {
            $coefficients[] = 1.0 / ($i + 2); // Simple decreasing weights
        }

        for ($i = 0; $i < count($samples); $i++) {
            if ($i < $order) {
                $residuals[] = $samples[$i];
            } else {
                $prediction = 0;
                for ($j = 0; $j < $order; $j++) {
                    $prediction += $coefficients[$j] * $samples[$i - $j - 1];
                }
                $residuals[] = $samples[$i] - intval($prediction);
            }
        }

        return ['residuals' => $residuals, 'coefficients' => ['order' => $order, 'coeff' => $coefficients]];
    }

    private function riceEncode($residuals)
    {
        // Simplified Rice/Golomb coding
        // In practice, you'd choose optimal Rice parameter
        $riceParameter = $this->calculateOptimalRiceParameter($residuals);
        $encoded = [];

        foreach ($residuals as $residual) {
            $encoded[] = $this->riceEncodeValue($residual, $riceParameter);
        }

        return [
            'parameter' => $riceParameter,
            'values' => $encoded,
            'count' => count($residuals)
        ];
    }

    private function calculateOptimalRiceParameter($residuals)
    {
        // Calculate optimal Rice parameter based on residual distribution
        $absSum = array_sum(array_map('abs', $residuals));
        $count = count($residuals);
        $mean = $count > 0 ? $absSum / $count : 1;

        // Rice parameter estimation
        return max(1, min(15, intval(log($mean * 2) / log(2))));
    }

    private function riceEncodeValue($value, $parameter)
    {
        // Simplified Rice encoding (store as array for simplicity)
        // In real implementation, this would be packed into bits
        $absValue = abs($value);
        $quotient = intval($absValue / (1 << $parameter));
        $remainder = $absValue % (1 << $parameter);
        $sign = $value < 0 ? 1 : 0;

        return [
            'quotient' => $quotient,
            'remainder' => $remainder,
            'sign' => $sign
        ];
    }

    private function saveCompressedData($compressedData, $outputPath)
    {
        $handle = fopen($outputPath, 'wb');
        if (!$handle) {
            throw new \Exception("Cannot create compressed file");
        }

        // Use JSON for simplicity (in production, use binary format)
        $jsonData = json_encode($compressedData, JSON_UNESCAPED_SLASHES);

        // Write size header then data
        fwrite($handle, pack('N', strlen($jsonData)));
        fwrite($handle, $jsonData);
        fclose($handle);
    }

    private function loadCompressedData($inputPath)
    {
        $handle = fopen($inputPath, 'rb');
        if (!$handle) {
            throw new \Exception("Cannot open compressed file");
        }

        $sizeData = fread($handle, 4);
        if (strlen($sizeData) < 4) {
            throw new \Exception("Invalid compressed file format");
        }

        $size = unpack('N', $sizeData)[1];
        $jsonData = fread($handle, $size);
        fclose($handle);

        $data = json_decode($jsonData, true);
        if (!$data) {
            throw new \Exception("Invalid compressed data");
        }

        return $data;
    }

    private function decompressAudioBlocks($compressedData)
    {
        $rawPath = $this->tempDir . '/decompressed_raw_' . uniqid() . '.raw';
        $handle = fopen($rawPath, 'wb');

        if (!$handle) {
            throw new \Exception("Cannot create decompressed file");
        }

        $totalBlocks = count($compressedData['blocks']);
        $processedBlocks = 0;

        Log::info('Starting block decompression...');

        foreach ($compressedData['blocks'] as $blockData) {
            $samples = $this->decompressBlock($blockData);

            // Convert samples back to bytes
            $binaryData = '';
            foreach ($samples as $sample) {
                $sample = max(-32768, min(32767, intval($sample)));
                $binaryData .= pack('s', $sample);
            }

            fwrite($handle, $binaryData);

            $processedBlocks++;
            if ($processedBlocks % 50 === 0) {
                $progress = round(($processedBlocks / $totalBlocks) * 100);
                Log::info("Decompression progress: {$progress}%");
            }
        }

        fclose($handle);
        return $rawPath;
    }

    private function decompressBlock($blockData)
    {
        // Rice decode first
        $residuals = $this->riceDecode($blockData['data']);

        // Apply inverse prediction based on method used
        switch ($blockData['method']) {
            case 'constant':
                return $this->inverseConstantPrediction($residuals);
            case 'linear':
                return $this->inverseLinearPrediction($residuals, $blockData['coefficients']);
            case 'lpc':
                return $this->inverseLpcPrediction($residuals, $blockData['coefficients']);
            default:
                return $residuals;
        }
    }

    private function riceDecode($riceData)
    {
        $decoded = [];
        $parameter = $riceData['parameter'];

        foreach ($riceData['values'] as $encodedValue) {
            $absValue = ($encodedValue['quotient'] << $parameter) + $encodedValue['remainder'];
            $value = $encodedValue['sign'] ? -$absValue : $absValue;
            $decoded[] = $value;
        }

        return $decoded;
    }

    private function inverseConstantPrediction($residuals)
    {
        $samples = [];
        $previous = 0;

        foreach ($residuals as $residual) {
            $sample = $residual + $previous;
            $samples[] = $sample;
            $previous = $sample;
        }

        return $samples;
    }

    private function inverseLinearPrediction($residuals, $coefficients)
    {
        $samples = [];
        $order = $coefficients['order'] ?? 4;

        for ($i = 0; $i < count($residuals); $i++) {
            if ($i < $order) {
                $samples[] = $residuals[$i];
            } else {
                $prediction = 0;
                for ($j = 1; $j <= $order; $j++) {
                    $prediction += $samples[$i - $j];
                }
                $prediction = intval($prediction / $order);
                $samples[] = $residuals[$i] + $prediction;
            }
        }

        return $samples;
    }

    private function inverseLpcPrediction($residuals, $coefficients)
    {
        $samples = [];
        $order = $coefficients['order'] ?? 4;
        $coeff = $coefficients['coeff'] ?? [];

        for ($i = 0; $i < count($residuals); $i++) {
            if ($i < $order) {
                $samples[] = $residuals[$i];
            } else {
                $prediction = 0;
                for ($j = 0; $j < $order; $j++) {
                    if (isset($coeff[$j])) {
                        $prediction += $coeff[$j] * $samples[$i - $j - 1];
                    }
                }
                $samples[] = $residuals[$i] + intval($prediction);
            }
        }

        return $samples;
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