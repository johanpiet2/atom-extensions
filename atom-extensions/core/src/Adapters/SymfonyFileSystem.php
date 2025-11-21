<?php

declare(strict_types=1);

namespace AtomExtensions\Adapters;

use AtomExtensions\Contracts\FileSystemInterface;

/**
 * Symfony file system adapter.
 *
 * Implements FileSystemInterface using AtoM's file handling.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class SymfonyFileSystem implements FileSystemInterface
{
    public function getFilePath(object $digitalObject): ?string
    {
        if (!$digitalObject instanceof \QubitDigitalObject) {
            return null;
        }

        try {
            // Try modern method first
            if (method_exists($digitalObject, 'getAbsolutePath')) {
                return $digitalObject->getAbsolutePath();
            }

            // Fallback to path property
            $path = (string) $digitalObject->path;

            if (empty($path)) {
                return null;
            }

            // Make absolute if relative
            if (!str_starts_with($path, '/')) {
                $uploadPath = sfConfig::get('sf_upload_dir', sfConfig::get('sf_web_dir').'/uploads');
                $path = $uploadPath.'/'.$path;
            }

            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    public function getFileSize(string $path): ?int
    {
        if (!$this->fileExists($path)) {
            return null;
        }

        $size = filesize($path);

        return false !== $size ? $size : null;
    }

    public function getMimeType(string $path): ?string
    {
        if (!$this->fileExists($path)) {
            return null;
        }

        // Try finfo first (most reliable)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $path);
            finfo_close($finfo);

            if (false !== $mimeType) {
                return $mimeType;
            }
        }

        // Fallback to mime_content_type
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($path);
            if (false !== $mimeType) {
                return $mimeType;
            }
        }

        return null;
    }

    public function readFile(string $path): ?string
    {
        if (!$this->isReadable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return false !== $content ? $content : null;
    }

    public function writeFile(string $path, string $content): bool
    {
        try {
            // Ensure directory exists
            $dir = dirname($path);
            if (!is_dir($dir)) {
                $this->createDirectory($dir);
            }

            return false !== file_put_contents($path, $content);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function copyFile(string $source, string $destination): bool
    {
        if (!$this->fileExists($source)) {
            return false;
        }

        try {
            // Ensure destination directory exists
            $dir = dirname($destination);
            if (!is_dir($dir)) {
                $this->createDirectory($dir);
            }

            return copy($source, $destination);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteFile(string $path): bool
    {
        if (!$this->fileExists($path)) {
            return false;
        }

        try {
            return unlink($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createDirectory(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        if (is_dir($path)) {
            return true;
        }

        try {
            return mkdir($path, $mode, $recursive);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUploadPath(): string
    {
        // Try sfConfig first
        if (class_exists('sfConfig')) {
            $uploadDir = sfConfig::get('sf_upload_dir');
            if ($uploadDir) {
                return $uploadDir;
            }

            $webDir = sfConfig::get('sf_web_dir');
            if ($webDir) {
                return $webDir.'/uploads';
            }
        }

        // Fallback to relative path
        return realpath(__DIR__.'/../../../../../uploads') ?: '/var/www/atom/uploads';
    }

    public function getBaseUrl(): string
    {
        // Try QubitSetting first
        try {
            if (class_exists('QubitSetting')) {
                $baseUrl = \QubitSetting::getByName('siteBaseUrl');
                if ($baseUrl) {
                    return rtrim($baseUrl->getValue(['sourceCulture' => true]), '/');
                }
            }
        } catch (\Exception $e) {
            // Fall through to defaults
        }

        // Try sfContext
        try {
            if (class_exists('sfContext') && sfContext::hasInstance()) {
                $request = sfContext::getInstance()->getRequest();
                if ($request) {
                    return $request->getUriPrefix();
                }
            }
        } catch (\Exception $e) {
            // Fall through
        }

        // Final fallback
        return '';
    }
}
