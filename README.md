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

## Active development

This package is under constant development and will be updated to reflect new checks that the Plugin Team review process throws at us. If you have feedback on these sniffs and want us to add new custom sniffs, [please open an issue](https://github.com/thetwopct/wp-org-submission-rules/issues). This file can be found in our [GitHub](https://github.com/thetwopct/wp-org-submission-rules) repo. 
