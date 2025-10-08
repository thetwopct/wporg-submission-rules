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
- Examples from lines 9, 12, 28, 40, 64, 99

#### Reserved Prefix Violations
- `wp_` prefix (reserved for WordPress core) - line 23
- Single underscore `_` prefix - line 102

#### Security Violations
- Missing nonce checks on `$_POST` usage - lines 41-42
- Missing nonce checks on `$_REQUEST` usage - lines 115-116
- `$_GET` used outside function (performance issue) - line 48

#### Anti-Pattern Violations
- `if (!function_exists())` wrapper - line 33

#### Inline Tags Violations
- Inline `<script>` tag - line 79
- Inline `<style>` tag - line 84

#### Translation Function Violations
- Variable used in `__()` function instead of string literal - line 90

## Expected Test Results

When running the sniffs on `test-plugin.php`, you should see approximately:
- **19 errors**
- **2 warnings**

### Key Violations Detected

1. ✅ **Short Prefixes**: `BFG`, `bfg`, `ABC`, `my`, `add`, `do` (all < 4 chars)
2. ✅ **Reserved Prefixes**: `wp_`, `_`
3. ✅ **Missing Nonce Checks**: 4 instances of `$_POST`/`$_REQUEST` without verification
4. ✅ **Performance Issue**: `$_GET` used outside of function
5. ✅ **Function Exists Anti-pattern**: Using `if (!function_exists())` wrapper
6. ✅ **Inline Tags**: Both `<script>` and `<style>` tags
7. ✅ **Translation Issues**: Variable in `__()` function

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

## What's NOT Checked

The sniffs intentionally skip:
- **Class methods** - Only standalone functions are checked for prefixes
- **Magic methods** - `__construct()`, `__call()`, etc. are allowed
- **Translation functions** - `__()`, `_e()`, etc. are excluded from prefix checks
- **Namespaced classes** - Classes inside namespaces don't require underscored prefixes

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
- `WPOrgSubmissionRules.ForbiddenTags.ForbiddenInlineTags`
- `WPOrgSubmissionRules.Internationalization.TranslationFunctionStringLiteral`
- `WPOrgSubmissionRules.Naming.FunctionExistsWrapper`
- `WPOrgSubmissionRules.Naming.PrefixLength`
- `WPOrgSubmissionRules.Naming.UniqueName`
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
