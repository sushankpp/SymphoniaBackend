<?php

namespace App\Services;

class AudioCompressor
{
    // Rice parameter k (tunable, usually 3..5; 4 is a good start)
    private $rice_k = 4;

    public function compress($inputWavPath, $outputCmpPath)
    {
        $handle = fopen($inputWavPath, 'rb');
        if (!$handle) throw new \Exception("Cannot open WAV file");

        $header = fread($handle, 44);
        $sampleRate = unpack('V', substr($header, 24, 4))[1];
        $numChannels = unpack('v', substr($header, 22, 2))[1];
        $bitsPerSample = unpack('v', substr($header, 34, 2))[1];

        if ($bitsPerSample !== 16) {
            fclose($handle);
            throw new \Exception("Only 16-bit PCM WAV supported");
        }

        $out = fopen($outputCmpPath, 'wb');
        fwrite($out, pack('Vvvi', $sampleRate, $numChannels, $bitsPerSample, 0));

        $prevSample = 0;
        $sampleCount = 0;

        $bitBuffer = 0;
        $bitCount = 0;

        $writeBits = function($bits, $count) use (&$out, &$bitBuffer, &$bitCount) {
            for ($i = $count - 1; $i >= 0; $i--) {
                $bit = ($bits >> $i) & 1;
                $bitBuffer = ($bitBuffer << 1) | $bit;
                $bitCount++;
                if ($bitCount == 8) {
                    fwrite($out, chr($bitBuffer));
                    $bitBuffer = 0;
                    $bitCount = 0;
                }
            }
        };

        $flushBits = function() use (&$out, &$bitBuffer, &$bitCount) {
            if ($bitCount > 0) {
                $bitBuffer <<= (8 - $bitCount);
                fwrite($out, chr($bitBuffer));
                $bitBuffer = 0;
                $bitCount = 0;
            }
        };

        $riceEncode = function($x) use ($writeBits) {
            $u = ($x << 1) ^ ($x >> 31);
            $q = $u >> $this->rice_k;
            $r = $u & ((1 << $this->rice_k) -1);

            for ($i = 0; $i < $q; $i++) {
                $writeBits(1,1);
            }
            $writeBits(0,1);
            $writeBits($r, $this->rice_k);
        };

        while (!feof($handle)) {
            $chunk = fread($handle, 2048);
            if (!$chunk) break;
            $samples = unpack('v*', $chunk);

            foreach ($samples as $s) {
                if ($s > 32767) $s -= 65536;
                $delta = $s - $prevSample;
                $prevSample = $s;
                $riceEncode($delta);
                $sampleCount++;
            }
        }

        $flushBits();
        fclose($handle);

        $outHandle = fopen($outputCmpPath, 'rb+');
        fseek($outHandle, 8);
        fwrite($outHandle, pack('i', $sampleCount));
        fclose($outHandle);
        fclose($out);
    }

    public function decompress($inputCmpPath, $outputWavPath)
    {
        $handle = fopen($inputCmpPath, 'rb');
        if (!$handle) throw new \Exception("Cannot open compressed file");

        $header = fread($handle, 12);
        $sampleRate = unpack('V', substr($header, 0, 4))[1];
        $numChannels = unpack('v', substr($header, 4, 2))[1];
        $bitsPerSample = unpack('v', substr($header, 6, 2))[1];
        $numSamples = unpack('i', substr($header, 8, 4))[1];

        $out = fopen($outputWavPath, 'wb');
        fwrite($out, $this->wavHeader($numSamples, $sampleRate, $numChannels, $bitsPerSample));

        $prevSample = 0;
        $samplesDecoded = 0;

        $bitBuffer = 0;
        $bitCount = 0;

        $readBit = function() use (&$handle, &$bitBuffer, &$bitCount) {
            if ($bitCount == 0) {
                $byte = fread($handle, 1);
                if ($byte === false) return false;
                $bitBuffer = ord($byte);
                $bitCount = 8;
            }
            $bitCount--;
            return ($bitBuffer >> $bitCount) & 1;
        };

        $riceDecode = function() use ($readBit) {
            $k = $this->rice_k;
            $q = 0;
            while (true) {
                $bit = $readBit();
                if ($bit === false) return false;
                if ($bit == 0) break;
                $q++;
            }

            $r = 0;
            for ($i = 0; $i < $k; $i++) {
                $bit = $readBit();
                if ($bit === false) return false;
                $r = ($r << 1) | $bit;
            }

            $u = ($q << $k) | $r;
            $x = (($u & 1) == 0) ? ($u >> 1) : -(($u >> 1) + 1);
            return $x;
        };

        while ($samplesDecoded < $numSamples) {
            $delta = $riceDecode();
            if ($delta === false) break;

            $sample = $prevSample + $delta;
            $prevSample = $sample;

            if ($sample < 0) $sample += 65536;

            fwrite($out, pack('v', $sample));
            $samplesDecoded++;
        }

        fclose($handle);
        fclose($out);
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
