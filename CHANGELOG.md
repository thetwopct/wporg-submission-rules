# Changelog

## 2.0.0 — 11th September 2026

Version 2 adds five sniffs for issues flagged by the WordPress.org Plugin Review Team, supports PHP_CodeSniffer 4, and removes the `NonceCheck` sniff in favour of WordPress Coding Standards. It also raises the minimum PHP and PHP_CodeSniffer versions.

### Breaking changes

- Renamed the Composer package from `thetwopct/wp-org-submission-rules` to `thetwopct/wporg-submission-rules`.
- Requires PHP 7.2+ and PHP_CodeSniffer 3.13.6+ or 4.0.2+; version 1 required PHP 5.4+ and PHP_CodeSniffer 3.9+. Earlier PHP_CodeSniffer versions are affected by [CVE-2026-67434](https://github.com/PHPCSStandards/PHP_CodeSniffer/security/advisories/GHSA-hmqg-cxww-wqhq).
- Removed `WPOrgSubmissionRules.Security.NonceCheck`. It produced too many false positives by flagging any use of `$_POST`, `$_GET`, or `$_REQUEST` in a function without a nonce call. Use `WordPress.Security.NonceVerification` from WordPress Coding Standards instead. Remove any references to the sniff or its `MissingNonceCheck` and `SuperglobalOutsideFunction` codes; PHP_CodeSniffer fails when a ruleset references a sniff that does not exist.
- Enabled the five new sniffs by default, so code that passed with version 1 may now report errors or warnings.

### New sniffs

#### `PluginHeader.RequiresPlugins` ([#2](https://github.com/thetwopct/wporg-submission-rules/issues/2))

Checks the `Requires Plugins` header in the main plugin file:

- `InvalidSlug`: an entry that is not a WordPress.org slug, such as `Gravity Forms` or `woocommerce/woocommerce.php`.
- `NotInDirectory`: a slug that is not in the WordPress.org Plugin Directory, such as a premium plugin like `gravityforms`.
- `ClosedInDirectory`: a plugin that has been closed on WordPress.org.
- `SelfDependency`: the plugin's own slug.
- `LookupFailed` (warning): WordPress.org could not be reached.

This is the first sniff in the ruleset that makes network requests. It contacts the WordPress.org API only when a main plugin file declares `Requires Plugins`, and it caches results for one day. To stay offline and check only the slug format, set `checkDirectory` to `false`.

#### `Security.DirectFileAccess` ([#3](https://github.com/thetwopct/wporg-submission-rules/issues/3))

Flags PHP files that run code when loaded without first preventing direct access, for example with `if ( ! defined( 'ABSPATH' ) ) exit;`:

- `Missing`: the file has no guard.
- `Misplaced`: code runs before the guard.

Files that only define classes, functions, or constants, or only return an array such as `index.asset.php`, are not flagged.

#### `ExternalServices.Disclosure`

In files that make remote requests with functions such as `wp_remote_get()` or `curl_init()`, checks that each external service is documented in an `== External services ==` section of the readme:

- `MissingReadme` / `MissingSection`: there is no readme, or it has no External services section.
- `UndocumentedService`: a URL's domain is not mentioned in the section.
- `MissingPolicyLinks` (warning): the service is mentioned without links to its terms of service and privacy policy.

WordPress.org, `example.com`, and local URLs are ignored. Use the `excludedDomains` property to change the list.

#### `Concurrency.NonAtomicTransient`

Warns about race conditions caused by reading a transient and then writing it back:

- `ReadModifyWrite` (warning): for example, a rate limit that reads a count with `get_transient()` and writes back `$count + 1`.
- `CheckThenSet` (warning): for example, a lock that checks `get_transient()` in an `if` and then sets it.

The usual cache-refill pattern is not flagged.

#### `PluginHeader.TestedUpTo`

- `Found`: a `Tested up to` header exists in the main plugin file. It belongs in `readme.txt`, and declaring it in the PHP file can override the readme value on WordPress.org.

### Improvements

- Compatibility with PHP_CodeSniffer 4 while retaining PHP_CodeSniffer 3 support.
- Fully qualified calls such as `\__()` and `\define()` now produce the same findings under PHP_CodeSniffer 3 and 4.
- Namespaced functions such as `Other\define()` are no longer mistaken for global functions with the same name.
- `PrefixLength` no longer checks an unrelated name after a global `namespace {}` block or a `namespace\foo()` call.
- Automatic ruleset registration through the PHP_CodeSniffer Composer installer.

### Upgrading

1. Check that PHP 7.2 or later is installed, then replace the old Composer package and allow dependencies to update:

   ```bash
   composer remove --dev thetwopct/wp-org-submission-rules --no-update
   composer require --dev thetwopct/wporg-submission-rules:^2.0 --with-all-dependencies
   ```

2. Remove references to `WPOrgSubmissionRules.Security.NonceCheck` from your ruleset. For nonce checks, add WordPress Coding Standards if it is not already installed:

   ```bash
   composer require --dev wp-coding-standards/wpcs
   ```

3. Run PHP_CodeSniffer and review the new findings. Exclude any new sniff you do not want:

   ```xml
   <rule ref="WPOrgSubmissionRules">
       <exclude name="WPOrgSubmissionRules.Concurrency.NonAtomicTransient"/>
   </rule>
   ```

4. If CI has no network access, disable the WordPress.org lookup:

   ```xml
   <rule ref="WPOrgSubmissionRules.PluginHeader.RequiresPlugins">
       <properties>
           <property name="checkDirectory" value="false"/>
       </properties>
   </rule>
   ```

The `WPOrgSubmissionRules` standard name and all other existing sniff codes are unchanged.
