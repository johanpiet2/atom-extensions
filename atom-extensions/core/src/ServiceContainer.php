<?php

declare(strict_types=1);

namespace AtomExtensions;

use AtomExtensions\Contracts\ConfigurationInterface;
use AtomExtensions\Contracts\DatabaseInterface;
use AtomExtensions\Contracts\EventDispatcherInterface;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\ExtensionManifest;
use AtomExtensions\Contracts\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Simple service container.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class ServiceContainer
{
    private array $services = [];
    private array $factories = [];

    /**
     * Register a service instance.
     */
    public function register(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Register a service factory.
     */
    public function registerFactory(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /**
     * Get a service by name.
     */
    public function get(string $name): ?object
    {
        // Return existing instance
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }

        // Create from factory
        if (isset($this->factories[$name])) {
            $this->services[$name] = $this->factories[$name]($this);

            return $this->services[$name];
        }

        return null;
    }

    /**
     * Check if service exists.
     */
    public function has(string $name): bool
    {
        return isset($this->services[$name]) || isset($this->factories[$name]);
    }

    /**
     * Get all registered service names.
     */
    public function getServiceNames(): array
    {
        return array_unique([
            ...array_keys($this->services),
            ...array_keys($this->factories),
        ]);
    }
}

/**
 * Extension context implementation.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class Context implements ExtensionContext
{
    public function __construct(
        private ServiceContainer $container,
        private string $extensionPath,
        private ExtensionManifest $manifest
    ) {
    }

    public function getService(string $name): ?object
    {
        return $this->container->get($name);
    }

    public function hasService(string $name): bool
    {
        return $this->container->has($name);
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        $service = $this->container->get('events');

        if (!$service instanceof EventDispatcherInterface) {
            throw new \RuntimeException('Event dispatcher service not available');
        }

        return $service;
    }

    public function getConfiguration(): ConfigurationInterface
    {
        $service = $this->container->get('configuration');

        if (!$service instanceof ConfigurationInterface) {
            throw new \RuntimeException('Configuration service not available');
        }

        return $service;
    }

    public function getLogger(): LoggerInterface
    {
        $service = $this->container->get('logger');

        if (!$service instanceof LoggerInterface) {
            throw new \RuntimeException('Logger service not available');
        }

        return $service;
    }

    public function getDatabase(): DatabaseInterface
    {
        $service = $this->container->get('database');

        if (!$service instanceof DatabaseInterface) {
            throw new \RuntimeException('Database service not available');
        }

        return $service;
    }

    public function getFileSystem(): FileSystemInterface
    {
        $service = $this->container->get('filesystem');

        if (!$service instanceof FileSystemInterface) {
            throw new \RuntimeException('FileSystem service not available');
        }

        return $service;
    }

    /**
     * Get the extension's base path.
     */
    public function getPath(): string
    {
        return $this->extensionPath;
    }

    /**
     * Get the extension's manifest.
     */
    public function getManifest(): ExtensionManifest
    {
        return $this->manifest;
    }
}
