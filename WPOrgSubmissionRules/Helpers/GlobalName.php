<?php
namespace WPOrgSubmissionRules\Helpers;

use PHP_CodeSniffer\Files\File;

/**
 * Reads global function names the same way in PHP_CodeSniffer 3 and 4.
 *
 * PHP_CodeSniffer 3 splits \wp_remote_get into T_NS_SEPARATOR and T_STRING tokens,
 * while PHP_CodeSniffer 4 keeps it as a single T_NAME_FULLY_QUALIFIED token. Sniffs
 * looking for global function calls should register both T_STRING and
 * T_NAME_FULLY_QUALIFIED, and read the name with get().
 */
class GlobalName
{
    /**
     * Returns the name without a leading backslash (wp_remote_get for wp_remote_get or
     * \wp_remote_get), or null if the token is not a global name (e.g. Foo\wp_remote_get).
     */
    public static function get(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        if ($token['code'] === T_NAME_FULLY_QUALIFIED) {
            $name = substr($token['content'], 1);
            return strpos($name, '\\') === false ? $name : null;
        }

        if ($token['code'] !== T_STRING) {
            return null;
        }

        // The last part of Foo\bar or namespace\bar in PHP_CodeSniffer 3.
        if ($stackPtr > 1 && $tokens[$stackPtr - 1]['code'] === T_NS_SEPARATOR
            && in_array($tokens[$stackPtr - 2]['code'], [T_STRING, T_NAMESPACE], true)
        ) {
            return null;
        }

        return $token['content'];
    }

    /**
     * Returns the first token of the name: the backslash of \wp_remote_get in
     * PHP_CodeSniffer 3, otherwise the token itself.
     */
    public static function getStart(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        return $stackPtr > 0 && $tokens[$stackPtr]['code'] === T_STRING && $tokens[$stackPtr - 1]['code'] === T_NS_SEPARATOR ? $stackPtr - 1 : $stackPtr;
    }
}
