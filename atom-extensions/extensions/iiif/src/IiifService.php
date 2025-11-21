<?php

declare(strict_types=1);

namespace AtomExtensions\Iiif;

use AtomExtensions\Contracts\ConfigurationInterface;
use AtomExtensions\Contracts\DatabaseInterface;
use AtomExtensions\Contracts\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * IIIF Service.
 *
 * Handles IIIF image server integration, manifest generation, and viewer rendering.
 * Framework-independent - uses ONLY abstraction interfaces.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class IiifService
{
    private array $settings = [];
    private string $baseUrl;
    private int $apiVersion;

    public function __construct(
        private DatabaseInterface $database,
        private FileSystemInterface $fileSystem,
        private ConfigurationInterface $configuration,
        private LoggerInterface $logger
    ) {
        $this->loadSettings();
        $this->initializeConfiguration();
    }

    /**
     * Load IIIF settings from database.
     */
    private function loadSettings(): void
    {
        $defaults = [
            'iiif_enabled' => true,
            'iiif_base_url' => 'https://archives.theahg.co.za/iiif/2',
            'iiif_api_version' => 2,
            'iiif_server_type' => 'cantaloupe',
            'iiif_slash_substitute' => '_SL_',
            'iiif_enable_manifests' => true,
            'iiif_enable_download' => true,
            'iiif_viewer_height' => 600,
            'iiif_carousel_autorotate' => true,
            'iiif_carousel_interval' => 5000,
        ];

        foreach ($defaults as $name => $defaultValue) {
            $this->settings[$name] = $this->database->getSetting($name, $defaultValue);
        }
    }

    /**
     * Initialize configuration from loaded settings.
     */
    private function initializeConfiguration(): void
    {
        $this->baseUrl = rtrim((string) $this->settings['iiif_base_url'], '/');
        $this->apiVersion = (int) $this->settings['iiif_api_version'];

        if (empty($this->baseUrl)) {
            $this->logger->warning('IIIF base URL not configured');
            $this->baseUrl = 'https://localhost/iiif/2';
        }
    }

    /**
     * Get IIIF base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get IIIF API version.
     */
    public function getApiVersion(): int
    {
        return $this->apiVersion;
    }

    /**
     * Check if digital object is IIIF-compatible.
     */
    public function isIiifCompatible(object $digitalObject): bool
    {
        if (!$this->settings['iiif_enabled']) {
            return false;
        }

        // Get MIME type
        $mimeType = $digitalObject->mimeType ?? $digitalObject->mediaType ?? null;

        if (!$mimeType) {
            return false;
        }

        // IIIF-compatible image formats
        $compatibleFormats = [
            'image/jpeg',
            'image/jpg',
            'image/tiff',
            'image/tif',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        return in_array(strtolower($mimeType), $compatibleFormats);
    }

    /**
     * Build IIIF identifier for a digital object.
     */
    public function buildIdentifier(object $digitalObject): string
    {
        // Get file path from file system
        $filePath = $this->fileSystem->getFilePath($digitalObject);

        if (!$filePath) {
            throw new \RuntimeException('Could not determine file path for digital object');
        }

        // Get relative path from uploads directory
        $uploadPath = $this->fileSystem->getUploadPath();
        $relativePath = str_replace($uploadPath.'/', '', $filePath);

        // Convert slashes for Cantaloupe
        $slashSubstitute = (string) $this->settings['iiif_slash_substitute'];
        $identifier = str_replace('/', $slashSubstitute, $relativePath);

        return $identifier;
    }

    /**
     * Build IIIF info.json URL for a digital object.
     */
    public function buildInfoUrl(object $digitalObject): string
    {
        $identifier = $this->buildIdentifier($digitalObject);

        return sprintf('%s/%s/info.json', $this->baseUrl, rawurlencode($identifier));
    }

    /**
     * Build IIIF image URL.
     */
    public function buildImageUrl(
        object $digitalObject,
        string $region = 'full',
        string $size = 'full',
        string $rotation = '0',
        string $quality = 'default',
        string $format = 'jpg'
    ): string {
        $identifier = $this->buildIdentifier($digitalObject);

        return sprintf(
            '%s/%s/%s/%s/%s/%s.%s',
            $this->baseUrl,
            rawurlencode($identifier),
            $region,
            $size,
            $rotation,
            $quality,
            $format
        );
    }

    /**
     * Render IIIF viewer HTML for a digital object.
     */
    public function renderViewer(object $digitalObject, array $options = []): string
    {
        $infoUrl = $this->buildInfoUrl($digitalObject);
        $viewerId = 'iiif-viewer-'.($digitalObject->id ?? uniqid());
        $height = $options['height'] ?? $this->settings['iiif_viewer_height'];
        $showDownload = $options['show_download'] ?? $this->settings['iiif_enable_download'];

        // Build OpenSeadragon configuration
        $config = json_encode([
            'id' => $viewerId,
            'tileSources' => [$infoUrl],
            'prefixUrl' => '/plugins/arIiifPlugin/vendor/openseadragon/images/',
            'showNavigator' => true,
            'showRotationControl' => true,
            'showFlipControl' => true,
            'showHomeControl' => true,
            'showZoomControl' => true,
            'showFullPageControl' => true,
            'defaultZoomLevel' => 0,
            'minZoomLevel' => 0.5,
            'maxZoomLevel' => 10,
            'visibilityRatio' => 1.0,
            'constrainDuringPan' => true,
            'animationTime' => 1.2,
        ]);

        // Generate HTML
        $html = <<<HTML
<div class="iiif-viewer-container">
    <div id="{$viewerId}" style="width: 100%; height: {$height}px; background: #000;"></div>
HTML;

        if ($showDownload) {
            $downloadUrl = $this->buildImageUrl($digitalObject, 'full', 'max', '0', 'default', 'jpg');
            $html .= <<<HTML
    <div class="iiif-controls" style="margin-top: 10px;">
        <a href="{$downloadUrl}" class="btn btn-sm btn-primary" download>
            <i class="fa fa-download"></i> Download Image
        </a>
    </div>
HTML;
        }

        $html .= <<<HTML
</div>

<script>
(function() {
    if (typeof OpenSeadragon === 'undefined') {
        console.error('OpenSeadragon not loaded');
        return;
    }
    
    try {
        var viewer = OpenSeadragon({$config});
        console.log('IIIF viewer initialized for {$viewerId}');
    } catch (e) {
        console.error('Failed to initialize IIIF viewer:', e);
    }
})();
</script>
HTML;

        return $html;
    }

    /**
     * Generate IIIF Presentation API manifest.
     */
    public function generateManifest(object $resource): array
    {
        if (!$this->settings['iiif_enable_manifests']) {
            throw new \RuntimeException('IIIF manifests are disabled');
        }

        $baseUrl = $this->fileSystem->getBaseUrl();
        $manifestId = sprintf('%s/iiif/%s/manifest', $baseUrl, $resource->slug ?? $resource->id);

        // Build manifest structure
        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => $manifestId,
            '@type' => 'sc:Manifest',
            'label' => $resource->title ?? 'Untitled',
            'metadata' => $this->buildMetadata($resource),
            'sequences' => [
                [
                    '@type' => 'sc:Sequence',
                    'canvases' => $this->buildCanvases($resource),
                ],
            ],
        ];

        // Add optional description
        if (isset($resource->scopeAndContent) && !empty($resource->scopeAndContent)) {
            $manifest['description'] = strip_tags($resource->scopeAndContent);
        }

        // Add thumbnail if available
        $thumbnail = $this->getResourceThumbnail($resource);
        if ($thumbnail) {
            $manifest['thumbnail'] = $thumbnail;
        }

        return $manifest;
    }

    /**
     * Build metadata section for manifest.
     */
    private function buildMetadata(object $resource): array
    {
        $metadata = [];

        // Add common metadata fields
        $fields = [
            'identifier' => 'Identifier',
            'title' => 'Title',
            'levelOfDescription' => 'Level of Description',
            'extentAndMedium' => 'Extent and Medium',
        ];

        foreach ($fields as $property => $label) {
            if (isset($resource->{$property}) && !empty($resource->{$property})) {
                $metadata[] = [
                    'label' => $label,
                    'value' => (string) $resource->{$property},
                ];
            }
        }

        return $metadata;
    }

    /**
     * Build canvases (pages) for manifest.
     */
    private function buildCanvases(object $resource): array
    {
        $canvases = [];

        // Get digital objects for this resource
        $digitalObjects = $this->getDigitalObjects($resource);

        foreach ($digitalObjects as $index => $digitalObject) {
            if (!$this->isIiifCompatible($digitalObject)) {
                continue;
            }

            try {
                $canvases[] = $this->buildCanvas($digitalObject, $index);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to build canvas for digital object', [
                    'digital_object_id' => $digitalObject->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $canvases;
    }

    /**
     * Build individual canvas for digital object.
     */
    private function buildCanvas(object $digitalObject, int $index): array
    {
        $baseUrl = $this->fileSystem->getBaseUrl();
        $canvasId = sprintf('%s/iiif/canvas/%s', $baseUrl, $digitalObject->id ?? $index);
        $imageId = $this->buildInfoUrl($digitalObject);

        // Get image dimensions (would come from info.json in real implementation)
        $width = 1000; // Default, should be fetched from actual image
        $height = 1000;

        return [
            '@id' => $canvasId,
            '@type' => 'sc:Canvas',
            'label' => $digitalObject->name ?? "Image ".($index + 1),
            'width' => $width,
            'height' => $height,
            'images' => [
                [
                    '@type' => 'oa:Annotation',
                    'motivation' => 'sc:painting',
                    'resource' => [
                        '@id' => $imageId,
                        '@type' => 'dctypes:Image',
                        'format' => $digitalObject->mimeType ?? 'image/jpeg',
                        'service' => [
                            '@context' => 'http://iiif.io/api/image/2/context.json',
                            '@id' => str_replace('/info.json', '', $imageId),
                            'profile' => 'http://iiif.io/api/image/2/level2.json',
                        ],
                        'width' => $width,
                        'height' => $height,
                    ],
                    'on' => $canvasId,
                ],
            ],
        ];
    }

    /**
     * Get digital objects for a resource.
     */
    private function getDigitalObjects(object $resource): array
    {
        // In AtoM context, this would query the database
        // For now, return empty array - adapter will provide real implementation
        try {
            $objects = $this->database->findBy('digital_object', [
                'information_object_id' => $resource->id,
            ]);

            return $objects;
        } catch (\Exception $e) {
            $this->logger->debug('Could not fetch digital objects', [
                'resource_id' => $resource->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get thumbnail for resource.
     */
    private function getResourceThumbnail(object $resource): ?array
    {
        // Would return thumbnail info if available
        return null;
    }
}
