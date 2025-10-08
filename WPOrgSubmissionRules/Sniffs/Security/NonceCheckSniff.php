<?php
namespace WPOrgSubmissionRules\Sniffs\Security;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Checks that $_POST, $_GET, and $_REQUEST usage is accompanied by nonce verification.
 */
class NonceCheckSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     */
    public function register()
    {
        return [T_VARIABLE];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $varName = $tokens[$stackPtr]['content'];

        // Check if it's a superglobal that requires nonce checking
        if (!in_array($varName, ['$_POST', '$_GET', '$_REQUEST'], true)) {
            return;
        }

        // Find the function scope
        $functionPtr = $phpcsFile->getCondition($stackPtr, T_FUNCTION);

        if (!$functionPtr) {
            // Using superglobal outside of function - performance issue
            $phpcsFile->addWarning(
                'Using %s outside of a function causes performance issues. This check will run on every page load.',
                $stackPtr,
                'SuperglobalOutsideFunction',
                [$varName]
            );
            return;
        }

        // Check if nonce verification exists in the same function
        $hasNonceCheck = $this->hasNonceCheckInScope($phpcsFile, $functionPtr);

        if (!$hasNonceCheck) {
            $phpcsFile->addError(
                'Usage of %s requires a nonce check (wp_verify_nonce() or check_ajax_referer()) in the same function.',
                $stackPtr,
                'MissingNonceCheck',
                [$varName]
            );
        }
    }

    /**
     * Check if a nonce verification function exists within the given function scope.
     */
    private function hasNonceCheckInScope(File $phpcsFile, $functionPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $functionToken = $tokens[$functionPtr];

        // Get the scope boundaries
        if (!isset($functionToken['scope_opener']) || !isset($functionToken['scope_closer'])) {
            return false;
        }

        $scopeOpener = $functionToken['scope_opener'];
        $scopeCloser = $functionToken['scope_closer'];

        // List of WordPress nonce verification functions
        $nonceFunctions = [
            'wp_verify_nonce',
            'check_ajax_referer',
            'check_admin_referer',
        ];

        // Search for nonce verification functions in the function scope
        for ($i = $scopeOpener; $i < $scopeCloser; $i++) {
            if ($tokens[$i]['code'] === T_STRING &&
                in_array($tokens[$i]['content'], $nonceFunctions, true)) {
                return true;
            }
        }

        return false;
    }
}
