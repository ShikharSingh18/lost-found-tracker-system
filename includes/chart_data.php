<?php
/*
 * Admin charts (dashboard + reports): builds the config arrays that assets/js/charts.js draws.
 * Only counts and category / status / location names are used - never claimant names,
 * emails or proof-of-ownership text (POPIA data minimisation).
 *
 * A config: ['id', 'title', 'type' (default), 'types' (allowed), 'labels' => [...],
 *            'series' => [['name' => ..., 'data' => [...]]], 'colors' => [per-label colours]]
 */

const CHART_STATUS_COLORS = [
    'Found' => '#157347', 'Claimed' => '#2f5bea', 'Disposed' => '#b3261e',
    'Pending' => '#e0a100', 'Approved' => '#157347', 'Rejected' => '#b3261e',
];

function chart_cfg(string $id, string $title, string $type, array $labels, array $series, array $extra = []): array
{
    return ['id' => $id, 'title' => $title, 'type' => $type, 'labels' => array_values($labels), 'series' => $series] + $extra;
}

/** A card holding one chart; the JSON goes in an escaped data attribute. */
function chart_card(array $cfg, string $class = ''): string
{
    $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<section class="card chart-card ' . e($class) . '"><div class="chart-mount" data-chart="' . e($json) . '">'
        . '<p class="muted" style="margin:0">Charts need JavaScript. The figures are also in the tables on this page.</p></div></section>';
}

/** Doughnut-style config from a [status => count] map (keeps the order of $order). */
function chart_status_cfg(string $id, string $title, array $order, array $counts, string $seriesName): array
{
    return chart_cfg($id, $title, 'doughnut', $order,
        [['name' => $seriesName, 'data' => array_map(fn ($s) => (int) ($counts[$s] ?? 0), $order)]],
        ['types' => ['doughnut', 'pie', 'bar', 'hbar'], 'colors' => array_map(fn ($s) => CHART_STATUS_COLORS[$s] ?? '#6b7a90', $order)]);
}

function chart_month_label(string $ym): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m', $ym);
    return $d ? $d->format('M y') : $ym;
}

/** Dashboard: items logged vs claims submitted, last 12 months. */
function chart_monthly(PDO $pdo): array
{
    $start = (new DateTimeImmutable('first day of this month'))->modify('-11 months');
    $keys = [];
    for ($i = 0; $i < 12; $i++) $keys[] = $start->modify("+$i months")->format('Y-m');
    $count = function (string $table, string $col) use ($pdo, $start): array {   // table/col are constants, not user input
        $stmt = $pdo->prepare("SELECT DATE_FORMAT($col, '%Y-%m') AS m, COUNT(*) AS n FROM $table WHERE $col >= :d GROUP BY m");
        $stmt->execute([':d' => $start->format('Y-m-d')]);
        return array_column($stmt->fetchAll(), 'n', 'm');
    };
    $items = $count('items', 'created_at');
    $claims = $count('claims', 'date_submitted');
    return chart_cfg('activity-month', 'Activity per month (last 12 months)', 'line', array_map('chart_month_label', $keys), [
        ['name' => 'Items logged', 'data' => array_map(fn ($k) => (int) ($items[$k] ?? 0), $keys), 'color' => '#2f5bea'],
        ['name' => 'Claims submitted', 'data' => array_map(fn ($k) => (int) ($claims[$k] ?? 0), $keys), 'color' => '#e0a100'],
    ], ['types' => ['line', 'area', 'bar', 'stacked']]);
}

/**
 * Reports page: charts that follow the current filters.
 * $fromSql / $whereSql / $params are the same fragments the table uses (prepared statements).
 */
function report_charts(PDO $pdo, string $type, string $fromSql, string $whereSql, array $params, array $byStatus): array
{
    $isItems = $type === 'items';
    $charts = [];
    $order = $isItems ? STATUS_LABELS : CLAIM_STATUSES;
    $charts[] = chart_status_cfg('report-status', ($isItems ? 'Items' : 'Claims') . ' by status', $order, $byStatus, $isItems ? 'Items' : 'Claims');

    $group = function (string $expr, string $extra = '') use ($pdo, $fromSql, $whereSql, $params): array {
        $stmt = $pdo->prepare("SELECT $expr AS k, COUNT(*) AS n $fromSql $whereSql GROUP BY k ORDER BY n DESC, k $extra");
        $stmt->execute($params);
        return $stmt->fetchAll();
    };

    $cat = $group('c.category_name');
    $charts[] = chart_cfg('report-category', ($isItems ? 'Items' : 'Claims') . ' by category', 'hbar',
        array_column($cat, 'k'), [['name' => $isItems ? 'Items' : 'Claims', 'data' => array_map('intval', array_column($cat, 'n'))]],
        ['types' => ['hbar', 'bar', 'doughnut', 'pie']]);

    $dateCol = $isItems ? 'i.date_found' : 'cl.date_submitted';
    $stmt = $pdo->prepare("SELECT DATE_FORMAT($dateCol, '%Y-%m') AS k, COUNT(*) AS n $fromSql $whereSql GROUP BY k ORDER BY k");
    $stmt->execute($params);
    $months = array_slice($stmt->fetchAll(), -24);
    $charts[] = chart_cfg('report-time', ($isItems ? 'Items found' : 'Claims submitted') . ' per month', 'bar',
        array_map(fn ($r) => chart_month_label($r['k']), $months), [['name' => $isItems ? 'Items found' : 'Claims submitted', 'data' => array_map(fn ($r) => (int) $r['n'], $months)]],
        ['types' => ['bar', 'line', 'area']]);

    if ($isItems) {
        $stmt = $pdo->prepare("SELECT i.location_found AS k, COUNT(*) AS n $fromSql $whereSql GROUP BY i.location_found ORDER BY n DESC, k LIMIT 8");
        $stmt->execute($params);
        $loc = $stmt->fetchAll();
        $charts[] = chart_cfg('report-location', 'Top locations where items were found', 'hbar',
            array_column($loc, 'k'), [['name' => 'Items', 'data' => array_map('intval', array_column($loc, 'n'))]],
            ['types' => ['hbar', 'bar', 'doughnut', 'pie']]);
    }
    return $charts;
}
