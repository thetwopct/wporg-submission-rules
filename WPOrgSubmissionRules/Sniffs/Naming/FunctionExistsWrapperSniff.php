<?php
namespace WPOrgSubmissionRules\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use WPOrgSubmissionRules\Helpers\GlobalName;

/**
 * Detects the anti-pattern of wrapping function declarations in if (!function_exists()).
 * This is discouraged for plugins as it can lead to silent failures.
 */
class FunctionExistsWrapperSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     */
    public function register()
    {
        return [T_IF];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$stackPtr]['scope_opener']) || !isset($tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        // Look for the pattern: if (!function_exists(...))
        $hasNegation = false;
        $hasFunctionExists = false;

        // Check the condition part of the if statement
        $openParen = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr, $stackPtr + 10);
        if (!$openParen || !isset($tokens[$openParen]['parenthesis_closer'])) {
            return;
        }

        $closeParen = $tokens[$openParen]['parenthesis_closer'];

        // Look for boolean NOT operator and function_exists
        for ($i = $stackPtr; $i < $closeParen; $i++) {
            if ($tokens[$i]['code'] === T_BOOLEAN_NOT) {
                $hasNegation = true;
            }

            if (GlobalName::get($phpcsFile, $i) === 'function_exists') {
                $hasFunctionExists = true;
            }
        }

        // If we found the pattern, check if there's a function declaration inside
        if ($hasNegation && $hasFunctionExists) {
            $scopeOpener = $tokens[$stackPtr]['scope_opener'];
            $scopeCloser = $tokens[$stackPtr]['scope_closer'];

            $functionDecl = $phpcsFile->findNext(T_FUNCTION, $scopeOpener, $scopeCloser);
            if ($functionDecl) {
                $phpcsFile->addWarning(
                    'Do not use if (!function_exists()) as a workaround for naming conflicts. Use unique prefixes instead. If something else has a function with the same name and loads first, your plugin will break silently.',
                    $stackPtr,
                    'FunctionExistsWrapper'
                );
            }
        }
    }
}
