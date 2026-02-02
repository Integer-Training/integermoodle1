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

require_once('../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$context = context_system::instance();
require_capability('local/errorlogger:view', $context);

// Handle "clear all" action.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'clearall' && confirm_sesskey()) {
    $DB->delete_records('local_errorlogger_logs');
    redirect(
        new moodle_url('/local/errorlogger/view.php'),
        get_string('logs_cleared', 'local_errorlogger'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_url(new moodle_url('/local/errorlogger/view.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_errorlogger'));
$PAGE->set_heading(get_string('pluginname', 'local_errorlogger'));
$PAGE->set_pagelayout('standard');

// Load DataTables CSS.
$PAGE->requires->css('/local/errorlogger/js/jquery.dataTables.min.css');

// Highlight sidebar nav.
if ($node = $PAGE->navigation->find('local_errorlogger', navigation_node::TYPE_CUSTOM)) {
    $node->make_active();
}

// Summary statistics.
$now = time();
$last24h = $now - (24 * 3600);
$totalcount    = $DB->count_records('local_errorlogger_logs');
$criticalcount = $DB->count_records('local_errorlogger_logs', ['severity' => 1]);
$warningcount  = $DB->count_records('local_errorlogger_logs', ['severity' => 2]);
$noticecount   = $DB->count_records('local_errorlogger_logs', ['severity' => 3]);
$last24count   = $DB->count_records_select('local_errorlogger_logs',
    'timecreated > :since', ['since' => $last24h]);

$templatecontext = [
    'total_count'    => $totalcount,
    'critical_count' => $criticalcount,
    'warning_count'  => $warningcount,
    'notice_count'   => $noticecount,
    'last24_count'   => $last24count,
    'ajax_url'       => (new moodle_url('/local/errorlogger/ajax.php'))->out(false),
    'detail_url'     => (new moodle_url('/local/errorlogger/detail.php'))->out(false),
    'clear_url'      => (new moodle_url('/local/errorlogger/view.php',
        ['action' => 'clearall', 'sesskey' => sesskey()]))->out(false),
    'settings_url'   => (new moodle_url('/admin/settings.php',
        ['section' => 'local_errorlogger']))->out(false),
    'sesskey'        => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_errorlogger/view', $templatecontext);
echo $OUTPUT->footer();

// After footer: RequireJS is now loaded by Moodle.
// We use require(['jquery']) to get jQuery via AMD, then dynamically load
// DataTables with AMD detection disabled to prevent RequireJS conflicts.
$ajaxurl   = (new moodle_url('/local/errorlogger/ajax.php'))->out(false);
$detailurl = (new moodle_url('/local/errorlogger/detail.php'))->out(false);
$sk        = sesskey();
$dtjsurl   = (new moodle_url('/local/errorlogger/js/jquery.dataTables.min.js'))->out(false);

echo '<script>
require(["jquery"], function($) {
    // Make jQuery global so DataTables can find it.
    window.jQuery = $;
    window.$ = $;

    // Temporarily remove AMD define so DataTables registers as a plain jQuery plugin
    // instead of trying (and failing) to register as an anonymous AMD module.
    var _savedDefine = window.define;
    window.define = undefined;

    var dtScript = document.createElement("script");
    dtScript.src = "' . $dtjsurl . '";
    dtScript.onload = function() {
        // Restore AMD define.
        window.define = _savedDefine;

        // DataTables is now attached to $.fn.DataTable — initialise the table.
        var table = $("#errorlog-table").DataTable({
            processing: true,
            serverSide: true,
            order: [[0, "desc"]],
            pageLength: 25,
            dom: "lfrtip",
            ajax: {
                url: "' . $ajaxurl . '",
                type: "GET",
                data: function(d) {
                    d.sesskey          = "' . $sk . '";
                    d.filter_type      = $("#el-filter-type").val();
                    d.filter_severity  = $("#el-filter-severity").val();
                    d.filter_date_from = $("#el-filter-datefrom").val();
                    d.filter_date_to   = $("#el-filter-dateto").val();
                    d.order_column     = ["timecreated","severity","type","message","","component",""][d.order[0].column] || "timecreated";
                    d.order_dir        = d.order[0].dir;
                }
            },
            columns: [
                { data: "time", width: "130px" },
                {
                    data: "severity",
                    width: "80px",
                    render: function(data, type, row) {
                        return "<span class=\"el-pill el-pill--" + row.sev_class + "\">" + data + "</span>";
                    }
                },
                { data: "type", width: "80px" },
                { data: "message", className: "el-msg-cell" },
                { data: "explanation", orderable: false, className: "el-explain-cell", width: "240px" },
                { data: "component", width: "100px" },
                { data: "user", width: "90px" },
                {
                    data: "id",
                    orderable: false,
                    width: "46px",
                    className: "text-center",
                    render: function(data) {
                        return "<a href=\"' . $detailurl . '?id=" + data + "\" class=\"btn btn-sm btn-outline-primary\" title=\"View detail\">" +
                               "<i class=\"fa fa-eye\"></i></a>";
                    }
                }
            ],
            language: {
                emptyTable: "No error logs found.",
                processing: "<div class=\"spinner-border spinner-border-sm text-primary\" role=\"status\"></div> Loading..."
            }
        });

        $("#el-apply-filters").on("click", function() {
            table.ajax.reload();
        });

        $("#el-clear-all").on("click", function() {
            var msg = $(this).data("confirm");
            var url = $(this).data("url");
            if (confirm(msg)) {
                window.location.href = url;
            }
        });
    };
    dtScript.onerror = function() {
        console.error("ErrorLogger: Failed to load DataTables JS");
    };
    document.head.appendChild(dtScript);
});
</script>';
