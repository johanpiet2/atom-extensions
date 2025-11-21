#!/bin/bash

# AtoM Extension Framework - Package Verification Script
# Run this BEFORE installing to verify package integrity

set -e

echo "=========================================="
echo "AtoM Extension Framework v4.0"
echo "Package Verification"
echo "=========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "bootstrap.php" ]; then
    echo "❌ ERROR: Must run from atom-extensions directory"
    echo "Usage: cd atom-extensions && bash verify.sh"
    exit 1
fi

echo "✓ Found bootstrap.php"

# Check core files
echo ""
echo "Checking core files..."

required_files=(
    "core/src/Contracts/Extension.php"
    "core/src/Contracts/ExtensionContext.php"
    "core/src/Contracts/DatabaseInterface.php"
    "core/src/ExtensionManager.php"
    "core/src/MonologFactory.php"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ❌ MISSING: $file"
        exit 1
    fi
done

# Check extensions
echo ""
echo "Checking extensions..."

extensions=(
    "metadata-extraction"
    "security-clearance"
    "iiif"
    "zoom-pan"
)

for ext in "${extensions[@]}"; do
    manifest="extensions/$ext/manifest.json"
    extension_php="extensions/$ext/src/Extension.php"
    
    if [ -f "$manifest" ] && [ -f "$extension_php" ]; then
        echo "  ✓ $ext extension"
    else
        echo "  ❌ INCOMPLETE: $ext extension"
        exit 1
    fi
done

# Check composer.json
echo ""
echo "Checking package configuration..."
if [ -f "composer.json" ]; then
    echo "  ✓ composer.json"
else
    echo "  ❌ MISSING: composer.json"
    exit 1
fi

# Check documentation
echo ""
echo "Checking documentation..."
docs=(
    "README.md"
    "ARCHITECTURE.md"
    "INSTALLATION.md"
    "QUICK-START.md"
)

for doc in "${docs[@]}"; do
    if [ -f "$doc" ]; then
        echo "  ✓ $doc"
    else
        echo "  ⚠  WARNING: Missing $doc"
    fi
done

# Count files
echo ""
echo "Package contents:"
php_count=$(find . -name "*.php" -not -path "./vendor/*" | wc -l)
json_count=$(find . -name "*.json" -not -path "./vendor/*" | wc -l)
md_count=$(find . -name "*.md" | wc -l)

echo "  • PHP files: $php_count"
echo "  • JSON files: $json_count"
echo "  • Documentation: $md_count"

# PHP syntax check
echo ""
echo "Checking PHP syntax..."
php_files=$(find core extensions -name "*.php" 2>/dev/null || true)
syntax_errors=0

for file in $php_files; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "  ❌ Syntax error in: $file"
        syntax_errors=$((syntax_errors + 1))
    fi
done

if [ $syntax_errors -eq 0 ]; then
    echo "  ✓ All PHP files have valid syntax"
else
    echo "  ❌ Found $syntax_errors PHP syntax errors"
    exit 1
fi

# Check PHP version
echo ""
echo "Checking PHP version..."
php_version=$(php -r "echo PHP_VERSION;")
php_major=$(php -r "echo PHP_MAJOR_VERSION;")
php_minor=$(php -r "echo PHP_MINOR_VERSION;")

echo "  • Current PHP version: $php_version"

if [ "$php_major" -lt 8 ] || ([ "$php_major" -eq 8 ] && [ "$php_minor" -lt 1 ]); then
    echo "  ⚠  WARNING: PHP 8.1+ recommended (you have $php_version)"
else
    echo "  ✓ PHP version compatible"
fi

# Check composer
echo ""
echo "Checking Composer..."
if command -v composer &> /dev/null; then
    echo "  ✓ Composer installed"
    
    # Check if dependencies are installed
    if [ -d "vendor" ]; then
        echo "  ✓ Dependencies installed"
    else
        echo "  ⚠  Dependencies not installed (run: composer install)"
    fi
else
    echo "  ❌ Composer not found (required for installation)"
    echo "     Install: sudo apt install composer"
fi

# Final summary
echo ""
echo "=========================================="
echo "Verification Complete!"
echo "=========================================="
echo ""
echo "✅ Package integrity verified"
echo ""
echo "Next steps:"
echo "1. Run: composer install"
echo "2. Copy to AtoM directory"
echo "3. Edit config/ProjectConfiguration.class.php"
echo "4. Add: require_once sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php';"
echo "5. Clear cache: php symfony cc"
echo ""
echo "See QUICK-START.md for detailed instructions"
