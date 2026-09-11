<?php
namespace WPOrgSubmissionRules\Sniffs\Internationalization;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use WPOrgSubmissionRules\Helpers\GlobalName;

class TranslationFunctionStringLiteralSniff implements Sniff
{
    /**
     * Returns the tokens that this sniff is interested in.
     */
    public function register()
    {
        return [T_STRING, T_NAME_FULLY_QUALIFIED];
    }

	/**
     * Processes the tokens and identifies errors.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $functionName = GlobalName::get($phpcsFile, $stackPtr);

        $gettextFunctions = [
            '__', '_e', '_x', 'esc_html__', 'esc_html_e', 'esc_html_x',
            'esc_attr__', 'esc_attr_e', 'esc_attr_x'
        ];

        // Only target known translation functions
        if (!in_array($functionName, $gettextFunctions, true)) {
            return;
        }

        $openingParenthesis = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr);
        $closingParenthesis = $tokens[$openingParenthesis]['parenthesis_closer'];
        $parameters = $this->getFunctionParameters($phpcsFile, $openingParenthesis, $closingParenthesis);

        // Check the first parameter (text to be translated)
        if (isset($parameters[0])) {
            $textToken = $tokens[$parameters[0]];
            if ($textToken['code'] !== T_CONSTANT_ENCAPSED_STRING) {
                $phpcsFile->addError(
                    'The text parameter of %s() must be a string literal, not a variable or function call.',
                    $stackPtr,
                    'TextNotLiteral',
                    [$functionName]
                );
            }
        }

        // Check the second parameter (text domain)
        if (isset($parameters[1])) {
            $domainToken = $tokens[$parameters[1]];
            if ($domainToken['code'] !== T_CONSTANT_ENCAPSED_STRING) {
                $phpcsFile->addError(
                    'The text domain parameter of %s() must be a string literal.',
                    $stackPtr,
                    'TextDomainNotLiteral',
                    [$functionName]
                );
            }
        }
    }

    /**
     * Retrieves the parameters of a function call.
     */
    private function getFunctionParameters(File $phpcsFile, $openParenthesis, $closeParenthesis)
    {
        $tokens = $phpcsFile->getTokens();
        $params = [];
        $currentLevel = 0;
        $lastParam = $openParenthesis + 1;

        for ($i = $openParenthesis + 1; $i < $closeParenthesis; $i++) {
            // Keep track of nested parentheses
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $currentLevel++;
            } elseif ($tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
                $currentLevel--;
            }

            // Comma separates parameters
            if ($tokens[$i]['code'] === T_COMMA && $currentLevel === 0) {
                $params[] = $phpcsFile->findNext(Tokens::$emptyTokens, $lastParam, $i, true);
                $lastParam = $i + 1;
            }
        }

        // Add the last parameter (or only one if no comma)
        $params[] = $phpcsFile->findNext(Tokens::$emptyTokens, $lastParam, $closeParenthesis, true);

        return $params;
    }
}