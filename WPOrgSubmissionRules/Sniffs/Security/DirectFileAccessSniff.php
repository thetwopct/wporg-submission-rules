<?php
namespace WPOrgSubmissionRules\Sniffs\Security;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Detects PHP files that run code when loaded but don't prevent direct access.
 *
 * Files that run code must start with a guard such as
 * if ( ! defined( 'ABSPATH' ) ) exit;
 * after the opening tag and any namespace, use or declare statements.
 *
 * Files that only contain class, interface, trait, enum, function or const
 * definitions don't need one, and neither do files that only return an array
 * (such as index.asset.php build files).
 */
class DirectFileAccessSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     */
    public function register()
    {
        return [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens    = $phpcsFile->getTokens();
        $firstCode = null;
        $i         = 0;

        // Walk the file's top-level statements in order.
        while ($i < $phpcsFile->numTokens) {
            $code = $tokens[$i]['code'];

            if (isset(Tokens::$emptyTokens[$code]) || in_array($code, [T_OPEN_TAG, T_CLOSE_TAG, T_SEMICOLON, T_CLOSE_CURLY_BRACKET], true)
                || ($code === T_INLINE_HTML && trim($tokens[$i]['content']) === '')
            ) {
                $i++;
                continue;
            }

            // Attributes and modifiers before a definition.
            if ($code === T_ATTRIBUTE && isset($tokens[$i]['attribute_closer'])) {
                $i = $tokens[$i]['attribute_closer'] + 1;
                continue;
            }
            if (in_array($code, [T_ABSTRACT, T_FINAL, T_READONLY], true)) {
                $i++;
                continue;
            }

            $guardEnd = $this->getGuardEnd($phpcsFile, $i);
            if ($guardEnd !== false) {
                if ($firstCode !== null) {
                    $phpcsFile->addError(
                        'This code runs before the direct file access check on line %s. Move the check above it, straight after the opening <?php tag and any namespace or use statements.',
                        $firstCode,
                        'Misplaced',
                        [$tokens[$i]['line']]
                    );
                }
                return $phpcsFile->numTokens;
            }

            $end = $this->getDefinitionEnd($phpcsFile, $i);
            if ($end === false) {
                // Code that runs when the file is loaded.
                if ($firstCode === null) {
                    $firstCode = $i;
                }
                $end = $this->getStatementEnd($phpcsFile, $i);
            }

            $i = $end + 1;
        }

        if ($firstCode !== null) {
            $phpcsFile->addError(
                'This file runs code when loaded but does not prevent direct access. Add if ( ! defined( \'ABSPATH\' ) ) exit; straight after the opening <?php tag and any namespace or use statements, before any other code.',
                $firstCode,
                'Missing'
            );
        }

        return $phpcsFile->numTokens;
    }

    /**
     * Returns the end of a statement that doesn't run code (a definition or declaration), or false.
     */
    private function getDefinitionEnd(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $code   = $tokens[$stackPtr]['code'];
        $next   = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        switch ($code) {
            case T_NAMESPACE:
                // namespace\foo() is a call, not a declaration.
                if ($next !== false && $tokens[$next]['code'] === T_NS_SEPARATOR) {
                    return false;
                }
                // Check inside a braced namespace as if it were the top level.
                if (isset($tokens[$stackPtr]['scope_opener'])) {
                    return $tokens[$stackPtr]['scope_opener'];
                }
                return $phpcsFile->findEndOfStatement($stackPtr);

            case T_USE:
            case T_CONST:
                return $phpcsFile->findEndOfStatement($stackPtr);

            case T_DECLARE:
                return isset($tokens[$stackPtr]['scope_closer']) ? false : $phpcsFile->findEndOfStatement($stackPtr);

            case T_FUNCTION:
                // Named functions only, not closures.
                if ($next !== false && $tokens[$next]['code'] === T_BITWISE_AND) {
                    $next = $phpcsFile->findNext(Tokens::$emptyTokens, $next + 1, null, true);
                }
                if ($next === false || $tokens[$next]['code'] !== T_STRING) {
                    return false;
                }
                return isset($tokens[$stackPtr]['scope_closer']) ? $tokens[$stackPtr]['scope_closer'] : false;

            case T_CLASS:
            case T_INTERFACE:
            case T_TRAIT:
                return isset($tokens[$stackPtr]['scope_closer']) ? $tokens[$stackPtr]['scope_closer'] : false;

            case T_RETURN:
                // A file that returns an array, such as index.asset.php.
                if ($next !== false && ($tokens[$next]['code'] === T_ARRAY || $tokens[$next]['code'] === T_OPEN_SHORT_ARRAY)) {
                    $closer = $tokens[$next]['code'] === T_ARRAY
                        ? $tokens[$phpcsFile->findNext(T_OPEN_PARENTHESIS, $next)]['parenthesis_closer']
                        : $tokens[$next]['bracket_closer'];
                    $after = $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true);
                    if ($after !== false && in_array($tokens[$after]['code'], [T_SEMICOLON, T_CLOSE_TAG], true)) {
                        return $after;
                    }
                }
                return false;
        }

        if (defined('T_ENUM') && $code === T_ENUM && isset($tokens[$stackPtr]['scope_closer'])) {
            return $tokens[$stackPtr]['scope_closer'];
        }

        return false;
    }

    /**
     * Returns the end of a statement that runs code.
     */
    private function getStatementEnd(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Control structures with a body, e.g. if (...) { ... }.
        if (isset($tokens[$stackPtr]['scope_closer']) && $tokens[$stackPtr]['scope_condition'] === $stackPtr) {
            return $tokens[$stackPtr]['scope_closer'];
        }

        if ($tokens[$stackPtr]['code'] === T_INLINE_HTML || $tokens[$stackPtr]['code'] === T_OPEN_TAG_WITH_ECHO) {
            return $tokens[$stackPtr]['code'] === T_INLINE_HTML ? $stackPtr : $phpcsFile->findEndOfStatement($stackPtr + 1);
        }

        return max($stackPtr, $phpcsFile->findEndOfStatement($stackPtr));
    }

    /**
     * Returns the end of a direct file access guard starting at this token, or false.
     *
     * Accepts, with defined() or function_exists() on any name:
     * - if ( ! defined( 'ABSPATH' ) ) exit;  (with or without braces, exit/die/return)
     * - defined( 'ABSPATH' ) || exit;  (or "or", exit/die)
     */
    private function getGuardEnd(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $code   = $tokens[$stackPtr]['code'];
        $check  = '(?:defined|function_exists)\(\'[^\']+\'\)';

        if ($code === T_IF) {
            $opener = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);
            if ($opener === false || !isset($tokens[$opener]['parenthesis_closer'])) {
                return false;
            }

            $closer    = $tokens[$opener]['parenthesis_closer'];
            $condition = $this->getContent($phpcsFile, $opener + 1, $closer - 1);
            if (!preg_match('/^(?:!' . $check . '|false===' . $check . '|' . $check . '===false)$/', $condition)) {
                return false;
            }

            // The body must exit directly, not just contain an exit (e.g. in a nested function).
            $exits = [T_EXIT, T_RETURN];
            if (isset($tokens[$stackPtr]['scope_opener'])) {
                $bodyLevel = $tokens[$stackPtr]['level'] + 1;
                for ($i = $tokens[$stackPtr]['scope_opener'] + 1; $i < $tokens[$stackPtr]['scope_closer']; $i++) {
                    if (in_array($tokens[$i]['code'], $exits, true) && $tokens[$i]['level'] === $bodyLevel) {
                        return $tokens[$stackPtr]['scope_closer'];
                    }
                }
                return false;
            }

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true);

            return $next !== false && in_array($tokens[$next]['code'], $exits, true) ? $phpcsFile->findEndOfStatement($next) : false;
        }

        if ($code === T_STRING || $code === T_NS_SEPARATOR || $code === T_BOOLEAN_NOT) {
            $end     = $phpcsFile->findEndOfStatement($stackPtr);
            $content = $this->getContent($phpcsFile, $stackPtr, $end);
            if (preg_match('/^(?:' . $check . '(?:\|\||or)|!' . $check . '(?:&&|and))(?:exit|die)\b/', $content)) {
                return $end;
            }
        }

        return false;
    }

    /**
     * Returns the lowercased code in a token range, without whitespace, comments or namespace
     * separators, and with double quotes as single quotes.
     */
    private function getContent(File $phpcsFile, $start, $end)
    {
        $tokens  = $phpcsFile->getTokens();
        $content = '';

        for ($i = $start; $i <= $end; $i++) {
            if (!isset(Tokens::$emptyTokens[$tokens[$i]['code']])) {
                $content .= $tokens[$i]['content'];
            }
        }

        return str_replace(['"', '\\'], ['\'', ''], strtolower($content));
    }
}
