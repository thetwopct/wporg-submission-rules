<?php
namespace WPOrgSubmissionRules\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Checks that prefixes in function names, class names, constants, and global variables
 * are of adequate length and don't use reserved WordPress prefixes.
 */
class PrefixLengthSniff implements Sniff
{
    /**
     * Minimum required prefix length (configurable via ruleset.xml)
     */
    public $minPrefixLength = 4;

    /**
     * Returns the token types that this sniff is interested in.
     */
    public function register()
    {
        return [
            T_FUNCTION,
            T_CLASS,
            T_STRING,
            T_VARIABLE,
            T_NAMESPACE,
        ];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        switch ($token['code']) {
            case T_FUNCTION:
                $this->processFunctionDeclaration($phpcsFile, $stackPtr);
                break;

            case T_CLASS:
                $this->processClassDeclaration($phpcsFile, $stackPtr);
                break;

            case T_STRING:
                $this->processDefine($phpcsFile, $stackPtr);
                break;

            case T_VARIABLE:
                $this->processGlobalVariable($phpcsFile, $stackPtr);
                break;

            case T_NAMESPACE:
                $this->processNamespace($phpcsFile, $stackPtr);
                break;
        }
    }

    /**
     * Process function declarations.
     */
    private function processFunctionDeclaration(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findNext(T_STRING, $stackPtr + 1);

        if (!$namePtr) {
            return;
        }

        $name = $tokens[$namePtr]['content'];

        // Skip magic methods and WordPress core functions
        if ($this->isExcludedName($name)) {
            return;
        }

        // Skip methods inside classes - only check standalone functions
        $classPtr = $phpcsFile->getCondition($stackPtr, T_CLASS);
        if ($classPtr !== false) {
            return;
        }

        $this->checkPrefix($phpcsFile, $namePtr, $name, 'function');
    }

    /**
     * Process class declarations.
     */
    private function processClassDeclaration(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findNext(T_STRING, $stackPtr + 1);

        if (!$namePtr) {
            return;
        }

        $name = $tokens[$namePtr]['content'];
        $this->checkPrefix($phpcsFile, $namePtr, $name, 'class');
    }

    /**
     * Process define() calls.
     */
    private function processDefine(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['content'] !== 'define') {
            return;
        }

        $openParen = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr);
        if (!$openParen) {
            return;
        }

        $firstParam = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, $openParen);
        if (!$firstParam) {
            return;
        }

        $constantName = trim($tokens[$firstParam]['content'], '\'"');
        $this->checkPrefix($phpcsFile, $firstParam, $constantName, 'constant');
    }

    /**
     * Process global variables.
     */
    private function processGlobalVariable(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Check if this variable is declared as global
        $prevPtr = $phpcsFile->findPrevious(T_WHITESPACE, $stackPtr - 1, null, true);
        if (!$prevPtr || $tokens[$prevPtr]['code'] !== T_GLOBAL) {
            return;
        }

        $varName = ltrim($tokens[$stackPtr]['content'], '$');
        $this->checkPrefix($phpcsFile, $stackPtr, $varName, 'global variable');
    }

    /**
     * Process namespace declarations.
     */
    private function processNamespace(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findNext(T_STRING, $stackPtr + 1);

        if (!$namePtr) {
            return;
        }

        $name = $tokens[$namePtr]['content'];
        $this->checkPrefix($phpcsFile, $namePtr, $name, 'namespace');
    }

    /**
     * Check if a name should be excluded from prefix checking.
     */
    private function isExcludedName($name)
    {
        // Skip magic methods
        if (strpos($name, '__') === 0) {
            return true;
        }

        // Skip WordPress translation functions
        $translationFunctions = ['__', '_e', '_x', '_n', '_nx', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e'];
        if (in_array($name, $translationFunctions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Check the prefix of a name.
     */
    private function checkPrefix(File $phpcsFile, $stackPtr, $name, $type)
    {
        // Check for reserved WordPress prefixes first (before extracting prefix)
        $reservedCheckResult = $this->checkReservedPrefix($phpcsFile, $stackPtr, $name, $type);

        // If a reserved prefix error was triggered, we can skip the length check
        // as the reserved prefix is the more serious issue
        if ($reservedCheckResult) {
            return;
        }

        // Extract prefix (everything before first underscore)
        $prefix = '';
        if (strpos($name, '_') !== false) {
            $prefix = substr($name, 0, strpos($name, '_'));
        } else {
            // If no underscore, we might be dealing with a namespace or camelCase
            // For classes and namespaces without underscores, we'll be lenient
            if ($type === 'class' || $type === 'namespace') {
                return;
            }
            // For functions/constants/globals without underscores, consider the whole name
            $prefix = $name;
        }

        // Check if prefix is too short
        if (strlen($prefix) < $this->minPrefixLength) {
            $phpcsFile->addError(
                'The prefix "%s" (extracted from %s "%s") is too short. Prefixes must be at least %d characters long.',
                $stackPtr,
                'PrefixTooShort',
                [$prefix, $type, $name, $this->minPrefixLength]
            );
        }
    }

    /**
     * Check if a name uses reserved WordPress patterns.
     *
     * @return bool True if a reserved prefix error was found
     */
    private function checkReservedPrefix(File $phpcsFile, $stackPtr, $fullName, $type)
    {
        // Check for double underscore at start (but allow it for magic methods)
        if (strpos($fullName, '__') === 0 && $type !== 'function') {
            $phpcsFile->addError(
                'The prefix "__" (double underscore) is reserved for WordPress core. Do not use it in your plugin.',
                $stackPtr,
                'ReservedDoubleUnderscore'
            );
            return true;
        }

        // Check for single underscore at start
        if (strpos($fullName, '_') === 0 && strpos($fullName, '__') !== 0) {
            $phpcsFile->addError(
                'The prefix "_" (single underscore) is reserved for WordPress core. Do not use it in your plugin.',
                $stackPtr,
                'ReservedSingleUnderscore'
            );
            return true;
        }

        // Extract prefix for wp_ check
        $prefix = '';
        if (strpos($fullName, '_') !== false) {
            $prefix = substr($fullName, 0, strpos($fullName, '_'));
        } else {
            $prefix = $fullName;
        }

        $lowerPrefix = strtolower($prefix);

        // Check for wp_ prefix
        if ($lowerPrefix === 'wp') {
            $phpcsFile->addError(
                'The prefix "wp_" is reserved for WordPress core. Do not use it in your plugin.',
                $stackPtr,
                'ReservedWpPrefix'
            );
            return true;
        }

        return false;
    }
}
