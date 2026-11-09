<?php
/*
 * Module 9: shared helpers for the reports and the audit log (Phase 4, Section 7.4.2, Table 21).
 *
 * - report_dates(): reads ?from=YYYY-MM-DD&to=YYYY-MM-DD, ignores bad dates and swaps
 *   them if "from" is after "to" (same rule as Search).
 * - send_csv(): streams a CSV download. Excel-friendly (UTF-8 BOM) and protected against
 *   CSV/formula injection: a cell that starts with = + - @ (or a tab / carriage return)
 *   is prefixed with ' so Excel shows it as text instead of running it as a formula.
 * - pager_links(): page numbers that keep the current filters.
 */

const STATUS_LABELS = ['Found', 'Claimed', 'Disposed'];
const CLAIM_STATUSES = ['Pending', 'Approved', 'Rejected'];

// audit_log actions (Phase 4 Section 6) in plain words
const AUDIT_LABELS = [
    'ITEM_LOGGED' => 'Item logged', 'ITEM_UPDATED' => 'Item edited', 'ITEM_STATUS_CHANGED' => 'Item status changed',
    'ITEM_DISPOSED' => 'Item disposed', 'ITEM_RELEASED' => 'Item collected by owner',
    'CLAIM_SUBMITTED' => 'Claim submitted', 'CLAIM_APPROVED' => 'Claim approved', 'CLAIM_REJECTED' => 'Claim rejected',
    'CLAIM_PROOF_REMOVED' => 'Proof text removed (privacy)',
];
// user_admin_log actions (Module 8)
const ACCOUNT_LABELS = [
    'USER_CREATED' => 'Account created', 'ROLE_CHANGED' => 'Role changed', 'USER_DEACTIVATED' => 'Deactivated',
    'USER_REACTIVATED' => 'Reactivated', 'PASSWORD_SET_BY_ADMIN' => 'Temporary password set',
    'ACCOUNT_UNLOCKED' => 'Unlocked', 'PASSWORD_CHANGED_BY_USER' => 'Changed own password',
    'USER_ANONYMISED' => 'Personal information deleted', 'DATA_EXPORTED' => 'Downloaded own data',
    'ERASURE_REQUESTED' => 'Asked for deletion', 'ERASURE_REQUEST_DECLINED' => 'Deletion request declined',
];

/** @return array{0:?string,1:?string,2:?string} [from, to, warning] as Y-m-d strings or null */
function report_dates(string $fromRaw, string $toRaw): array
{
    $parse = static function (string $v): ?string {
        if ($v === '') return null;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    };
    $from = $parse($fromRaw);
    $to = $parse($toRaw);
    $warning = null;
    if (($fromRaw !== '' && $from === null) || ($toRaw !== '' && $to === null)) {
        $warning = 'A date was not valid and was ignored.';
    }
    if ($from && $to && $from > $to) {
        [$from, $to] = [$to, $from];
        $warning = '"From" date is after the "To" date, so the dates were swapped.';
    }
    return [$from, $to, $warning];
}

/** End of a "to" day for DATETIME columns: < next day 00:00 */
function day_after(string $ymd): string
{
    return (new DateTimeImmutable($ymd))->modify('+1 day')->format('Y-m-d');
}

function csv_cell(mixed $v): string
{
    $s = $v === null ? '' : (string) $v;
    if ($s !== '' && str_contains("=+-@\t\r", $s[0])) {
        $s = "'" . $s;
    }
    return $s;
}

/**
 * Sends a CSV file and stops the script.
 * @param iterable<array> $rows
 */
function send_csv(string $filename, array $header, iterable $rows): never
{
    // BUG-07: Excel on South African Windows (en-ZA: decimal comma) expects ';' between
    // columns and put a comma file into column A. Default = ';' (opens straight in Excel
    // here); ?sep=comma gives the standard comma CSV for other programs / other regions.
    $sep = ($_GET['sep'] ?? '') === 'comma' ? ',' : ';';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel reads UTF-8 (names with accents)
    fputcsv($out, $header, $sep, '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_cell', array_values($row)), $sep, '"', '');
    }
    fclose($out);
    exit;
}

/** Export buttons: Excel (;) and standard comma CSV, keeping the current filters. */
function csv_buttons(string $page, array $query, int $total): string
{
    if ($total < 1) return '';
    $rows = $total . ' row' . ($total === 1 ? '' : 's');
    return '<div class="export-links"><a class="btn btn-outline" download href="' . e(url($page . '?' . http_build_query($query + ['export' => 'csv']))) . '">Export to Excel (' . $rows . ')</a>'
        . '<a class="small" download href="' . e(url($page . '?' . http_build_query($query + ['export' => 'csv', 'sep' => 'comma']))) . '">Comma-separated CSV</a></div>';
}

function pager_links(string $page, array $query, int $current, int $pages): string
{
    if ($pages <= 1) return '';
    $html = '<nav class="pager" aria-label="Pagination">';
    $show = array_unique(array_filter([1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $pages], fn ($p) => $p >= 1 && $p <= $pages));
    sort($show);
    $prev = 0;
    foreach ($show as $p) {
        if ($prev && $p > $prev + 1) $html .= '<span aria-hidden="true">…</span>';
        $html .= $p === $current
            ? '<span class="on" aria-current="page">' . $p . '</span>'
            : '<a href="' . e(url($page . '?' . http_build_query($query + ['page' => $p]))) . '">' . $p . '</a>';
        $prev = $p;
    }
    return $html . '</nav>';
}

function claim_pill(string $status): string
{
    return '<span class="pill pill-' . strtolower(e($status)) . '">' . e($status) . '</span>';
}
