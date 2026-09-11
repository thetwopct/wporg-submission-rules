<?php
namespace WPOrgSubmissionRules\Sniffs\ExternalServices;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use WPOrgSubmissionRules\Helpers\GlobalName;

/**
 * Detects external services that are not documented in the plugin's readme.
 *
 * WordPress.org requires every external service a plugin connects to be documented
 * in an "External services" readme section: what the service is and what it is used
 * for, what data is sent and when, and links to its terms of service and privacy policy.
 *
 * For each file that makes a remote request, this sniff:
 * - checks the plugin's readme has an "External services" section;
 * - checks every URL host found in that file's strings is mentioned in the section;
 * - warns when a mentioned service has no terms of service or privacy policy link.
 *
 * The plugin root is the nearest parent directory containing a PHP file with a
 * "Plugin Name" header, which is where the readme is expected to be.
 */
class DisclosureSniff implements Sniff
{
    /**
     * Domains (and their subdomains) that never need documenting.
     * Can be extended via ruleset.xml.
     */
    public $excludedDomains = [
        'wordpress.org',
        'w.org',
        'wp.org',
        'example.com',
        'example.net',
        'example.org',
        'localhost',
        'schema.org',
        'w3.org',
    ];

    /**
     * Functions that make remote requests.
     */
    private $remoteFunctions = [
        'wp_remote_get',
        'wp_remote_post',
        'wp_remote_head',
        'wp_remote_request',
        'wp_safe_remote_get',
        'wp_safe_remote_post',
        'wp_safe_remote_head',
        'wp_safe_remote_request',
        'wp_remote_fopen',
        'download_url',
        'curl_init',
        'fsockopen',
    ];

    /**
     * Plugin root for each directory checked, or false if there isn't one.
     */
    private $pluginRoots = [];

    /**
     * Parsed readme for each plugin root.
     */
    private $readmes = [];

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
        $calls = $this->findRemoteCalls($phpcsFile);
        if ($calls === []) {
            return $phpcsFile->numTokens;
        }

        // Without a plugin root there is no readme to compare against.
        $root = $this->findPluginRoot(dirname($phpcsFile->getFilename()));
        if ($root === false) {
            return $phpcsFile->numTokens;
        }

        $readme = $this->getReadme($root);

        foreach ($calls as $call) {
            if ($readme['file'] === null) {
                $phpcsFile->addError(
                    'This plugin connects to an external service, but no readme.txt was found in the plugin root (%s) to document it. Add an "== External services ==" section explaining what each service is used for, what data is sent and when, with links to its terms of service and privacy policy.',
                    $call,
                    'MissingReadme',
                    [$root]
                );
            } elseif ($readme['section'] === null) {
                $phpcsFile->addError(
                    'This plugin connects to an external service, but %s has no "== External services ==" section. Document each service: what it is used for, what data is sent and when, with links to its terms of service and privacy policy.',
                    $call,
                    'MissingSection',
                    [basename($readme['file'])]
                );
            }
        }

        $this->checkServiceUrls($phpcsFile, (string) $readme['section']);

        // Remote calls and URLs are checked for the whole file, so only process each file once.
        return $phpcsFile->numTokens;
    }

    /**
     * Returns the stack pointers of remote request function calls.
     */
    private function findRemoteCalls(File $phpcsFile)
    {
        $tokens = $phpcsFile->getTokens();
        $calls  = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            $name = strtolower((string) GlobalName::get($phpcsFile, $i));
            if (!in_array($name, $this->remoteFunctions, true) && $name !== 'file_get_contents') {
                continue;
            }

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, $i + 1, null, true);
            if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
                continue;
            }

            // Skip method calls and declarations with the same name.
            $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, $i - 1, null, true);
            if ($prev !== false && in_array($tokens[$prev]['code'], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }

            // file_get_contents() only counts when it is given a URL.
            if ($name === 'file_get_contents') {
                $arg = $phpcsFile->findNext(Tokens::$emptyTokens, $next + 1, null, true);
                if ($arg === false || !preg_match('#^[\'"]https?://#i', $tokens[$arg]['content'])) {
                    continue;
                }
            }

            $calls[] = $i;
        }

        return $calls;
    }

    /**
     * Checks every URL host in the file's strings is documented in the readme section.
     */
    private function checkServiceUrls(File $phpcsFile, $section)
    {
        $tokens   = $phpcsFile->getTokens();
        $reported = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_CONSTANT_ENCAPSED_STRING && $tokens[$i]['code'] !== T_DOUBLE_QUOTED_STRING) {
                continue;
            }

            if (!preg_match_all('#https?://([a-z0-9-]+(?:\.[a-z0-9-]+)+)#i', $tokens[$i]['content'], $matches)) {
                continue;
            }

            foreach ($matches[1] as $host) {
                $host = strtolower($host);
                if ($this->isExcludedHost($host)) {
                    continue;
                }

                // Report each service once per file.
                $domain = $this->getBaseDomain($host);
                if (isset($reported[$domain])) {
                    continue;
                }
                $reported[$domain] = true;

                if (!$this->mentionsService($section, $domain)) {
                    $phpcsFile->addError(
                        'The external service "%s" is not documented in the readme\'s "== External services ==" section. Explain what it is used for, what data is sent and when, with links to its terms of service and privacy policy.',
                        $i,
                        'UndocumentedService',
                        [$domain]
                    );
                } elseif (!$this->hasPolicyLinks($section, $domain)) {
                    $phpcsFile->addWarning(
                        'Could not find terms of service and privacy policy links for the external service "%s" in the readme\'s "== External services ==" section. Reviewers will check these links exist and have the proper content.',
                        $i,
                        'MissingPolicyLinks',
                        [$domain]
                    );
                }
            }
        }
    }

    /**
     * Whether a host is local, reserved or excluded.
     */
    private function isExcludedHost($host)
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        if (preg_match('/\.(?:test|local|localhost|invalid|example)$/', $host)) {
            return true;
        }

        foreach ($this->excludedDomains as $domain) {
            $domain = strtolower(trim($domain));
            if ($host === $domain || substr($host, -strlen($domain) - 1) === '.' . $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduces a host to its registrable domain, e.g. api.example.co.uk to example.co.uk.
     */
    private function getBaseDomain($host)
    {
        $labels = explode('.', $host);
        $count  = count($labels);

        if ($count <= 2 || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        // Second-level country domains such as co.uk and com.au.
        $keep = strlen($labels[$count - 1]) === 2 && in_array($labels[$count - 2], ['ac', 'co', 'com', 'edu', 'gov', 'net', 'org'], true) ? 3 : 2;

        return implode('.', array_slice($labels, -$keep));
    }

    /**
     * Whether the text mentions the service by domain (substack.com) or name (Substack).
     */
    private function mentionsService($text, $domain)
    {
        if (stripos($text, $domain) !== false) {
            return true;
        }

        $name = strtok($domain, '.');

        return strlen($name) >= 3 && preg_match('/\b' . preg_quote($name, '/') . '\b/i', $text) === 1;
    }

    /**
     * Whether the service's part of the section links to terms of service and a privacy policy.
     */
    private function hasPolicyLinks($section, $domain)
    {
        // If the section has a subheading per service, only look at the ones that mention it.
        $blocks   = preg_split('/^\s*(?:=[^=].*=|#{3,}\s.*)\s*$/m', $section);
        $relevant = '';
        foreach ($blocks as $block) {
            if ($this->mentionsService($block, $domain)) {
                $relevant .= $block . "\n";
            }
        }
        if ($relevant === '') {
            $relevant = $section;
        }

        $hasTerms   = false;
        $hasPrivacy = false;
        foreach (preg_split('/\r\n|\r|\n/', $relevant) as $line) {
            if (!preg_match('#https?://#i', $line)) {
                continue;
            }
            $hasTerms   = $hasTerms || preg_match('/\b(?:terms|tos|legal|conditions)\b/i', $line) === 1;
            $hasPrivacy = $hasPrivacy || stripos($line, 'privacy') !== false;
        }

        return $hasTerms && $hasPrivacy;
    }

    /**
     * Finds the plugin root: the nearest directory containing the main plugin file.
     */
    private function findPluginRoot($dir)
    {
        $visited = [];

        while (true) {
            if (isset($this->pluginRoots[$dir])) {
                $root = $this->pluginRoots[$dir];
                break;
            }

            $visited[] = $dir;

            if ($this->containsMainPluginFile($dir)) {
                $root = $dir;
                break;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                $root = false;
                break;
            }
            $dir = $parent;
        }

        foreach ($visited as $visitedDir) {
            $this->pluginRoots[$visitedDir] = $root;
        }

        return $root;
    }

    /**
     * Whether a directory has a PHP file with a "Plugin Name" header, read the way get_file_data() does.
     */
    private function containsMainPluginFile($dir)
    {
        $files = glob($dir . '/*.php');

        foreach ($files ?: [] as $file) {
            $header = file_get_contents($file, false, null, 0, 8192);
            if ($header !== false && preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*Plugin Name:[ \t]*[^\s*]/mi', $header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds the readme in the plugin root and extracts its "External services" section.
     */
    private function getReadme($root)
    {
        if (isset($this->readmes[$root])) {
            return $this->readmes[$root];
        }

        $readme  = ['file' => null, 'section' => null];
        $entries = scandir($root);

        foreach (['readme.txt', 'readme.md'] as $name) {
            foreach ($entries ?: [] as $entry) {
                if (strtolower($entry) === $name) {
                    $readme['file'] = $root . DIRECTORY_SEPARATOR . $entry;
                    break 2;
                }
            }
        }

        if ($readme['file'] !== null) {
            $readme['section'] = $this->getExternalServicesSection((string) file_get_contents($readme['file']));
        }

        return $this->readmes[$root] = $readme;
    }

    /**
     * Returns the text of the "External services" section, or null if there isn't one.
     *
     * Accepts readme.txt (== Heading ==) and Markdown (## Heading) sections titled
     * "External services", "Third party services" or "3rd party services".
     */
    private function getExternalServicesSection($contents)
    {
        $section = null;

        foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
            if (preg_match('/^\s*(?:={2,}\s*(.+?)\s*={2,}|#{1,2}\s+(.+?)[\s#]*)$/', $line, $matches)) {
                if ($section !== null) {
                    break;
                }

                $title = $matches[1] !== '' ? $matches[1] : $matches[2];
                if (preg_match('/\b(?:external|third[\s-]*party|3rd[\s-]*party)\s+services?\b/i', $title)) {
                    $section = '';
                }
                continue;
            }

            if ($section !== null) {
                $section .= $line . "\n";
            }
        }

        return $section;
    }
}
