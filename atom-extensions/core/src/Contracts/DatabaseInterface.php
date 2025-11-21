<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Database abstraction interface.
 *
 * Provides database operations without exposing Propel/ORM specifics.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
interface DatabaseInterface
{
    /**
     * Get a setting value by name.
     *
     * @param string $name    Setting name
     * @param mixed  $default Default value if not found
     *
     * @return mixed Setting value or default
     */
    public function getSetting(string $name, mixed $default = null): mixed;

    /**
     * Set a setting value.
     *
     * @param string $name  Setting name
     * @param mixed  $value Setting value
     */
    public function setSetting(string $name, mixed $value): void;

    /**
     * Check if a setting exists.
     */
    public function hasSetting(string $name): bool;

    /**
     * Find an entity by ID.
     *
     * @param string $entityType Entity class/type name
     * @param int    $id         Entity ID
     *
     * @return object|null Entity instance or null
     */
    public function findById(string $entityType, int $id): ?object;

    /**
     * Find one entity by criteria.
     *
     * @param string $entityType Entity class/type name
     * @param array  $criteria   Search criteria as key-value pairs
     *
     * @return object|null First matching entity or null
     */
    public function findOneBy(string $entityType, array $criteria): ?object;

    /**
     * Find multiple entities by criteria.
     *
     * @param string   $entityType Entity class/type name
     * @param array    $criteria   Search criteria
     * @param int|null $limit      Maximum number of results
     * @param int      $offset     Result offset
     *
     * @return array Array of entity instances
     */
    public function findBy(string $entityType, array $criteria, ?int $limit = null, int $offset = 0): array;

    /**
     * Save (insert or update) an entity.
     *
     * @param object $entity Entity to save
     */
    public function save(object $entity): void;

    /**
     * Delete an entity.
     *
     * @param object $entity Entity to delete
     */
    public function delete(object $entity): void;

    /**
     * Execute a raw SQL query.
     *
     * USE WITH CAUTION - prefer entity methods when possible.
     *
     * @param string $sql    SQL query
     * @param array  $params Query parameters
     *
     * @return array Query results
     */
    public function query(string $sql, array $params = []): array;

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction.
     */
    public function commit(): void;

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void;
}
