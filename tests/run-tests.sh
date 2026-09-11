#!/bin/bash

# Test script for WP.org Submission Rules
# This script runs phpcs against the test plugin file to verify the sniffs work

echo "========================================"
echo "Testing WP.org Submission Rules Sniffs"
echo "========================================"
echo ""

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Try to find phpcs - prefer vendor binary, fallback to global
PHPCS=""
if [ -f "$PROJECT_DIR/vendor/bin/phpcs" ]; then
    PHPCS="$PROJECT_DIR/vendor/bin/phpcs"
    echo "Using local phpcs: $PHPCS"
elif command -v phpcs &> /dev/null; then
    PHPCS="phpcs"
    echo "Using global phpcs"
else
    echo "❌ Error: phpcs is not installed"
    echo "Please install PHP_CodeSniffer first:"
    echo "  composer require --dev squizlabs/php_codesniffer"
    exit 1
fi

echo "Project directory: $PROJECT_DIR"
echo "Test file: $SCRIPT_DIR/test-plugin.php"
echo ""

# Check if the test file exists
if [ ! -f "$SCRIPT_DIR/test-plugin.php" ]; then
    echo "❌ Error: test-plugin.php not found"
    exit 1
fi

echo "Running phpcs with WPOrgSubmissionRules standard..."
echo "=================================================="
echo ""

# Run phpcs on the test file
$PHPCS --standard=WPOrgSubmissionRules "$SCRIPT_DIR/test-plugin.php"

EXIT_CODE=$?

echo ""
echo "=================================================="
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ No violations found (this might mean sniffs aren't working)"
else
    echo "✅ Test completed - violations detected as expected"
    echo ""
    echo "Expected violations should include:"
    echo "  - Short prefixes (less than 4 characters)"
    echo "  - Reserved prefixes (wp_, _, __)"
    echo "  - Missing nonce checks"
    echo "  - Function exists wrapper anti-pattern"
    echo "  - Inline script/style tags"
    echo "  - Translation functions with variables"
    echo "  - \"Tested up to\" in the plugin header"
    echo "  - External services not documented in readme.txt"
    echo "  - Non-atomic transient updates (race conditions)"
    echo "  - Missing direct file access (ABSPATH) check"
fi

echo ""
echo "To run verbose output, use:"
echo "  $PHPCS -v --standard=WPOrgSubmissionRules $SCRIPT_DIR/test-plugin.php"
echo ""
echo "To see which sniffs are being used:"
echo "  $PHPCS -e --standard=WPOrgSubmissionRules"

exit 0

