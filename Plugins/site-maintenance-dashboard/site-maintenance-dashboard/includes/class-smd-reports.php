<?php
if (!defined('ABSPATH')) exit;

class SMD_Reports {
    public static function init() {}

    public static function output_monthly_report($client_id, $ym, $format='html') {
        $client = SMD_DB::get_client($client_id);
        if (!$client) wp_die('Client notsyed found.');

        $plan = $client['plan_id'] ? SMD_DB::get_plan((int)$client['plan_id']) : null;
        $logs = self::get_logs_for_month($client_id, $ym);

        $mins_used = SMD_DB::month_minutes($client_id, $ym);
        $hours_used = round($mins_used / 60, 2);
        $hours_included = $plan ? floatval($plan['monthly_hours']) : 0;
        $counts = SMD_DB::month_counts($client_id, $ym);

        if ($format === 'csv') {
            self::output_csv($client, $plan, $ym, $logs, $hours_used);
            return;
        }

        // html (print-ready)
        self::output_html($client, $plan, $ym, $logs, $hours_used, $hours_included, $counts);
    }

    private static function get_logs_for_month($client_id, $ym) {
        global $wpdb;
        $t = SMD_DB::tables();
        $start = $ym . '-01';
        $end = date('Y-m-d', strtotime($start . ' +1 month'));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t['logs']} WHERE client_id=%d AND task_date >= %s AND task_date < %s ORDER BY task_date DESC, id DESC",
            (int)$client_id, $start, $end
        ), ARRAY_A);
    }

    private static function output_csv($client, $plan, $ym, $logs, $hours_used) {
        $filename = 'maintenance-report-' . $ym . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Report month', $ym]);
        fputcsv($out, ['Site', $client['site_name']]);
        fputcsv($out, ['Site URL', $client['site_url']]);
        fputcsv($out, ['Plan', $plan['name'] ?? '']);
        fputcsv($out, ['Hours used', $hours_used]);
        fputcsv($out, []);
        fputcsv($out, ['Date','Category','Summary','Result','Minutes','Automated']);
        foreach ($logs as $l) {
            fputcsv($out, [
                $l['task_date'],
                $l['category'],
                $l['task_summary'],
                $l['result'],
                $l['minutes'],
                $l['is_automated'] ? 'Yes' : 'No'
            ]);
        }
        fclose($out);
    }

    private static function output_html($client, $plan, $ym, $logs, $hours_used, $hours_included, $counts) {
        $title = 'Maintenance Report — ' . $ym;
        $site = esc_html($client['site_name'] ?: 'Website');
        $site_url = $client['site_url'] ? esc_url($client['site_url']) : '';
        $plan_name = esc_html($plan['name'] ?? '—');

        $month_label = date_i18n('F Y', strtotime($ym.'-01'));
        $used = esc_html($hours_used);
        $included = esc_html($hours_included);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>'.esc_html($title).'</title>';
        echo '<style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;margin:24px;color:#111}
            .wrap{max-width:980px;margin:0 auto}
            .top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
            h1{margin:0 0 8px 0;font-size:24px}
            .muted{color:#555;font-size:13px}
            .card{border:1px solid #e7e7e7;border-radius:14px;padding:16px;margin:14px 0}
            .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
            .stat{font-size:22px;font-weight:700}
            table{width:100%;border-collapse:collapse;margin-top:10px}
            th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;vertical-align:top}
            th{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#444}
            @media print{.noprint{display:none} body{margin:0}}
            @media (max-width:800px){.grid{grid-template-columns:1fr}}
        </style></head><body><div class="wrap">';
        echo '<div class="top"><div>';
        echo '<h1>'.esc_html($site).' — Maintenance Report</h1>';
        echo '<div class="muted">'.esc_html($month_label).' • Plan: '.$plan_name.'</div>';
        if ($site_url) echo '<div class="muted">Site: '.$site_url.'</div>';
        echo '</div>';
        echo '<div class="noprint"><button onclick="window.print()">Print / Save as PDF</button></div>';
        echo '</div>';

        echo '<div class="card"><div class="grid">';
        echo '<div><div class="muted">Total tasks</div><div class="stat">'.esc_html($counts['total_tasks']).'</div></div>';
        echo '<div><div class="muted">Backups</div><div class="stat">'.esc_html($counts['backup_tasks']).'</div></div>';
        echo '<div><div class="muted">Security</div><div class="stat">'.esc_html($counts['security_tasks']).'</div></div>';
        echo '</div></div>';

        echo '<div class="card"><div class="grid">';
        echo '<div><div class="muted">Hours used</div><div class="stat">'.$used.'h</div></div>';
        echo '<div><div class="muted">Hours included</div><div class="stat">'.$included.'h</div></div>';
        echo '<div><div class="muted">Automated tasks</div><div class="stat">'.esc_html($counts['automated_tasks']).'</div></div>';
        echo '</div><div class="muted" style="margin-top:10px">Manual work is time-tracked. Automated tasks show 0 hours.</div></div>';

        echo '<div class="card"><h2 style="margin:0 0 8px 0;font-size:16px">Detailed work log</h2>';
        echo '<table><thead><tr><th>Date</th><th>Task</th><th>Result</th><th>Time</th></tr></thead><tbody>';
        if (!$logs) {
            echo '<tr><td colspan="4" class="muted">No entries for this month.</td></tr>';
        } else {
            foreach ($logs as $l) {
                $time = $l['is_automated'] ? 'Automated' : (round(((int)$l['minutes'])/60,2).'h');
                echo '<tr>';
                echo '<td>'.esc_html(date_i18n('M j, Y', strtotime($l['task_date']))).'</td>';
                echo '<td><strong>'.esc_html($l['category']).'</strong><div class="muted">'.esc_html($l['task_summary']).'</div></td>';
                echo '<td>'.esc_html(ucfirst($l['result'])).'</td>';
                echo '<td>'.esc_html($time).'</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';

        echo '<div class="muted">Generated on '.esc_html(date_i18n('M j, Y H:i')).'</div>';
        echo '</div></body></html>';
    }
}
