<?php

declare(strict_types=1);

namespace AtomExtensions\ZoomPan;

use AtomExtensions\Contracts\ConfigurationInterface;
use AtomExtensions\Contracts\DatabaseInterface;
use AtomExtensions\Contracts\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Zoom/Pan Service.
 *
 * Provides simple iframe-based zoom and pan viewing for various file types.
 * Framework-independent - uses ONLY abstraction interfaces.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class ZoomPanService
{
    private array $settings = [];

    public function __construct(
        private DatabaseInterface $database,
        private FileSystemInterface $fileSystem,
        private ConfigurationInterface $configuration,
        private LoggerInterface $logger
    ) {
        $this->loadSettings();
    }

    /**
     * Load settings from database.
     */
    private function loadSettings(): void
    {
        $defaults = [
            'zoompan_enabled' => true,
            'zoompan_height' => '600px',
            'zoompan_use_openseadragon' => false, // Simple iframe by default
            'zoompan_supported_formats' => ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'tif', 'pdf'],
        ];

        foreach ($defaults as $name => $defaultValue) {
            $this->settings[$name] = $this->database->getSetting($name, $defaultValue);
        }
    }

    /**
     * Check if zoom/pan is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->settings['zoompan_enabled'];
    }

    /**
     * Check if digital object is supported by zoom/pan viewer.
     */
    public function isSupported(object $digitalObject): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // Get file extension
        $fileName = $digitalObject->name ?? $digitalObject->filename ?? '';

        if (empty($fileName)) {
            return false;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $supportedFormats = $this->settings['zoompan_supported_formats'];

        if (is_string($supportedFormats)) {
            $supportedFormats = explode(',', $supportedFormats);
        }

        return in_array($extension, $supportedFormats);
    }

	/**
	 * Render Zoom/Pan viewer WITHOUT requiring the legacy plugin.
	 * This is a full standalone viewer built inside the extension framework.
	 *
	 * It injects:
	 *  - toolbar (zoom, rotate, reset, fullscreen)
	 *  - pan/drag support
	 *  - optional PDF handling
	 *  - JS + CSS from extension folder
	 *
	 * @param object $digitalObject
	 * @param array  $options
	 * @return string
	 */
	public function renderViewer(object $digitalObject, array $options = []): string
	{
		$height = (int)($options['height'] ?? $this->settings['zoompan_height']);
		$viewerId = 'zoom-pan-viewer-'.($digitalObject->id ?? uniqid());

		// ------------------------------------------------------------
		// 1. Resolve image/PDF file URL — using FileSystemInterface
		// ------------------------------------------------------------
		$baseUrl = $this->fileSystem->getBaseUrl();
		$baseUrl = $this->normalizeBaseUrl($baseUrl);

		$fileUrl = sprintf(
			'%s/uploads/digitalobject/%s',
			rtrim($baseUrl, '/'),
			rawurlencode($digitalObject->path ?? $digitalObject->filename ?? '')
		);

		$mimeType = $digitalObject->mimeType ?? 'application/octet-stream';

		// ------------------------------------------------------------
		// 2. Load JS/CSS from this extension's public folder
		// ------------------------------------------------------------
		$jsUrl  = $baseUrl . '/atom-extensions/extensions/zoom-pan/public/zoom-pan.js';
		$cssUrl = $baseUrl . '/atom-extensions/extensions/zoom-pan/public/zoom-pan.css';

		// ------------------------------------------------------------
		// 3. Render HTML viewer (no plugin, no iframe)
		// ------------------------------------------------------------
		$html = <<<HTML
	<link rel="stylesheet" href="{$cssUrl}" />

	<div id="{$viewerId}" class="zoom-pan-container" style="height: {$height}px;">
		<div class="zoom-pan-toolbar">
			<button data-action="zoom-in">+</button>
			<button data-action="zoom-out">−</button>
			<button data-action="rotate-left">⟲</button>
			<button data-action="rotate-right">⟳</button>
			<button data-action="reset">Reset</button>
			<button data-action="fullscreen">⛶</button>
		</div>

		<div class="zoom-pan-stage">
			<img src="{$fileUrl}" class="zoom-pan-image" />
		</div>
	</div>

	<script src="{$jsUrl}" defer></script>
	<script>
	document.addEventListener("DOMContentLoaded", function() {
		new ZoomPanViewer("#{$viewerId}", {
			mimeType: "{$mimeType}",
			height: {$height}
		});
	});
	</script>
	<!-- PDF.js Loader -->
	<script src="{$baseUrl}/atom-extensions/extensions/zoom-pan/public/pdf.min.js"></script>
	<script>
		if (window.pdfjsLib) {
			pdfjsLib.GlobalWorkerOptions.workerSrc =
				'{$baseUrl}/atom-extensions/extensions/zoom-pan/public/pdf.worker.min.js';
		}
	</script>
	HTML;

		return $html;
	}

	
    /**
     * Normalize base URL to handle installation folders.
     */
    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        // Handle duplicate installation folder in path
        $installFolder = basename($baseUrl);
        $pattern = '#('.preg_quote($installFolder, '#').'/)+#';
        $baseUrl = preg_replace($pattern, $installFolder.'/', $baseUrl);

        return rtrim($baseUrl, '/');
    }

    /**
     * Detect viewer type based on MIME type.
     */
    public function detectViewerType(object $digitalObject): string
    {
        $mimeType = $digitalObject->mimeType ?? $digitalObject->mediaType ?? '';

        if (empty($mimeType)) {
            // Try to determine from file extension
            $fileName = $digitalObject->name ?? '';
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            return match ($extension) {
                'pdf' => 'pdf',
                'txt', 'html', 'xml', 'doc', 'docx', 'odt' => 'text',
                default => 'image',
            };
        }

        // Detect from MIME type
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ('application/pdf' === $mimeType) {
            return 'pdf';
        }

        $textTypes = [
            'text/plain',
            'text/html',
            'text/xml',
            'application/xml',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text',
        ];

        if (in_array($mimeType, $textTypes)) {
            return 'text';
        }

        // Default to image
        return 'image';
    }

    /**
     * Get viewer thumbnail HTML.
     */
    public function renderThumbnail(object $digitalObject, array $options = []): string
    {
        $defaults = [
            'size' => 'thumbnail',
            'class' => 'zoom-pan-thumbnail',
            'link' => true,
            'alt' => $digitalObject->name ?? 'Thumbnail',
        ];

        $options = array_merge($defaults, $options);

        // Get thumbnail URL (would need actual implementation)
        $thumbnailUrl = $this->getThumbnailUrl($digitalObject, $options['size']);

        // Build image tag
        $img = sprintf(
            '<img src="%s" alt="%s" class="%s">',
            htmlspecialchars($thumbnailUrl),
            htmlspecialchars($options['alt']),
            htmlspecialchars($options['class'])
        );

        // Add link if requested
        if ($options['link']) {
            $baseUrl = $this->fileSystem->getBaseUrl();
            $viewerUrl = sprintf('%s/digitalobject/%s', $baseUrl, $digitalObject->id ?? '');
            $img = sprintf(
                '<a href="%s" class="zoom-pan-thumbnail-link">%s</a>',
                htmlspecialchars($viewerUrl),
                $img
            );
        }

        return $img;
    }

    /**
     * Get thumbnail URL for digital object.
     */
    private function getThumbnailUrl(object $digitalObject, string $size): string
    {
        // In AtoM context, this would generate actual thumbnail
        // For now, return placeholder
        $baseUrl = $this->fileSystem->getBaseUrl();

        return sprintf('%s/digitalobject/thumbnail/%s/%s', $baseUrl, $digitalObject->id ?? 0, $size);
    }

    /**
     * Get viewer configuration as array.
     */
    public function getViewerConfig(object $digitalObject, array $options = []): array
    {
        return [
            'digitalObjectId' => $digitalObject->id ?? null,
            'viewerType' => $this->detectViewerType($digitalObject),
            'fileName' => $digitalObject->name ?? 'Unknown',
            'mimeType' => $digitalObject->mimeType ?? 'application/octet-stream',
            'height' => $options['height'] ?? $this->settings['zoompan_height'],
            'useOpenSeadragon' => $this->settings['zoompan_use_openseadragon'],
            'supportedFormats' => $this->settings['zoompan_supported_formats'],
        ];
    }
}
