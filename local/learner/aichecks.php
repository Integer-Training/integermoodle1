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
 * AI Check Tool — Upload documents and view AI detection history.
 *
 * @package   local_learner
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

global $DB, $USER, $PAGE, $OUTPUT, $CFG;

// Permission check: admin or teacher.
$is_admin = has_capability('local/learner:view', context_system::instance());
$is_teacher = $DB->record_exists_sql(
    "SELECT 1 FROM {role_assignments} ra
     JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('teacher','editingteacher')
     WHERE ra.userid = ?",
    [$USER->id]
);

if (!$is_admin && !$is_teacher) {
    throw new moodle_exception('nopermission');
}

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/learner/aichecks.php'));
$PAGE->set_context($context);
$PAGE->set_title('AI Check Tool');

$PAGE->requires->jquery();

// External assets.
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo '<link href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" rel="stylesheet">';
echo '<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>';

echo $OUTPUT->header();

// ============================================================
// BUILD HISTORY DATA — UNION of standalone + assignment scans
// ============================================================

$history = [];

// 1. Standalone scans from local_learner_aichecks.
$standalone_where = $is_admin ? '' : 'WHERE ac.userid = :userid';
$standalone_params = $is_admin ? [] : ['userid' => $USER->id];

$standalone_sql = "SELECT ac.id, ac.userid, ac.filename, ac.predicted_class, ac.class_probability,
                          ac.ai_pct, ac.mixed_pct, ac.human_pct, ac.timecreated,
                          u.firstname, u.lastname
                   FROM {local_learner_aichecks} ac
                   JOIN {user} u ON u.id = ac.userid
                   {$standalone_where}
                   ORDER BY ac.timecreated DESC";

if ($DB->get_manager()->table_exists('local_learner_aichecks')) {
    $standalone_records = $DB->get_records_sql($standalone_sql, $standalone_params);
    foreach ($standalone_records as $rec) {
        $cls = strtolower($rec->predicted_class);
        $history[] = [
            'type' => 'standalone',
            'type_label' => 'Standalone',
            'date_raw' => $rec->timecreated,
            'date' => userdate($rec->timecreated, '%d %b %Y %H:%M'),
            'document' => $rec->filename,
            'learner' => $rec->firstname . ' ' . $rec->lastname,
            'predicted_class' => ucfirst($cls),
            'is_human' => ($cls === 'human'),
            'is_ai' => ($cls === 'ai'),
            'is_mixed' => ($cls === 'mixed'),
            'probability' => round($rec->class_probability * 100) . '%',
            'ai_pct' => $rec->ai_pct,
            'mixed_pct' => $rec->mixed_pct,
            'human_pct' => $rec->human_pct,
            'report_url' => (new moodle_url('/local/learner/aicheckreport.php', ['id' => $rec->id]))->out(),
        ];
    }
}

// 2. Assignment scans from plagiarism_gptzero_files.
$gptzero_table_exists = $DB->get_manager()->table_exists('plagiarism_gptzero_files');

if ($gptzero_table_exists) {
    if ($is_admin) {
        $assign_sql = "SELECT pf.id, pf.cm, pf.userid, pf.filename, pf.predicted_class,
                              pf.class_probability, pf.timesubmitted, pf.scanurl,
                              u.firstname, u.lastname,
                              a.name AS assignment_name, c.fullname AS course_name,
                              sub.id AS submission_id
                       FROM {plagiarism_gptzero_files} pf
                       JOIN {user} u ON u.id = pf.userid
                       JOIN {course_modules} cm ON cm.id = pf.cm
                       JOIN {assign} a ON a.id = cm.instance
                       JOIN {course} c ON c.id = a.course
                       LEFT JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = pf.userid AND sub.latest = 1
                       WHERE pf.predicted_class IS NOT NULL AND pf.predicted_class != ''
                       ORDER BY pf.timesubmitted DESC";
        $assign_records = $DB->get_records_sql($assign_sql);
    } else {
        // Tutor: only their learners via group membership.
        $assign_sql = "SELECT pf.id, pf.cm, pf.userid, pf.filename, pf.predicted_class,
                              pf.class_probability, pf.timesubmitted, pf.scanurl,
                              u.firstname, u.lastname,
                              a.name AS assignment_name, c.fullname AS course_name,
                              sub.id AS submission_id
                       FROM {plagiarism_gptzero_files} pf
                       JOIN {user} u ON u.id = pf.userid
                       JOIN {course_modules} cm ON cm.id = pf.cm
                       JOIN {assign} a ON a.id = cm.instance
                       JOIN {course} c ON c.id = a.course
                       LEFT JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = pf.userid AND sub.latest = 1
                       JOIN {groups_members} gm_s ON gm_s.userid = pf.userid
                       JOIN {groups} g ON g.id = gm_s.groupid AND g.courseid = a.course
                       JOIN {groups_members} gm_t ON gm_t.groupid = g.id AND gm_t.userid = :tutorid
                       WHERE pf.predicted_class IS NOT NULL AND pf.predicted_class != ''
                       ORDER BY pf.timesubmitted DESC";
        $assign_records = $DB->get_records_sql($assign_sql, ['tutorid' => $USER->id]);
    }

    foreach ($assign_records as $rec) {
        $cls = strtolower($rec->predicted_class);

        // Try to extract class probabilities from stored JSON.
        $aiPct = 0;
        $mixedPct = 0;
        $humanPct = 0;
        if (!empty($rec->scanurl) && strpos($rec->scanurl, '{') === 0) {
            $stored = json_decode($rec->scanurl, true);
            if (isset($stored['documents'][0]['class_probabilities'])) {
                $cp = $stored['documents'][0]['class_probabilities'];
                $aiPct = round(($cp['ai'] ?? 0) * 100);
                $mixedPct = round(($cp['mixed'] ?? 0) * 100);
                $humanPct = round(($cp['human'] ?? 0) * 100);
            }
        }
        // Fallback estimation.
        if ($aiPct == 0 && $mixedPct == 0 && $humanPct == 0) {
            $prob = round($rec->class_probability * 100);
            if ($cls === 'ai') { $aiPct = $prob; $humanPct = 100 - $prob; }
            else if ($cls === 'human') { $humanPct = $prob; $aiPct = 100 - $prob; }
            else { $mixedPct = $prob; $aiPct = round((100 - $prob) / 2); $humanPct = 100 - $prob - $aiPct; }
        }

        $reportUrl = '';
        if (!empty($rec->submission_id)) {
            $reportUrl = (new moodle_url('/local/learner/aireport.php', ['id' => $rec->submission_id]))->out();
        }

        $history[] = [
            'type' => 'assignment',
            'type_label' => 'Assignment',
            'date_raw' => $rec->timesubmitted,
            'date' => userdate($rec->timesubmitted, '%d %b %Y %H:%M'),
            'document' => $rec->assignment_name . ' (' . $rec->course_name . ')',
            'learner' => $rec->firstname . ' ' . $rec->lastname,
            'predicted_class' => ucfirst($cls),
            'is_human' => ($cls === 'human'),
            'is_ai' => ($cls === 'ai'),
            'is_mixed' => ($cls === 'mixed'),
            'probability' => round($rec->class_probability * 100) . '%',
            'ai_pct' => $aiPct,
            'mixed_pct' => $mixedPct,
            'human_pct' => $humanPct,
            'report_url' => $reportUrl,
        ];
    }
}

// Sort combined history by date descending.
usort($history, function($a, $b) {
    return $b['date_raw'] - $a['date_raw'];
});

$sesskey = sesskey();
$uploadurl = new moodle_url('/local/learner/aicheckupload.php');

// Dashboard back link.
$dashurl = $is_admin
    ? new moodle_url('/local/admindashboard/index.php')
    : new moodle_url('/local/learner/tutordash.php');
?>

<div class="aic-shell" id="ai-checks-page">

    <!-- HEADER -->
    <div class="aic-header">
        <div class="aic-header-inner">
            <div>
                <h1 class="aic-title">AI Check Tool</h1>
                <p class="aic-subtitle">Upload documents to check for AI-generated content</p>
            </div>
            <div class="aic-header-actions">
                <a href="<?php echo $dashurl; ?>" class="aic-hdr-btn">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- UPLOAD PANEL -->
    <div class="aic-panel">
        <div class="aic-panel-hdr">
            <div>
                <h3><i class="bi bi-cloud-upload"></i> Upload Document for AI Check</h3>
                <p>Supported formats: .docx, .doc, .pdf, .txt, .rtf (max 10MB)</p>
            </div>
        </div>
        <div class="aic-panel-body">
            <div class="aic-upload-zone" id="aicDropZone">
                <i class="bi bi-file-earmark-arrow-up aic-upload-icon"></i>
                <p class="aic-upload-text">Drop file here or <strong>click to browse</strong></p>
                <p class="aic-upload-hint">.docx, .doc, .pdf, .txt, .rtf &mdash; max 10MB</p>
                <input type="file" id="aicFileInput" accept=".docx,.doc,.pdf,.txt,.rtf" style="display:none;">
            </div>

            <!-- File info (shown after selection) -->
            <div class="aic-file-info" id="aicFileInfo" style="display:none;">
                <div class="aic-file-details">
                    <i class="bi bi-file-earmark-text aic-file-icon"></i>
                    <div>
                        <div class="aic-file-name" id="aicFileName"></div>
                        <div class="aic-file-size" id="aicFileSize"></div>
                    </div>
                    <button class="aic-file-remove" id="aicFileRemove" title="Remove file">&times;</button>
                </div>
                <button class="aic-scan-btn" id="aicScanBtn">
                    <i class="bi bi-search"></i> Run AI Check
                </button>
            </div>

            <!-- Loading state -->
            <div class="aic-loading" id="aicLoading" style="display:none;">
                <div class="aic-spinner"></div>
                <p>Scanning document with GPTZero... This may take up to 2 minutes.</p>
            </div>

            <!-- Result (shown after scan) -->
            <div class="aic-result" id="aicResult" style="display:none;">
                <div class="aic-result-header">
                    <i class="bi bi-check-circle-fill" style="color:#4CAF50;font-size:24px;"></i>
                    <span>Scan Complete</span>
                </div>
                <div class="aic-result-pills" id="aicResultPills"></div>
                <a href="#" class="aic-result-report" id="aicResultReport" target="_blank">
                    <i class="bi bi-bar-chart-line"></i> View Detailed Report
                </a>
            </div>

            <!-- Error -->
            <div class="aic-error" id="aicError" style="display:none;"></div>
        </div>
    </div>

    <!-- HISTORY PANEL -->
    <div class="aic-panel">
        <div class="aic-panel-hdr aic-panel-hdr--alt">
            <div>
                <h3><i class="bi bi-clock-history"></i> AI Check History <span class="aic-badge"><?php echo count($history); ?></span></h3>
                <p>All AI detection scans &mdash; standalone uploads and assignment checks</p>
            </div>
        </div>
        <div class="aic-panel-body">
            <?php if (!empty($history)): ?>
            <table id="aicHistoryTable" class="aic-table display" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Document</th>
                        <th>Checked By / Learner</th>
                        <th>Result</th>
                        <th>AI %</th>
                        <th>Mixed %</th>
                        <th>Human %</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                    <tr>
                        <td data-order="<?php echo $item['date_raw']; ?>"><?php echo $item['date']; ?></td>
                        <td>
                            <?php if ($item['type'] === 'standalone'): ?>
                                <span class="aic-type-pill aic-type-pill--standalone">Standalone</span>
                            <?php else: ?>
                                <span class="aic-type-pill aic-type-pill--assignment">Assignment</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo s($item['document']); ?></td>
                        <td><?php echo s($item['learner']); ?></td>
                        <td>
                            <?php if ($item['is_human']): ?>
                                <span class="aic-result-pill aic-result-pill--human">Human</span>
                            <?php elseif ($item['is_ai']): ?>
                                <span class="aic-result-pill aic-result-pill--ai">AI</span>
                            <?php else: ?>
                                <span class="aic-result-pill aic-result-pill--mixed">Mixed</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="aic-pct aic-pct--ai"><?php echo $item['ai_pct']; ?>%</span></td>
                        <td><span class="aic-pct aic-pct--mixed"><?php echo $item['mixed_pct']; ?>%</span></td>
                        <td><span class="aic-pct aic-pct--human"><?php echo $item['human_pct']; ?>%</span></td>
                        <td>
                            <?php if (!empty($item['report_url'])): ?>
                                <a href="<?php echo $item['report_url']; ?>" class="aic-action-link" title="View Report">
                                    <i class="bi bi-bar-chart-line"></i> Report
                                </a>
                            <?php else: ?>
                                <span style="color:#9ca3af;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="aic-empty-state">
                <i class="bi bi-robot" style="font-size:48px;color:#cbd5e1;"></i>
                <p>No AI detection scans have been performed yet</p>
                <p style="font-size:12px;color:#9ca3af;">Upload a document above to run your first AI check</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    var dropZone = document.getElementById('aicDropZone');
    var fileInput = document.getElementById('aicFileInput');
    var fileInfo = document.getElementById('aicFileInfo');
    var fileName = document.getElementById('aicFileName');
    var fileSize = document.getElementById('aicFileSize');
    var fileRemove = document.getElementById('aicFileRemove');
    var scanBtn = document.getElementById('aicScanBtn');
    var loading = document.getElementById('aicLoading');
    var resultDiv = document.getElementById('aicResult');
    var resultPills = document.getElementById('aicResultPills');
    var resultReport = document.getElementById('aicResultReport');
    var errorDiv = document.getElementById('aicError');

    var selectedFile = null;

    // Click to browse.
    dropZone.addEventListener('click', function() {
        fileInput.click();
    });

    // Drag events.
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('aic-upload-zone--active');
    });
    dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('aic-upload-zone--active');
    });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('aic-upload-zone--active');
        if (e.dataTransfer.files.length > 0) {
            handleFile(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', function() {
        if (fileInput.files.length > 0) {
            handleFile(fileInput.files[0]);
        }
    });

    function handleFile(file) {
        var ext = file.name.split('.').pop().toLowerCase();
        var allowed = ['docx', 'doc', 'pdf', 'txt', 'rtf'];
        if (allowed.indexOf(ext) === -1) {
            showError('Unsupported file type. Allowed: ' + allowed.join(', '));
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            showError('File is too large. Maximum size is 10MB.');
            return;
        }

        selectedFile = file;
        fileName.textContent = file.name;
        fileSize.textContent = formatBytes(file.size);
        dropZone.style.display = 'none';
        fileInfo.style.display = 'flex';
        resultDiv.style.display = 'none';
        errorDiv.style.display = 'none';
    }

    fileRemove.addEventListener('click', function() {
        resetUpload();
    });

    function resetUpload() {
        selectedFile = null;
        fileInput.value = '';
        dropZone.style.display = '';
        fileInfo.style.display = 'none';
        resultDiv.style.display = 'none';
        errorDiv.style.display = 'none';
        loading.style.display = 'none';
    }

    // Run AI Check.
    scanBtn.addEventListener('click', function() {
        if (!selectedFile) return;

        scanBtn.disabled = true;
        fileInfo.style.display = 'none';
        loading.style.display = 'flex';
        errorDiv.style.display = 'none';
        resultDiv.style.display = 'none';

        var formData = new FormData();
        formData.append('document', selectedFile);
        formData.append('sesskey', '<?php echo $sesskey; ?>');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo $uploadurl->out(false); ?>', true);
        xhr.onload = function() {
            loading.style.display = 'none';
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showResult(data);
                } else {
                    showError(data.error || 'Unknown error occurred.');
                    scanBtn.disabled = false;
                    fileInfo.style.display = 'flex';
                }
            } catch (e) {
                showError('Invalid response from server.');
                scanBtn.disabled = false;
                fileInfo.style.display = 'flex';
            }
        };
        xhr.onerror = function() {
            loading.style.display = 'none';
            showError('Network error. Please try again.');
            scanBtn.disabled = false;
            fileInfo.style.display = 'flex';
        };
        xhr.send(formData);
    });

    function showResult(data) {
        resultPills.innerHTML =
            '<span class="ai-pill ai-pill-ai">AI ' + data.ai_pct + '%</span>' +
            '<span class="ai-pill ai-pill-mixed">Mixed ' + data.mixed_pct + '%</span>' +
            '<span class="ai-pill ai-pill-human' +
                (data.predicted_class === 'human' ? ' highlighted' : '') +
                '">Human ' + data.human_pct + '%</span>';
        resultReport.href = data.report_url;
        resultDiv.style.display = 'block';
    }

    function showError(msg) {
        errorDiv.textContent = msg;
        errorDiv.style.display = 'block';
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // DataTable init.
    if (document.getElementById('aicHistoryTable')) {
        $('#aicHistoryTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: { search: 'Filter:' },
            columnDefs: [
                { targets: [5, 6, 7], className: 'text-center' },
                { targets: [8], orderable: false }
            ]
        });
    }

});
</script>

<style>
/* ==========================================================================
   AI CHECKS TOOL — Editorial Aesthetic (matches Tutor / Admin Dashboard)
   CSS prefix: aic-
   ========================================================================== */

@import url('https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap');

#ai-checks-page *,
#ai-checks-page *::before,
#ai-checks-page *::after {
    box-sizing: border-box;
}

.aic-shell {
    font-family: 'Libre Baskerville', Georgia, serif;
    color: #1a2238;
    max-width: 100%;
    padding: 0;
}

/* Header */
.aic-header {
    background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 60%, #6ea3c1 100%);
    border-radius: 10px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
}
.aic-header::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(247,200,115,0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.aic-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28px 32px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
    gap: 16px;
}
.aic-title {
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    letter-spacing: -0.5px;
}
.aic-subtitle {
    font-size: 14px;
    color: rgba(255,255,255,0.65);
    margin: 4px 0 0;
    font-style: italic;
}
.aic-header-actions { display: flex; gap: 10px; }
.aic-hdr-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-family: 'Libre Baskerville', Georgia, serif;
    text-decoration: none;
    transition: background 0.2s;
}
.aic-hdr-btn:hover {
    background: rgba(255,255,255,0.22);
    color: #fff;
    text-decoration: none;
}

/* Panels */
.aic-panel {
    background: #fff;
    border: 1px solid #e3eaf2;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 28px;
}
.aic-panel-hdr {
    padding: 20px 28px;
    background: linear-gradient(135deg, #3a5ba0 0%, #6ea3c1 100%);
    color: #fff;
}
.aic-panel-hdr--alt {
    background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%);
}
.aic-panel-hdr h3 {
    margin: 0;
    font-size: 19px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.aic-panel-hdr p {
    margin: 4px 0 0;
    font-size: 13px;
    opacity: 0.8;
    font-style: italic;
}
.aic-panel-body { padding: 24px 28px; }

.aic-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    font-size: 13px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 20px;
    margin-left: 8px;
}

/* Upload zone */
.aic-upload-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 48px 32px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #f8fafc;
}
.aic-upload-zone:hover,
.aic-upload-zone--active {
    border-color: #3a5ba0;
    background: rgba(58,91,160,0.04);
}
.aic-upload-icon {
    font-size: 48px;
    color: #6ea3c1;
    display: block;
    margin-bottom: 12px;
}
.aic-upload-text {
    font-size: 16px;
    color: #1a2238;
    margin: 0 0 6px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-upload-hint {
    font-size: 13px;
    color: #9ca3af;
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* File info */
.aic-file-info {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: #f8fafc;
    border: 1px solid #e3eaf2;
    border-radius: 10px;
}
.aic-file-details {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}
.aic-file-icon {
    font-size: 32px;
    color: #3a5ba0;
}
.aic-file-name {
    font-weight: 600;
    font-size: 14px;
    color: #1a2238;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-file-size {
    font-size: 12px;
    color: #6b7280;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-file-remove {
    background: none;
    border: none;
    font-size: 24px;
    color: #9ca3af;
    cursor: pointer;
    padding: 0 8px;
    line-height: 1;
}
.aic-file-remove:hover { color: #dc2626; }

.aic-scan-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: #3a5ba0;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    white-space: nowrap;
}
.aic-scan-btn:hover { background: #1a2238; }
.aic-scan-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Loading */
.aic-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 40px;
    color: #6b7280;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e3eaf2;
    border-top-color: #3a5ba0;
    border-radius: 50%;
    animation: aic-spin 0.8s linear infinite;
}
@keyframes aic-spin { to { transform: rotate(360deg); } }

/* Result */
.aic-result {
    padding: 20px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
}
.aic-result-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 700;
    color: #166534;
    margin-bottom: 12px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-result-pills {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.aic-result-report {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #3a5ba0;
    text-decoration: none;
}
.aic-result-report:hover { text-decoration: underline; }

/* Reuse pills from aireport.php */
.ai-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #ddd;
    background: #f5f5f5;
    color: #666;
}
.ai-pill-ai { background: #fff3e0; border-color: #ffcc80; color: #e65100; }
.ai-pill-mixed { background: #fff8e1; border-color: #ffe082; color: #f57f17; }
.ai-pill-human { background: #e8f5e9; border-color: #a5d6a7; color: #2e7d32; }
.ai-pill-human.highlighted { background: #2e7d32; border-color: #2e7d32; color: white; font-weight: 600; }

/* Error */
.aic-error {
    padding: 14px 20px;
    background: #fee2e2;
    border: 1px solid #fca5a5;
    border-radius: 8px;
    color: #991b1b;
    font-size: 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* History table */
.aic-table {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
}
.aic-table thead th {
    background: #1a2238 !important;
    color: #fff !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    padding: 12px 14px !important;
    border: none !important;
    white-space: nowrap;
}
.aic-table thead th:first-child { border-radius: 6px 0 0 0; }
.aic-table thead th:last-child { border-radius: 0 6px 0 0; }
.aic-table tbody td {
    font-size: 13px;
    padding: 10px 14px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    vertical-align: middle;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-table tbody tr:hover td { background: #f8fafc !important; }

/* Type pills */
.aic-type-pill {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-type-pill--standalone { background: rgba(110,163,193,0.15); color: #0e7490; }
.aic-type-pill--assignment { background: #fef3c7; color: #92400e; }

/* Result pills */
.aic-result-pill {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-result-pill--human { background: #dcfce7; color: #166534; }
.aic-result-pill--ai { background: #fff3e0; color: #e65100; }
.aic-result-pill--mixed { background: #F3E5F5; color: #7B1FA2; }

/* Percentage inline */
.aic-pct {
    font-size: 12px;
    font-weight: 600;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-pct--ai { color: #e65100; }
.aic-pct--mixed { color: #f57f17; }
.aic-pct--human { color: #2e7d32; }

/* Action links */
.aic-action-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    color: #3a5ba0;
    text-decoration: none;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.aic-action-link:hover { color: #1a2238; text-decoration: none; }

/* Empty state */
.aic-empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #6b7280;
}
.aic-empty-state p {
    margin: 8px 0 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* DataTables overrides */
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #e3eaf2;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 13px;
}
.dataTables_wrapper .dataTables_length select {
    border: 1px solid #e3eaf2;
    border-radius: 6px;
    padding: 4px 8px;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #3a5ba0 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 4px;
}

/* Print */
@media print {
    .aic-header-actions, .aic-upload-zone, .aic-file-info,
    .aic-loading, .aic-result, .aic-error { display: none !important; }
    .aic-panel { break-inside: avoid; }
}
</style>

<?php
echo $OUTPUT->footer();
