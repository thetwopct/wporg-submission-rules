# Development Guide

This guide explains how to develop and test the WP.org Submission Rules locally before publishing to Packagist.

## Testing Methods

### Method 1: Composer Path Repository (Recommended)

Use a local path repository in your test project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../wporg-submission-rules",
      "options": {
        "symlink": true
      }
    }
  ],
  "require-dev": {
    "squizlabs/php_codesniffer": "^3.13.6 || ^4.0.2",
    "thetwopct/wporg-submission-rules": "@dev"
  }
}
```

**Install and run:**
```bash
composer install
./vendor/bin/phpcs --standard=WPOrgSubmissionRules .
```

**Pros:** Changes reflect immediately, clean setup
**Cons:** Requires composer.json modification

### Method 2: phpcs Installed Paths

Configure phpcs globally to know about your local sniffs:

```bash
phpcs --config-set installed_paths /path/to/wporg-submission-rules
phpcs -i  # Verify WPOrgSubmissionRules appears
```

**Then use from anywhere:**
```bash
cd /any/wordpress-plugin
phpcs --standard=WPOrgSubmissionRules .
```

**Pros:** No composer.json changes needed, works everywhere
**Cons:** Global configuration, manual setup

### Method 3: Direct Path in phpcs.xml

Create `.phpcs.xml` in your test project:

```xml
<?xml version="1.0"?>
<ruleset name="My Plugin">
    <config name="installed_paths" value="/path/to/wporg-submission-rules"/>
    <rule ref="WPOrgSubmissionRules"/>
</ruleset>
```

**Run:**
```bash
phpcs --standard=.phpcs.xml .
```

**Pros:** Project-specific, no global changes
**Cons:** Requires phpcs.xml file

### Method 4: wp-env (Docker) Testing

For wp-env projects, mount the sniffs directory and run inside Docker:

**1. Add to `.wp-env.json`:**
```json
{
  "mappings": {
    "wp-content/mu-plugins/wporg-sniffs": "../wporg-submission-rules"
  }
}
```

**2. Update `composer.json` with Docker path:**
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/var/www/html/wp-content/mu-plugins/wporg-sniffs",
      "options": {
        "symlink": true
      }
    }
  ],
  "require-dev": {
    "thetwopct/wporg-submission-rules": "@dev"
  }
}
```

**3. Run inside Docker:**
```bash
wp-env run cli --env-cwd=wp-content/plugins/your-plugin composer install
wp-env run cli --env-cwd=wp-content/plugins/your-plugin vendor/bin/phpcs --standard=WPOrgSubmissionRules .
```

**Pros:** Matches production environment, consistent with wp-env workflow
**Cons:** More complex setup, slower iteration

## PHP_CodeSniffer 3 and 4

The sniffs support PHP 7.2+ and PHP_CodeSniffer 3.13.6+ and 4.0.2+ (earlier versions have a security vulnerability, CVE-2026-67434). The main difference between them is how namespaced names are tokenized:

- PHP_CodeSniffer 3 splits `\wp_remote_get` into `T_NS_SEPARATOR` and `T_STRING`, and `Foo\Bar` into `T_STRING`, `T_NS_SEPARATOR`, `T_STRING`
- PHP_CodeSniffer 4 keeps them as single `T_NAME_FULLY_QUALIFIED` and `T_NAME_QUALIFIED` tokens

A sniff that looks for a global function call should register both `T_STRING` and `T_NAME_FULLY_QUALIFIED`, and read the name with `WPOrgSubmissionRules\Helpers\GlobalName::get()`. It returns `wp_remote_get` for either version, and `null` for namespaced functions like `Foo\wp_remote_get()`.

Use the `Tokens::$emptyTokens` style properties, not the `Tokens::EMPTY_TOKENS` constants, which don't exist in PHP_CodeSniffer 3.

Helper classes go in `WPOrgSubmissionRules/Helpers/`. They're loaded by `WPOrgSubmissionRules/autoload.php`, because PHP_CodeSniffer doesn't autoload a standard's own classes when it's referenced from another ruleset.

To test against both versions, install each into its own directory and run the tests with each, from the repo root:

```bash
composer require squizlabs/php_codesniffer:^3 --working-dir=/tmp/phpcs3
composer require squizlabs/php_codesniffer:^4 --working-dir=/tmp/phpcs4
/tmp/phpcs3/vendor/bin/phpcs --standard=./WPOrgSubmissionRules tests/test-plugin.php
/tmp/phpcs4/vendor/bin/phpcs --standard=./WPOrgSubmissionRules tests/test-plugin.php
```

The results should match, apart from the column of fully qualified calls.

## Common Issues & Solutions

### Path Repository Not Found
**Error:** `The url supplied for the path (...) repository does not exist`

**Solutions:**
- Verify path exists: `ls -la /path/to/wporg-submission-rules/composer.json`
- Use relative paths: `../wporg-submission-rules`
- For wp-env: ensure mapping exists and restart: `wp-env destroy && wp-env start`

### Minimum Stability Error
**Error:** `minimum-stability` issues with `*` version

**Solution:** Use `@dev` instead of `*`:
```json
"thetwopct/wporg-submission-rules": "@dev"
```

### Changes Not Reflecting
**Solutions:**
- **Symlinks:** Run `composer update`
- **Docker:** Restart wp-env
- **Global paths:** No action needed

### Wrong phpcs Version
**Problem:** Using global phpcs instead of project version

**Solution:** Always use `./vendor/bin/phpcs` in projects with composer dependencies

## Debugging Commands

```bash
# List installed standards
phpcs -i

# Show all sniffs in standard
phpcs -e --standard=WPOrgSubmissionRules

# Verbose output
phpcs -v --standard=WPOrgSubmissionRules file.php

# Show sniff codes
phpcs -s --standard=WPOrgSubmissionRules file.php

# Test specific sniff
phpcs --standard=WPOrgSubmissionRules --sniffs=WPOrgSubmissionRules.Naming.PrefixLength file.php

# Show current config
phpcs --config-show
```
