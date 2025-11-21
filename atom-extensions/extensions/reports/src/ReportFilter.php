<?php

declare(strict_types=1);

namespace AtomExtensions\Reports;

/**
 * Simple filter bag for report parameters.
 */
final class ReportFilter
{
    /**
     * @var array<string, mixed>
     */
    private array $values;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        if (\array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }

        return $default;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }
}
