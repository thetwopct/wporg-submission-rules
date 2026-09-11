<?php
namespace WPOrgSubmissionRules\Sniffs\PluginHeader;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Detects a "Tested up to" header in the main plugin file.
 *
 * "Tested up to" is a readme.txt header, not a plugin header. Declaring it in the
 * main PHP file can override the readme value on WordPress.org.
 *
 * Headers are parsed the same way as WordPress core's get_file_data(): only the
 * first 8 KB of the file is read, and the main plugin file is the one with a
 * "Plugin Name" header.
 */
class TestedUpToSniff implements Sniff
{
    /**
     * Number of bytes WordPress reads when parsing file headers.
     */
    const HEADER_BYTES = 8192;

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
        $content = substr($phpcsFile->getTokensAsString(0, $phpcsFile->numTokens, true), 0, self::HEADER_BYTES);
        $lines   = preg_split('/\r\n|\r|\n/', $content);

        if ($this->getHeaderValue($lines, 'Plugin Name') !== null) {
            foreach ($lines as $index => $line) {
                $value = $this->matchHeader($line, 'Tested up to');
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

    /**
     * Returns the first non-empty value of a header, or null if it is not declared.
     */
    private function getHeaderValue(array $lines, $name)
    {
        foreach ($lines as $line) {
            $value = $this->matchHeader($line, $name);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Matches a single line against a header using the same pattern as get_file_data().
     */
    private function matchHeader($line, $name)
    {
        if (!preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/i', $line, $matches)) {
            return null;
        }

        // Mirrors _cleanup_header_comment().
        return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $matches[1]));
    }
}
