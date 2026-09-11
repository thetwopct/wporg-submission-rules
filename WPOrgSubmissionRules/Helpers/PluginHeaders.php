<?php
namespace WPOrgSubmissionRules\Helpers;

use PHP_CodeSniffer\Files\File;

/**
 * Reads plugin file headers the same way as WordPress core's get_file_data().
 *
 * Only the first 8 KB of the file is read, and the main plugin file is the one
 * with a "Plugin Name" header.
 */
class PluginHeaders
{
    /**
     * Number of bytes WordPress reads when parsing file headers.
     */
    const HEADER_BYTES = 8192;

    /**
     * Returns the lines of the file that WordPress reads headers from.
     */
    public static function getLines(File $phpcsFile)
    {
        $content = substr($phpcsFile->getTokensAsString(0, $phpcsFile->numTokens, true), 0, self::HEADER_BYTES);

        return preg_split('/\r\n|\r|\n/', $content);
    }

    /**
     * Whether the lines declare a non-empty "Plugin Name" header.
     */
    public static function isMainPluginFile(array $lines)
    {
        foreach ($lines as $line) {
            $value = self::match($line, 'Plugin Name');
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches a single line against a header using the same pattern as get_file_data().
     *
     * Returns the header's value, or null if the line is not that header.
     */
    public static function match($line, $name)
    {
        if (!preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/i', $line, $matches)) {
            return null;
        }

        // Mirrors _cleanup_header_comment().
        return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $matches[1]));
    }
}
