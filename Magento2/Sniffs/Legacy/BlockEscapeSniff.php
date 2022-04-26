<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento2\Sniffs\Legacy;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use function count;
use function in_array;
use function strpos;

class BlockEscapeSniff implements Sniff
{
    /**
     * Magento escape methods.
     *
     * @var array
     */
    protected $allowedMethods = [
        'escapeUrl',
        'escapeHtml',
        'escapeHtmlAttr',
        'escapeUrl',
        'escapeJsQuote',
        'escapeQuote',
        'escapeXssInUrl',
        'escapeJs',
        'escapeCss',
        'getJsLayout'
    ];

    /**
     * @var array
     */
    private $tokens;

    /**
     * @var File
     */
    private $file;

    /**
     * @var array
     */
    private $escapeTokenPositions;

    public function register()
    {
        return [
            T_ECHO,
            T_OPEN_TAG_WITH_ECHO,
            T_PRINT,
        ];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $this->file = $phpcsFile;
        $this->tokens = $this->file->getTokens();
        $this->findEscapes($stackPtr);

        foreach ($this->escapeTokenPositions as $escapeTokenPosition) {
            $index = $escapeTokenPosition;
            $foundEscaped = false;
            do {
                $index--;
                switch ($this->tokens[$index]['content']) {
                    case '$block':
                        $this->fixDeprecatedEscape($index);
                        $foundEscaped = true;
                        break;
                    case '$escaper':
                        $foundEscaped = true;
                        break;
                }
            } while ($index > $stackPtr && $foundEscaped === false);
        }

        if (count($this->escapeTokenPositions) > 0) {
            if ($this->tokens[0]['code'] !== T_OPEN_TAG) {
                return;
            }
            $endOfStatement = $this->file->findNext([T_CLOSE_TAG], 0);
            for ($index = 0; $index < $endOfStatement; $index++) {
                if (strpos($this->tokens[$index]['content'], 'Magento\Framework\Escaper')) {
                    return;
                }
            }
            $this->fixVarAnnotation();
        }

    }

    private function fixDeprecatedEscape(int $stackPtr): void
    {
        $fix = $this->file->addFixableWarning('Deprecated escape detected ', $stackPtr, 'DeprecatedEscape');
        if (!$fix) {
            return;
        }

        $this->file->fixer->beginChangeset();
        $this->file->fixer->replaceToken($stackPtr, '$escaper');
        $this->file->fixer->endChangeset();
    }

    private function findEscapes(int $stackPtr): void
    {
        $endOfStatement = $this->file->findNext([T_CLOSE_TAG, T_SEMICOLON], $stackPtr);
        for ($index = $stackPtr; $index < $endOfStatement; $index++) {
            if (in_array($this->tokens[$index]['content'], $this->allowedMethods, true)) {
                $this->escapeTokenPositions[] = $index;
            }
        }
    }

    private function fixVarAnnotation()
    {
        $fix = $this->file->addFixableWarning('Missing var annotation ', 0, 'MissingVarAnnotation');
        if (!$fix) {
            return;
        }

        $this->file->fixer->beginChangeset();
        $this->file->fixer->addContent(0, '/** @var \Magento\Framework\Escaper $escaper */' . "\n");
        $this->file->fixer->endChangeset();
    }
}
