# WordPress.org-specific plugin review code sniffs

When submitting a plugin to the WordPress.org repo, there are several checks that the plugin review team apply to your plugin, but which are not fully covered by WordPress Coding Standards or included in the [Plugin Check (PCP)](https://wordpress.org/plugins/plugin-check/) plugin.

This sniff ruleset tries to bring attention to and fix some of the checks that are missed.

This is an additional ruleset you can add to [PHPCSStandards PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/). PHP CodeSniffer tokenizes PHP files and detects violations of a defined set of coding standards, and also corrects coding standard violations. PHP_CodeSniffer is an essential development tool that ensures your code remains clean and consistent.

If you use these sniffs and indeed PHP_CodeSniffer I would urge you to [donate](https://opencollective.com/php_codesniffer) _something_ to the project as without funding it will go away and all our code will be worse off.

## Install

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

One of the rules looks for unique names of , and you can add a prefix in your custom rules:

```
<rule ref="WPOrgSubmissionRules.Naming.UniqueName">
	<properties>
		<property name="requiredPrefix" value="my_unique_name_" />
	</properties>
</rule>
```

## What the sniffs detect:

Here are some of the review issues from WordPress.org that these sniffs try to make sure you avoid:

1) Use wp_enqueue commands

Any inline CSS or JS is flagged.

2) Generic function/class/define/namespace/option names

All plugins must have unique function names, namespaces, defines, class and option names. This prevents your plugin from conflicting with other plugins or themes. WordPress.org expect your plugin to use ore unique and distinct names.

3) Options and Transients must be prefixed.

This is really important because the options are stored in a shared location and under the name you have set. If two plugins use the same name for options, they will find an interesting conflict when trying to read information introduced by the other plugin.

4) Internationalization: Don't use variables or defines as text, context or text domain parameters.

In order to make a string translatable in your plugin you are using a set of special functions. These functions collectively are known as "gettext". There is a dedicated team in the WordPress community to translate and help other translating strings of WordPress core, plugins and themes to other languages.

To make them be able to translate this plugin, please do not use variables or function calls for the text, context or text domain parameters of any gettext function, all of them NEED to be strings. Note that the translation parser reads the code without executing it, so it won't be able to read anything that is not a string within these functions.

## New rules

## Nonces and User Permissions Needed for Security

Please add a nonce check to your input calls ($_POST, $_GET, $REQUEST) to prevent unauthorized access.

If you use wp_ajax_ to trigger submission checks, remember they also need a nonce check.

👮 Checking permissions: Keep in mind, a nonce check alone is not bulletproof security. Do not rely on nonces for authorization purposes. When needed, use it together with current_user_can() in order to prevent users without the right permissions from accessing things they shouldn't.

Also make sure that the nonce logic is correct by making sure it cannot be bypassed. Checking the nonce with current_user_can() is great, but mixing it with other checks can make the condition more complex and, without realising it, bypassable, remember that anything can be sent through an input, don't trust any input.

Keep performance in mind. Don't check for post submission outside of functions. Doing so means that the check will run on every single load of the plugin, which means that every single person who views any page on a site using your plugin will be checking for a submission. This will make your code slow and unwieldy for users on any high traffic site, leading to instability and eventually crashes.

## Generic function/class/define/namespace/option names

All plugins must have unique function names, namespaces, defines, class and option names. This prevents your plugin from conflicting with other plugins or themes. We need you to update your plugin to use more unique and distinct names.

A good way to do this is with a prefix. For example, if your plugin is called "Better Field Groups for ACF" then you could use names like these:
function bettfigr_save_post(){ ... }
class BETTFIGR_Admin { ... }
update_option( 'bettfigr_options', $options );
register_setting( 'bettfigr_settings', 'bettfigr_user_id', ... );
define( 'BETTFIGR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
global $bettfigr_options;
add_action('wp_ajax_bettfigr_save_data', ... );
namespace ymmvplugins\betterfieldgroupsforacf;

Disclaimer: These are just examples that may have been self-generated from your plugin name, we trust you can find better options. If you have a good alternative, please use it instead, this is just an example.

Don't try to use two (2) or three (3) letter prefixes anymore. We host nearly 100-thousand plugins on WordPress.org alone. There are tens of thousands more outside our servers. Believe us, you’re going to run into conflicts.

You also need to avoid the use of __ (double underscores), wp_ , or _ (single underscore) as a prefix. Those are reserved for WordPress itself. You can use them inside your classes, but not as stand-alone function.

Please remember, if you're using _n() or __() for translation, that's fine. We're only talking about functions you've created for your plugin, not the core functions from WordPress. In fact, those core features are why you need to not use those prefixes in your own plugin! You don't want to break WordPress for your users.

Related to this, using if (!function_exists('NAME')) { around all your functions and classes sounds like a great idea until you realize the fatal flaw. If something else has a function with the same name and their code loads first, your plugin will break. Using if-exists should be reserved for shared libraries only.

Remember: Good prefix names are unique and distinct to your plugin. This will help you and the next person in debugging, as well as prevent conflicts.

Examples:

This plugin is using the prefix "bfg" for 2 element(s).

# The prefix "bfg" is too short, we require prefixes to be over 4 characters.
better-field-groups-for-acf.php:197 define('BFG_SAVING_' . $post_id, true);
better-field-groups-for-acf.php:37 class BFG_For_ACF

## Active development

This package is under constant development and will be updated to reflect new checks that the Plugin Team review process throws at us. If you have feedback on these sniffs and want us to add new custom sniffs, [please open an issue](https://github.com/thetwopct/wp-org-submission-rules/issues). This file can be found in our [GitHub](https://github.com/thetwopct/wp-org-submission-rules) repo.
