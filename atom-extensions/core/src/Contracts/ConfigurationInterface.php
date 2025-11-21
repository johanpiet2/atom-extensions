<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Configuration management interface.
 *
 * Provides access to configuration values with environment overrides.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
interface ConfigurationInterface
{
    /**
     * Get a configuration value.
     *
     * @param string $key     Configuration key (supports dot notation: 'section.subsection.key')
     * @param mixed  $default Default value if not found
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a configuration value.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool;

    /**
     * Get all configuration values.
     */
    public function all(): array;

    /**
     * Get configuration for a specific section.
     *
     * @param string $section Section name
     *
     * @return array Configuration values in that section
     */
    public function getSection(string $section): array;

    /**
     * Merge configuration values.
     *
     * @param array $config Configuration to merge
     */
    public function merge(array $config): void;

    /**
     * Get the current environment (dev, staging, production).
     */
    public function getEnvironment(): string;

    /**
     * Check if running in debug mode.
     */
    public function isDebug(): bool;
}
