<?php

declare(strict_types=1);

namespace AtomExtensions\Adapters;

use AtomExtensions\Contracts\DatabaseInterface;

/**
 * Symfony/Propel database adapter.
 *
 * Implements DatabaseInterface using AtoM's Propel ORM.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class SymfonyDatabase implements DatabaseInterface
{
    public function getSetting(string $name, mixed $default = null): mixed
    {
        try {
            $setting = \QubitSetting::getByName($name);

            return $setting ? $setting->getValue(['sourceCulture' => true]) : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    public function setSetting(string $name, mixed $value): void
    {
        try {
            $setting = \QubitSetting::getByName($name);

            if (!$setting) {
                $setting = new \QubitSetting();
                $setting->name = $name;
            }

            $setting->setValue($value, ['sourceCulture' => true]);
            $setting->save();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to set setting '{$name}': ".$e->getMessage(), 0, $e);
        }
    }

    public function hasSetting(string $name): bool
    {
        try {
            return null !== \QubitSetting::getByName($name);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function findById(string $entityType, int $id): ?object
    {
        try {
            // Map generic entity types to Qubit classes
            $className = $this->resolveEntityClass($entityType);

            if (!class_exists($className)) {
                throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
            }

            return $className::getById($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findOneBy(string $entityType, array $criteria): ?object
    {
        try {
            $className = $this->resolveEntityClass($entityType);

            if (!class_exists($className)) {
                throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
            }

            // Use Propel Criteria for search
            $c = new \Criteria();

            foreach ($criteria as $column => $value) {
                $c->add(constant("{$className}Peer::".strtoupper($column)), $value);
            }

            return call_user_func([$className.'Peer', 'doSelectOne'], $c);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findBy(string $entityType, array $criteria, ?int $limit = null, int $offset = 0): array
    {
        try {
            $className = $this->resolveEntityClass($entityType);

            if (!class_exists($className)) {
                throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
            }

            $c = new \Criteria();

            foreach ($criteria as $column => $value) {
                $c->add(constant("{$className}Peer::".strtoupper($column)), $value);
            }

            if (null !== $limit) {
                $c->setLimit($limit);
            }

            if ($offset > 0) {
                $c->setOffset($offset);
            }

            return call_user_func([$className.'Peer', 'doSelect'], $c);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function save(object $entity): void
    {
        if (!method_exists($entity, 'save')) {
            throw new \InvalidArgumentException('Entity does not have a save() method');
        }

        try {
            $entity->save();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to save entity: '.$e->getMessage(), 0, $e);
        }
    }

    public function delete(object $entity): void
    {
        if (!method_exists($entity, 'delete')) {
            throw new \InvalidArgumentException('Entity does not have a delete() method');
        }

        try {
            $entity->delete();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to delete entity: '.$e->getMessage(), 0, $e);
        }
    }

    public function query(string $sql, array $params = []): array
    {
        try {
            $con = \Propel::getConnection();
            $stmt = $con->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new \RuntimeException('Query failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function beginTransaction(): void
    {
        \Propel::getConnection()->beginTransaction();
    }

    public function commit(): void
    {
        \Propel::getConnection()->commit();
    }

    public function rollback(): void
    {
        \Propel::getConnection()->rollBack();
    }

    /**
     * Map generic entity type names to Qubit class names.
     */
    private function resolveEntityClass(string $entityType): string
    {
        // Allow direct class names
        if (class_exists($entityType)) {
            return $entityType;
        }

        // Map common entity types
        $mapping = [
            'digital_object' => 'QubitDigitalObject',
            'information_object' => 'QubitInformationObject',
            'actor' => 'QubitActor',
            'event' => 'QubitEvent',
            'term' => 'QubitTerm',
            'setting' => 'QubitSetting',
            'repository' => 'QubitRepository',
            'user' => 'QubitUser',
        ];

        $normalized = strtolower(str_replace(['\\', '_'], '', $entityType));

        foreach ($mapping as $key => $className) {
            if (str_contains($normalized, str_replace('_', '', $key))) {
                return $className;
            }
        }

        // Try prefixing with Qubit
        $qubitClass = 'Qubit'.ucfirst($entityType);
        if (class_exists($qubitClass)) {
            return $qubitClass;
        }

        return $entityType;
    }
}
