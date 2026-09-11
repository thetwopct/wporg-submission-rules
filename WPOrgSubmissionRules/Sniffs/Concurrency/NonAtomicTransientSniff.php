<?php
namespace WPOrgSubmissionRules\Sniffs\Concurrency;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use WPOrgSubmissionRules\Helpers\GlobalName;

/**
 * Detects non-atomic read-then-write sequences on transients.
 *
 * Reading a transient and then writing it back is a race condition: simultaneous
 * requests can all read the same value before any of them writes. This matters for
 * rate limits (read-increment-write) and locks (check-then-set), but not for the
 * common cache refill pattern, which is ignored.
 *
 * A set_transient() call is checked against the nearest earlier get_transient() call
 * with the same key in the same function:
 * - ReadModifyWrite: the value written is derived from the value read
 *   (e.g. $count + 1, $count++, $list[] = $item).
 * - CheckThenSet: the value read is used in an if condition and the value written
 *   is a fixed flag (e.g. 1, true, 'locked', time()).
 */
class NonAtomicTransientSniff implements Sniff
{
    /**
     * Write functions and the read function they pair with.
     */
    private $pairs = [
        'set_transient'      => 'get_transient',
        'set_site_transient' => 'get_site_transient',
    ];

    /**
     * Functions whose return value is a typical lock flag.
     */
    private $flagFunctions = ['time', 'microtime', 'current_time'];

    /**
     * Returns the token types that this sniff is interested in.
     */
    public function register()
    {
        return [T_STRING, T_NAME_FULLY_QUALIFIED];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $name = strtolower((string) GlobalName::get($phpcsFile, $stackPtr));

        if (!isset($this->pairs[$name])) {
            return;
        }

        $setParen = $this->getCallParenthesis($phpcsFile, $stackPtr);
        if ($setParen === false) {
            return;
        }

        $setArgs = $this->getArguments($phpcsFile, $setParen);
        if (count($setArgs) < 2) {
            return;
        }

        $key   = $this->getContent($phpcsFile, $setArgs[0][0], $setArgs[0][1]);
        $value = $setArgs[1];

        $getPtr = $this->findRead($phpcsFile, $stackPtr, $value[1], $this->pairs[$name], $key);
        if ($getPtr === false) {
            return;
        }

        if ($this->isReadModifyWrite($phpcsFile, $getPtr, $value)) {
            $phpcsFile->addWarning(
                'Transient %s is read, modified and written back non-atomically. Simultaneous requests can read the same value and overwrite each other, e.g. letting requests exceed a rate limit. Use an atomic operation instead, such as wp_cache_incr() with a persistent object cache, or an atomic database UPDATE.',
                $stackPtr,
                'ReadModifyWrite',
                [$key]
            );
        } elseif ($this->isCheckThenSet($phpcsFile, $getPtr, $stackPtr, $value)) {
            $phpcsFile->addWarning(
                'Transient %s is checked and then set non-atomically. Simultaneous requests can all pass the check before any of them sets it, bypassing the lock. Use an atomic lock instead, such as wp_cache_add() with a persistent object cache, or an INSERT IGNORE into the options table (see WP_Upgrader::create_lock()).',
                $stackPtr,
                'CheckThenSet',
                [$key]
            );
        }
    }

    /**
     * Finds the nearest read of the same key in the same function, looking back from $end.
     */
    private function findRead(File $phpcsFile, $setPtr, $end, $readFunction, $key)
    {
        $tokens = $phpcsFile->getTokens();

        // Limit the search to the innermost function or closure.
        $start = 0;
        foreach (array_reverse($tokens[$setPtr]['conditions'], true) as $condPtr => $condCode) {
            if (($condCode === T_FUNCTION || $condCode === T_CLOSURE) && isset($tokens[$condPtr]['scope_opener'])) {
                $start = $tokens[$condPtr]['scope_opener'];
                break;
            }
        }

        for ($i = $end; $i > $start; $i--) {
            if (strtolower((string) GlobalName::get($phpcsFile, $i)) !== $readFunction) {
                continue;
            }

            $paren = $this->getCallParenthesis($phpcsFile, $i);
            if ($paren === false) {
                continue;
            }

            $args = $this->getArguments($phpcsFile, $paren);
            if ($args !== [] && $this->getContent($phpcsFile, $args[0][0], $args[0][1]) === $key) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Whether the value written is derived from the value read.
     */
    private function isReadModifyWrite(File $phpcsFile, $getPtr, array $value)
    {
        $tokens = $phpcsFile->getTokens();

        // The read happens inside the value, e.g. get_transient($key) + 1.
        if ($getPtr >= $value[0] && $getPtr <= $value[1]) {
            return true;
        }

        $variable = $this->getAssignedVariable($phpcsFile, $getPtr);
        if ($variable === null) {
            return false;
        }

        // Written back after modifying it in place, e.g. $count++ or $list[] = $item.
        $modified = false;
        for ($i = $getPtr; $i <= $value[1]; $i++) {
            if ($tokens[$i]['code'] !== T_VARIABLE || $tokens[$i]['content'] !== $variable) {
                continue;
            }

            $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, $i - 1, null, true);
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, $i + 1, null, true);

            // Reassigned: a modification if it uses its own value ($count = $count + 1),
            // otherwise it no longer holds the value read (e.g. a cache refill).
            if ($i < $value[0] && $tokens[$next]['code'] === T_EQUAL) {
                $end = $phpcsFile->findEndOfStatement($next);
                if (!preg_match('/' . preg_quote($variable, '/') . '\b/', $this->getContent($phpcsFile, $next + 1, $end))) {
                    return false;
                }
                $modified = true;
                continue;
            }

            if ($tokens[$prev]['code'] === T_INC || $tokens[$prev]['code'] === T_DEC) {
                $modified = true;
                continue;
            }

            // Skip over $list[...] to what follows it.
            $isElement = false;
            while ($tokens[$next]['code'] === T_OPEN_SQUARE_BRACKET && isset($tokens[$next]['bracket_closer'])) {
                $next      = $phpcsFile->findNext(Tokens::$emptyTokens, $tokens[$next]['bracket_closer'] + 1, null, true);
                $isElement = true;
            }

            $isElementWrite = $isElement && $tokens[$next]['code'] === T_EQUAL;
            if ($isElementWrite || $tokens[$next]['code'] === T_INC || $tokens[$next]['code'] === T_DEC
                || (isset(Tokens::$assignmentTokens[$tokens[$next]['code']]) && $tokens[$next]['code'] !== T_EQUAL && $tokens[$next]['code'] !== T_COALESCE_EQUAL)
            ) {
                $modified = true;
            }
        }

        if ($modified) {
            return true;
        }

        // Derived in the value itself, e.g. $count + 1.
        $content = $this->getContent($phpcsFile, $value[0], $value[1]);

        return $content !== $variable && preg_match('/' . preg_quote($variable, '/') . '\b/', $content) === 1;
    }

    /**
     * Whether the value read is used in an if condition and a fixed flag is written.
     */
    private function isCheckThenSet(File $phpcsFile, $getPtr, $setPtr, array $value)
    {
        if (!$this->isFlag($phpcsFile, $value)) {
            return false;
        }

        if ($this->isInCondition($phpcsFile, $getPtr)) {
            return true;
        }

        $variable = $this->getAssignedVariable($phpcsFile, $getPtr);
        if ($variable === null) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();
        for ($i = $getPtr + 1; $i < $setPtr; $i++) {
            if ($tokens[$i]['code'] === T_VARIABLE && $tokens[$i]['content'] === $variable && $this->isInCondition($phpcsFile, $i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the value is a fixed flag such as 1, true, 'locked' or time().
     */
    private function isFlag(File $phpcsFile, array $value)
    {
        $tokens = $phpcsFile->getTokens();
        $first  = $phpcsFile->findNext(Tokens::$emptyTokens, $value[0], $value[1] + 1, true);
        $last   = $phpcsFile->findPrevious(Tokens::$emptyTokens, $value[1], $value[0] - 1, true);

        if ($first === $last) {
            return in_array($tokens[$first]['code'], [T_LNUMBER, T_DNUMBER, T_TRUE, T_CONSTANT_ENCAPSED_STRING], true);
        }

        // A whole-value call such as time(), or \time(), which PHP_CodeSniffer 3 splits in two.
        if ($tokens[$first]['code'] === T_NS_SEPARATOR) {
            $first++;
        }
        $paren = $this->getCallParenthesis($phpcsFile, $first);

        return $paren !== false
            && in_array(strtolower((string) GlobalName::get($phpcsFile, $first)), $this->flagFunctions, true)
            && $tokens[$paren]['parenthesis_closer'] === $last;
    }

    /**
     * Whether a token is inside the condition of an if or elseif.
     */
    private function isInCondition(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis'])) {
            return false;
        }

        foreach ($tokens[$stackPtr]['nested_parenthesis'] as $opener => $closer) {
            if (isset($tokens[$opener]['parenthesis_owner'])) {
                $owner = $tokens[$tokens[$opener]['parenthesis_owner']]['code'];
                if ($owner === T_IF || $owner === T_ELSEIF) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the variable a call's result is assigned to, e.g. $count in $count = (int) get_transient().
     */
    private function getAssignedVariable(File $phpcsFile, $callPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $prev   = $phpcsFile->findPrevious(Tokens::$emptyTokens, GlobalName::getStart($phpcsFile, $callPtr) - 1, null, true);

        // Skip casts and wrappers such as (int), intval( and absint(.
        while ($prev !== false) {
            if (isset(Tokens::$castTokens[$tokens[$prev]['code']])) {
                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, $prev - 1, null, true);
                continue;
            }

            if ($tokens[$prev]['code'] === T_OPEN_PARENTHESIS) {
                $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $prev - 1, null, true);
                if ($before !== false && in_array(strtolower((string) GlobalName::get($phpcsFile, $before)), ['intval', 'absint'], true)) {
                    $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, GlobalName::getStart($phpcsFile, $before) - 1, null, true);
                    continue;
                }
            }

            break;
        }

        if ($prev === false || $tokens[$prev]['code'] !== T_EQUAL) {
            return null;
        }

        $variable = $phpcsFile->findPrevious(Tokens::$emptyTokens, $prev - 1, null, true);

        return $variable !== false && $tokens[$variable]['code'] === T_VARIABLE ? $tokens[$variable]['content'] : null;
    }

    /**
     * Returns the opening parenthesis if the token is a function call (not a method or declaration).
     */
    private function getCallParenthesis(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (GlobalName::get($phpcsFile, $stackPtr) === null) {
            return false;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS || !isset($tokens[$next]['parenthesis_closer'])) {
            return false;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);
        if ($prev !== false && in_array($tokens[$prev]['code'], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
            return false;
        }

        return $next;
    }

    /**
     * Splits a call's arguments into [start, end] token ranges.
     */
    private function getArguments(File $phpcsFile, $openParen)
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$openParen]['parenthesis_closer'];
        $args   = [];
        $start  = $openParen + 1;

        for ($i = $start; $i < $closer; $i++) {
            // Skip nested calls, arrays and closures.
            if (isset($tokens[$i]['parenthesis_closer']) && $tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $i = $tokens[$i]['parenthesis_closer'];
            } elseif (isset($tokens[$i]['bracket_closer']) && $tokens[$i]['bracket_closer'] > $i) {
                $i = $tokens[$i]['bracket_closer'];
            } elseif ($tokens[$i]['code'] === T_COMMA) {
                $args[] = [$start, $i - 1];
                $start  = $i + 1;
            }
        }

        if ($phpcsFile->findNext(Tokens::$emptyTokens, $start, $closer, true) !== false) {
            $args[] = [$start, $closer - 1];
        }

        return $args;
    }

    /**
     * Returns the code in a token range without whitespace or comments.
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

        return $content;
    }
}
