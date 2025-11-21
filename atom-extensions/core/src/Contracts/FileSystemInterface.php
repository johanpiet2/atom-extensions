<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * File system abstraction interface.
 *
 * Provides file operations for digital objects and uploads.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
interface FileSystemInterface
{
    /**
     * Get the absolute file path for a digital object.
     *
     * @param object $digitalObject Digital object entity
     *
     * @return string|null Absolute file path or null if not found
     */
    public function getFilePath(object $digitalObject): ?string;

    /**
     * Check if a file exists.
     */
    public function fileExists(string $path): bool;

    /**
     * Check if a path is readable.
     */
    public function isReadable(string $path): bool;

    /**
     * Get file size in bytes.
     *
     * @return int|null File size or null if file doesn't exist
     */
    public function getFileSize(string $path): ?int;

    /**
     * Get file MIME type.
     *
     * @return string|null MIME type or null if cannot be determined
     */
    public function getMimeType(string $path): ?string;

    /**
     * Read file contents.
     *
     * @return string|null File contents or null if file cannot be read
     */
    public function readFile(string $path): ?string;

    /**
     * Write content to a file.
     *
     * @param string $path    File path
     * @param string $content Content to write
     *
     * @return bool Success status
     */
    public function writeFile(string $path, string $content): bool;

    /**
     * Copy a file.
     *
     * @param string $source      Source file path
     * @param string $destination Destination file path
     *
     * @return bool Success status
     */
    public function copyFile(string $source, string $destination): bool;

    /**
     * Delete a file.
     *
     * @return bool Success status
     */
    public function deleteFile(string $path): bool;

    /**
     * Create a directory.
     *
     * @param string $path      Directory path
     * @param int    $mode      Directory permissions
     * @param bool   $recursive Create parent directories if needed
     *
     * @return bool Success status
     */
    public function createDirectory(string $path, int $mode = 0755, bool $recursive = true): bool;

    /**
     * Get the upload directory path.
     *
     * @return string Absolute path to uploads directory
     */
    public function getUploadPath(): string;

    /**
     * Get the base URL for accessing files.
     *
     * @return string Base URL
     */
    public function getBaseUrl(): string;
}
