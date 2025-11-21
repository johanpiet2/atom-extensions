<?php

declare(strict_types=1);

namespace AtomExtensions\Iiif;

use AtomExtensions\Contracts\Extension as ExtensionInterface;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\ExtensionManifest;

// Ensure the IIIF service class is available when this extension boots.
require_once __DIR__ . '/IiifService.php';

/**
 * IIIF Extension.
 *
 * Provides International Image Interoperability Framework (IIIF) support:
 * - Deep zoom image viewing with OpenSeadragon
 * - IIIF Presentation API 2.1 manifest generation
 * - Cantaloupe image server integration
 * - Image carousel with multiple views
 * - Responsive, mobile-friendly viewer
 *
 * NO SYMFONY DEPENDENCIES - uses only abstraction interfaces.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class Extension implements ExtensionInterface
{
    private ?IiifService $iiifService = null;
    private ?ExtensionContext $context = null;

    public function getManifest(): ExtensionManifest
    {
        return ExtensionManifest::fromJson(__DIR__.'/../../manifest.json');
    }

    public function boot(ExtensionContext $context): void
    {
        $this->context = $context;

        // Create IIIF service
        $this->iiifService = new IiifService(
            $context->getDatabase(),
            $context->getFileSystem(),
            $context->getConfiguration(),
            $context->getLogger()
        );

        // Register as service
        $context->getService('container')?->register('iiif.service', $this->iiifService);

        // Listen for view rendering to inject IIIF viewer
        $context->getEventDispatcher()->listen(
            'view.render.digital_object',
            fn ($event) => $this->injectViewer($event),
            priority: 50
        );

        // Listen for manifest generation requests
        $context->getEventDispatcher()->listen(
            'iiif.manifest.requested',
            fn ($event) => $this->generateManifest($event)
        );

        $context->getLogger()->info('IIIF extension booted', [
            'cantaloupe_url' => $this->iiifService->getBaseUrl(),
            'api_version' => $this->iiifService->getApiVersion(),
        ]);
    }

    public function shutdown(): void
    {
        if ($this->context) {
            $events = $this->context->getEventDispatcher();
            $events->removeAllListeners('view.render.digital_object');
            $events->removeAllListeners('iiif.manifest.requested');
        }

        $this->iiifService = null;
        $this->context = null;
    }

    /**
     * Inject IIIF viewer into digital object display.
     */
    private function injectViewer($event): void
    {
        $digitalObject = $event->getSubject();

        if (!$digitalObject || !$this->iiifService->isIiifCompatible($digitalObject)) {
            return;
        }

        try {
            $viewerHtml = $this->iiifService->renderViewer($digitalObject);

            // Add viewer HTML to event for rendering
            $event->getPayload()['iiif_viewer'] = $viewerHtml;

            $this->context->getLogger()->debug('IIIF viewer injected', [
                'digital_object_id' => $digitalObject->id ?? null,
                'mime_type' => $digitalObject->mimeType ?? null,
            ]);
        } catch (\Exception $e) {
            $this->context->getLogger()->error(
                'Failed to inject IIIF viewer: '.$e->getMessage(),
                [
                    'exception' => $e,
                    'digital_object_id' => $digitalObject->id ?? null,
                ]
            );
        }
    }

    /**
     * Generate IIIF manifest for information object.
     */
    private function generateManifest($event): void
    {
        $resource = $event->getSubject();
        $format = $event->get('format', 'json');

        try {
            $manifest = $this->iiifService->generateManifest($resource);

            // Add manifest to event
            $event->getPayload()['manifest'] = $manifest;

            $this->context->getLogger()->info('IIIF manifest generated', [
                'resource_id' => $resource->id ?? null,
                'format' => $format,
                'image_count' => count($manifest['sequences'][0]['canvases'] ?? []),
            ]);
        } catch (\Exception $e) {
            $this->context->getLogger()->error(
                'Failed to generate IIIF manifest: '.$e->getMessage(),
                [
                    'exception' => $e,
                    'resource_id' => $resource->id ?? null,
                ]
            );
        }
    }
}
