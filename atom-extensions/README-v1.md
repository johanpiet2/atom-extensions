# AtoM Extension Framework

A modern, framework-independent extension system for Access to Memory (AtoM) that enables gradual migration away from Symfony 1.4 dependencies.

## Overview

This extension framework provides:

- **Clean Abstractions**: Extensions use interfaces instead of direct Symfony/Propel dependencies
- **Dependency Injection**: Services are injected, eliminating global state (`sfContext::getInstance()`)
- **Event-Driven Architecture**: Loosely coupled communication between extensions
- **Gradual Migration Path**: Existing plugins can be converted incrementally
- **Modern PHP**: Uses PHP 8.3+ features (typed properties, enums, attributes)
- **Testability**: Extensions can be unit tested without AtoM bootstrap

## Directory Structure

```
atom-extensions/
├── bootstrap.php                    # Loads extension framework into AtoM
├── core/
│   └── src/
│       ├── Contracts/               # Framework interfaces
│       │   ├── Extension.php
│       │   ├── ExtensionContext.php
│       │   ├── DatabaseInterface.php
│       │   ├── FileSystemInterface.php
│       │   ├── EventDispatcherInterface.php
│       │   └── ConfigurationInterface.php
│       ├── Adapters/                # Symfony 1.4 adapters
│       │   ├── SymfonyDatabase.php
│       │   ├── SymfonyFileSystem.php
│       │   └── SymfonyEventDispatcher.php
│       ├── ExtensionManager.php     # Extension orchestrator
│       └── ServiceContainer.php     # Dependency injection
└── extensions/
    └── metadata-extraction/         # Example extension
        ├── manifest.json
        └── src/
            ├── Extension.php        # Extension entry point
            └── MetadataExtractor.php
```

## Installation

### 1. Add to AtoM

```bash
# Copy framework to AtoM installation
cd /usr/share/nginx/atom
mkdir atom-extensions
cp -r /path/to/atom-extensions/* atom-extensions/

# Set permissions
chown -R www-data:www-data atom-extensions
```

### 2. Bootstrap in AtoM

Add to `config/ProjectConfiguration.class.php`:

```php
public function setup()
{
    // ... existing plugin enablement ...
    
    // Bootstrap extension framework
    require_once sfConfig::get('sf_root_dir').'/atom-extensions/bootstrap.php';
}
```

### 3. Verify Installation

```bash
# Check AtoM logs for:
# "Extension framework initialized successfully"
# "Loaded extension: metadata-extraction"
# "Booted extension: metadata-extraction"

tail -f /var/log/nginx/atom_error.log
```

## Creating an Extension

### 1. Create Directory Structure

```bash
cd atom-extensions/extensions
mkdir my-extension
cd my-extension
mkdir -p src config
```

### 2. Create Manifest

`manifest.json`:

```json
{
  "name": "my-extension",
  "version": "1.0.0",
  "description": "My custom extension",
  "authors": [
    {
      "name": "Your Name",
      "email": "your.email@example.com"
    }
  ],
  "requires": ["database", "filesystem"],
  "provides": ["my-service"],
  "atom_min_version": "2.9.0",
  "php_min_version": "8.1.0"
}
```

### 3. Create Extension Class

`src/Extension.php`:

```php
<?php

namespace AtomExtensions\MyExtension;

use AtomExtensions\Contracts\Extension as ExtensionInterface;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\ExtensionManifest;

class Extension implements ExtensionInterface
{
    public function getManifest(): ExtensionManifest
    {
        return ExtensionManifest::fromJson(__DIR__.'/../../manifest.json');
    }

    public function boot(ExtensionContext $context): void
    {
        // Get services via dependency injection
        $db = $context->getDatabase();
        $files = $context->getFileSystem();
        $logger = $context->getLogger();
        
        // Register event listeners
        $context->getEventDispatcher()->listen(
            'digital_object.created',
            function($event) use ($logger) {
                $logger->info('Digital object created!');
            }
        );
        
        $logger->info('My extension booted');
    }

    public function shutdown(): void
    {
        // Cleanup when extension is disabled
    }
}
```

## Key Concepts

### No Symfony Dependencies

Extensions NEVER directly use:

❌ **BAD** (Symfony-coupled):
```php
$setting = QubitSetting::getByName('foo');
$user = sfContext::getInstance()->getUser();
$digitalObject->save();
```

✅ **GOOD** (Framework-independent):
```php
$setting = $context->getDatabase()->getSetting('foo');
$filePath = $context->getFileSystem()->getFilePath($digitalObject);
$context->getDatabase()->save($entity);
```

### Dependency Injection

Services are injected, never accessed globally:

```php
class MyService
{
    public function __construct(
        private DatabaseInterface $database,
        private LoggerInterface $logger
    ) {}
    
    public function doSomething(): void
    {
        $value = $this->database->getSetting('key');
        $this->logger->info("Got: {$value}");
    }
}
```

### Event System

Loosely coupled communication:

```php
// Extension A: Dispatch event
$context->getEventDispatcher()->dispatch('item.processed', [
    'subject' => $item,
    'status' => 'success'
]);

// Extension B: Listen for event
$context->getEventDispatcher()->listen('item.processed', 
    function($event) {
        $item = $event->getSubject();
        // React to event
    }
);
```

## Converting Existing Plugins

### Step 1: Create Extension Wrapper

Your existing plugin stays functional. Create an extension that wraps it:

```php
// extensions/my-plugin-wrapper/src/Extension.php
class Extension implements ExtensionInterface
{
    public function boot(ExtensionContext $context): void
    {
        // Initialize old plugin code but use new interfaces
        $extractor = new MyOldExtractor(
            $context->getDatabase(),  // Instead of QubitSetting
            $context->getFileSystem() // Instead of sfContext
        );
    }
}
```

### Step 2: Refactor Service Classes

Update individual service classes to use interfaces:

```php
// OLD: Direct Symfony dependencies
class MyService
{
    public function process($object)
    {
        $setting = QubitSetting::getByName('foo');
        $object->save();
    }
}

// NEW: Interface-based
class MyService
{
    public function __construct(
        private DatabaseInterface $db
    ) {}
    
    public function process($object)
    {
        $setting = $this->db->getSetting('foo');
        $this->db->save($object);
    }
}
```

### Step 3: Migrate Incrementally

- Keep plugin functional during migration
- Convert one feature at a time
- Test thoroughly after each change
- Eventually remove old plugin code

## Migration Status: Metadata Extraction

The `metadata-extraction` extension demonstrates full migration:

### Before (Symfony Plugin):
- Direct `QubitSetting::getByName()` calls
- `sfContext::getInstance()->getLogger()` everywhere
- `$digitalObject->save()` (Propel ORM)
- Plugin configuration in `arMetadataExtractionPluginConfiguration`

### After (Modern Extension):
- ✅ `$context->getDatabase()->getSetting()`
- ✅ Injected `LoggerInterface $logger`
- ✅ `$context->getDatabase()->save($entity)`
- ✅ Clean `Extension::boot()` method
- ✅ NO Symfony imports in core logic
- ✅ Unit testable without AtoM

## Available Services

Extensions can access these services through `ExtensionContext`:

| Service | Interface | Purpose |
|---------|-----------|---------|
| `database` | `DatabaseInterface` | Settings, entities, queries |
| `filesystem` | `FileSystemInterface` | File operations, uploads |
| `events` | `EventDispatcherInterface` | Event pub/sub |
| `logger` | `LoggerInterface` (PSR-3) | Logging |
| `configuration` | `ConfigurationInterface` | App configuration |

## Events

Common events extensions can listen for:

- `digital_object.created` - New digital object uploaded
- `digital_object.updated` - Digital object modified
- `information_object.saved` - Description saved
- `user.authenticated` - User logged in
- Custom events from other extensions

## Testing Extensions

Extensions can be tested without full AtoM bootstrap:

```php
use PHPUnit\Framework\TestCase;

class MetadataExtractorTest extends TestCase
{
    public function testExtractsExif()
    {
        // Mock interfaces
        $database = $this->createMock(DatabaseInterface::class);
        $fileSystem = $this->createMock(FileSystemInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        // Test with mocks
        $extractor = new MetadataExtractor($database, $fileSystem, $logger);
        
        // No AtoM bootstrap needed!
    }
}
```

## Roadmap

### Phase 1 (Current): Proof of Concept ✅
- Core framework interfaces
- Symfony adapters
- Metadata extraction extension migrated
- Bootstrap integration

### Phase 2: Expand Extension Coverage
- Migrate `arIiifPlugin` to extension
- Migrate `arSecurityClearancePlugin` to extension
- Create extension development guide
- Add extension CLI tools

### Phase 3: Enhanced Abstractions
- Search/Elasticsearch interface
- Authentication/Authorization interface
- Template/View interface
- Form handling interface

### Phase 4: Community Distribution
- Composer packages for extensions
- Extension marketplace
- Version compatibility checking
- Migration tooling

### Phase 5: Core Migration
- Gradually move AtoM core features to extensions
- Reduce Symfony footprint
- Modern routing layer
- New template engine option

## Troubleshooting

### Extensions Not Loading

Check:
1. `bootstrap.php` is included in `ProjectConfiguration`
2. Extension has valid `manifest.json`
3. Extension has `src/Extension.php` file
4. PHP class name matches directory name
5. Check error logs for details

### Database Not Available

Extensions load before database is ready. Use deferred initialization:

```php
public function boot(ExtensionContext $context): void
{
    $context->getEventDispatcher()->listen(
        'context.load_factories',
        function() use ($context) {
            // Database is ready now
            $setting = $context->getDatabase()->getSetting('key');
        }
    );
}
```

### Service Not Found

Ensure required services are in manifest:

```json
{
  "requires": ["database", "filesystem", "logger"]
}
```

## Contributing

Extensions are the future of AtoM customization. To contribute:

1. Create extensions using interfaces only
2. Document your extension with README
3. Share on GitHub with `atom-extension` topic
4. Submit to extension registry (coming soon)

## License

GNU Affero General Public License v3.0 (same as AtoM)

## Author

Johan Pieterse <pieterse.johan3@gmail.com>

Part of The AHG archives project (theahg.co.za)
