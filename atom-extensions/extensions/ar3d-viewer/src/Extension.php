<?php

declare(strict_types=1);

namespace AtomExtensions\Ar3dViewer;

use AtomExtensions\Contracts\Extension as ExtensionInterface;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\ExtensionManifest;

/**
 * ar3DViewerPlugin bridge extension.
 *
 * This extension does NOT implement any runtime behaviour by itself.
 * The actual 3D viewer functionality lives in the legacy Symfony
 * plugin ar3DViewerPlugin (plugins/ar3DViewerPlugin).
 *
 * The purpose of this class is only to expose that plugin into the
 * AtoM extension framework (for discovery, metadata, logging, etc.).
 */
final class Extension implements ExtensionInterface
{
    private ?ExtensionContext $context = null;

    /**
     * Return the extension manifest metadata.
     */
    public function getManifest(): ExtensionManifest
    {
        return ExtensionManifest::fromJson(__DIR__ . '/../../manifest.json');
    }

    /**
     * Boot hook – currently just logs that the bridge is active.
     */
    public function boot(ExtensionContext $context): void
    {
        $this->context = $context;

        $context->getLogger()->debug('Booting ar3DViewerPlugin extension bridge');
    }

    /**
     * Shutdown hook – currently a no-op except for logging.
     */
    public function shutdown(): void
    {
        if ($this->context !== null) {
            $this->context->getLogger()->debug('Shutting down ar3DViewerPlugin extension bridge');
        }

        $this->context = null;
    }
}
