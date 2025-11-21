<?php

declare(strict_types=1);

namespace AtomExtensions\ZoomPan;

use AtomExtensions\Contracts\Extension as ExtensionInterface;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\ExtensionManifest;

// Ensure the ZoomPan service class is available when this extension boots.
require_once __DIR__ . '/ZoomPanService.php';

/**
 * Zoom/Pan Extension.
 *
 * Provides simple iframe-based zoom and pan viewing for documents and images.
 * Simpler alternative to full IIIF for basic zoom/pan needs.
 *
 * Features:
 * - Iframe-based viewer for quick integration
 * - OpenSeadragon support for images
 * - PDF viewing with zoom controls
 * - Text document display
 *
 * NO SYMFONY DEPENDENCIES - uses only abstraction interfaces.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class Extension implements ExtensionInterface
{
    private ?ZoomPanService $zoomPanService = null;
    private ?ExtensionContext $context = null;

    public function getManifest(): ExtensionManifest
    {
        return ExtensionManifest::fromJson(__DIR__.'/../../manifest.json');
    }

    public function boot(ExtensionContext $context): void
    {
        $this->context = $context;

        // Create zoom/pan service
        $this->zoomPanService = new ZoomPanService(
            $context->getDatabase(),
            $context->getFileSystem(),
            $context->getConfiguration(),
            $context->getLogger()
        );

        // Register as service
        $context->getService('container')?->register('zoompan.service', $this->zoomPanService);

        // Listen for viewer rendering requests
        $context->getEventDispatcher()->listen(
            'view.render.digital_object',
            fn ($event) => $this->injectViewer($event),
            priority: 40 // Lower priority than IIIF, runs as fallback
        );

        $context->getLogger()->info('Zoom/Pan extension booted', [
            'enabled' => $this->zoomPanService->isEnabled(),
        ]);
    }

    public function shutdown(): void
    {
        if ($this->context) {
            $this->context->getEventDispatcher()->removeAllListeners('view.render.digital_object');
        }

        $this->zoomPanService = null;
        $this->context = null;
    }

    /**
     * Inject zoom/pan viewer into digital object display.
     */
    private function injectViewer($event): void
    {
        $digitalObject = $event->getSubject();

        // Skip if IIIF viewer was already injected
        if ($event->has('iiif_viewer')) {
            $this->context->getLogger()->debug('IIIF viewer present, skipping zoom/pan');

            return;
        }

        if (!$digitalObject || !$this->zoomPanService->isSupported($digitalObject)) {
            return;
        }

        try {
            $viewerHtml = $this->zoomPanService->renderViewer($digitalObject);

            // Add viewer HTML to event
            $event->getPayload()['zoompan_viewer'] = $viewerHtml;

            $this->context->getLogger()->debug('Zoom/Pan viewer injected', [
                'digital_object_id' => $digitalObject->id ?? null,
                'mime_type' => $digitalObject->mimeType ?? null,
            ]);
        } catch (\Exception $e) {
            $this->context->getLogger()->error(
                'Failed to inject Zoom/Pan viewer: '.$e->getMessage(),
                [
                    'exception' => $e,
                    'digital_object_id' => $digitalObject->id ?? null,
                ]
            );
        }
    }
}
