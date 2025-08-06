<?php

namespace App\Services;

/**
 * Fixed HuffmanNode class
 */
/**
 * Simple Huffman implementation as fallback
 * (Keep this for compatibility but use OptimizedAudioCompressor instead)
 */
class HuffmanNode
{
    public $symbol;
    public $frequency;
    public $left;
    public $right;

    public function __construct($symbol = null, $frequency = 0)
    {
        $this->symbol = $symbol;
        $this->frequency = $frequency;
        $this->left = null;
        $this->right = null;
    }

    public function getCodes($prefix = '')
    {
        $codes = [];

        if ($this->symbol !== null) {
            $codes[$this->symbol] = $prefix ?: '0';
        } else {
            if ($this->left !== null) {
                $leftCodes = $this->left->getCodes($prefix . '0');
                $codes = array_merge($codes, $leftCodes);
            }
            if ($this->right !== null) {
                $rightCodes = $this->right->getCodes($prefix . '1');
                $codes = array_merge($codes, $rightCodes);
            }
        }

        return $codes;
    }

    public function isLeaf()
    {
        return $this->left === null && $this->right === null;
    }
}