<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Display a list of recently updated authority records.
 *
 * Legacy Symfony 1.4 action, tolerant of the new extension framework:
 * - Tries to initialise atom-extensions, but does NOT hard-fail if it fails.
 * - Always falls back to the original Propel-based query (doSearch()).
 */
class reportsReportAuthorityRecordAction extends sfAction
{
    /** @var string[] */
    public static $NAMES = [
        'className',
        'dateStart',
        'dateEnd',
        'dateOf',
        'limit',
        'authorityRecords',
    ];

    public function execute($request)
    {
        /** @var sfWebRequest $request */

        // Check authorization
        $user = $this->context->user;

        if (
            !$user->isAdministrator()
            && !$user->isSuperUser()
            && !$user->isAuditUser()
        ) {
            QubitAcl::forwardUnauthorized();
        }

        // --- Try to initialise the extension framework (non-fatal) ---
        if (function_exists('initializeExtensionFramework')) {
            try {
                if (
                    !isset($GLOBALS['atom_extension_manager'])
                    || !$GLOBALS['atom_extension_manager']
                    || !$GLOBALS['atom_extension_manager'] instanceof \AtomExtensions\ExtensionManager
                ) {
                    $manager = initializeExtensionFramework();

                    if ($manager instanceof \AtomExtensions\ExtensionManager) {
                        $GLOBALS['atom_extension_manager'] = $manager;
                    }
                }
            } catch (Throwable $e) {
                // Log, but DO NOT break the report.
                error_log(
                    'AuthorityRecord report: extension framework init failed: '
                    . $e->getMessage()
                );
            }
        }

        // --- Original Symfony form handling / default values ---
        $this->form = new sfForm([], [], false);
        $this->form->getValidatorSchema()->setOption('allow_extra_fields', true);

        foreach (self::$NAMES as $name) {
            $this->addField($name);
        }

        $defaults = [
			'className'          => 'QubitActor',
			'dateStart'          => '',
			'dateEnd'            => '',
			'dateOf'             => 'CREATED_AT',
			'publicationStatus'  => 'all',
			'limit'              => '10',
			'sort'               => 'updatedDown',
		];

        $params = array_merge(
			$defaults,
			$request->getRequestParameters(),
			$request->getGetParameters()
		);


        $this->form->bind($params);

        // Always run the search so $pager is available to the template
        $this->doSearch($request);
    }

    /**
     * Original Propel-based query logic, with minor PHP 8 clean-up.
     */
    protected function doSearch(sfWebRequest $request): void
    {
        $criteria = new Criteria();

        $this->sort = $request->getParameter('sort', 'updatedDown');

        // Avoid cross join with QubitObject
        $criteria->addJoin(QubitActor::ID, QubitObject::ID);

        $nameColumn = 'authorized_form_of_name';
        $this->nameColumnDisplay = 'Name';

        $criteria = QubitActor::addGetOnlyActorsCriteria($criteria);
        $criteria->add(QubitActor::PARENT_ID, null, Criteria::ISNOTNULL);

        // ------------------------
        // Date range: end date
        // ------------------------
        $dateEndValue = $this->form->getValue('dateEnd');
		$dateEnd = null;

		if ($dateEndValue !== null && $dateEndValue !== '') {
			$dateEnd = $this->parseDateEnd($dateEndValue);
		}


        // ------------------------
        // Date range: start date
        // ------------------------
        $dateOf = $this->form->getValue('dateOf');

        switch ($dateOf) {
            case 'CREATED_AT':
            case 'UPDATED_AT':
                $dateStartValue = $this->form->getValue('dateStart');

                if ($dateStartValue !== null && $dateStartValue !== '') {
                    $startDate = $this->parseDateStart($dateStartValue);

                    $criteria->addAnd(
                        constant('QubitObject::' . $dateOf),
                        $startDate,
                        Criteria::GREATER_EQUAL
                    );
                }

                if (null !== $dateEnd) {
                    $criteria->addAnd(
                        constant('QubitObject::' . $dateOf),
                        $dateEnd,
                        Criteria::LESS_EQUAL
                    );
                }

                break;

            default:
                // "both": apply to both CREATED_AT and UPDATED_AT
                $dateStartValue = $this->form->getValue('dateStart');

                if ($dateStartValue !== null && $dateStartValue !== '') {
                    $startDate = $this->parseDateStart($dateStartValue);

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

                if (null !== $dateEnd) {
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

                break;
        }

        // ------------------------
        // Sort criteria
        // ------------------------
        switch ($this->sort) {
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

        // Fallback criteria for name when sorting by name
        if ('nameDown' === $this->sort || 'nameUp' === $this->sort) {
            $criteria = QubitCultureFallback::addFallbackCriteria(
                $criteria,
                $this->form->getValue('className')
            );
        }

        // ------------------------
        // Pager
        // ------------------------
        $this->pager = new QubitPager('QubitActor');
        $this->pager->setCriteria($criteria);
        $this->pager->setMaxPerPage((int) $this->form->getValue('limit'));
        $this->pager->setPage($request->getParameter('page', 1));
    }

    /**
     * Add dynamic form fields (unchanged from original logic).
     */
    protected function addField(string $name): void
    {
        switch ($name) {
            case 'className':
                $choices = [
                    'QubitActor' => 'Authority Record',
                ];

                $this->form->setValidator(
                    $name,
                    new sfValidatorString()
                );
                $this->form->setWidget(
                    $name,
                    new sfWidgetFormSelect(['choices' => $choices])
                );

                break;

            case 'dateStart':
                $this->form->setValidator('dateStart', new sfValidatorString());
                $this->form->setWidget('dateStart', new sfWidgetFormInput());

                break;

            case 'dateEnd':
                $this->form->setValidator('dateEnd', new sfValidatorString());
                $this->form->setWidget('dateEnd', new sfWidgetFormInput());

                break;

            case 'dateOf':
                $choices = [
                    'CREATED_AT' => $this->context->i18n->__('Creation'),
                    'UPDATED_AT' => $this->context->i18n->__('Revision'),
                    'both'       => $this->context->i18n->__('Both'),
                ];

                $this->form->setValidator(
                    $name,
                    new sfValidatorChoice(['choices' => array_keys($choices)])
                );
                $this->form->setWidget(
                    $name,
                    new arWidgetFormSelectRadio([
                        'choices' => $choices,
                        'class'   => 'radio inline',
                    ])
                );

                break;

            default:
                // Other fields from $NAMES are currently not used or have default handling.
                break;
        }
    }

    /**
     * Helper to parse dateStart into Y-m-d 00:00:00.
     * Accepts either "YYYY-MM-DD" (HTML5 date input) or "DD/MM/YYYY".
     */
    protected function parseDateStart(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            return '2020-01-01 00:00:00';
        }

        // Case 1: HTML5 date input: YYYY-MM-DD
        if (strpos($input, '-') !== false) {
            $parts = explode('-', $input);

            if (count($parts) === 3) {
                [$year, $month, $day] = $parts;
                $year = (int) $year;
                $month = (int) $month;
                $day = (int) $day;

                if (checkdate($month, $day, $year)) {
                    $dt = date_create(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));

                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        // Case 2: legacy format DD/MM/YYYY
        if (strpos($input, '/') !== false) {
            $parts = explode('/', $input);

            if (count($parts) === 3) {
                [$day, $month, $year] = $parts;
                $day = (int) $day;
                $month = (int) $month;
                $year = (int) $year;

                if (checkdate($month, $day, $year)) {
                    $dt = date_create(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));

                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        // Fallback default if parsing fails
        return '2020-01-01 00:00:00';
    }

    /**
     * Helper to parse dateEnd into Y-m-d 23:59:59.
     * Accepts either "YYYY-MM-DD" or "DD/MM/YYYY".
     */
    protected function parseDateEnd(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            return date('Y-m-d 23:59:59');
        }

        // Case 1: HTML5 date input: YYYY-MM-DD
        if (strpos($input, '-') !== false) {
            $parts = explode('-', $input);

            if (count($parts) === 3) {
                [$year, $month, $day] = $parts;
                $year = (int) $year;
                $month = (int) $month;
                $day = (int) $day;

                if (checkdate($month, $day, $year)) {
                    $dt = date_create(sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $day));

                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        // Case 2: legacy format DD/MM/YYYY
        if (strpos($input, '/') !== false) {
            $parts = explode('/', $input);

            if (count($parts) === 3) {
                [$day, $month, $year] = $parts;
                $day = (int) $day;
                $month = (int) $month;
                $year = (int) $year;

                if (checkdate($month, $day, $year)) {
                    $dt = date_create(sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $day));

                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        // Fallback: today 23:59:59
        return date('Y-m-d 23:59:59');
    }
}
