<?php decorate_with('layout_2col'); ?>

<?php
  // Make sure $dateOf is always defined to avoid PHP notices
  if (!isset($dateOf)) {
    $dateOf = $sf_request->getParameter('dateOf', 'both');
  }
?>

<?php slot('title'); ?>
  <h1 class="multiline">
    <?php echo image_tag('/images/icons-large/icon-new.png', ['width' => '42', 'height' => '42']); ?>
    <?php echo __('Browse Authority Record/Actor Report'); ?>
  </h1>
<?php end_slot(); ?>

<?php // Compact table styling for this report only ?>
<?php slot('styles'); ?>
<style>
  /* Compact table cells */
  .authority-report-table table.table-sm td,
  .authority-report-table table.table-sm th {
    padding: .25rem .5rem;
    font-size: .8rem;
    vertical-align: top;
  }

  /* Column toggle labels + make checkboxes always visible */
  .authority-report-table .column-toggles label {
    margin-right: .5rem;
    white-space: nowrap;
    font-size: .8rem;
  }

  .authority-report-table .authority-report-col-toggle {
    display: inline-block !important;
    margin-right: .15rem;
  }

  /* Narrow date fields in sidebar */
  .authority-report-sidebar .authority-report-date {
    max-width: 180px; /* physical box width */
    width: 100%;
  }

  /* Extra safety: only shrink date fields on this report */
  body.reports.reportAuthorityRecord #sidebar input[type="date"] {
    max-width: 180px; /* you can drop to e.g. 150px if you want even smaller */
  }

  /* Toolbar buttons under search */
  .authority-report-toolbar {
    margin-top: .5rem;
  }

  .authority-report-toolbar .btn {
    margin-right: .25rem;
    margin-top: .25rem;
  }

  /* Fullscreen mode: hide sidebar, let main take full width */
  body.authority-report-fullscreen #wrapper > .row > #sidebar {
    display: none !important;
  }

  body.authority-report-fullscreen #wrapper > .row > #main-column {
    flex: 0 0 100% !important;
    max-width: 100% !important;
  }

  /* Let the table breathe in fullscreen */
  body.authority-report-fullscreen .authority-report-table {
    max-height: calc(100vh - 220px);
  }
</style>

<?php end_slot(); ?>

<?php slot('sidebar'); ?>

<?php echo $form->renderGlobalErrors(); ?>

<section class="sidebar-widget authority-report-sidebar">

  <div class="mb-2">
    <?php echo link_to(
        __('Back to reports'),
        ['module' => 'reports', 'action' => 'reportSelect'],
        ['class' => 'btn btn-sm btn-secondary', 'title' => __('Back to reports')]
    ); ?>
  </div>

  <h4 class="h6 mb-3"><?php echo __('Filter options'); ?></h4>

  <div>
    <?php echo $form->renderFormTag(
        url_for(['module' => 'reports', 'action' => 'reportAuthorityRecord']),
        ['method' => 'get', 'class' => 'form']
    ); ?>

      <?php echo $form->renderHiddenFields(); ?>

      <div id="divTypeOfReport" style="display:none">
        <?php echo $form->className->label('Types of Reports')->renderRow(); ?>
      </div>

      <div class="mb-2">
        <?php echo render_field(
            $form->dateStart->label(__('Date Start')),
            null,
            [
                'type'  => 'date',
                'class' => 'form-control form-control-sm authority-report-date',
            ]
        ); ?>
      </div>

      <div class="mb-2">
        <?php echo render_field(
            $form->dateEnd->label(__('Date End')),
            null,
            [
                'type'  => 'date',
                'class' => 'form-control form-control-sm authority-report-date',
            ]
        ); ?>
      </div>

      <div class="mb-3">
        <label class="form-label d-block"><?php echo __('Date of'); ?></label>
        <div class="small">
          <?php echo $form['dateOf']->render(); ?>
        </div>
      </div>

      <button type="submit" class="btn btn-sm btn-primary">
        <?php echo __('Search'); ?>
      </button>

      <!-- Toolbar-style buttons under search -->
      <div class="authority-report-toolbar">
        <div class="btn-group" role="group" aria-label="<?php echo __('Report tools'); ?>">
          <button
            type="button"
            id="authority-report-fullscreen-btn"
            class="btn btn-sm btn-outline-secondary"
            title="<?php echo __('Toggle fullscreen'); ?>">
            <i class="fas fa-expand" aria-hidden="true"></i>
          </button>

          <button
            type="button"
            id="authority-report-export-btn"
            class="btn btn-sm btn-outline-secondary"
            title="<?php echo __('Export visible columns to CSV'); ?>">
            <i class="fas fa-file-csv" aria-hidden="true"></i>
          </button>
        </div>
      </div>

    </form>
  </div>
</section>

<?php end_slot(); ?>

<?php slot('content'); ?>

<?php
  // Column keys + labels for toggles
  $columnConfig = [
      'authorized_name'        => __('Authorized Form Of Name'),
      'dates'                  => __('Dates Of Existence'),
      'history'                => __('History'),
      'places'                 => __('Places'),
      'legal_status'           => __('Legal Status'),
      'mandates'               => __('Mandates'),
      'internal_structures'    => __('Internal Structures'),
      'general_context'        => __('General Context'),
      'institution'            => __('Institution Responsible'),
      'rules'                  => __('Rules'),
      'sources'                => __('Sources'),
      'revision_history'       => __('Revision History'),
      'corp_ids'               => __('Corporate Body Identifiers'),
      'entity_type'            => __('Entity Type'),
      'description_status'     => __('Description Status'),
      'description_detail'     => __('Description Detail'),
      'description_identifier' => __('Description Identifier'),
      'source_standard'        => __('Source Standard'),
      'timestamp'              => ('CREATED_AT' !== $dateOf) ? __('Updated') : __('Created'),
  ];
?>

<div class="mb-2 small authority-report-table column-toggles">
  <strong><?php echo __('Columns'); ?>:</strong>
  <?php foreach ($columnConfig as $key => $label) { ?>
    <label>
      <input
        type="checkbox"
        class="authority-report-col-toggle"
        data-col-key="<?php echo $key; ?>"
        checked="checked"
      />
      <?php echo $label; ?>
    </label>
  <?php } ?>
</div>

<div class="table-responsive authority-report-table" style="max-height: 450px; overflow: auto;">
  <table class="table table-bordered table-striped table-sm mb-0">
    <thead class="table-light">
      <tr>
        <th class="text-nowrap" data-col-key="authorized_name">
          <?php echo __('Authorized Form Of Name'); ?>
        </th>
        <th class="text-nowrap" data-col-key="dates">
          <?php echo __('Dates Of Existence'); ?>
        </th>
        <th class="text-nowrap" data-col-key="history">
          <?php echo __('History'); ?>
        </th>
        <th class="text-nowrap" data-col-key="places">
          <?php echo __('Places'); ?>
        </th>
        <th class="text-nowrap" data-col-key="legal_status">
          <?php echo __('Legal Status'); ?>
        </th>
        <th class="text-nowrap" data-col-key="mandates">
          <?php echo __('Mandates'); ?>
        </th>
        <th class="text-nowrap" data-col-key="internal_structures">
          <?php echo __('Internal Structures'); ?>
        </th>
        <th class="text-nowrap" data-col-key="general_context">
          <?php echo __('General Context'); ?>
        </th>
        <th class="text-nowrap" data-col-key="institution">
          <?php echo __('Institution Responsible'); ?>
        </th>
        <th class="text-nowrap" data-col-key="rules">
          <?php echo __('Rules'); ?>
        </th>
        <th class="text-nowrap" data-col-key="sources">
          <?php echo __('Sources'); ?>
        </th>
        <th class="text-nowrap" data-col-key="revision_history">
          <?php echo __('Revision History'); ?>
        </th>
        <th class="text-nowrap" data-col-key="corp_ids">
          <?php echo __('Corporate Body Identifiers'); ?>
        </th>
        <th class="text-nowrap" data-col-key="entity_type">
          <?php echo __('Entity Type'); ?>
        </th>
        <th class="text-nowrap" data-col-key="description_status">
          <?php echo __('Description Status'); ?>
        </th>
        <th class="text-nowrap" data-col-key="description_detail">
          <?php echo __('Description Detail'); ?>
        </th>
        <th class="text-nowrap" data-col-key="description_identifier">
          <?php echo __('Description Identifier'); ?>
        </th>
        <th class="text-nowrap" data-col-key="source_standard">
          <?php echo __('Source Standard'); ?>
        </th>

        <?php if ('CREATED_AT' !== $dateOf) { ?>
          <th class="text-nowrap" data-col-key="timestamp">
            <?php echo __('Updated'); ?>
          </th>
        <?php } else { ?>
          <th class="text-nowrap" data-col-key="timestamp">
            <?php echo __('Created'); ?>
          </th>
        <?php } ?>
      </tr>
    </thead>

    <tbody>
      <?php $row = 0; ?>
      <?php foreach ($pager->getResults() as $result) { ?>
        <tr class="<?php echo 0 == ++$row % 2 ? 'even' : 'odd'; ?>">

          <!-- Authorized name -->
          <td class="text-nowrap" data-col-key="authorized_name">
            <?php
              if (isset($result->authorizedFormOfName)) {
                  echo link_to($result->authorizedFormOfName, [$result, 'module' => 'actor']);
              } else {
                  echo '-';
              }
            ?>
          </td>

          <!-- Dates of existence -->
          <td class="text-nowrap" data-col-key="dates">
            <?php echo $result->datesOfExistence ?: '-'; ?>
          </td>

          <!-- History -->
          <td data-col-key="history">
            <?php
              if ($result->history) {
                  $snippet = mb_substr($result->history, 0, 200);
                  if (mb_strlen($result->history) > 200) {
                      $snippet .= '...';
                  }
                  echo nl2br(htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8'));
              } else {
                  echo '-';
              }
            ?>
          </td>

          <!-- Places -->
          <td data-col-key="places">
            <?php echo $result->places ?: '-'; ?>
          </td>

          <!-- Legal status -->
          <td data-col-key="legal_status">
            <?php echo $result->legalStatus ?: '-'; ?>
          </td>

          <!-- Mandates -->
          <td data-col-key="mandates">
            <?php echo $result->mandates ?: '-'; ?>
          </td>

          <!-- Internal structures -->
          <td data-col-key="internal_structures">
            <?php echo $result->internalStructures ?: '-'; ?>
          </td>

          <!-- General context -->
          <td data-col-key="general_context">
            <?php echo $result->generalContext ?: '-'; ?>
          </td>

          <!-- Institution responsible -->
          <td class="text-nowrap" data-col-key="institution">
            <?php echo $result->institutionResponsibleIdentifier ?: '-'; ?>
          </td>

          <!-- Rules -->
          <td data-col-key="rules">
            <?php echo $result->rules ?: '-'; ?>
          </td>

          <!-- Sources -->
          <td data-col-key="sources">
            <?php echo $result->sources ?: '-'; ?>
          </td>

          <!-- Revision history -->
          <td data-col-key="revision_history">
            <?php echo $result->revisionHistory ?: '-'; ?>
          </td>

          <!-- Corporate body identifiers -->
          <td data-col-key="corp_ids">
            <?php echo $result->corporateBodyIdentifiers ?: '-'; ?>
          </td>

          <!-- Entity type -->
          <td class="text-nowrap" data-col-key="entity_type">
            <?php
              if ($result->entityTypeId) {
                  $term = QubitTerm::getById($result->entityTypeId);
                  echo $term ?: '-';
              } else {
                  echo '-';
              }
            ?>
          </td>

          <!-- Description status -->
          <td class="text-nowrap" data-col-key="description_status">
            <?php
              if ($result->descriptionStatusId) {
                  $term = QubitTerm::getById($result->descriptionStatusId);
                  echo $term ?: '-';
              } else {
                  echo '-';
              }
            ?>
          </td>

          <!-- Description detail -->
          <td class="text-nowrap" data-col-key="description_detail">
            <?php
              if ($result->descriptionDetailId) {
                  $term = QubitTerm::getById($result->descriptionDetailId);
                  echo $term ?: '-';
              } else {
                  echo '-';
              }
            ?>
          </td>

          <!-- Description identifier -->
          <td class="text-nowrap" data-col-key="description_identifier">
            <?php echo $result->descriptionIdentifier ?: '-'; ?>
          </td>

          <!-- Source standard -->
          <td data-col-key="source_standard">
            <?php echo $result->sourceStandard ?: '-'; ?>
          </td>

          <!-- Created / Updated -->
          <td class="text-nowrap" data-col-key="timestamp">
            <?php if ('CREATED_AT' !== $dateOf) { ?>
              <?php echo $result->updatedAt; ?>
            <?php } else { ?>
              <?php echo $result->createdAt; ?>
            <?php } ?>
          </td>

        </tr>
      <?php } ?>
    </tbody>
  </table>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    console.log('[AuthorityReport] JS loaded');

    // --- Column visibility with persistence ---
    var STORAGE_KEY = 'atom_authority_report_columns_v3';

    function loadColumnState() {
      try {
        var raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
          console.log('[AuthorityReport] No saved column state, using defaults');
          return {};
        }
        var parsed = JSON.parse(raw);
        console.log('[AuthorityReport] Loaded column state:', parsed);
        return (parsed && typeof parsed === 'object') ? parsed : {};
      } catch (e) {
        console.warn('[AuthorityReport] Failed to load column state', e);
        return {};
      }
    }

    function saveColumnState(state) {
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        console.log('[AuthorityReport] Saved column state:', state);
      } catch (e) {
        console.warn('[AuthorityReport] Failed to save column state', e);
      }
    }

    // Only affect table header + cells, NEVER the checkboxes
    function setColumnVisibility(key, visible) {
      var selector =
        '.authority-report-table table thead th[data-col-key="' + key + '"], ' +
        '.authority-report-table table tbody td[data-col-key="' + key + '"]';

      var nodes = document.querySelectorAll(selector);

      nodes.forEach(function (el) {
        if (visible) {
          el.style.display = '';
          el.classList.remove('d-none');
        } else {
          el.style.display = 'none';
          el.classList.add('d-none');
        }
      });
    }

    var state = loadColumnState();

    // Default visible columns
    var defaultVisible = {
      authorized_name:        true,
      dates:                  true,
      history:                false,
      places:                 false,
      legal_status:           false,
      mandates:               false,
      internal_structures:    false,
      general_context:        false,
      institution:            true,
      rules:                  false,
      sources:                false,
      revision_history:       false,
      corp_ids:               false,
      entity_type:            true,
      description_status:     true,
      description_detail:     false,
      description_identifier: true,
      source_standard:        false,
      timestamp:              true
    };

    // Merge defaults into stored state
    Object.keys(defaultVisible).forEach(function (key) {
      if (typeof state[key] === 'undefined') {
        state[key] = defaultVisible[key];
      }
    });

    var checkboxes = document.querySelectorAll('.authority-report-col-toggle');

    checkboxes.forEach(function (cb) {
      var key = cb.getAttribute('data-col-key');
      if (!key) {
        return;
      }

      // Sync checkbox with state
      cb.checked = !!state[key];

      // Apply visibility to table columns
      setColumnVisibility(key, cb.checked);

      // Toggle on click
      cb.addEventListener('change', function () {
        var visible = cb.checked;
        state[key] = visible;
        console.log('[AuthorityReport] Column toggled:', key, '=>', visible);
        setColumnVisibility(key, visible);
        saveColumnState(state);
      });
    });

    // Safety: if somehow all columns are hidden, restore defaults
    if (!Object.values(state).some(function (v) { return !!v; })) {
      console.warn('[AuthorityReport] All columns hidden, restoring defaults');
      Object.keys(defaultVisible).forEach(function (key) {
        state[key] = defaultVisible[key];

        var cb = document.querySelector(
          '.authority-report-col-toggle[data-col-key="' + key + '"]'
        );
        if (cb) {
          cb.checked = state[key];
        }

        setColumnVisibility(key, state[key]);
      });
      saveColumnState(state);
    }

    // --- Export table to CSV (only visible columns) ---
    function exportAuthorityReportCsv() {
      var table = document.querySelector('.authority-report-table table');
      if (!table) {
        console.warn('[AuthorityReport] Table not found for CSV export');
        return;
      }

      console.log('[AuthorityReport] Starting CSV export');

      var rows = Array.from(table.querySelectorAll('tr'));
      var csv = [];

      rows.forEach(function (row) {
        var cells = Array.from(row.querySelectorAll('th, td'));

        var rowData = cells
          .filter(function (cell) {
            // skip hidden columns
            var styleDisplay = window.getComputedStyle(cell).display;
            var keep = styleDisplay !== 'none' && !cell.classList.contains('d-none');
            return keep;
          })
          .map(function (cell) {
            var text = cell.innerText || cell.textContent || '';
            text = text.replace(/\s+/g, ' ').trim();
            if (text.indexOf('"') !== -1 || text.indexOf(',') !== -1 || text.indexOf('\n') !== -1) {
              text = '"' + text.replace(/"/g, '""') + '"';
            }
            return text;
          });

        csv.push(rowData.join(','));
      });

      var csvContent = csv.join('\n');
      console.log('[AuthorityReport] CSV created, length:', csvContent.length);

      var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      var url = URL.createObjectURL(blob);

      var link = document.createElement('a');
      link.href = url;
      link.download = 'authority_record_report.csv';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      URL.revokeObjectURL(url);

      console.log('[AuthorityReport] CSV download triggered');
    }

    // --- Wire up Export button with debug ---
    var exportBtn = document.getElementById('authority-report-export-btn');
    if (exportBtn) {
      console.log('[AuthorityReport] Export button found');
      exportBtn.addEventListener('click', function (e) {
        e.preventDefault();
        console.log('[AuthorityReport] Export button clicked');
        exportAuthorityReportCsv();
      });
    } else {
      console.warn('[AuthorityReport] Export button NOT found');
    }

    // --- Fullscreen toggle with debug ---
    var fullscreenBtn = document.getElementById('authority-report-fullscreen-btn');
    if (fullscreenBtn) {
      console.log('[AuthorityReport] Fullscreen button found');
      fullscreenBtn.addEventListener('click', function (e) {
        e.preventDefault();
        console.log('[AuthorityReport] Fullscreen button clicked');
        document.body.classList.toggle('authority-report-fullscreen');
        console.log('[AuthorityReport] Body class now:', document.body.className);
      });
    } else {
      console.warn('[AuthorityReport] Fullscreen button NOT found');
    }
  });
</script>


<?php end_slot(); ?>

<?php slot('after-content'); ?>
  <?php echo get_partial('default/pager', ['pager' => $pager]); ?>
<?php end_slot(); ?>

