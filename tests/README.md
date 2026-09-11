# Testing WP.org Submission Rules

This directory contains test files and scripts for validating the WP.org Submission Rules sniffs.

## Quick Start

To run all tests on the test plugin file:

```bash
./run-tests.sh
```

Or run phpcs directly:

```bash
phpcs --standard=WPOrgSubmissionRules test-plugin.php
```

## Test Files

### `test-plugin.php`

A sample WordPress plugin file containing **deliberate violations** to test all the sniffs. This file includes:

#### Prefix Length Violations
- Short prefixes (< 4 characters): `BFG_`, `bfg_`, `ABC_`
- Examples from lines 10, 13, 29, 41, 65, 100

#### Reserved Prefix Violations
- `wp_` prefix (reserved for WordPress core) - line 24
- Single underscore `_` prefix - line 103

#### Security Violations
- Missing nonce checks on `$_POST` usage - lines 42-43
- Missing nonce checks on `$_REQUEST` usage - lines 116-117
- `$_GET` used outside function (performance issue) - line 49
- No direct file access check before the first code - line 10

#### Anti-Pattern Violations
- `if (!function_exists())` wrapper - line 34

#### Inline Tags Violations
- Inline `<script>` tag - line 80
- Inline `<style>` tag - line 85

#### Translation Function Violations
- Variable used in `__()` function instead of string literal - line 91

#### Plugin Header Violations
- `Tested up to` declared in the main plugin file header - line 6

#### External Services Violations
Checked against the `== External services ==` section of `readme.txt` in this directory.
- `substack.com` not documented in the readme - line 131
- `mailgun.net` documented without terms and privacy links (warning) - line 134

#### Concurrency Warnings
- Read-increment-write on a transient (rate limit) - line 152
- Check-then-set on a transient (duplicate lock) - line 161
- Cache refill on a transient is OK and not flagged - line 170

## Expected Test Results

When running the sniffs on `test-plugin.php`, you should see approximately:
- **22 errors**
- **5 warnings**

### Key Violations Detected

1. ✅ **Short Prefixes**: `BFG`, `bfg`, `ABC`, `my`, `add`, `do` (all < 4 chars)
2. ✅ **Reserved Prefixes**: `wp_`, `_`
3. ✅ **Missing Nonce Checks**: 4 instances of `$_POST`/`$_REQUEST` without verification
4. ✅ **Performance Issue**: `$_GET` used outside of function
5. ✅ **Function Exists Anti-pattern**: Using `if (!function_exists())` wrapper
6. ✅ **Inline Tags**: Both `<script>` and `<style>` tags
7. ✅ **Translation Issues**: Variable in `__()` function
8. ✅ **Tested up to Header**: Declared in the plugin header instead of readme.txt
9. ✅ **External Services**: Undocumented service, and a service without terms/privacy links
10. ✅ **Non-atomic Transients**: Read-increment-write and check-then-set race conditions
11. ✅ **Direct File Access**: No `ABSPATH` check in a file that runs code

## Testing on Your Own Plugin

To test these rules on your own WordPress plugin:

```bash
# From the project root
phpcs --standard=WPOrgSubmissionRules /path/to/your/plugin/

# Or with more detail
phpcs -v --standard=WPOrgSubmissionRules /path/to/your/plugin/
```

## Configuring Prefix Requirements

You can customize the minimum prefix length in your `.phpcs.xml` file:

```xml
<rule ref="WPOrgSubmissionRules.Naming.PrefixLength">
    <properties>
        <property name="minPrefixLength" value="5"/>
    </properties>
</rule>
```

## Understanding the Rules

### 1. Prefix Length Check
The sniff extracts the prefix by taking everything **before the first underscore** (`_`).

Examples:
- `BFG_For_ACF` → prefix is `BFG` (3 chars, too short)
- `bettfigr_save_post` → prefix is `bettfigr` (8 chars, OK)
- `define('ABC_CONSTANT')` → prefix is `ABC` (3 chars, too short)

### 2. Reserved Prefix Check
WordPress reserves these prefixes:
- `wp_` - for WordPress core functions
- `_` (single underscore) - for WordPress internal use
- `__` (double underscore) - for magic methods (allowed in functions)

### 3. Nonce Check
Any usage of `$_POST`, `$_GET`, or `$_REQUEST` should have a corresponding nonce verification:
- `wp_verify_nonce()`
- `check_ajax_referer()`
- `check_admin_referer()`

### 4. Function Exists Wrapper
Don't wrap your functions in `if (!function_exists())`. If another plugin loads first with the same function name, your plugin will silently fail. Use unique prefixes instead.

### 5. Tested up to Header
The main plugin file is found the same way WordPress does it: the file with a `Plugin Name:` header in its first 8 KB. Any `Tested up to:` header in that file is flagged. To suppress it, put `// phpcs:disable` before the header docblock, because `phpcs:ignore` inside a docblock is not read.

### 6. External Services
The plugin root is the nearest parent directory containing the main plugin file, and the readme is `readme.txt` (or `readme.md`) in that directory. The section can be titled `External services`, `Third party services` or `3rd party services`.

Remote requests are calls to `wp_remote_*()`, `wp_safe_remote_*()`, `wp_remote_fopen()`, `download_url()`, `curl_init()`, `fsockopen()`, or `file_get_contents()` with a URL. In files that make one, every URL in a string is checked:
- `MissingReadme` / `MissingSection` (error) - no readme, or no `External services` section. Reported at each remote request.
- `UndocumentedService` (error) - the URL's domain isn't mentioned in the section. Mention it by domain (`substack.com`) or name (`Substack`).
- `MissingPolicyLinks` (warning) - the service is mentioned, but is missing a line with a URL and "terms" (or "tos", "legal", "conditions"), or a line with a URL and "privacy". With a `= Service name =` subheading per service, only that service's subheading is checked.

Domains are matched on their registrable domain, so `api.substack.com` needs `substack.com`. A readme that only says "Google Maps" will not match `maps.googleapis.com`, so name the domain.

Known limitations:
- JavaScript `fetch()` calls are not checked, as the ruleset only checks PHP files
- URLs defined in one file and requested from another are not matched, although a missing section is still reported
- Requests made through HTTP libraries such as Guzzle are not detected
- It can't check that the terms and privacy links exist and have the proper content, which reviewers do check

### 7. Non-atomic Transients
For each `set_transient()` / `set_site_transient()`, the sniff looks for an earlier read of the same key (written the same way) in the same function:
- `ReadModifyWrite` (warning) - the value written is derived from the value read: `$count + 1`, `$count++`, `$count += 1`, `$count = $count + 1`, `$list[] = $item`, or `get_transient($key) + 1`
- `CheckThenSet` (warning) - the value read is used in an `if` condition, and a fixed flag is written: `1`, `true`, `'locked'` or `time()`

To fix them:
- **Lock** - `wp_cache_add()` with a persistent object cache (`wp_using_ext_object_cache()`), or an `INSERT IGNORE` into the options table and check a row was inserted (see `WP_Upgrader::create_lock()`). `add_option()` is not a safe lock: it checks whether the option exists before inserting, and its insert overwrites rather than fails, so two simultaneous requests can both succeed.
- **Counter** - `wp_cache_incr()` with a persistent object cache, or an atomic `UPDATE ... SET option_value = option_value + 1`

Known limitations:
- "Only do this once" flags (e.g. an admin notice) are flagged as `CheckThenSet`, even though a race there is harmless
- A key read in one function and written in another, or built differently at each call, is not matched
- Options, meta and object cache values have the same race, but are not checked

### 8. Direct File Access
The guard can come after the opening tag, comments, `declare()`, the `namespace` line and `use` imports, but must come before any other code:
- `Missing` (error) - the file runs code when loaded and has no guard. Reported at the first line of code.
- `Misplaced` (error) - there is a guard, but code runs before it. Reported at the first line of code.

Accepted guards, with `defined()` (any constant) or `function_exists()`:
- `if ( ! defined( 'ABSPATH' ) ) exit;`, with or without braces, using `exit`, `die` or `return`. `false === defined( 'ABSPATH' )` also works, and the body can do other things before exiting (e.g. send a 403 header).
- `defined( 'ABSPATH' ) || exit;` or `defined( 'ABSPATH' ) or die( 'message' );`

Code that runs when loaded includes function calls (including `define()`), `new`, `require`/`include`, variable assignments, `echo`, HTML outside of `<?php` tags and control structures such as `if ( ! class_exists() )`. Command-line scripts that are meant to be run directly, such as build scripts, should be excluded in your phpcs config.

## What's NOT Checked

The sniffs intentionally skip:
- **Class methods** - Only standalone functions are checked for prefixes
- **Magic methods** - `__construct()`, `__call()`, etc. are allowed
- **Translation functions** - `__()`, `_e()`, etc. are excluded from prefix checks
- **Namespaced classes** - Classes inside namespaces don't require underscored prefixes
- **Other plugin files** - `Tested up to` is only checked in the main plugin file
- **Plain links** - URLs in files that don't make remote requests aren't checked against the readme
- **WordPress.org and local URLs** - `wordpress.org`, `example.com`, `localhost`, `.test`/`.local` domains and private IPs never need documenting
- **Cache refills** - Reading a transient, then writing freshly computed data back to it, isn't flagged as a race
- **Definition-only files** - Files that only contain class, interface, trait, enum, function or `const` definitions don't need a direct file access check
- **Array-only files** - Files that only `return` an array, such as `index.asset.php` build files, don't need a direct file access check

## Running Specific Sniffs

To test only specific sniffs:

```bash
# Only test prefix length
phpcs --standard=WPOrgSubmissionRules --sniffs=WPOrgSubmissionRules.Naming.PrefixLength test-plugin.php

# Only test nonce checks
phpcs --standard=WPOrgSubmissionRules --sniffs=WPOrgSubmissionRules.Security.NonceCheck test-plugin.php

# Only test inline tags
phpcs --standard=WPOrgSubmissionRules --sniffs=WPOrgSubmissionRules.ForbiddenTags.ForbiddenInlineTags test-plugin.php
```

## Available Sniffs

View all available sniffs in the standard:

```bash
phpcs -e --standard=WPOrgSubmissionRules
```

Current sniffs:
- `WPOrgSubmissionRules.Concurrency.NonAtomicTransient`
- `WPOrgSubmissionRules.ExternalServices.Disclosure`
- `WPOrgSubmissionRules.ForbiddenTags.ForbiddenInlineTags`
- `WPOrgSubmissionRules.Internationalization.TranslationFunctionStringLiteral`
- `WPOrgSubmissionRules.Naming.FunctionExistsWrapper`
- `WPOrgSubmissionRules.Naming.PrefixLength`
- `WPOrgSubmissionRules.Naming.UniqueName`
- `WPOrgSubmissionRules.PluginHeader.TestedUpTo`
- `WPOrgSubmissionRules.Security.DirectFileAccess`
- `WPOrgSubmissionRules.Security.NonceCheck`

## Troubleshooting

### "Standard not found" error
Make sure you've installed the package and the standard is registered:

```bash
composer install
phpcs -i
```

You should see `WPOrgSubmissionRules` in the list.

### Too many false positives
Some sniffs might be overly strict for your use case. You can exclude specific sniffs in your `.phpcs.xml`:

```xml
<rule ref="WPOrgSubmissionRules">
    <exclude name="WPOrgSubmissionRules.Naming.FunctionExistsWrapper"/>
</rule>
```

### Need to whitelist specific code
Use phpcs annotations to ignore specific lines:

```php
// phpcs:ignore WPOrgSubmissionRules.Naming.PrefixLength
function wp_my_custom_function() {
    // This line will be ignored
}
```

## Contributing

Found a false positive or want to add a new rule? Please [open an issue](https://github.com/thetwopct/wp-org-submission-rules/issues) on GitHub.
