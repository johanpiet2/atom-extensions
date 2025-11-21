<?php

declare(strict_types=1);

namespace AtomExtensions\MetadataExtraction;

use AtomExtensions\Contracts\Extension as ExtensionInterface;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\ExtensionManifest;

// Ensure the MetadataExtractor service class is available when this extension boots.
require_once __DIR__ . '/MetadataExtractor.php';

/**
 * Metadata Extraction Extension.
 *
 * Extracts EXIF, IPTC, and XMP metadata from uploaded digital objects.
 * NO SYMFONY DEPENDENCIES - uses only abstraction interfaces.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class Extension implements ExtensionInterface
{
    private ?MetadataExtractor $extractor = null;
    private ?ExtensionContext $context = null;

    public function getManifest(): ExtensionManifest
    {
        return ExtensionManifest::fromJson(__DIR__.'/../../manifest.json');
    }

    public function boot(ExtensionContext $context): void
    {
        $this->context = $context;

        // Create the metadata extractor service
        $this->extractor = new MetadataExtractor(
            $context->getDatabase(),
            $context->getFileSystem(),
            $context->getLogger()
        );

        // Register it as a service so other extensions can use it
        $context->getService('container')?->register('metadata.extractor', $this->extractor);

        // Listen for digital object creation events
        $context->getEventDispatcher()->listen(
            'digital_object.created',
            fn ($event) => $this->handleDigitalObjectCreated($event),
            priority: 10
        );

        // Listen for digital object updates
        $context->getEventDispatcher()->listen(
            'digital_object.updated',
            fn ($event) => $this->handleDigitalObjectUpdated($event),
            priority: 10
        );

        $context->getLogger()->info('Metadata Extraction extension booted');
    }

    public function shutdown(): void
    {
        if ($this->context) {
            $this->context->getEventDispatcher()->removeAllListeners('digital_object.created');
            $this->context->getEventDispatcher()->removeAllListeners('digital_object.updated');
        }

        $this->extractor = null;
        $this->context = null;
    }

    /**
     * Handle digital object created event.
     */
    private function handleDigitalObjectCreated($event): void
    {
        $digitalObject = $event->getSubject();

        if (!$digitalObject) {
            return;
        }

        try {
            $this->extractor->processDigitalObject($digitalObject);
        } catch (\Exception $e) {
            $this->context->getLogger()->error(
                'Failed to extract metadata: '.$e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Handle digital object updated event.
     */
    private function handleDigitalObjectUpdated($event): void
    {
        // Only process if the file was changed
        if ($event->has('file_changed') && $event->get('file_changed')) {
            $this->handleDigitalObjectCreated($event);
        }
    }
}
