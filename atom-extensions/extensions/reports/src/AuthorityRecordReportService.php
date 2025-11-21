<?php

declare(strict_types=1);

namespace AtomExtensions\Reports;

use AtomExtensions\Contracts\DatabaseInterface;
use Criteria;
use Psr\Log\LoggerInterface;
use QubitActor;
use QubitCultureFallback;
use QubitObject;
use QubitPager;

final class AuthorityRecordReportService
{
    private const DEFAULT_FILTER = [
        'className'         => 'QubitActor',
        'dateStart'         => null,
        'dateEnd'           => null,
        'dateOf'            => 'CREATED_AT',
        'publicationStatus' => 'all',
        'limit'             => 10,
        'sort'              => 'updatedDown',
        'page'              => 1,
    ];

    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly LoggerInterface $logger
    ) {
    }

    public function search(ReportFilter $filter): AuthorityRecordReportResult
    {
        $values = \array_merge(self::DEFAULT_FILTER, $filter->all());

        $sort = (string) $values['sort'];
        $dateOf = (string) $values['dateOf'];

        $criteria = new Criteria();

        // Avoid cross join between QubitActor and QubitObject
        $criteria->addJoin(QubitActor::ID, QubitObject::ID);

        $nameColumn = 'authorized_form_of_name';

        $criteria = QubitActor::addGetOnlyActorsCriteria($criteria);
        $criteria->add(QubitActor::PARENT_ID, null, Criteria::ISNOTNULL);

        $dateEnd = $this->buildEndDate($values['dateEnd']);

        switch ($dateOf) {
            case 'CREATED_AT':
            case 'UPDATED_AT':
                $this->applySingleDateFieldRange($criteria, $values['dateStart'], $dateEnd, $dateOf);
                break;

            default:
                $this->applyBothDatesRange($criteria, $values['dateStart'], $dateEnd);
                $this->applySorting($criteria, $sort, $nameColumn, $values['className']);
                break;
        }

        $pager = new QubitPager('QubitActor');
        $pager->setCriteria($criteria);
        $pager->setMaxPerPage((int) $values['limit']);
        $pager->setPage((int) $values['page']);

        $this->logger->debug('Authority record report generated', [
            'dateOf' => $dateOf,
            'sort'   => $sort,
            'limit'  => (int) $values['limit'],
            'page'   => (int) $values['page'],
        ]);

        return new AuthorityRecordReportResult(
            $pager,
            $sort,
            $dateOf,
            $values['dateStart'] !== null ? (string) $values['dateStart'] : null,
            $values['dateEnd'] !== null ? (string) $values['dateEnd'] : null
        );
    }

    private function buildEndDate(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $day = substr($raw, 0, strpos($raw, '/'));
        $rest = substr($raw, strpos($raw, '/') + 1);
        $month = substr($rest, 0, strpos($rest, '/'));
        $year = substr($rest, strpos($rest, '/') + 1, 4);

        if ((int) $month < 10) {
            $month = '0' . $month;
        }

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return date('Y-m-d 23:59:59');
        }

        $date = date_create($year . '-' . $month . '-' . $day . ' 23.59.59');

        return $date !== false ? date_format($date, 'Y-m-d H:i:s') : null;
    }

    private function applySingleDateFieldRange(
        Criteria $criteria,
        ?string $dateStart,
        ?string $dateEnd,
        string $field
    ): void {
        if ($dateStart !== null && $dateStart !== '') {
            $day = substr($dateStart, 0, strpos($dateStart, '/'));
            $rest = substr($dateStart, strpos($dateStart, '/') + 1);
            $month = substr($rest, 0, strpos($rest, '/'));
            $year = substr($rest, strpos($rest, '/') + 1, 4);

            if ((int) $month < 10) {
                $month = '0' . $month;
            }

            if (checkdate((int) $month, (int) $day, (int) $year)) {
                $tmp = date_create($year . '-' . $month . '-' . $day . ' 00.00.00');
                $startDate = date_format($tmp, 'Y-m-d H:i:s');
            } else {
                $startDate = date('2020-01-01 23.59.59');
            }

            $criteria->addAnd(
                constant(QubitObject::class . '::' . $field),
                $startDate,
                Criteria::GREATER_EQUAL
            );
        }

        if ($dateEnd !== null) {
            $criteria->addAnd(
                constant(QubitObject::class . '::' . $field),
                $dateEnd,
                Criteria::LESS_EQUAL
            );
        }
    }

    private function applyBothDatesRange(
        Criteria $criteria,
        ?string $dateStart,
        ?string $dateEnd
    ): void {
        if ($dateStart !== null && $dateStart !== '') {
            $day = substr($dateStart, 0, strpos($dateStart, '/'));
            $rest = substr($dateStart, strpos($dateStart, '/') + 1);
            $month = substr($rest, 0, strpos($rest, '/'));
            $year = substr($rest, strpos($rest, '/') + 1);

            $tmp = date_create($year . '-' . $month . '-' . $day . ' 00.00.00');
            $startDate = date_format($tmp, 'Y-m-d H:i:s');

            $c1 = $criteria->getNewCriterion(
                QubitObject::CREATED_AT,
                $startDate,
                Criteria::GREATER_EQUAL
            );
            $c2 = $criteria->getNewCriterion(
                QubitObject::UPDATED_AT,
                $startDate,
                Criteria::GREATER_EQUAL
            );
            $c1->addOr($c2);
            $criteria->addAnd($c1);
        }

        if ($dateEnd !== null) {
            $c3 = $criteria->getNewCriterion(
                QubitObject::CREATED_AT,
                $dateEnd,
                Criteria::LESS_EQUAL
            );
            $c4 = $criteria->getNewCriterion(
                QubitObject::UPDATED_AT,
                $dateEnd,
                Criteria::LESS_EQUAL
            );
            $c3->addOr($c4);
            $criteria->addAnd($c3);
        }
    }

    private function applySorting(
        Criteria $criteria,
        string $sort,
        string $nameColumn,
        string $className
    ): void {
        switch ($sort) {
            case 'nameDown':
                $criteria->addDescendingOrderByColumn($nameColumn);
                break;

            case 'nameUp':
                $criteria->addAscendingOrderByColumn($nameColumn);
                break;

            case 'updatedUp':
                $criteria->addAscendingOrderByColumn(QubitObject::UPDATED_AT);
                break;

            case 'updatedDown':
            default:
                $criteria->addDescendingOrderByColumn(QubitObject::UPDATED_AT);
                break;
        }

        if ($sort === 'nameDown' || $sort === 'nameUp') {
            QubitCultureFallback::addFallbackCriteria($criteria, $className);
        }
    }
}
