<?php

/**
 * AtoM Extension Framework Bootstrap.
 *
 * This file initializes the extension framework and loads all extensions.
 * Include this file from AtoM's plugin configuration or initialization.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

// If someone runs this file directly, show a warning and stop.
// When included from AtoM (web or CLI), this condition will be false.
if (
    PHP_SAPI === 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && basename((string) $_SERVER['SCRIPT_FILENAME']) === 'bootstrap.php'
) {
    echo "ERROR: This file must be included from within AtoM.\n";

    return;
}

// Autoload extension framework classes
require_once __DIR__.'/core/src/Contracts/Extension.php';
require_once __DIR__.'/core/src/Contracts/ExtensionContext.php';
require_once __DIR__.'/core/src/Contracts/ExtensionManifest.php';
require_once __DIR__.'/core/src/Contracts/DatabaseInterface.php';
require_once __DIR__.'/core/src/Contracts/FileSystemInterface.php';
require_once __DIR__.'/core/src/Contracts/EventDispatcherInterface.php';
require_once __DIR__.'/core/src/Contracts/ConfigurationInterface.php';
require_once __DIR__.'/core/src/Adapters/SymfonyDatabase.php';
require_once __DIR__.'/core/src/Adapters/SymfonyFileSystem.php';
require_once __DIR__.'/core/src/Adapters/SymfonyEventDispatcher.php';
require_once __DIR__.'/core/src/ServiceContainer.php';
require_once __DIR__.'/core/src/ExtensionManager.php';
//require_once __DIR__.'/core/src/MonologFactory.php';
require_once __DIR__.'/core/src/SimpleLogger.php';

use AtomExtensions\Adapters\SymfonyDatabase;
use AtomExtensions\Adapters\SymfonyEventDispatcher;
use AtomExtensions\Adapters\SymfonyFileSystem;
use AtomExtensions\ExtensionManager;
//use AtomExtensions\MonologFactory;
use AtomExtensions\SimpleLogger;


/**
 * Simple configuration adapter.
 */
class SymfonyConfiguration implements AtomExtensions\Contracts\ConfigurationInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        if (class_exists('sfConfig')) {
            return sfConfig::get($key, $default);
        }

        return $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (class_exists('sfConfig')) {
            sfConfig::set($key, $value);
        }
    }

    public function has(string $key): bool
    {
        if (class_exists('sfConfig')) {
            return sfConfig::has($key);
        }

        return false;
    }

    public function all(): array
    {
        if (class_exists('sfConfig')) {
            return sfConfig::getAll();
        }

        return [];
    }

    public function getSection(string $section): array
    {
        return [];
    }

    public function merge(array $config): void
    {
        foreach ($config as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getEnvironment(): string
    {
        return $this->get('sf_environment', 'prod');
    }

    public function isDebug(): bool
    {
        return $this->get('sf_debug', false);
    }
}

/**
 * Initialize and boot the extension framework.
 */
function initializeExtensionFramework(): ?ExtensionManager
{
    try {
        // Ensure Symfony context is ready
        if (!class_exists('sfContext')) {
            error_log('Extension framework: sfContext class not found');

            return null;
        }

        if (!sfContext::hasInstance()) {
            error_log('Extension framework: sfContext instance not available');

            return null;
        }

        $context = sfContext::getInstance();

        // Create adapter instances
        $database = new SymfonyDatabase();
        $fileSystem = new SymfonyFileSystem();
        $eventDispatcher = new SymfonyEventDispatcher($context->getEventDispatcher());
        $configuration = new SymfonyConfiguration();

        // Use our lightweight PSR-3 logger (no Monolog)
        $logger = new SimpleLogger();

        // Path to the extensions directory (sibling of this bootstrap.php)
        $extensionsPath = dirname(__FILE__) . '/extensions';

        if (!is_dir($extensionsPath)) {
            $logger->warning('Extensions directory not found', [
                'path' => $extensionsPath,
            ]);

            return null;
        }

        // Create and boot the extension manager
        $manager = new ExtensionManager(
            $extensionsPath,
            $database,
            $fileSystem,
            $eventDispatcher,
            $configuration,
            $logger
        );

        $manager->discover();
        $manager->bootAll();

        $logger->info('Extension framework initialized successfully', [
            'extensions_path' => $extensionsPath,
        ]);

        return $manager;
    } catch (Throwable $e) {
        // Last-resort logging
        error_log(
            'Extension framework initialization failed: '
            . $e->getMessage()
            . ' in '
            . $e->getFile()
            . ':'
            . $e->getLine()
        );

        return null;
    }
}

// Register initialization on context.load_factories event
if (class_exists('sfContext') && sfContext::hasInstance()) {
    $dispatcher = sfContext::getInstance()->getEventDispatcher();
    $dispatcher->connect('context.load_factories', function () {
        static $initialized = false;

        if (!$initialized) {
            $GLOBALS['atom_extension_manager'] = initializeExtensionFramework();
            $initialized = true;
        }
    });
}
