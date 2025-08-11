<?php
namespace App\Services;

class AudioHuffmanCompressor
{
    public function compress($inputWavPath, $outputAudPath)
    {
        $handle = fopen($inputWavPath, 'rb');
        if (!$handle) throw new \Exception("Cannot open WAV file");

        // Read WAV header (44 bytes)
        $header = fread($handle, 44);

        $sampleRate = unpack('V', substr($header, 24, 4))[1];
        $numChannels = unpack('v', substr($header, 22, 2))[1];
        $bitsPerSample = unpack('v', substr($header, 34, 2))[1];

        if ($bitsPerSample !== 16) {
            fclose($handle);
            throw new \Exception("Only 16-bit PCM WAV supported");
        }

        $freq = [];

        // Read PCM data chunk by chunk, update frequency table
        while (!feof($handle)) {
            $chunk = fread($handle, 1024 * 2); // 1024 samples * 2 bytes/sample
            if (!$chunk) break;

            $samples = unpack('v*', $chunk);

            foreach ($samples as $s) {
                if ($s > 32767) $s -= 65536;
                $freq[$s] = ($freq[$s] ?? 0) + 1;
            }
        }
        fclose($handle);

        // Build Huffman tree and generate codes
        $tree = $this->buildTree($freq);
        $codes = [];
        $this->generateCodes($tree, '', $codes);

        // Re-open WAV for encoding
        $handle = fopen($inputWavPath, 'rb');
        fread($handle, 44); // Skip header

        $bitstring = '';

        while (!feof($handle)) {
            $chunk = fread($handle, 1024 * 2);
            if (!$chunk) break;

            $samples = unpack('v*', $chunk);
            foreach ($samples as $s) {
                if ($s > 32767) $s -= 65536;
                $bitstring .= '0' . $codes[$s];
            }
        }
        fclose($handle);

        // Pack bits into bytes
        $binData = '';
        for ($i = 0; $i < strlen($bitstring); $i += 8) {
            $byte = substr($bitstring, $i, 8);
            $binData .= chr(bindec(str_pad($byte, 8, '0', STR_PAD_RIGHT)));
        }

        $numSamples = array_sum($freq) / $numChannels;
        $meta = pack('Vvvi', $sampleRate, $numChannels, $bitsPerSample, $numSamples);
        $compressedTree = $this->compressTree($freq);
        $output = $meta . pack('N', strlen($compressedTree)) . $compressedTree . $binData;

        file_put_contents($outputAudPath, $output);
    }

    public function decompress($inputAudPath, $outputWavPath)
    {
        $handle = fopen($inputAudPath, 'rb');
        if (!$handle) throw new \Exception("Cannot open compressed file");

        // Read metadata
        $meta = fread($handle, 12);
        $sampleRate = unpack('V', substr($meta, 0, 4))[1];
        $numChannels = unpack('v', substr($meta, 4, 2))[1];
        $bitsPerSample = unpack('v', substr($meta, 6, 2))[1];
        $numSamples = unpack('i', substr($meta, 8, 4))[1];

        // Read compressed tree length
        $treeLenData = fread($handle, 4);
        $treeLen = unpack('N', $treeLenData)[1];

        // Read compressed tree
        $compressedTree = fread($handle, $treeLen);
        $freq = $this->decompressTree($compressedTree);

        // Build Huffman tree and codes
        $tree = $this->buildTree($freq);
        $codes = [];
        $this->generateCodes($tree, '', $codes);
        $reverseCodes = array_flip($codes);
        $maxCodeLen = max(array_map('strlen', $codes));

        $samples = [];
        $buffer = '';

        // Read bit data chunk by chunk to avoid memory issues
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if (!$chunk) break;

            for ($i = 0; $i < strlen($chunk); $i++) {
                $buffer .= str_pad(decbin(ord($chunk[$i])), 8, '0', STR_PAD_LEFT);
            }

            $pos = 0;
            $bufLen = strlen($buffer);

            while ($pos < $bufLen) {
                if ($buffer[$pos] !== '0') {
                    throw new \Exception("Unexpected prefix bit during decompression");
                }
                $pos++;

                $code = '';
                for ($j = 0; $j < $maxCodeLen && $pos < $bufLen; $j++) {
                    $code .= $buffer[$pos++];
                    if (isset($reverseCodes[$code])) {
                        $samples[] = $reverseCodes[$code];
                        break;
                    }
                }

                if (!isset($reverseCodes[$code])) {
                    // Need more bits - rewind buffer and break to read more
                    $pos -= strlen($code) + 1;
                    break;
                }
            }
            $buffer = substr($buffer, $pos);
        }
        fclose($handle);

        if (count($samples) !== $numSamples * $numChannels) {
            throw new \Exception("Decompressed sample count mismatch");
        }

        foreach ($samples as &$s) {
            if ($s < 0) $s += 65536;
        }
        unset($s);

        $pcm = pack('v*', ...$samples);
        $wavHeader = $this->wavHeader($numSamples, $sampleRate, $numChannels, $bitsPerSample);
        file_put_contents($outputWavPath, $wavHeader . $pcm);
    }

    private function buildTree($freq)
    {
        $nodes = [];
        foreach ($freq as $symbol => $f) {
            $nodes[] = ['symbol' => $symbol, 'freq' => $f, 'left' => null, 'right' => null];
        }

        while (count($nodes) > 1) {
            usort($nodes, fn($a, $b) => $a['freq'] <=> $b['freq']);
            $left = array_shift($nodes);
            $right = array_shift($nodes);
            $nodes[] = [
                'symbol' => null,
                'freq' => $left['freq'] + $right['freq'],
                'left' => $left,
                'right' => $right
            ];
        }
        return $nodes[0];
    }

    private function generateCodes($node, $prefix, &$codes)
    {
        if ($node['symbol'] !== null) {
            $codes[$node['symbol']] = $prefix ?: '0';
        } else {
            $this->generateCodes($node['left'], $prefix . '0', $codes);
            $this->generateCodes($node['right'], $prefix . '1', $codes);
        }
    }

    private function compressTree($freq)
    {
        $compressed = '';
        foreach ($freq as $value => $count) {
            $compressed .= pack('vN', $value, $count);
        }
        return $compressed;
    }

    private function decompressTree($compressed)
    {
        $freq = [];
        $len = strlen($compressed);
        $i = 0;
        while ($i < $len) {
            $value = unpack('v', substr($compressed, $i, 2))[1];
            $i += 2;
            $count = unpack('N', substr($compressed, $i, 4))[1];
            $i += 4;
            $freq[$value] = $count;
        }
        return $freq;
    }

    private function wavHeader($numSamples, $sampleRate, $numChannels, $bitsPerSample)
    {
        $byteRate = $sampleRate * $numChannels * $bitsPerSample / 8;
        $blockAlign = $numChannels * $bitsPerSample / 8;
        $dataSize = $numSamples * $numChannels * $bitsPerSample / 8;
        $chunkSize = 36 + $dataSize;

        $wavHeader = '';
        $wavHeader .= pack('A4', 'RIFF');
        $wavHeader .= pack('V', $chunkSize);
        $wavHeader .= pack('A4', 'WAVE');
        $wavHeader .= pack('A4', 'fmt ');
        $wavHeader .= pack('V', 16);
        $wavHeader .= pack('v', 1);
        $wavHeader .= pack('v', $numChannels);
        $wavHeader .= pack('V', $sampleRate);
        $wavHeader .= pack('V', $byteRate);
        $wavHeader .= pack('v', $blockAlign);
        $wavHeader .= pack('v', $bitsPerSample);
        $wavHeader .= pack('A4', 'data');
        $wavHeader .= pack('V', $dataSize);

        return $wavHeader;
    }
}
