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
      "url": "../wp-org-submission-rules",
      "options": {
        "symlink": true
      }
    }
  ],
  "require-dev": {
    "squizlabs/php_codesniffer": "^3.9.0",
    "thetwopct/wp-org-submission-rules": "@dev"
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
phpcs --config-set installed_paths /path/to/wp-org-submission-rules
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
    <config name="installed_paths" value="/path/to/wp-org-submission-rules"/>
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
    "wp-content/mu-plugins/wporg-sniffs": "../wp-org-submission-rules"
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
    "thetwopct/wp-org-submission-rules": "@dev"
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

## Common Issues & Solutions

### Path Repository Not Found
**Error:** `The url supplied for the path (...) repository does not exist`

**Solutions:**
- Verify path exists: `ls -la /path/to/wp-org-submission-rules/composer.json`
- Use relative paths: `../wp-org-submission-rules`
- For wp-env: ensure mapping exists and restart: `wp-env destroy && wp-env start`

### Minimum Stability Error
**Error:** `minimum-stability` issues with `*` version

**Solution:** Use `@dev` instead of `*`:
```json
"thetwopct/wp-org-submission-rules": "@dev"
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
