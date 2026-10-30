<?php
// includes/report_range.php
// Shared period definitions for the Reports page filter and the export endpoint.

/**
 * Allowed period keys => labels.
 */
function citifix_report_ranges(): array
{
    return [
        'all'    => 'All Time',
        'day'    => 'Today',
        'week'   => 'This Week',
        '6weeks' => 'Last 6 Weeks',
        'month'  => 'This Month',
    ];
}

/**
 * Start/end unix timestamps (Asia/Manila) for a period key.
 * 'all' returns null bounds (no filtering).
 *
 * @return array{startTs: ?int, endTs: ?int, period: string}
 */
function citifix_range_bounds(string $range): array
{
    $tz  = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);

    switch ($range) {
        case 'day':
            $start = (clone $now)->setTime(0, 0, 0);
            $end   = (clone $now)->setTime(23, 59, 59);
            break;

        case 'week':
            $start = (new DateTime('monday this week', $tz))->setTime(0, 0, 0);
            $end   = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
            break;

        case '6weeks':
            $start = (new DateTime('monday this week', $tz))->setTime(0, 0, 0);
            $start->modify('-5 weeks');
            $end   = (clone $now)->setTime(23, 59, 59);
            break;

        case 'month':
            $start = (new DateTime('first day of this month', $tz))->setTime(0, 0, 0);
            $end   = (new DateTime('last day of this month', $tz))->setTime(23, 59, 59);
            break;

        default: // all
            return ['startTs' => null, 'endTs' => null, 'period' => 'All time'];
    }

    $period = $range === 'day'
        ? $start->format('F j, Y')
        : $start->format('M j, Y') . ' – ' . $end->format('M j, Y');

    return [
        'startTs' => $start->getTimestamp(),
        'endTs'   => $end->getTimestamp(),
        'period'  => $period,
    ];
}