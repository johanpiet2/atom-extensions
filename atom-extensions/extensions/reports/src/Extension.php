<?php

declare(strict_types=1);

namespace AtomExtensions\Reports;

use AtomExtensions\Contracts\Extension as ExtensionInterface;
use AtomExtensions\Contracts\ExtensionContext;
use AtomExtensions\Contracts\ExtensionManifest;

// Avoid redeclaration if this file is required twice for any reason.
if (class_exists(Extension::class, false)) {
    // Already loaded.
    return;
}

/**
 * Reports extension – currently only Authority Record report.
 */
final class Extension implements ExtensionInterface
{
    private ?ExtensionContext $context = null;

    private ?AuthorityRecordReportService $authorityService = null;

    public function getManifest(): ExtensionManifest
    {
        return ExtensionManifest::fromJson(__DIR__ . '/../../manifest.json');
    }

    public function boot(ExtensionContext $context): void
    {
        $this->context = $context;

        $this->authorityService = new AuthorityRecordReportService(
            $context->getDatabase(),
            $context->getLogger()
        );

        $container = $context->getService('container');
        if ($container !== null) {
            $container->register('reports.authority_record', $this->authorityService);
        }

        $context->getLogger()->info('Reports (authority record) extension booted');
    }

    public function shutdown(): void
    {
        if ($this->context !== null) {
            $this->context->getLogger()->info('Reports extension shutdown');
        }

        $this->authorityService = null;
        $this->context = null;
    }
}
