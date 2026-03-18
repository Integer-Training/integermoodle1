<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Communication Logs — main page.
 *
 * Aggregates emails, SMS, and notification logs into one searchable DataTable.
 *
 * @package   local_commlogs
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/commlogs:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/commlogs/index.php'));
$PAGE->set_title(get_string('commlogs', 'local_commlogs'));
$PAGE->set_heading(get_string('commlogs', 'local_commlogs'));
$PAGE->set_pagelayout('admin');

$dbman = $DB->get_manager();

// 90-day cutoff.
$cutoff = time() - (90 * 86400);

$rows = [];

// ─── 1. Manual Login Emails ──────────────────────────────────────────
if ($dbman->table_exists('local_leaner_email')) {
    $sql = "SELECT le.id,
                   le.userid,
                   le.email_status,
                   le.sentby,
                   CAST(le.timecreated AS SIGNED) AS timecreated,
                   u.firstname   AS rec_first,
                   u.lastname    AS rec_last,
                   u.email       AS rec_email,
                   s.firstname   AS send_first,
                   s.lastname    AS send_last
              FROM {local_leaner_email} le
              JOIN {user} u ON u.id = le.userid
         LEFT JOIN {user} s ON s.id = CAST(le.sentby AS SIGNED)
             WHERE CAST(le.timecreated AS SIGNED) >= :cutoff
          ORDER BY timecreated DESC";
    $records = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

    foreach ($records as $r) {
        $rows[] = [
            'timestamp'         => (int)$r->timecreated,
            'type'              => 'email',
            'category'          => 'Login Email',
            'recipient_name'    => $r->rec_first . ' ' . $r->rec_last,
            'recipient_contact' => $r->rec_email,
            'content_preview'   => 'Manual login credentials email',
            'full_content'      => '<p>Manual login credentials email sent to <strong>' .
                                   s($r->rec_first . ' ' . $r->rec_last) . '</strong> (' . s($r->rec_email) . ').</p>' .
                                   '<p>Status: ' . s($r->email_status) . '</p>',
            'status'            => 'sent',
            'sent_by'           => ($r->send_first) ? $r->send_first . ' ' . $r->send_last : 'Unknown',
        ];
    }
}

// ─── 2. SMS Messages ────────────────────────────────────────────────
if ($dbman->table_exists('local_twiliosms_log')) {
    $sql = "SELECT sl.id,
                   sl.userid,
                   sl.phone,
                   sl.message,
                   sl.status,
                   sl.error_msg,
                   sl.timecreated,
                   u.firstname AS rec_first,
                   u.lastname  AS rec_last,
                   u.email     AS rec_email
              FROM {local_twiliosms_log} sl
              JOIN {user} u ON u.id = sl.userid
             WHERE sl.timecreated >= :cutoff
          ORDER BY sl.timecreated DESC";
    $records = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

    foreach ($records as $r) {
        $preview = shorten_text(strip_tags($r->message), 100);
        $rows[] = [
            'timestamp'         => (int)$r->timecreated,
            'type'              => 'sms',
            'category'          => 'SMS Credentials',
            'recipient_name'    => $r->rec_first . ' ' . $r->rec_last,
            'recipient_contact' => $r->phone,
            'content_preview'   => $preview,
            'full_content'      => '<p>' . s($r->message) . '</p>' .
                                   ($r->error_msg ? '<p class="text-danger"><strong>Error:</strong> ' . s($r->error_msg) . '</p>' : ''),
            'status'            => $r->status,
            'sent_by'           => 'System',
        ];
    }
}

// ─── 3. News Notifications ──────────────────────────────────────────
if ($dbman->table_exists('local_news_notifications') && $dbman->table_exists('local_news')) {
    $sql = "SELECT nn.id,
                   nn.newsid,
                   nn.userid,
                   nn.timesent,
                   nn.status,
                   n.title   AS news_title,
                   n.content AS news_content,
                   u.firstname AS rec_first,
                   u.lastname  AS rec_last,
                   u.email     AS rec_email
              FROM {local_news_notifications} nn
              JOIN {local_news} n ON n.id = nn.newsid
              JOIN {user} u ON u.id = nn.userid
             WHERE nn.timesent >= :cutoff
          ORDER BY nn.timesent DESC";
    $records = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

    foreach ($records as $r) {
        $preview = shorten_text(strip_tags($r->news_content), 100);
        $rows[] = [
            'timestamp'         => (int)$r->timesent,
            'type'              => 'notification',
            'category'          => 'News Alert',
            'recipient_name'    => $r->rec_first . ' ' . $r->rec_last,
            'recipient_contact' => $r->rec_email,
            'content_preview'   => $r->news_title . ' — ' . $preview,
            'full_content'      => '<h5>' . s($r->news_title) . '</h5>' .
                                   format_text($r->news_content, FORMAT_HTML),
            'status'            => $r->status,
            'sent_by'           => 'System',
        ];
    }
}

// ─── 4. Moodle Notifications ────────────────────────────────────────
$sql = "SELECT n.id,
               n.useridfrom,
               n.useridto,
               n.subject,
               n.fullmessagehtml,
               n.eventtype,
               n.timecreated,
               ur.firstname AS rec_first,
               ur.lastname  AS rec_last,
               ur.email     AS rec_email,
               CASE WHEN n.useridfrom > 0 THEN us.firstname ELSE NULL END AS send_first,
               CASE WHEN n.useridfrom > 0 THEN us.lastname  ELSE NULL END AS send_last
          FROM {notifications} n
          JOIN {user} ur ON ur.id = n.useridto
     LEFT JOIN {user} us ON us.id = n.useridfrom AND n.useridfrom > 0
         WHERE n.component IN ('local_learner', 'local_draftfeedback')
           AND n.timecreated >= :cutoff
      ORDER BY n.timecreated DESC";
$records = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

$eventmap = [
    'workbook_graded'    => 'Grading Notification',
    'casestudy_approved' => 'Case Study Approved',
    'casestudy_rejected' => 'Case Study Rejected',
    'draftfeedback'      => 'Draft Feedback',
];

foreach ($records as $r) {
    $category = isset($eventmap[$r->eventtype]) ? $eventmap[$r->eventtype] : 'Notification';
    $preview  = shorten_text(strip_tags($r->fullmessagehtml ?: $r->subject), 100);

    $sentby = 'System';
    if ($r->useridfrom > 0 && $r->send_first) {
        $sentby = $r->send_first . ' ' . $r->send_last;
    }

    $rows[] = [
        'timestamp'         => (int)$r->timecreated,
        'type'              => 'notification',
        'category'          => $category,
        'recipient_name'    => $r->rec_first . ' ' . $r->rec_last,
        'recipient_contact' => $r->rec_email,
        'content_preview'   => $preview,
        'full_content'      => '<h5>' . s($r->subject) . '</h5>' .
                               format_text($r->fullmessagehtml, FORMAT_HTML),
        'status'            => 'sent',
        'sent_by'           => $sentby,
    ];
}

// ─── Sort all rows by timestamp descending ──────────────────────────
usort($rows, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// ─── Add template flags and formatted dates ─────────────────────────
$kpi_total = count($rows);
$kpi_emails = 0;
$kpi_sms = 0;
$kpi_notifs = 0;
$kpi_sent = 0;
$kpi_failed = 0;
$kpi_skipped = 0;

foreach ($rows as &$row) {
    // Type flags.
    $row['is_email']        = ($row['type'] === 'email');
    $row['is_sms']          = ($row['type'] === 'sms');
    $row['is_notification'] = ($row['type'] === 'notification');

    // Status flags.
    $row['is_sent']    = ($row['status'] === 'sent');
    $row['is_failed']  = ($row['status'] === 'failed');
    $row['is_skipped'] = ($row['status'] === 'skipped');

    // Human-readable date.
    $row['datetime'] = userdate($row['timestamp'], '%d %b %Y, %H:%M');

    // Type labels.
    if ($row['is_email']) {
        $row['type_label'] = 'Email';
        $kpi_emails++;
    } else if ($row['is_sms']) {
        $row['type_label'] = 'SMS';
        $kpi_sms++;
    } else {
        $row['type_label'] = 'Notification';
        $kpi_notifs++;
    }

    // Status label.
    $row['status_label'] = ucfirst($row['status']);

    // KPI counts.
    if ($row['is_sent'])    $kpi_sent++;
    if ($row['is_failed'])  $kpi_failed++;
    if ($row['is_skipped']) $kpi_skipped++;
}
unset($row);

// ─── Build template context ─────────────────────────────────────────
$templatecontext = [
    'rows'         => array_values($rows),
    'has_rows'     => !empty($rows),
    'kpi_total'    => $kpi_total,
    'kpi_emails'   => $kpi_emails,
    'kpi_sms'      => $kpi_sms,
    'kpi_notifs'   => $kpi_notifs,
    'kpi_sent'     => $kpi_sent,
    'kpi_failed'   => $kpi_failed,
    'kpi_skipped'  => $kpi_skipped,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_commlogs/logs', $templatecontext);
echo $OUTPUT->footer();
