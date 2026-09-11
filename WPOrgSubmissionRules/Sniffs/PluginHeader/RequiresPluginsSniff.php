<?php
namespace WPOrgSubmissionRules\Sniffs\PluginHeader;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use WPOrgSubmissionRules\Helpers\PluginHeaders;

/**
 * Checks the "Requires Plugins" header in the main plugin file.
 *
 * The header is a comma-separated list of WordPress.org plugin slugs. WordPress
 * ignores anything that isn't in slug format, and can only install dependencies
 * that are in the WordPress.org plugin directory. For each slug, this sniff:
 * - checks it is in slug format (classic-editor, not "Classic Editor");
 * - checks it isn't the plugin's own slug;
 * - looks it up in the WordPress.org plugin directory, and reports it if it
 *   doesn't exist or has been closed.
 *
 * The directory is only contacted when a main plugin file declares the header.
 * Results are cached for a day, and the lookup can be turned off with the
 * checkDirectory property.
 */
class RequiresPluginsSniff implements Sniff
{
    /**
     * Whether to look up each slug in the WordPress.org plugin directory.
     * Set to false in ruleset.xml to never make network requests.
     */
    public $checkDirectory = true;

    /**
     * WordPress.org plugin information API.
     */
    const API_URL = 'https://api.wordpress.org/plugins/info/1.2/';

    /**
     * Seconds to wait for the WordPress.org API.
     */
    const TIMEOUT = 5;

    /**
     * Seconds to cache lookup results on disk.
     */
    const CACHE_TTL = 86400;

    /**
     * Lookup results by slug, loaded from the disk cache on first use.
     */
    private $cache = null;

    /**
     * Whether a lookup has failed this run, so the rest are skipped rather than each timing out.
     */
    private $unreachable = false;

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
                $value = PluginHeaders::match($line, 'Requires Plugins');
                if ($value !== null) {
                    $this->checkSlugs($phpcsFile, $value, $index + 1);
                    // WordPress only reads the first one.
                    break;
                }
            }
        }

        // Headers are file-level, so only process each file once.
        return $phpcsFile->numTokens;
    }

    /**
     * Checks each slug in the header's value.
     */
    private function checkSlugs(File $phpcsFile, $value, $line)
    {
        $ownSlug = basename(dirname($phpcsFile->getFilename()));

        foreach (explode(',', $value) as $slug) {
            $slug = trim($slug);
            if ($slug === '') {
                continue;
            }

            // The same format check as WP_Plugin_Dependencies::sanitize_dependency_slugs().
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $phpcsFile->addErrorOnLine(
                    '"%s" in the "Requires Plugins" header is not a WordPress.org plugin slug, so WordPress will ignore it. Use the slug at the end of the plugin\'s WordPress.org URL, e.g. "classic-editor" for https://wordpress.org/plugins/classic-editor/',
                    $line,
                    'InvalidSlug',
                    [$slug]
                );
                continue;
            }

            if ($slug === $ownSlug) {
                $phpcsFile->addErrorOnLine(
                    '"%s" in the "Requires Plugins" header is this plugin\'s own slug. A plugin cannot require itself, so remove it from the header.',
                    $line,
                    'SelfDependency',
                    [$slug]
                );
                continue;
            }

            if (!$this->checkDirectory) {
                continue;
            }

            $result = $this->lookup($slug);

            if ($result === null) {
                $phpcsFile->addWarningOnLine(
                    'Could not reach the WordPress.org plugin directory to check that "%s" in the "Requires Plugins" header exists. Set the checkDirectory property to false to skip this lookup.',
                    $line,
                    'LookupFailed',
                    [$slug]
                );
            } elseif ($result['status'] === 'not_found') {
                $phpcsFile->addErrorOnLine(
                    'Plugin with slug "%s" in the "Requires Plugins" header was not found in the WordPress.org plugin directory. Dependencies must be in the directory for this to work, so remove it from the header, or correct it to the slug at the end of the plugin\'s WordPress.org URL.',
                    $line,
                    'NotInDirectory',
                    [$slug]
                );
            } elseif ($result['status'] === 'closed') {
                $phpcsFile->addErrorOnLine(
                    'Plugin with slug "%s" in the "Requires Plugins" header has been closed on WordPress.org (%s) and cannot be installed, so remove it from the header.',
                    $line,
                    'ClosedInDirectory',
                    [$slug, $result['reason'] !== '' ? $result['reason'] : 'no reason given']
                );
            }
        }
    }

    /**
     * Looks up a slug, using the cache when it is fresh.
     *
     * Returns an array with a status of "found", "not_found" or "closed", or null if the lookup failed.
     */
    private function lookup($slug)
    {
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wp-org-submission-rules-requires-plugins.json';

        if ($this->cache === null) {
            $cached      = @file_get_contents($cacheFile);
            $cached      = is_string($cached) ? json_decode($cached, true) : null;
            $this->cache = is_array($cached) ? $cached : [];
        }

        if (isset($this->cache[$slug]['status'], $this->cache[$slug]['time']) && $this->cache[$slug]['time'] > time() - self::CACHE_TTL) {
            return $this->cache[$slug];
        }

        if ($this->unreachable) {
            return null;
        }

        $result = $this->fetch($slug);
        if ($result === null) {
            $this->unreachable = true;
            return null;
        }

        $result['time']      = time();
        $this->cache[$slug] = $result;
        @file_put_contents($cacheFile, json_encode($this->cache), LOCK_EX);

        return $result;
    }

    /**
     * Asks the WordPress.org plugin information API about a slug.
     */
    private function fetch($slug)
    {
        // Only the slug is needed, so leave out the fields that make up most of the response.
        $query = http_build_query([
            'action'  => 'plugin_information',
            'request' => [
                'slug'   => $slug,
                'fields' => array_fill_keys(['sections', 'versions', 'description', 'screenshots', 'tags', 'contributors', 'banners', 'icons', 'ratings', 'compatibility'], 0),
            ],
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout'       => self::TIMEOUT,
                // The API answers unknown and closed plugins with a 404, and the body says which.
                'ignore_errors' => true,
                'user_agent'    => 'wp-org-submission-rules',
            ],
        ]);

        $body = @file_get_contents(self::API_URL . '?' . $query, false, $context);
        $data = is_string($body) ? json_decode($body, true) : null;

        if (!is_array($data)) {
            return null;
        }

        if (isset($data['error'])) {
            if ($data['error'] === 'closed') {
                return ['status' => 'closed', 'reason' => isset($data['reason_text']) ? (string) $data['reason_text'] : ''];
            }

            // e.g. "Plugin not found."
            return is_string($data['error']) && stripos($data['error'], 'not found') !== false ? ['status' => 'not_found'] : null;
        }

        return isset($data['slug']) ? ['status' => 'found'] : null;
    }
}
