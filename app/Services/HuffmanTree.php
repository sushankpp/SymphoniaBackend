<?php

namespace App\Services;

class HuffmanTree
{
    private $codes = [];

    public function buildTree($frequencyMap)
    {
        if (empty($frequencyMap)) {
            throw new \Exception("Frequency map is empty");
        }

        arsort($frequencyMap);
        $frequencyMap = array_slice($frequencyMap, 0, 100, true);

        $nodes = [];
        foreach ($frequencyMap as $symbol => $frequency) {
            $nodes[] = new HuffmanNode($symbol, $frequency);
        }

        if (count($nodes) === 1) {
            $this->codes[$nodes[0]->symbol] = '0';
            return $nodes[0];
        }

        while (count($nodes) > 1) {
            usort($nodes, function ($a, $b) {
                return $a->frequency <=> $b->frequency;
            });

            $left = array_shift($nodes);
            $right = array_shift($nodes);


            $parent = new HuffmanNode(null, $left->frequency + $right->frequency);
            $parent->left = $left;
            $parent->right = $right;

            $nodes[] = $parent;
        }

        $root = $nodes[0];
        $this->generateCodes($root, '');

        return $root;
    }

    private function generateCodes($node, $code = '')
    {
        if ($node->symbol !== null) {
            $this->codes[$node->symbol] = $code ?: '0';
            return;
        }

        if ($node->left) {
            $this->generateCodes($node->left, $code . '0');
        }
        if ($node->right) {
            $this->generateCodes($node->right, $code . '1');
        }
    }

    public function getCodes()
    {
        return $this->codes;
    }
}