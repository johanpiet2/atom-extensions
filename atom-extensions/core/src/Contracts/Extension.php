<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Base interface for all AtoM extensions.
 *
 * Extensions are self-contained modules that enhance AtoM functionality
 * without directly depending on Symfony 1.4 components.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
interface Extension
{
    /**
     * Get the extension manifest containing metadata and requirements.
     */
    public function getManifest(): ExtensionManifest;

    /**
     * Boot the extension with provided context.
     *
     * This is where extensions register event listeners, services,
     * and perform any initialization needed.
     *
     * @param ExtensionContext $context Provides access to core services
     */
    public function boot(ExtensionContext $context): void;

    /**
     * Called when the extension is being disabled.
     *
     * Perform any cleanup needed (remove event listeners, etc.)
     */
    public function shutdown(): void;
}
