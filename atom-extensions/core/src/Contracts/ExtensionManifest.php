<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Extension manifest containing metadata and requirements.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class ExtensionManifest
{
    public function __construct(
        private string $name,
        private string $version,
        private string $description,
        private array $authors = [],
        private array $requires = [],
        private array $provides = [],
        private ?string $atomMinVersion = null,
        private ?string $atomMaxVersion = null,
        private ?string $phpMinVersion = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    /**
     * Get required services/extensions.
     *
     * @return array Service identifiers this extension depends on
     */
    public function getRequires(): array
    {
        return $this->requires;
    }

    /**
     * Get services this extension provides.
     *
     * @return array Service identifiers this extension registers
     */
    public function getProvides(): array
    {
        return $this->provides;
    }

    public function getAtomMinVersion(): ?string
    {
        return $this->atomMinVersion;
    }

    public function getAtomMaxVersion(): ?string
    {
        return $this->atomMaxVersion;
    }

    public function getPhpMinVersion(): ?string
    {
        return $this->phpMinVersion;
    }

    /**
     * Create manifest from JSON file.
     */
    public static function fromJson(string $jsonPath): self
    {
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException("Manifest not found: {$jsonPath}");
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in manifest: '.json_last_error_msg());
        }

        return new self(
            name: $data['name'] ?? throw new \RuntimeException('Missing name in manifest'),
            version: $data['version'] ?? '1.0.0',
            description: $data['description'] ?? '',
            authors: $data['authors'] ?? [],
            requires: $data['requires'] ?? [],
            provides: $data['provides'] ?? [],
            atomMinVersion: $data['atom_min_version'] ?? null,
            atomMaxVersion: $data['atom_max_version'] ?? null,
            phpMinVersion: $data['php_min_version'] ?? null
        );
    }
}
