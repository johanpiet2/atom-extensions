<?php

declare(strict_types=1);

namespace AtomExtensions\MetadataExtraction;

use AtomExtensions\Contracts\DatabaseInterface;
use AtomExtensions\Contracts\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Metadata Extractor Service.
 *
 * Framework-independent metadata extraction from image files.
 * Uses ONLY abstraction interfaces - NO Symfony/Propel dependencies.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class MetadataExtractor
{
    private array $settings = [];

    public function __construct(
        private DatabaseInterface $database,
        private FileSystemInterface $fileSystem,
        private LoggerInterface $logger
    ) {
        $this->loadSettings();
    }

    /**
     * Load extension settings from database.
     */
    private function loadSettings(): void
    {
        $defaults = [
            'metadata_extraction_enabled' => true,
            'extract_exif' => true,
            'extract_iptc' => true,
            'extract_xmp' => true,
            'overwrite_title' => false,
            'overwrite_description' => false,
            'auto_generate_keywords' => true,
            'extract_gps_coordinates' => true,
            'add_technical_metadata' => true,
        ];

        foreach ($defaults as $name => $defaultValue) {
            $this->settings[$name] = $this->database->getSetting($name, $defaultValue);
        }
    }

    /**
     * Process a digital object and extract metadata.
     *
     * @param object $digitalObject The digital object (QubitDigitalObject in AtoM context)
     *
     * @return bool Success status
     */
    public function processDigitalObject(object $digitalObject): bool
    {
        if (!$this->settings['metadata_extraction_enabled']) {
            return false;
        }

        // Get file path through abstraction
        $filePath = $this->fileSystem->getFilePath($digitalObject);

        if (!$filePath || !$this->fileSystem->fileExists($filePath)) {
            $this->logger->warning('Metadata extraction: File not found', [
                'object_id' => $digitalObject->id ?? null,
            ]);

            return false;
        }

        if (!$this->fileSystem->isReadable($filePath)) {
            $this->logger->warning('Metadata extraction: File not readable', [
                'path' => $filePath,
            ]);

            return false;
        }

        // Check if it's an image file
        $mimeType = $this->fileSystem->getMimeType($filePath);
        if (!$this->isImageFile($mimeType)) {
            return false;
        }

        try {
            // Extract all metadata
            $metadata = $this->extractMetadata($filePath);

            if (empty($metadata)) {
                $this->logger->debug('No metadata found in file', ['path' => $filePath]);

                return false;
            }

            // Apply metadata to information object
            $this->applyMetadata($digitalObject, $metadata);

            $this->logger->info('Successfully extracted metadata', [
                'path' => $filePath,
                'fields' => count($metadata),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Metadata extraction failed: '.$e->getMessage(), [
                'path' => $filePath,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Extract all metadata from a file.
     */
    private function extractMetadata(string $filePath): array
    {
        $metadata = [];

        // Extract EXIF data
        if ($this->settings['extract_exif']) {
            $exif = $this->extractExif($filePath);
            $metadata = array_merge($metadata, $exif);
        }

        // Extract IPTC data
        if ($this->settings['extract_iptc']) {
            $iptc = $this->extractIptc($filePath);
            $metadata = array_merge($metadata, $iptc);
        }

        // Extract XMP data
        if ($this->settings['extract_xmp']) {
            $xmp = $this->extractXmp($filePath);
            $metadata = array_merge($metadata, $xmp);
        }

        return $metadata;
    }

    /**
     * Extract EXIF metadata.
     */
    private function extractExif(string $filePath): array
    {
        if (!function_exists('exif_read_data')) {
            return [];
        }

        try {
            $exif = @exif_read_data($filePath, null, true);

            if (!$exif) {
                return [];
            }

            $metadata = [];

            // Title from ImageDescription
            if (isset($exif['IFD0']['ImageDescription'])) {
                $metadata['title'] = $this->cleanText($exif['IFD0']['ImageDescription']);
            }

            // Description from Comments
            if (isset($exif['COMMENT'][0])) {
                $metadata['description'] = $this->cleanText($exif['COMMENT'][0]);
            }

            // Creator/Artist
            if (isset($exif['IFD0']['Artist'])) {
                $metadata['creator'] = $this->cleanText($exif['IFD0']['Artist']);
            }

            // Date taken
            if (isset($exif['EXIF']['DateTimeOriginal'])) {
                $metadata['date'] = $this->parseDate($exif['EXIF']['DateTimeOriginal']);
            } elseif (isset($exif['IFD0']['DateTime'])) {
                $metadata['date'] = $this->parseDate($exif['IFD0']['DateTime']);
            }

            // GPS coordinates
            if ($this->settings['extract_gps_coordinates'] && isset($exif['GPS'])) {
                $gps = $this->parseGpsCoordinates($exif['GPS']);
                if ($gps) {
                    $metadata['gps_latitude'] = $gps['latitude'];
                    $metadata['gps_longitude'] = $gps['longitude'];
                }
            }

            // Technical metadata
            if ($this->settings['add_technical_metadata']) {
                $technical = [];

                if (isset($exif['COMPUTED']['Width'], $exif['COMPUTED']['Height'])) {
                    $technical[] = 'Dimensions: '.$exif['COMPUTED']['Width'].'x'.$exif['COMPUTED']['Height'];
                }

                if (isset($exif['IFD0']['Make'], $exif['IFD0']['Model'])) {
                    $technical[] = 'Camera: '.$exif['IFD0']['Make'].' '.$exif['IFD0']['Model'];
                }

                if (isset($exif['EXIF']['FocalLength'])) {
                    $technical[] = 'Focal Length: '.$this->formatFraction($exif['EXIF']['FocalLength']);
                }

                if (isset($exif['EXIF']['FNumber'])) {
                    $technical[] = 'Aperture: f/'.$this->formatFraction($exif['EXIF']['FNumber']);
                }

                if (isset($exif['EXIF']['ExposureTime'])) {
                    $technical[] = 'Shutter Speed: '.$this->formatFraction($exif['EXIF']['ExposureTime']).'s';
                }

                if (isset($exif['EXIF']['ISOSpeedRatings'])) {
                    $technical[] = 'ISO: '.$exif['EXIF']['ISOSpeedRatings'];
                }

                if (!empty($technical)) {
                    $metadata['technical'] = implode("\n", $technical);
                }
            }

            return $metadata;
        } catch (\Exception $e) {
            $this->logger->debug('EXIF extraction failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Extract IPTC metadata.
     */
    private function extractIptc(string $filePath): array
    {
        try {
            $size = getimagesize($filePath, $info);

            if (!isset($info['APP13'])) {
                return [];
            }

            $iptc = iptcparse($info['APP13']);

            if (!$iptc) {
                return [];
            }

            $metadata = [];

            // Title from ObjectName
            if (isset($iptc['2#005'][0])) {
                $metadata['title'] = $this->cleanText($iptc['2#005'][0]);
            }

            // Description from Caption
            if (isset($iptc['2#120'][0])) {
                $metadata['description'] = $this->cleanText($iptc['2#120'][0]);
            }

            // Creator from Byline
            if (isset($iptc['2#080'][0])) {
                $metadata['creator'] = $this->cleanText($iptc['2#080'][0]);
            }

            // Keywords
            if (isset($iptc['2#025']) && $this->settings['auto_generate_keywords']) {
                $keywords = array_map([$this, 'cleanText'], $iptc['2#025']);
                $metadata['keywords'] = array_filter($keywords);
            }

            // Copyright
            if (isset($iptc['2#116'][0])) {
                $metadata['copyright'] = $this->cleanText($iptc['2#116'][0]);
            }

            // Date created
            if (isset($iptc['2#055'][0])) {
                $metadata['date'] = $this->parseDate($iptc['2#055'][0]);
            }

            return $metadata;
        } catch (\Exception $e) {
            $this->logger->debug('IPTC extraction failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Extract XMP metadata using exiftool.
     */
    private function extractXmp(string $filePath): array
    {
        // Check if exiftool is available
        if (!$this->isExiftoolAvailable()) {
            return [];
        }

        try {
            $command = 'exiftool -json '.escapeshellarg($filePath).' 2>/dev/null';
            $output = shell_exec($command);

            if (!$output) {
                return [];
            }

            $data = json_decode($output, true);

            if (!is_array($data) || empty($data[0])) {
                return [];
            }

            $xmp = $data[0];
            $metadata = [];

            // Extract common XMP fields
            if (isset($xmp['Title'])) {
                $metadata['title'] = $this->cleanText($xmp['Title']);
            }

            if (isset($xmp['Description'])) {
                $metadata['description'] = $this->cleanText($xmp['Description']);
            }

            if (isset($xmp['Creator'])) {
                $metadata['creator'] = $this->cleanText($xmp['Creator']);
            }

            if (isset($xmp['Subject']) && is_array($xmp['Subject'])) {
                $metadata['keywords'] = array_map([$this, 'cleanText'], $xmp['Subject']);
            }

            return $metadata;
        } catch (\Exception $e) {
            $this->logger->debug('XMP extraction failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Apply extracted metadata to the information object.
     *
     * Uses database abstraction - NO Propel dependencies.
     */
    private function applyMetadata(object $digitalObject, array $metadata): void
    {
        // Get the related information object
        // In AtoM context, this would be digitalObject->informationObject
        // We access it generically through properties
        $informationObject = $digitalObject->informationObject ?? $digitalObject->object ?? null;

        if (!$informationObject) {
            $this->logger->warning('No information object found for digital object');

            return;
        }

        // Apply title
        if (isset($metadata['title']) && ($this->settings['overwrite_title'] || empty($informationObject->title))) {
            $informationObject->title = $metadata['title'];
        }

        // Apply description
        if (isset($metadata['description'])) {
            if ($this->settings['overwrite_description'] || empty($informationObject->scopeAndContent)) {
                $informationObject->scopeAndContent = $metadata['description'];
            }
        }

        // Apply technical metadata
        if (isset($metadata['technical'])) {
            $existing = $informationObject->physicalCharacteristics ?? '';
            $informationObject->physicalCharacteristics = $existing
                ? $existing."\n\n".$metadata['technical']
                : $metadata['technical'];
        }

        // Save the information object
        $this->database->save($informationObject);

        // Handle creator/events
        if (isset($metadata['creator'])) {
            $this->addCreator($informationObject, $metadata['creator'], $metadata['date'] ?? null);
        }

        // Handle keywords/terms
        if (isset($metadata['keywords']) && $this->settings['auto_generate_keywords']) {
            $this->addKeywords($informationObject, $metadata['keywords']);
        }

        // Handle GPS coordinates (store as notes or custom property)
        if (isset($metadata['gps_latitude'], $metadata['gps_longitude'])) {
            $gpsNote = sprintf(
                'GPS Coordinates: %s, %s',
                $metadata['gps_latitude'],
                $metadata['gps_longitude']
            );

            $existing = $informationObject->locationOfOriginals ?? '';
            $informationObject->locationOfOriginals = $existing
                ? $existing."\n".$gpsNote
                : $gpsNote;

            $this->database->save($informationObject);
        }
    }

    /**
     * Add creator actor and creation event.
     */
    private function addCreator(object $informationObject, string $creatorName, ?string $date): void
    {
        try {
            // Find or create actor
            $actor = $this->database->findOneBy('actor', ['authorized_form_of_name' => $creatorName]);

            if (!$actor) {
                // Create new actor (entity type will be resolved by adapter)
                $actor = new \stdClass();
                $actor->authorizedFormOfName = $creatorName;
                $actor->entityTypeId = 1; // Person type
                $this->database->save($actor);
            }

            // Create event linking actor to information object
            // In AtoM this would be QubitEvent
            $event = new \stdClass();
            $event->informationObjectId = $informationObject->id;
            $event->actorId = $actor->id;
            $event->typeId = 1; // Creation event type

            if ($date) {
                $event->date = $date;
            }

            $this->database->save($event);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add creator: '.$e->getMessage());
        }
    }

    /**
     * Add keywords as subject terms.
     */
    private function addKeywords(object $informationObject, array $keywords): void
    {
        // This would create term relationships in AtoM
        // Simplified for now - in production would use proper term taxonomy
        foreach ($keywords as $keyword) {
            try {
                // Implementation depends on AtoM's term system
                // Would use database abstraction to create/link terms
                $this->logger->debug('Would add keyword: '.$keyword);
            } catch (\Exception $e) {
                $this->logger->debug('Failed to add keyword: '.$e->getMessage());
            }
        }
    }

    /**
     * Helper methods.
     */
    private function isImageFile(?string $mimeType): bool
    {
        return $mimeType && str_starts_with($mimeType, 'image/');
    }

    private function isExiftoolAvailable(): bool
    {
        static $available = null;

        if (null === $available) {
            $available = null !== shell_exec('which exiftool 2>/dev/null');
        }

        return $available;
    }

    private function cleanText(string $text): string
    {
        return trim(strip_tags($text));
    }

    private function parseDate(string $dateString): ?string
    {
        try {
            // Handle EXIF format: 2024:11:15 10:30:45
            $dateString = str_replace(':', '-', substr($dateString, 0, 10)).substr($dateString, 10);
            $date = new \DateTime($dateString);

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseGpsCoordinates(array $gps): ?array
    {
        if (!isset($gps['GPSLatitude'], $gps['GPSLongitude'])) {
            return null;
        }

        $latitude = $this->convertGpsCoordinate($gps['GPSLatitude'], $gps['GPSLatitudeRef'] ?? 'N');
        $longitude = $this->convertGpsCoordinate($gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? 'E');

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function convertGpsCoordinate(array $coordinate, string $ref): float
    {
        $degrees = $this->evaluateFraction($coordinate[0]);
        $minutes = $this->evaluateFraction($coordinate[1]);
        $seconds = $this->evaluateFraction($coordinate[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if (in_array($ref, ['S', 'W'])) {
            $decimal *= -1;
        }

        return round($decimal, 6);
    }

    private function evaluateFraction(string $fraction): float
    {
        $parts = explode('/', $fraction);

        if (count($parts) === 2) {
            return (float) $parts[0] / (float) $parts[1];
        }

        return (float) $fraction;
    }

    private function formatFraction(string $fraction): string
    {
        $value = $this->evaluateFraction($fraction);

        if ($value >= 1) {
            return number_format($value, 1);
        }

        return $fraction;
    }
}
