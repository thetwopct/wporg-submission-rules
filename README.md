# WordPress.org-specific plugin review code sniffs

When submitting a plugin to the WordPress.org repo, there are several checks that the plugin review team apply to your plugin, but which are not fully covered by WordPress Coding Standards or included in the [Plugin Check (PCP)](https://wordpress.org/plugins/plugin-check/) plugin.

This sniff ruleset tries to bring attention to and fix some of the checks that are missed to ensure your plugin passes first time, every time.

This ruleset is an additional ruleset you can add to [PHPCSStandards PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/). PHP CodeSniffer tokenizes PHP files and detects violations of a defined set of coding standards, and also corrects coding standard violations. PHP_CodeSniffer is an essential development tool that ensures your code remains clean and consistent.

If you use these sniffs and indeed PHP_CodeSniffer in your projects I would urge you to [donate](https://opencollective.com/php_codesniffer) _something_ to the project as without funding it will go away and all our code will be worse off.

## Install

Requires PHP 7.2 or later, and PHP_CodeSniffer 3.13.6+ or 4.0.2+.

The sniffs have been released on to [Packagist](https://packagist.org/packages/thetwopct/wp-org-submission-rules), so installation should be as simple as running:

```
composer require thetwopct/wp-org-submission-rules --dev
```

You can then check that the ruleset (WPOrgSubmissionRules) is now installed:

```
phpcs -i
```

You can then add it to your custom .phpcs.xml file to include in your sniffs:

```
<rule ref="WPOrgSubmissionRules"/>
```

or access the standard directly from the command line as per other standards:

```
phpcs --standard=WPOrgSubmissionRules your-file.php
```

or to run over your whole code:

```
phpcs --standard=WPOrgSubmissionRules .
```

One of the rules looks for unique names of variables, and you can add a prefix in your custom rules:

```
<rule ref="WPOrgSubmissionRules.Naming.UniqueName">
	<properties>
		<property name="requiredPrefix" value="my_unique_name_" />
	</properties>
</rule>
```

The external services rule ignores WordPress.org, `example.com` and local URLs. You can exclude other domains (this replaces the default list):

```
<rule ref="WPOrgSubmissionRules.ExternalServices.Disclosure">
	<properties>
		<property name="excludedDomains" type="array">
			<element value="wordpress.org"/>
			<element value="w.org"/>
			<element value="wp.org"/>
			<element value="example.com"/>
			<element value="mysite.com"/>
		</property>
	</properties>
</rule>
```

The `Requires Plugins` rule looks up each dependency in the WordPress.org plugin directory. You can turn the lookup off (for example, in CI without network access) and only check the slug format:

```
<rule ref="WPOrgSubmissionRules.PluginHeader.RequiresPlugins">
	<properties>
		<property name="checkDirectory" value="false" />
	</properties>
</rule>
```

## What the sniffs detect:

Here are some of the review issues from WordPress.org that these sniffs try to make sure you avoid:

### 1) Use wp_enqueue commands

Any inline CSS or JS is flagged via `<script>` or `<style>` tags.

**Sniff**: `WPOrgSubmissionRules.ForbiddenTags.ForbiddenInlineTags`

### 2) Generic function/class/define/namespace/option names

All plugins must have unique function names, namespaces, defines, class and option names. This prevents your plugin from conflicting with other plugins or themes. WordPress.org expect your plugin to use unique and distinct names.

**Sniff**: `WPOrgSubmissionRules.Naming.UniqueName`

### 3) Options and Transients must be prefixed

This is really important because the options are stored in a shared location and under the name you have set. If two plugins use the same name for options, they will find an interesting conflict when trying to read information introduced by the other plugin.

**Sniff**: `WPOrgSubmissionRules.Naming.UniqueName`

### 4) Internationalization: Don't use variables or defines as text, context or text domain parameters

In order to make a string translatable in your plugin you are using a set of special functions. These functions collectively are known as "gettext". There is a dedicated team in the WordPress community to translate and help other translating strings of WordPress core, plugins and themes to other languages.

To make them be able to translate this plugin, please do not use variables or function calls for the text, context or text domain parameters of any gettext function, all of them NEED to be strings. Note that the translation parser reads the code without executing it, so it won't be able to read anything that is not a string within these functions.

**Sniff**: `WPOrgSubmissionRules.Internationalization.TranslationFunctionStringLiteral`

### 5) Prefix length requirements

WordPress.org requires prefixes to be **at least 4 characters long**. The sniff detects short prefixes by extracting the part before the first underscore (this is dumb, but we need to play by their rules):

- `ABC_For_ACF` → prefix is `ABC` (3 chars, too short ❌)
- `abcfacf_save_post` → prefix is `abcfacf` (8 chars, OK ✅)

**Sniff**: `WPOrgSubmissionRules.Naming.PrefixLength`

### 6) Reserved prefixes (wp_, _, __)

WordPress reserves certain prefixes for core functionality:

- `wp_` - Reserved for WordPress core
- `_` (single underscore) - Reserved for WordPress internal use
- `__` (double underscore at start) - Reserved for magic methods

**Sniff**: `WPOrgSubmissionRules.Naming.PrefixLength`

### 7) Anti-pattern: function_exists() wrapper

Using `if (!function_exists('name')) { function name() {...} }` is an anti-pattern. If another plugin has a function with the same name and loads first, your plugin will silently fail. Use unique prefixes instead.

**Sniff**: `WPOrgSubmissionRules.Naming.FunctionExistsWrapper`

### 8) Declare "Tested up to" only in your readme file

"Tested up to" is a readme.txt header, not a plugin header. If it's also declared in the main PHP file's plugin headers, that value may take precedence over the one in your readme, so WordPress.org can display a compatibility version you did not intend.

The sniff finds the main plugin file the same way WordPress does (a `Plugin Name:` header in the first 8 KB) and flags any `Tested up to:` header in it. Other files are ignored.

**Sniff**: `WPOrgSubmissionRules.PluginHeader.TestedUpTo`

### 9) Undocumented use of a 3rd party / external service

Plugins can use external services, but each one must be documented in an `== External services ==` section of your readme: what the service is and what it is used for, what data is sent and when, and links to its terms of service and privacy policy. This applies even if you run the service yourself.

In any file that makes a remote request (`wp_remote_get()`, `wp_safe_remote_post()`, `curl_init()`, etc.), the sniff flags:

- A missing readme, or a readme with no `External services` section
- URLs to domains that aren't mentioned in that section
- Services mentioned without terms of service and privacy policy links (warning)

**Sniff**: `WPOrgSubmissionRules.ExternalServices.Disclosure`

### 10) Non-atomic transient updates (race conditions)

Reading a transient and then writing it back is not atomic, so simultaneous requests can all read the same value before any of them writes. Reviewers flag this under "Other possible issues" for:

- Rate limits: `$count = get_transient($key)` then `set_transient($key, $count + 1)` lets parallel requests exceed the limit
- Locks: `if (get_transient($key))` then `set_transient($key, 1)` lets parallel requests past the lock

Use an atomic operation instead, such as `wp_cache_add()` or `wp_cache_incr()` with a persistent object cache, or an atomic database query. These are reported as warnings, and the usual cache refill pattern is not flagged.

**Sniff**: `WPOrgSubmissionRules.Concurrency.NonAtomicTransient`

### 11) Allowing direct file access to plugin files

Any PHP file that runs code when loaded (function calls, creating class instances, including other files, output) can be requested directly in a browser, outside of WordPress. Prevent this by adding the following after the `<?php` tag and any namespace declaration, before any other code:

```php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
```

`defined( 'ABSPATH' ) || exit;` and other constants such as `WPINC` or `WP_UNINSTALL_PLUGIN` also work. Files that only contain class or function definitions don't need it.

**Sniff**: `WPOrgSubmissionRules.Security.DirectFileAccess`

### 12) Requires Plugins, plugin not found in WordPress.org directory

The `Requires Plugins` header is a comma-separated list of WordPress.org slugs for your plugin's dependencies. It needs the slug, not the plugin's name: the slug for https://wordpress.org/plugins/classic-editor/ is `classic-editor`. The dependency must also be in the WordPress.org plugin directory, so premium plugins such as Gravity Forms can't be listed.

In the main plugin file's `Requires Plugins` header, the sniff flags:

- Entries that aren't in slug format (e.g. `Gravity Forms` or `woocommerce/woocommerce.php`), which WordPress ignores
- The plugin's own slug
- Slugs that aren't in the WordPress.org plugin directory, or whose plugin has been closed

The last check asks the WordPress.org API about each slug. It only runs when the header is present, and results are cached for a day. If WordPress.org can't be reached, you get a warning rather than an error. See above to turn the lookup off.

**Sniff**: `WPOrgSubmissionRules.PluginHeader.RequiresPlugins`

## Checks covered by WordPress Coding Standards

Some review issues are already detected by the [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) (WPCS), so this ruleset doesn't duplicate them. Run WPCS alongside this ruleset for:

- **Nonce verification** (`WordPress.Security.NonceVerification`) — processing `$_POST`, `$_GET`, `$_REQUEST` or `$_FILES` data without verifying a nonce. This ruleset had its own `WPOrgSubmissionRules.Security.NonceCheck` sniff, which has been removed. Remove any references to it from your `.phpcs.xml`, as PHPCS stops with an error when a ruleset references a sniff that doesn't exist.

## Active development

This package is under constant development and will be updated to reflect new checks that the Plugin Team review process throws at us. If you have feedback on these sniffs and want us to add new custom sniffs, [please open an issue](https://github.com/thetwopct/wp-org-submission-rules/issues). This file can be found in our [GitHub](https://github.com/thetwopct/wp-org-submission-rules) repo.

## Disclaimer

This plugin is independently made and is not affiliated with WordPress.org. The WordPress® trademarks are the intellectual property of the WordPress Foundation. Uses of the WordPress® names in this repo are for identification purposes only and do not imply an endorsement by WordPress Foundation.