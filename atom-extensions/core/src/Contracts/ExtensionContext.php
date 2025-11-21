<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Context provided to extensions during boot.
 *
 * Provides access to core services without exposing Symfony internals.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
interface ExtensionContext
{
    /**
     * Get a service from the container.
     *
     * @param string $name Service identifier
     *
     * @return object|null Service instance or null if not found
     */
    public function getService(string $name): ?object;

    /**
     * Check if a service exists in the container.
     */
    public function hasService(string $name): bool;

    /**
     * Get the event dispatcher for registering listeners.
     */
    public function getEventDispatcher(): EventDispatcherInterface;

    /**
     * Get the configuration manager.
     */
    public function getConfiguration(): ConfigurationInterface;

    /**
     * Get the logger instance.
     */
    public function getLogger(): \Psr\Log\LoggerInterface;

    /**
     * Get the database interface.
     */
    public function getDatabase(): DatabaseInterface;

    /**
     * Get the file system interface.
     */
    public function getFileSystem(): FileSystemInterface;
}
