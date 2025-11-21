<?php

declare(strict_types=1);

namespace AtomExtensions\Reports;

use QubitPager;

final class AuthorityRecordReportResult
{
    public function __construct(
        public readonly QubitPager $pager,
        public readonly string $sort,
        public readonly string $dateOf,
        public readonly ?string $dateStart,
        public readonly ?string $dateEnd
    ) {
    }
}
