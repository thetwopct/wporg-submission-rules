<?php
namespace WPOrgSubmissionRules\Sniffs\PluginHeader;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use WPOrgSubmissionRules\Helpers\PluginHeaders;

/**
 * Detects a "Tested up to" header in the main plugin file.
 *
 * "Tested up to" is a readme.txt header, not a plugin header. Declaring it in the
 * main PHP file can override the readme value on WordPress.org.
 */
class TestedUpToSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }

    /**
     * Processes the tokens that this sniff is interested in.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $lines = PluginHeaders::getLines($phpcsFile);

        if (PluginHeaders::isMainPluginFile($lines)) {
            foreach ($lines as $index => $line) {
                $value = PluginHeaders::match($line, 'Tested up to');
                if ($value !== null) {
                    $phpcsFile->addErrorOnLine(
                        'Declare "Tested up to" only in your readme.txt, not in the main plugin file headers. It is not a plugin header and may override the readme value on WordPress.org. Found: "Tested up to: %s"',
                        $index + 1,
                        'Found',
                        [$value]
                    );
                }
            }
        }

        // Headers are file-level, so only process each file once.
        return $phpcsFile->numTokens;
    }
}
