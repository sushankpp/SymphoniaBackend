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
    private $chunkSize = 65536;
    private $maxExecutionTime = 30;

    public function __construct()
    {
        $this->tempDir = storage_path('app/temp/audio_compression');
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    private function le16_to_signed($val)
    {
        if ($val & 0x8000) {
            return $val - 0x10000;
        }
        return $val;
    }

    private function signed_to_le16($val)
    {
        return $val & 0xFFFF;
    }

    private function u32_to_signed($u)
    {
        if ($u & 0x80000000)
            return $u - 0x100000000;
        return $u;
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
            $maxSize = 50 * 1024 * 1024;

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

            Log::info('Applying compression algorithm...');
            $compressedData = $this->compressAudioData($rawPath);

            if (time() - $startTime > $this->maxExecutionTime) {
                throw new \Exception('Compression timeout during compression');
            }

            $this->saveCompressedData($compressedData, $outputPath);

            $this->createStreamableVersion($outputPath);

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

            $compressedData = $this->loadCompressedData($inputPath);

            $rawPath = $this->decompressAudioData($compressedData);

            $this->convertRawToAudio($rawPath, $outputPath, $compressedData['metadata']);

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
        $previousSamples = [0, 0];

        Log::info('Starting audio data compression...');

        while (!feof($handle)) {
            $chunk = fread($handle, $this->chunkSize);
            if ($chunk === false || empty($chunk))
                break;

            $compressedBlock = $this->compressBlock($chunk, $previousSamples);
            $compressedData['blocks'][] = $compressedBlock;

            if (isset($compressedBlock['last_samples'])) {
                $previousSamples = $compressedBlock['last_samples'];
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

    private function compressBlock($chunk, $previousSamples)
    {
        $samplesPerChannel = [];
        $deltasPerChannel = [];

        $len = strlen($chunk);
        $channels = 2;

        for ($i = 0; $i < $len - 1; $i += 2 * $channels) {
            if ($i + 2 * ($channels - 1) + 1 >= $len)
                break;

            for ($ch = 0; $ch < $channels; $ch++) {
                $offset = $i + ($ch * 2);
                $raw = substr($chunk, $offset, 2);
                $u = unpack('v', $raw)[1];
                $sample = $this->le16_to_signed($u);
                $samplesPerChannel[$ch][] = $sample;

                $delta = $sample - ($previousSamples[$ch] ?? 0);
                $deltasPerChannel[$ch][] = $delta;
                $previousSamples[$ch] = $sample;
            }
        }

        $compressed = [];
        foreach ($deltasPerChannel as $ch => $deltas) {
            $runs = [];
            if (empty($deltas)) {
                $compressed[$ch] = $runs;
                continue;
            }
            $cur = $deltas[0];
            $count = 1;
            for ($i = 1; $i < count($deltas); $i++) {
                if ($deltas[$i] === $cur && $count < 255) {
                    $count++;
                } else {
                    $runs[] = ['value' => $cur, 'count' => $count];
                    $cur = $deltas[$i];
                    $count = 1;
                }
            }
            $runs[] = ['value' => $cur, 'count' => $count];
            $compressed[$ch] = $runs;
        }

        return [
            'channels' => $channels,
            'block_size' => count($samplesPerChannel[0] ?? []),
            'samples_counts' => array_map(function ($c) {
                return count($c); }, $samplesPerChannel),
            'runs' => $compressed,
            'last_samples' => array_map(function ($arr) {
                return end($arr) ?: 0; }, $samplesPerChannel),
            'previousSamples' => $previousSamples
        ];
    }

    private function runLengthEncode($data)
    {
        if (empty($data))
            return [];

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

    private function saveCompressedData($compressedData, $outputPath)
    {
        $handle = fopen($outputPath, 'wb');
        if (!$handle) {
            throw new \Exception("Cannot create compressed file");
        }

        $binaryData = $this->serializeToBinary($compressedData);

        fwrite($handle, $binaryData);
        fclose($handle);
    }

    private function serializeToBinary($data)
    {
        $binary = '';

        $meta = $data['metadata'];
        $binary .= pack('N', $meta['sample_rate']);
        $binary .= pack('n', $meta['channels']);
        $binary .= pack('n', $meta['bits_per_sample']);

        $binary .= pack('N', count($data['blocks']));

        foreach ($data['blocks'] as $block) {
            $binary .= pack('N', $block['block_size']);
            $binary .= pack('n', $block['channels']);
            foreach ($block['samples_counts'] as $spc) {
                $binary .= pack('N', $spc);
            }

            foreach ($block['runs'] as $chRuns) {
                $binary .= pack('N', count($chRuns));
                foreach ($chRuns as $run) {
                    $binary .= pack('V', $run['value'] & 0xFFFFFFFF);
                    $binary .= pack('C', $run['count']);
                }
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
        $sampleRate = unpack('N', fread($handle, 4))[1];
        $channels = unpack('n', fread($handle, 2))[1];
        $bitsPerSample = unpack('n', fread($handle, 2))[1];

        $metadata = [
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bits_per_sample' => $bitsPerSample
        ];

        $numBlocks = unpack('N', fread($handle, 4))[1];

        $blocks = [];
        for ($b = 0; $b < $numBlocks; $b++) {
            $blockSize = unpack('N', fread($handle, 4))[1];
            $blockChannels = unpack('n', fread($handle, 2))[1];

            $samplesCounts = [];
            for ($ch = 0; $ch < $blockChannels; $ch++) {
                $samplesCounts[] = unpack('N', fread($handle, 4))[1];
            }

            $runs = [];
            for ($ch = 0; $ch < $blockChannels; $ch++) {
                $numRuns = unpack('N', fread($handle, 4))[1];
                $channelRuns = [];
                for ($r = 0; $r < $numRuns; $r++) {
                    $v = unpack('V', fread($handle, 4))[1];
                    $value = $this->u32_to_signed($v);
                    $count = unpack('C', fread($handle, 1))[1];
                    $channelRuns[] = ['value' => $value, 'count' => $count];
                }
                $runs[] = $channelRuns;
            }

            $blocks[] = [
                'channels' => $blockChannels,
                'samples_counts' => $samplesCounts,
                'runs' => $runs,
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
        if (!$handle)
            throw new \Exception("Cannot create decompressed file");

        $channels = $compressedData['metadata']['channels'] ?? 2;
        $previous = array_fill(0, $channels, 0);

        foreach ($compressedData['blocks'] as $block) {
            $deltasPerChannel = [];
            foreach ($block['samples_counts'] as $chIndex => $spc) {
                $runs = $block['samples'][$chIndex] ?? $block['runs'][$chIndex];
                $deltasPerChannel[$chIndex] = $this->runLengthDecode($runs);
            }

            $blockSamples = $block['block_size'];
            $binary = '';
            for ($i = 0; $i < $blockSamples; $i++) {
                for ($ch = 0; $ch < $channels; $ch++) {
                    $delta = $deltasPerChannel[$ch][$i] ?? 0;
                    $sample = $previous[$ch] + $delta;
                    $sample = max(-32768, min(32767, $sample));
                    $binary .= pack('v', $this->signed_to_le16($sample));
                    $previous[$ch] = $sample;
                }
            }
            fwrite($handle, $binary);
        }

        fclose($handle);
        return $rawPath;
    }

    private function createStreamableVersion($compressedPath)
    {
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

