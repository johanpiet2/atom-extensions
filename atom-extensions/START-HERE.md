# 🚀 Getting Started - AtoM Extension Framework v4.0

**All 4 of your plugins migrated to modern extensions!**

## What's In This Package

✅ **metadata-extraction** - Auto-extract EXIF/IPTC/XMP from images  
✅ **security-clearance** - 5-level classification system  
✅ **iiif** - Deep zoom viewer with Cantaloupe  
✅ **zoom-pan** - Simple iframe-based viewer  

## Installation (5 Steps)

### 1. Extract Package

```bash
cd /usr/share/nginx/atom_psis
tar xzf atom-extension-framework-v4-FINAL.tar.gz
```

### 2. Verify Package (Optional)

```bash
cd atom-extensions
bash verify.sh
```

### 3. Install Dependencies

```bash
composer install
```

### 4. Enable in AtoM

Edit `config/ProjectConfiguration.class.php` and add **AT THE END** of setup() method:

```php
public function setup()
{
    // ... existing plugin code ...
    
    // ADD THIS:
    if (file_exists(sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php')) {
        require_once sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php';
    }
}
```

### 5. Restart AtoM

```bash
sudo -u www-data php symfony cc
sudo systemctl restart php8.3-fpm
```

## Verify It Worked

```bash
# Check logs
tail -f /var/log/atom/atom-system.log
```

You should see:
```
Extension framework initialized successfully
Loaded extension: metadata-extraction
Loaded extension: security-clearance
Loaded extension: iiif
Loaded extension: zoom-pan
```

✅ **Success! All 4 extensions are loaded!**

## Troubleshooting

**Problem:** "This file must be included from within AtoM"  
**Solution:** See [QUICK-START.md](QUICK-START.md)

**Problem:** "Composer not found"  
**Solution:** `sudo apt install composer`

**Problem:** "No log files"  
**Solution:** `sudo mkdir -p /var/log/atom && sudo chown www-data:www-data /var/log/atom`

## Documentation

- **QUICK-START.md** - Installation guide
- **ULTIMATE-SUMMARY-v4.md** - Complete feature overview
- **ARCHITECTURE.md** - Technical details
- **extensions/*/README.md** - Individual extension docs

## Need Help?

**Email:** pieterse.johan3@gmail.com  
**Package:** atom-extension-framework-v4-FINAL.tar.gz (46KB)
