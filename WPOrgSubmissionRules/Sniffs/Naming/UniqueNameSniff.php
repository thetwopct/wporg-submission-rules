<?php
namespace WPOrgSubmissionRules\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use WPOrgSubmissionRules\Helpers\GlobalName;

class UniqueNameSniff implements Sniff
{
    /**
     * Required prefix (can be set via ruleset.xml)
     */
    public $requiredPrefix = '';

    /**
     * List of functions that require prefixed string arguments.
     */
    private $functionsRequiringPrefix = [
        'apply_filters',
        'do_action',
        'set_transient',
        'get_transient',
        'delete_transient',
        'define',
        'register_setting',
        'add_option',
        'update_option',
        'delete_option',
        'get_option',
    ];

    /**
     * Register the tokens to look for.
     */
    public function register()
    {
        return [
            T_STRING,
            T_CONSTANT_ENCAPSED_STRING,
        ];
    }

    /**
     * Process the token.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $tokenContent = $tokens[$stackPtr]['content'];

        // Skip checking HTML or general text in echo statements
        if (stripos($tokenContent, '<') !== false && stripos($tokenContent, '>') !== false) {
            return; // Ignore HTML tags like '<p>'
        }

        // Skip checking function and method names
        if ($tokens[$stackPtr]['code'] === T_STRING) {
            return;
        }

        // Check if the string argument is part of a function that requires a prefix
        $prevTokenPtr = $phpcsFile->findPrevious([T_STRING, T_NAME_FULLY_QUALIFIED], $stackPtr - 1);
        $prevTokenContent = $prevTokenPtr !== false ? (string) GlobalName::get($phpcsFile, $prevTokenPtr) : '';

        // Only process string arguments in relevant functions
        if (in_array($prevTokenContent, $this->functionsRequiringPrefix, true)) {
            $this->checkPrefix($phpcsFile, $stackPtr, trim($tokenContent, '\'"'));
        }
    }

    /**
     * Check if the given string argument has the correct prefix.
     */
    private function checkPrefix(File $phpcsFile, $stackPtr, $name)
    {
        // Case-insensitive check for prefix
        if (stripos($name, $this->requiredPrefix) !== 0) {
            $phpcsFile->addError(
                'The element "%s" must use the prefix "%s".',
                $stackPtr,
                'MissingPrefix',
                [$name, $this->requiredPrefix]
            );
        }
    }
}