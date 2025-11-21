# Quick Installation Guide

## What You're Getting

This package contains:

1. **Extension Framework Core** - Modern, Symfony-independent architecture
2. **Symfony Adapters** - Bridges to AtoM's existing Symfony 1.4 infrastructure
3. **Metadata Extraction Extension** - Fully migrated example (your arMetadataExtractionPlugin refactored)
4. **Complete Documentation** - See README.md

## File Count

- 15 PHP files (interfaces, adapters, services)
- 1 JSON manifest
- Full documentation
- Zero Symfony dependencies in extension code

## Installation Steps

### 1. Extract Package

```bash
# On your AtoM server
cd /usr/share/nginx/atom_psis  # or your AtoM path
tar xzf atom-extension-framework.tar.gz
chown -R www-data:www-data atom-extensions
```

### 2. Enable in AtoM

Edit `config/ProjectConfiguration.class.php`:

```php
class ProjectConfiguration extends sfProjectConfiguration
{
    public function setup()
    {
        // Existing code...
        $this->enablePlugins('...');
        
        // ADD THIS at the end:
        if (file_exists(sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php')) {
            require_once sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php';
        }
    }
}
```

### 3. Clear Cache

```bash
sudo -u www-data php symfony cc
sudo systemctl restart php8.3-fpm
```

### 4. Verify Installation

Check logs:

```bash
tail -f /var/log/nginx/psis_error.log | grep -i extension
```

You should see:
```
Extension framework initialized successfully
Loaded extension: metadata-extraction
Booted extension: metadata-extraction
```

### 5. Test Metadata Extraction

1. Upload an image with EXIF data to any information object
2. Check that metadata is automatically extracted
3. Verify in Physical Characteristics field

## What Changed from Your Plugin

### Before (arMetadataExtractionPlugin):
```php
// Coupled to Symfony
$setting = QubitSetting::getByName('foo');
$logger = sfContext::getInstance()->getLogger();
$digitalObject->save();
```

### After (metadata-extraction Extension):
```php
// Framework-independent
$setting = $this->database->getSetting('foo');
$this->logger->info('Message');
$this->database->save($entity);
```

## Key Benefits

1. **Testable** - Can unit test without AtoM bootstrap
2. **Portable** - Not tied to Symfony 1.4
3. **Maintainable** - Clean separation of concerns
4. **Extensible** - Easy to add new extensions
5. **Modern** - Uses PHP 8.3+ features

## Next Steps

### Option A: Keep Both
- Leave your old `arMetadataExtractionPlugin` disabled
- Extension system provides same functionality
- Can revert if needed

### Option B: Migrate Other Plugins
- Use this as template for `arIiifPlugin`
- Then `arSecurityClearancePlugin`
- Gradually modernize entire codebase

### Option C: Extend Further
- Add your own extensions
- Share with AtoM community
- Build on this foundation

## Troubleshooting

### Extensions Not Loading

**Problem**: No log messages about extensions

**Solution**: Check bootstrap.php is included and context is ready

```php
// Add debug to ProjectConfiguration::setup()
error_log('Loading extension framework...');
require_once sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php';
```

### Database Errors

**Problem**: "default context does not exist"

**Solution**: Extensions load before database ready. Framework handles this automatically via deferred initialization.

### Metadata Not Extracting

**Problem**: Upload works but no metadata extracted

**Solution**: 
1. Check settings in database:
   ```sql
   SELECT * FROM setting WHERE name LIKE 'metadata%';
   ```
2. Ensure exiftool is installed:
   ```bash
   which exiftool
   ```
3. Check file permissions on uploads directory

## Support

Questions? Email: pieterse.johan3@gmail.com

## Files Included

```
atom-extensions/
├── bootstrap.php                 # Loads framework into AtoM
├── composer.json                 # Package definition
├── README.md                     # Complete documentation
├── core/src/
│   ├── Contracts/               # 7 interface files
│   ├── Adapters/                # 3 Symfony adapter files
│   ├── ExtensionManager.php     # Orchestration
│   └── ServiceContainer.php     # DI container
└── extensions/
    └── metadata-extraction/
        ├── manifest.json
        └── src/
            ├── Extension.php             # Entry point
            └── MetadataExtractor.php     # Business logic
```

Total: ~2,500 lines of clean, documented, modern PHP code.
