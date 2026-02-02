# Site Backup 2.0 — "Epearl Vault"

## Why 2.0 Exists

v1.0 has a fundamental architectural flaw: **it runs the entire backup inside a single HTTP request**. On shared hosting (Hostinger), this causes a cascade of failures:

1. **MySQL drops the connection** after ~30s of inactivity (`wait_timeout`)
2. **PHP kills the process** at `max_execution_time` (usually 30–120s on shared hosting)
3. **The browser times out** waiting for the response
4. **No progress visibility** — user stares at a spinner with no idea what's happening
5. **No recovery** — if step 4 of 6 fails, all work from steps 1–3 is lost

The keepalive hack (pinging MySQL between steps) is a band-aid. The real fix is architectural: **never run heavy work in a web request**.

---

## Core Principle: Asynchronous Everything

```
v1.0:  Browser ──HTTP──▶ PHP ──47s of blocking work──▶ Response
v2.0:  Browser ──HTTP──▶ Queue task (instant) ──▶ Response
                          ↓
                    Cron picks up task
                          ↓
                    Runs in CLI (no timeout)
                          ↓
                    Updates progress in DB
                          ↓
                    Browser polls for status (AJAX)
```

Moodle's **ad-hoc task** system (`\core\task\adhoc_task`) is purpose-built for this. It:
- Runs via CLI cron (no `max_execution_time` web limit)
- Has its own MySQL connection (no shared hosting web timeouts)
- Is queued, retried on failure, and logged
- Is how Moodle's own backup/restore system works internally

---

## Architecture Overview

### The Three Layers

```
┌─────────────────────────────────────────────┐
│  PRESENTATION LAYER                         │
│  view.php + Mustache + AJAX polling         │
│  - Dashboard with live progress bars        │
│  - Download/delete/retry actions            │
│  - Settings page                            │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  ORCHESTRATION LAYER                        │
│  backup_manager.php (refactored)            │
│  - Queues ad-hoc tasks                      │
│  - Tracks state machine per backup          │
│  - Handles progress updates                 │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  EXECUTION LAYER (runs via cron)            │
│  task/run_backup.php (ad-hoc task)          │
│  - Step-by-step execution with checkpoints  │
│  - Each step updates progress in DB         │
│  - Resumable on failure                     │
└─────────────────────────────────────────────┘
```

### State Machine

Every backup follows this state machine:

```
QUEUED → DUMPING_DB → ZIPPING_THEMES → ZIPPING_PLUGINS → COPYING_CONFIG
    → ZIPPING_MOODLEDATA → CREATING_MASTER_ZIP → UPLOADING_TO_CLOUD → COMPLETE
                                                                         │
    Any step can → FAILED (with retry available)                         │
                                                                         ▼
                                                                     AVAILABLE
                                                              (download ready)
```

The `status` field in the logs table becomes the state. Each transition updates the row with:
- Current state
- Progress percentage
- Human-readable message ("Dumping database... 45 of 312 tables")
- Timestamp of last update

### Database Schema Changes

```sql
-- Rename: local_sitebackup_logs → local_sitebackup_jobs (conceptual shift)
ALTER TABLE local_sitebackup_logs ADD COLUMN progress_pct TINYINT DEFAULT 0;
ALTER TABLE local_sitebackup_logs ADD COLUMN progress_msg TEXT;
ALTER TABLE local_sitebackup_logs ADD COLUMN step VARCHAR(40) DEFAULT '';
ALTER TABLE local_sitebackup_logs ADD COLUMN local_path TEXT;
ALTER TABLE local_sitebackup_logs ADD COLUMN cloud_provider VARCHAR(20) DEFAULT '';
ALTER TABLE local_sitebackup_logs ADD COLUMN retry_count TINYINT DEFAULT 0;
ALTER TABLE local_sitebackup_logs ADD COLUMN queued_by INT DEFAULT 0;
ALTER TABLE local_sitebackup_logs ADD COLUMN timemodified INT DEFAULT 0;
```

---

## Detailed Design

### 1. Queueing a Backup (Instant — < 100ms)

When user clicks "Local Backup":

```php
// view.php — backup action (v2.0)
$job = new stdClass();
$job->filename    = 'sitebackup_' . date('Y-m-d_H-i-s') . '.zip';
$job->status      = 'queued';
$job->step        = 'queued';
$job->progress_pct = 0;
$job->progress_msg = 'Backup queued, waiting for cron...';
$job->queued_by   = $USER->id;
$job->timecreated = time();
$job->timemodified = time();
$jobid = $DB->insert_record('local_sitebackup_logs', $job);

// Queue the ad-hoc task.
$task = new \local_sitebackup\task\run_backup();
$task->set_custom_data(['job_id' => $jobid, 'skip_drive' => $skipdrive]);
$task->set_userid($USER->id);
\core\task\manager::queue_adhoc_task($task);

// Redirect immediately — backup hasn't started yet.
redirect(new moodle_url('/local/sitebackup/view.php'),
    get_string('backupqueued', 'local_sitebackup'), null,
    \core\output\notification::NOTIFY_INFO);
```

The page loads instantly. No waiting.

### 2. Executing the Backup (CLI via Cron)

New file: `classes/task/run_backup.php`

```php
class run_backup extends \core\task\adhoc_task {

    public function execute() {
        $data = $this->get_custom_data();
        $manager = new \local_sitebackup\backup_manager();
        $manager->execute_job((int) $data->job_id, !empty($data->skip_drive));
    }

    public function get_name() {
        return get_string('taskrunbackup', 'local_sitebackup');
    }
}
```

The `execute_job()` method in `backup_manager` runs each step and updates progress:

```php
public function execute_job(int $jobid, bool $skipdrive = false): void {
    global $DB, $CFG;

    $job = $DB->get_record('local_sitebackup_logs', ['id' => $jobid], '*', MUST_EXIST);

    $steps = [
        'dumping_db'        => ['method' => 'dump_database',    'label' => 'Dumping database...', 'pct' => 15],
        'zipping_themes'    => ['method' => 'zip_themes',       'label' => 'Archiving themes...',  'pct' => 30],
        'zipping_plugins'   => ['method' => 'zip_plugins',      'label' => 'Archiving plugins...', 'pct' => 50],
        'copying_config'    => ['method' => 'copy_config',      'label' => 'Copying config...',    'pct' => 55],
        'zipping_moodledata'=> ['method' => 'zip_moodledata',   'label' => 'Archiving uploads...', 'pct' => 70],
        'creating_zip'      => ['method' => 'create_master_zip','label' => 'Creating backup ZIP...','pct' => 85],
        'uploading'         => ['method' => 'upload_to_cloud',  'label' => 'Uploading to cloud...','pct' => 95],
    ];

    foreach ($steps as $stepname => $stepinfo) {
        // Update progress BEFORE the step runs.
        $this->update_progress($jobid, $stepname, $stepinfo['pct'], $stepinfo['label']);

        // Execute the step.
        try {
            $this->{$stepinfo['method']}();
        } catch (\Exception $e) {
            $this->mark_failed($jobid, $stepname, $e->getMessage());
            return; // Stop here — don't continue to next step.
        }
    }

    // All steps complete.
    $this->mark_complete($jobid);
}
```

### 3. Live Progress (AJAX Polling)

New file: `ajax.php` — lightweight endpoint for progress polling.

```php
// ajax.php
define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_login();
require_capability('local/sitebackup:manage', context_system::instance());

$jobid = required_param('jobid', PARAM_INT);
$job = $DB->get_record('local_sitebackup_logs', ['id' => $jobid]);

header('Content-Type: application/json');
echo json_encode([
    'status'       => $job->status,
    'step'         => $job->step,
    'progress_pct' => (int) $job->progress_pct,
    'progress_msg' => $job->progress_msg,
    'filesize'     => $job->filesize ? display_size($job->filesize) : null,
    'duration'     => $job->duration ? $job->duration . 's' : null,
    'error'        => $job->error_message ?: null,
    'download_url' => (!empty($job->local_path) && file_exists($job->local_path))
        ? (new moodle_url('/local/sitebackup/view.php',
            ['action' => 'download', 'downloadid' => $job->id]))->out(false)
        : null,
]);
```

Frontend JavaScript polls every 3 seconds while a backup is in progress:

```javascript
function pollProgress(jobId) {
    const interval = setInterval(async () => {
        const resp = await fetch(`ajax.php?jobid=${jobId}`);
        const data = await resp.json();

        // Update progress bar
        progressBar.style.width = data.progress_pct + '%';
        progressMsg.textContent = data.progress_msg;

        if (data.status === 'success' || data.status === 'failed') {
            clearInterval(interval);
            // Show completion or error state
            if (data.status === 'success') {
                showDownloadButton(data.download_url);
            } else {
                showError(data.error);
            }
        }
    }, 3000);
}
```

### 4. Progress Bar UI

Replace the current spinner overlay with a proper progress panel:

```
┌──────────────────────────────────────────────┐
│  ⚡ Backup in Progress                       │
│                                              │
│  ████████████████░░░░░░░░░  65%             │
│  Archiving plugins...                        │
│                                              │
│  ✓ Database dump (312 tables, 45MB)          │
│  ✓ Themes archived (3 themes)                │
│  ⟳ Archiving plugins... (12 of 18)          │
│  ○ Config copy                               │
│  ○ Create master ZIP                         │
│                                              │
│  Duration: 23s                               │
└──────────────────────────────────────────────┘
```

Each completed step shows a checkmark with details. The current step shows a spinner. Future steps show empty circles.

### 5. Google Drive — Proper OAuth via Moodle's System

Instead of rolling our own OAuth, use Moodle's built-in OAuth2 system:

```
Site Admin → Server → OAuth 2 services → Create "Google" issuer
```

Moodle's `\core\oauth2\api` handles:
- Token storage in `{oauth2_access_token}` and `{oauth2_refresh_token}` tables
- Automatic token refresh
- Proper error handling
- Works with Moodle's `\core\oauth2\client` for API calls

The plugin just needs to:
1. Reference the OAuth2 issuer by ID
2. Use `\core\oauth2\api::get_system_oauth_client($issuer)` to get an authenticated HTTP client
3. Make Drive API calls with that client

This eliminates all the custom `google_drive.php` OAuth code and the "Invalid grant_type:" issue.

### 6. Cloud Storage Abstraction

Design for multiple cloud providers:

```php
interface cloud_storage {
    public function is_configured(): bool;
    public function is_connected(): bool;
    public function upload(string $filepath, string $remotename): array;
    public function delete(string $fileid): void;
    public function list_files(): array;
}

class google_drive_storage implements cloud_storage { ... }
class s3_storage implements cloud_storage { ... }
class sftp_storage implements cloud_storage { ... }
```

Admin settings let user pick which provider(s) to use. A backup can upload to multiple destinations.

### 7. Email Notifications

After backup completes (success or failure), send a notification:

```php
// Using Moodle's message API.
$message = new \core\message\message();
$message->component = 'local_sitebackup';
$message->name = 'backup_complete';
$message->userfrom = \core_user::get_noreply_user();
$message->userto = $user;
$message->subject = "Backup complete: {$job->filename}";
$message->fullmessage = "Your site backup completed successfully.\n"
    . "Size: " . display_size($job->filesize) . "\n"
    . "Duration: {$job->duration}s\n"
    . "Download: {$downloadurl}";
$message->fullmessageformat = FORMAT_PLAIN;
$message->notification = 1;
message_send($message);
```

Register the notification provider in `db/messages.php`:

```php
$messageproviders = [
    'backup_complete' => ['defaults' => ['popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN]],
    'backup_failed'   => ['defaults' => ['popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN,
                                          'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN]],
];
```

### 8. Smarter Database Dumps

The current PHP fallback dumps every table in one go. For large databases:

**Table-by-table progress:**
```php
$tables = $DB->get_tables();
$total = count($tables);
foreach ($tables as $i => $table) {
    $this->update_progress($jobid, 'dumping_db',
        intval(($i / $total) * 15),  // 0-15% range for DB dump
        "Dumping table {$table} ({$i}/{$total})..."
    );
    $this->dump_single_table($handle, $table);
}
```

**Skip known-large, non-essential tables:**
```php
$skip_tables = [
    'logstore_standard_log',  // Can be gigabytes — rebuild from events
    'sessions',               // Ephemeral
    'cache_*',                // Rebuild on startup
    'task_adhoc',             // Queue — ephemeral
    'task_log',               // Historical — optional
];
```

This can reduce dump size by 50–80% on active sites.

### 9. Backup Verification

After creating the master ZIP, verify it:

```php
private function verify_backup(string $zippath): void {
    $zip = new \ZipArchive();
    $result = $zip->open($zippath, \ZipArchive::RDONLY);
    if ($result !== true) {
        throw new \Exception("Backup ZIP is corrupt (error code: {$result})");
    }

    // Verify manifest exists.
    if ($zip->locateName('manifest.json') === false) {
        throw new \Exception("Backup ZIP missing manifest.json");
    }

    // Verify database dump exists (if included).
    if (in_array('database', $this->contents) && $zip->locateName('database.sql') === false) {
        throw new \Exception("Backup ZIP missing database.sql");
    }

    $zip->close();
}
```

### 10. Retention & Cleanup

Automatic cleanup of old local backups:

```php
// After successful backup, enforce local retention.
$retention = (int) get_config('local_sitebackup', 'local_retention_count') ?: 5;
$storagedir = $CFG->dataroot . '/sitebackup';

$jobs = $DB->get_records('local_sitebackup_logs',
    ['status' => 'success'], 'timecreated DESC');

$count = 0;
foreach ($jobs as $job) {
    $count++;
    if ($count > $retention) {
        $filepath = $storagedir . '/' . $job->filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
```

---

## Implementation Phases

### Phase 1: Core Async (Fixes all current bugs)

**Goal:** Backup runs via cron, never blocks web request.

| Task | Files |
|------|-------|
| Create `task/run_backup.php` ad-hoc task | New file |
| Refactor `backup_manager.php` — split into `queue_job()` + `execute_job()` | Major rewrite |
| Add progress fields to DB schema | `db/upgrade.php`, `db/install.xml` |
| Create `ajax.php` for progress polling | New file |
| Update `view.php` — queue instead of execute, poll for progress | Rewrite |
| Update `view.mustache` — progress panel replaces spinner | Rewrite |
| Add JavaScript progress polling | New: `amd/src/progress.js` |
| Email notifications on complete/fail | `db/messages.php`, new notification templates |
| Local retention auto-cleanup | `backup_manager.php` |
| Backup verification after ZIP creation | `backup_manager.php` |

**Result:** Clicking "Local Backup" returns instantly. Progress bar shows live. Download appears when done. Works reliably on shared hosting.

### Phase 2: Cloud Storage (Fixes Google Drive)

**Goal:** Proper OAuth via Moodle's system, multi-provider support.

| Task | Files |
|------|-------|
| Create `cloud_storage` interface | New: `classes/cloud_storage.php` |
| Google Drive via `\core\oauth2` | Rewrite `classes/google_drive_storage.php` |
| Remove custom OAuth code | Delete old `google_drive.php`, `authorize.php` |
| S3 storage provider | New: `classes/s3_storage.php` |
| SFTP storage provider | New: `classes/sftp_storage.php` |
| Admin UI for provider selection | Update `settings.php` |
| Upload to multiple providers | Update `backup_manager.php` |

### Phase 3: Intelligence

**Goal:** Smarter, faster, smaller backups.

| Task | Files |
|------|-------|
| Table-by-table progress in DB dump | `backup_manager.php` |
| Skip non-essential large tables (configurable) | `settings.php`, `backup_manager.php` |
| Incremental backup — only changed files in moodledata | New: `classes/incremental.php` |
| Backup diffing — compare two backups | New: `classes/diff.php` |
| Selective restore from backup | New: `restore.php`, `classes/restore_manager.php` |

### Phase 4: Enterprise Polish

**Goal:** Production-grade reliability and compliance.

| Task | Files |
|------|-------|
| Backup encryption (AES-256) | `backup_manager.php` |
| GDPR data export integration | `classes/privacy/provider.php` |
| Backup schedule intelligence (auto-frequent during active periods) | `task/scheduled_backup.php` |
| Admin dashboard widget (Kopere integration) | New template |
| CLI command: `php admin/cli/sitebackup.php` | New: `cli/backup.php` |
| Health check: warn if no successful backup in X days | `lib.php` check API |

---

## Cron Dependency Note

This entire system depends on **Moodle cron running regularly**. On Hostinger:

```bash
# Verify cron is configured (should run every minute):
* * * * * /usr/bin/php /path/to/moodle/admin/cli/cron.php > /dev/null 2>&1
```

If cron only runs every 5 minutes, the backup will start within 5 minutes of being queued. The AJAX progress polling handles this gracefully — it shows "Waiting for cron to pick up task..." until the status changes from `queued`.

If cron is not configured at all, fall back to Moodle's web-based cron trigger (`admin/cron.php`), but this re-introduces the timeout issue. Phase 1 should detect this and warn the admin.

---

## Migration from v1.0

The v2.0 upgrade path:

1. `db/upgrade.php` adds new columns to `local_sitebackup_logs`
2. Existing log records get `step = 'legacy'`, `progress_pct = 100` (if success) or `0` (if failed)
3. Old `google_drive.php` kept as deprecated wrapper until Phase 2 replaces it
4. `authorize.php` redirects to Moodle OAuth2 setup page with instructions
5. Existing local backup ZIPs in `$CFG->dataroot/sitebackup/` are preserved
6. Scheduled task updated to use new ad-hoc queueing instead of direct execution

No data loss. No breaking changes. Clean upgrade.

---

## File Structure (Final v2.0)

```
local/sitebackup/
├── version.php
├── lib.php                          # Navigation hook + health check
├── settings.php                     # Admin settings (expanded)
├── view.php                         # Dashboard (queue + AJAX)
├── ajax.php                         # Progress polling endpoint
├── cli/
│   └── backup.php                   # CLI: php admin/cli/sitebackup.php
├── classes/
│   ├── backup_manager.php           # Orchestrator: queue_job() + execute_job()
│   ├── cloud_storage.php            # Interface
│   ├── google_drive_storage.php     # Google Drive via Moodle OAuth2
│   ├── s3_storage.php               # AWS S3
│   ├── sftp_storage.php             # SFTP
│   ├── restore_manager.php          # Phase 3: selective restore
│   ├── incremental.php              # Phase 3: incremental backups
│   ├── privacy/
│   │   └── provider.php             # GDPR compliance
│   └── task/
│       ├── run_backup.php           # Ad-hoc task (the worker)
│       └── scheduled_backup.php     # Daily cron (queues ad-hoc task)
├── db/
│   ├── install.xml                  # Updated schema
│   ├── upgrade.php                  # Migration from v1
│   ├── access.php                   # Capabilities
│   ├── tasks.php                    # Scheduled task
│   └── messages.php                 # Notification providers
├── amd/
│   └── src/
│       └── progress.js              # AJAX progress polling
├── templates/
│   ├── view.mustache                # Dashboard
│   ├── progress.mustache            # Progress panel partial
│   ├── notification_html.mustache   # Email: backup complete
│   └── notification_text.mustache   # Email: backup complete (plain)
├── lang/en/local_sitebackup.php
├── styles.css
├── issues.md
└── PLAN-v2.md                       # This file
```
