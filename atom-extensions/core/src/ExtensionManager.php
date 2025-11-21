<?php

declare(strict_types=1);

namespace AtomExtensions;

use AtomExtensions\Contracts\ConfigurationInterface;
use AtomExtensions\Contracts\DatabaseInterface;
use AtomExtensions\Contracts\EventDispatcherInterface;
use AtomExtensions\Contracts\Extension;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Extension Manager.
 *
 * Orchestrates loading, initialization, and lifecycle of extensions.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class ExtensionManager
{
    private array $extensions = [];
    private array $bootedExtensions = [];
    private ServiceContainer $container;
    private string $extensionsPath;

    public function __construct(
        string $extensionsPath,
        DatabaseInterface $database,
        FileSystemInterface $fileSystem,
        EventDispatcherInterface $eventDispatcher,
        ConfigurationInterface $configuration,
        LoggerInterface $logger
    ) {
        $this->extensionsPath = rtrim($extensionsPath, '/');
        $this->container = new ServiceContainer();

        // Register core services
        $this->container->register('database', $database);
        $this->container->register('filesystem', $fileSystem);
        $this->container->register('events', $eventDispatcher);
        $this->container->register('configuration', $configuration);
        $this->container->register('logger', $logger);
    }

    /**
     * Discover and load extensions from the extensions directory.
     */
    public function discover(): void
    {
        if (!is_dir($this->extensionsPath)) {
            throw new \RuntimeException("Extensions path does not exist: {$this->extensionsPath}");
        }

        $directories = glob($this->extensionsPath.'/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $manifestPath = $dir.'/manifest.json';

            if (!file_exists($manifestPath)) {
                continue;
            }

            try {
                $manifest = Contracts\ExtensionManifest::fromJson($manifestPath);
                $this->loadExtension($dir, $manifest);
            } catch (\Exception $e) {
                $this->container->get('logger')->error(
                    'Failed to load extension from '.$dir.': '.$e->getMessage()
                );
            }
        }
    }

    /**
     * Load an extension from a directory.
     */
    private function loadExtension(string $path, Contracts\ExtensionManifest $manifest): void
    {
        $name = $manifest->getName();

        // Check version compatibility
        if (!$this->isCompatible($manifest)) {
            $this->container->get('logger')->warning(
                "Extension {$name} is not compatible with current AtoM version"
            );

            return;
        }

        // Look for bootstrap file
        $bootstrapFile = $path.'/src/Extension.php';

        if (!file_exists($bootstrapFile)) {
            throw new \RuntimeException("Extension bootstrap file not found: {$bootstrapFile}");
        }

        require_once $bootstrapFile;

        // Extension class should match directory name
        $className = $this->guessExtensionClassName($path, $name);

        if (!class_exists($className)) {
            throw new \RuntimeException("Extension class not found: {$className}");
        }

        $extension = new $className();

        if (!$extension instanceof Extension) {
            throw new \RuntimeException(
                "Extension {$className} must implement ".Extension::class
            );
        }

        $this->extensions[$name] = [
            'instance' => $extension,
            'manifest' => $manifest,
            'path' => $path,
        ];

        $this->container->get('logger')->info("Loaded extension: {$name}");
    }

    /**
     * Boot all loaded extensions in dependency order.
     */
    public function bootAll(): void
    {
        // Sort by dependencies
        $sorted = $this->topologicalSort($this->extensions);

        foreach ($sorted as $name) {
            $this->boot($name);
        }
    }

    /**
     * Boot a specific extension.
     */
    public function boot(string $name): void
    {
        if (isset($this->bootedExtensions[$name])) {
            return; // Already booted
        }

        if (!isset($this->extensions[$name])) {
            throw new \RuntimeException("Extension not loaded: {$name}");
        }

        $extensionData = $this->extensions[$name];
        $manifest = $extensionData['manifest'];

        // Boot dependencies first
        foreach ($manifest->getRequires() as $dependency) {
            if (isset($this->extensions[$dependency])) {
                $this->boot($dependency);
            }
        }

        // Create context
        $context = new Context(
            $this->container,
            $extensionData['path'],
            $manifest
        );

        // Boot extension
        try {
            $extensionData['instance']->boot($context);
            $this->bootedExtensions[$name] = true;

            $this->container->get('logger')->info("Booted extension: {$name}");
        } catch (\Exception $e) {
            $this->container->get('logger')->error(
                "Failed to boot extension {$name}: ".$e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Shutdown a specific extension.
     */
    public function shutdown(string $name): void
    {
        if (!isset($this->bootedExtensions[$name])) {
            return;
        }

        try {
            $this->extensions[$name]['instance']->shutdown();
            unset($this->bootedExtensions[$name]);

            $this->container->get('logger')->info("Shutdown extension: {$name}");
        } catch (\Exception $e) {
            $this->container->get('logger')->error(
                "Failed to shutdown extension {$name}: ".$e->getMessage()
            );
        }
    }

    /**
     * Get the service container.
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    /**
     * Check if extension is compatible with current environment.
     */
    private function isCompatible(Contracts\ExtensionManifest $manifest): bool
    {
        // Check PHP version
        if ($manifest->getPhpMinVersion()) {
            if (version_compare(PHP_VERSION, $manifest->getPhpMinVersion(), '<')) {
                return false;
            }
        }

        // Check AtoM version (simplified - in production you'd check actual AtoM version)
        // For now we'll assume compatibility

        return true;
    }

    /**
     * Guess the extension class name from path and name.
     */
    private function guessExtensionClassName(string $path, string $name): string
    {
        // Try namespace from composer.json first
        $composerFile = $path.'/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            if (isset($composer['autoload']['psr-4'])) {
                $namespaces = array_keys($composer['autoload']['psr-4']);
                if (count($namespaces) > 0) {
                    return rtrim($namespaces[0], '\\').'\\Extension';
                }
            }
        }

        // Fallback: convert kebab-case to PascalCase
        $className = str_replace(['-', '_'], '', ucwords($name, '-_'));

        return "AtomExtensions\\{$className}\\Extension";
    }

    /**
     * Sort extensions by dependencies (topological sort).
     *
     * Only real extensions (keys of $extensions) are sorted and booted.
     * Dependencies that are not extensions (e.g. "database", "filesystem",
     * "logger") are treated as environment services and ignored here.
     */
    private function topologicalSort(array $extensions): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$sorted, &$visiting, $extensions): void {
            // Ignore non-extension deps like "database", "filesystem", "logger"
            if (!isset($extensions[$name])) {
                return;
            }

            if (isset($sorted[$name])) {
                return;
            }

            if (isset($visiting[$name])) {
                throw new \RuntimeException("Circular dependency detected: {$name}");
            }

            $visiting[$name] = true;

            foreach ($extensions[$name]['manifest']->getRequires() as $dep) {
                $visit($dep);
            }

            unset($visiting[$name]);
            $sorted[$name] = true;
        };

        foreach (array_keys($extensions) as $name) {
            $visit($name);
        }

        return array_keys($sorted);
    }
}
