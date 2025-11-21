# Quick Installation Guide

## Error: "This file must be included from within AtoM"

If you see this error, it means you're trying to run bootstrap.php directly. Here's how to fix it:

## ✅ Correct Installation

### Step 1: Extract Package

```bash
cd /usr/share/nginx/atom_psis
tar xzf atom-extension-framework-v4-FINAL.tar.gz
```

### Step 2: Install Composer Dependencies

```bash
cd atom-extensions
composer install
```

### Step 3: Enable in AtoM (IMPORTANT!)

Edit `/usr/share/nginx/atom_psis/config/ProjectConfiguration.class.php`:

```php
<?php

class ProjectConfiguration extends sfProjectConfiguration
{
    public function setup()
    {
        // Enable your existing plugins
        $this->enablePlugins(array(
            'sfDrupalPlugin',
            'sfIsaarPlugin',
            'arDominionPlugin',
            // ... other plugins ...
        ));

        // ADD THIS LINE AT THE END:
        if (file_exists(sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php')) {
            require_once sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php';
        }
    }
}
```

### Step 4: Setup Log Directory

```bash
sudo mkdir -p /var/log/atom
sudo chown www-data:www-data /var/log/atom
sudo chmod 755 /var/log/atom
```

### Step 5: Clear Cache

```bash
cd /usr/share/nginx/atom_psis
sudo -u www-data php symfony cc
sudo systemctl restart php8.3-fpm
```

### Step 6: Verify Installation

```bash
tail -f /var/log/atom/atom-system.log
```

You should see:
```
[...] system.INFO: Extension framework initialized successfully
[...] system.INFO: Loaded extension: metadata-extraction
[...] system.INFO: Loaded extension: security-clearance
[...] system.INFO: Loaded extension: iiif
[...] system.INFO: Loaded extension: zoom-pan
[...] system.INFO: Booted extension: metadata-extraction
[...] system.INFO: Booted extension: security-clearance
[...] system.INFO: Booted extension: iiif
[...] system.INFO: Booted extension: zoom-pan
```

## ❌ What NOT to Do

**Don't run bootstrap.php directly:**
```bash
php bootstrap.php  # ❌ WRONG - will show error
```

**Don't include from wrong location:**
```php
require_once '/atom-extensions/bootstrap.php';  # ❌ WRONG - missing path
```

## ✅ Correct Usage

**The bootstrap.php file is designed to be included ONLY from within AtoM's ProjectConfiguration.class.php**

The framework will:
1. Check for Symfony context
2. Load all extension interfaces
3. Create adapters for Symfony
4. Discover extensions
5. Boot all extensions
6. Register event listeners

## Troubleshooting

### "Extension framework not loading"

**Check:**
1. Is the require_once line in ProjectConfiguration.class.php?
2. Did you clear the cache?
3. Check PHP error log: `tail -f /var/log/nginx/atom_error.log`

### "Composer dependencies missing"

```bash
cd /usr/share/nginx/atom_psis/atom-extensions
composer install
```

### "Permission denied"

```bash
sudo chown -R www-data:www-data /usr/share/nginx/atom_psis/atom-extensions
sudo chmod -R 755 /usr/share/nginx/atom_psis/atom-extensions
```

### "No log files created"

```bash
# Create and set permissions
sudo mkdir -p /var/log/atom
sudo chown www-data:www-data /var/log/atom
sudo chmod 755 /var/log/atom

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm
```

## File Structure After Installation

```
/usr/share/nginx/atom_psis/
├── config/
│   └── ProjectConfiguration.class.php  ← Edit this file
└── atom-extensions/
    ├── bootstrap.php                    ← Include from ProjectConfiguration
    ├── composer.json
    ├── vendor/                          ← Created by composer install
    ├── core/
    └── extensions/
```

## Need Help?

**Email:** pieterse.johan3@gmail.com  
**Documentation:** See README.md and ULTIMATE-SUMMARY-v4.md in package
