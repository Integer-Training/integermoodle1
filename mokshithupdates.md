# Mokshith Updates — February 2026

All new features, enhancements, and bug fixes delivered to the Integer Training Moodle platform (forked from Epearl Academy `moodlebackup` repo) from February 1, 2026 onwards.

---

## New Plugins Created

### 1. Learner Dashboard (`local/learnerdashboard`) — v1.1.0

Standalone personalised dashboard for learners. Set as the **default landing page** after student login.

- 5 colour-themed KPI cards: Enrolled Courses, Due Assignments, Completed, Overall Progress (SVG ring), Messages (with unread badge)
- My Progression panel with expandable per-course rows showing individual assignment statuses (Pass, Refer, Pending Grading, Submitted, Not Submitted)
- Hours Spent chart (Highcharts areaspline) calculated from logstore data with 30-min idle cap
- Upcoming Due Dates section with colour-coded urgency (red/amber/green based on days remaining)
- Recent Messages integration with local_mail plugin (guarded with table_exists check)
- My Tutor card showing assigned tutor with avatar, email, and "Contact Tutor" button that opens mail compose with tutor pre-filled as recipient
- Draft Feedbacks card listing all draft feedback submissions with status badges
- Auto-redirect on login: students are sent here automatically; admins/tutors are not redirected
- Old `local/learner/mydash.php` redirects to the new dashboard for backward compatibility
- Glassmorphism design with dark gradient headers, backdrop-filter blur, decorative background blobs
- CSS namespace: `ld-` prefix, Inter font family

**Files:** `local/learnerdashboard/index.php`, `templates/dashboard.mustache`, `classes/observer.php`, `db/events.php`, `db/access.php`, `version.php`, `lib.php`, `lang/en/local_learnerdashboard.php`

---

### 2. Draft Feedback System (`local/draftfeedback`) — v1.0.0

Enables learners to submit assignment drafts for tutor feedback before final submission.

- Learner workflow: "Submit Draft for Feedback" button injected on assignment pages via `before_footer` callback
- Tutor workflow: dedicated draft list page with DataTable, view draft detail, provide written feedback
- Status tracking: pending, reviewed, revised
- GPTZero AI detection integration for draft review with detailed sentence-level report
- Moodle notification sent to learner when tutor provides feedback (popup + email)
- Dashboard integration: purple alert card on tutor dashboard and admin dashboard showing pending draft counts
- New database table: `local_draftfeedback`
- 4 capabilities: `submit`, `viewown`, `review`, `viewall`

**Files:** `local/draftfeedback/submit.php`, `index.php`, `view.php`, `feedback.php`, `aireport.php`, `lib.php`, `classes/manager.php`, `db/access.php`, `db/install.xml`, `db/messages.php`, `templates/draft_list.mustache`

---

### 3. Performance Optimizer (`local/performance`) — v1.0.0

Eliminates ~15-20 unnecessary database queries per page load.

- 3-tier cached role service (static memory, MUC session cache, DB query) replacing 4-7 scattered role queries per page
- API: `role_cache::is_admin()`, `::is_teacher()`, `::is_student()`, `::has_any_role()`
- Health dashboard with 10 automated checks and weighted score (0-100)
- 3 admin toggles: role cache, nav short-circuit, logstore fix
- 5 patches applied to existing files:
  - `theme/alpha/classes/output/core_renderer.php` — sidebar role checks use cache
  - `local/learnerdashboard/lib.php` — early return when Alpha theme detected
  - `local/learnerprogression/lib.php` — early return when Alpha theme detected
  - `local/kopere_dashboard/lib.php` — re-enabled menu cache
  - `local/learnerdashboard/index.php` — logstore query uses timestamp range instead of `YEAR(FROM_UNIXTIME())`

**Files:** `local/performance/classes/role_cache.php`, `classes/health_checker.php`, `index.php`, `settings.php`, `db/access.php`, `db/caches.php`, `styles.css`, `templates/dashboard.mustache`

---

### 4. Site Backup Manager (`local/sitebackup`) — v2.0.0 / v2.1.0

Full-site backup system with local download and Google Drive upload.

- v2.0: Background execution via Moodle ad-hoc tasks (no browser timeout), live progress UI polling every 3 seconds, database dump with multi-row INSERTs, MySQL keepalive for shared hosting
- v2.1: Fixed Google Drive OAuth to use Moodle `\curl` class (works around Hostinger WAF stripping POST bodies), added diagnostics page testing 3 HTTP methods
- Backs up: database, themes, local plugins, config.php, optionally moodledata/filedir
- Resumable chunked upload for files >5MB
- Retention policy: auto-deletes oldest backups beyond configured count
- Stale detection: backups stuck >2 hours auto-marked as failed

**Files:** `local/sitebackup/view.php`, `progress.php`, `diagnose.php`, `authorize.php`, `classes/backup_manager.php`, `classes/google_drive.php`, `classes/task/run_backup.php`, `classes/task/scheduled_backup.php`, `db/install.xml`, `db/upgrade.php`

---

## Major Feature Enhancements

### Tutor Dashboard Rewrite (`local/learner/tutordash.php`)

- Fixed double-counting bug in marking/resubmission queries
- Added 72-hour SLA rule: "Awaiting Overdue" vs "Awaiting OK" split
- Matched admin dashboard design system (Libre Baskerville headings, navy/blue/teal palette)
- All 4 stat cards are clickable, linking to mark allocation filtered views
- Added draft feedback count card (purple, integrated with draftfeedback plugin)

### Learner Progression Enhancements (`local/learnerprogression`)

- Added course filter dropdown for filtering by specific course
- Learning hours per course: queries logstore with 30-min idle cap, shows clickable purple badge
- Session log modal: clicking hours badge shows Date Accessed, Start, End, Length of Access per session
- Memory-efficient streaming via `get_recordset_sql`

### Admin Dashboard v1.2 (`local/admindashboard`)

- Online Users section: green-header panel showing users active in last 5 minutes with role-coloured pills
- All KPI cards made clickable (Total Learners, Total Tutors, Active Courses, Awaiting Marking)
- AI Detection Checks panel: DataTable showing all GPTZero scans with colour-coded results and View Report links
- Alert cards for inactive learners, overdue assignments, imminent deadlines

### Learner Management Rewrite (`local/learner/view.php`) — v1.1.0

- Complete rewrite: replaced 814-line inline HTML/CSS/JS with ~278 lines PHP + Mustache template
- Security fixes: `process.php` now requires `require_login()`, `require_sesskey()`, `require_capability()`
- SQL injection eliminated: consolidated 3 duplicate code paths into single parameterised query
- 5 KPI cards: Total, Active, Inactive, Created, Suspended (all clickable to filter table)
- Client-side filters: Activity Status, Course dropdown, Reset button
- Expandable child rows for per-course detail
- Styled tooltips on all action icons (Edit, Login As, Send Login, Activate/Deactivate)
- Bootstrap Icons standardised across all plugins

### AI Detection Integration (GPTZero) — v1.2.1 through v1.2.9

On-demand AI detection scanning for student submissions, integrated into the marking workflow.

- **v1.2.1:** Manual AI Check button on marking page (`aicheck.php`), detailed report with sentence-level highlighting (`aireport.php`), colour-coded results (Human green, AI orange, Mixed purple), confirmation dialog before scanning to conserve API quota
- **v1.2.2:** "View Report" auto-changes after scan (no refresh needed), Download/Print report button, AI Checks panel on admin dashboard, fixed database prefix errors in `tutor.php` and `tutorlearners.php`
- **v1.2.3:** Fixed "Back to Normal" function after Login As (custom `loginasfun.php` ensures `$SESSION->realuser` is set properly)
- **v1.2.4:** Added question filtering to strip assignment prompts before scanning (conserve API quota)
- **v1.2.5:** Rewrote question filter to be less aggressive (was removing student answers starting with "Describe", "Explain", etc.)
- **v1.2.6:** 3-pill probability display showing AI%, Mixed%, Human% instead of single predicted class. Usage statistics panel on report page (words used, scans made, next reset, remaining)
- **v1.2.7:** Fixed AI percentages mismatch between marking table and report page
- **v1.2.8:** Simplified AI Check UI
- **v1.2.9:** Disabled question filtering entirely — scans full documents. Regex-based filtering proved unreliable. DOCX text extraction retained.
- **v1.3.0 (data accuracy):** `aicheck.php` now calls GPTZero API directly (bypassing plugin's `transform_response()` which stripped `sentences[]` and `class_probabilities`). Full response cached to eliminate double API calls. Report stats grid shows GPTZero's authoritative document-level percentages.

### Case Study Review Workflow — v1.3.0

New tutor approve/reject and learner resubmit flow for case study assignments.

- New database table: `local_casestudy_reviews` with unique index on `(assignid, userid)`
- New AJAX endpoint: `local/learner/casestudy_review.php` handling approve, reject, resubmit actions
- Tutor UI: Approve/Reject buttons on progression pages next to case studies with "Submitted" or "Resubmitted" status, reject modal with feedback textarea
- Learner UI: "Resubmit" button on dashboard for rejected case studies, feedback tooltip icon showing rejection reason
- Status flow: Not Submitted → Submitted → Approved/Rejected → Resubmitted → Approved/Rejected
- Uses Moodle's `revert_to_draft()` API on rejection so learner can edit existing submission
- Backward compatible: pages work before upgrade via `table_exists()` guard

### Custom Login Screen & Front Page

- Glassmorphism design with animated inspirational quotes carousel (5 quotes cycling every 5 seconds)
- Split layout: branding/quotes on left, login form on right
- Responsive design with mobile optimisation
- Applied to Alpha theme: `loginform.mustache` and `tmpl-frontpage.mustache`

---

## Bug Fixes

### Submission Files Showing "Download Workbook" (Feb 25)
- **Problem:** All student submission files appeared as "Download Workbook" with a PDF icon regardless of actual filename or file type
- **Cause:** Someone had hardcoded `Download Workbook` in `mod/assign/classes/output/renderer.php` line 1397, replacing Moodle's standard file display
- **Fix:** Restored actual filenames with correct file-type icons and plagiarism check links

### Unit Expand/Collapse Not Working (Feb 20)
- **Problem:** Clicking units/courses to expand them didn't work or auto-closed on learner dashboard and progression pages
- **Cause:** Case study review JavaScript crashed on page load (`M.cfg` accessed without guard, unescaped `{{{sesskey}}}`, `sesskey` not set when no learners), killing all JS including toggle handlers
- **Fix:** Wrapped case study AJAX handlers in try/catch, added defensive `M.cfg` check, changed to `{{sesskey}}`, moved sesskey assignment outside conditional block
- **Files:** `dashboard.mustache`, both `progressions.mustache`, `progressions.php`, `learnerprogression/index.php`

### Case Study Assignments Hidden from Progression Pages (Feb 16)
- **Problem:** Case study assignments were completely invisible on progression and dashboard pages
- **Cause:** `AND a.name NOT LIKE '%Case Stud%'` in WHERE clause excluded them entirely
- **Fix:** Removed WHERE exclusion, added SQL CASE WHEN branches mapping case studies to Submitted/Not Submitted, PHP `$is_case_study` flag excludes them from percentage calculations while keeping them visible
- **Files:** `learnerprogression/index.php`, `learnerdashboard/index.php`, `learner/progressions.php`

### Pass/Refer Not Showing After Grading (Feb 16)
- **Problem:** Learners saw "Pending Grading" or "Submitted" instead of Pass/Refer even after tutor had graded
- **Cause:** Only checked `grade_grades.finalgrade` which depends on Moodle's grade sync; if sync hadn't pushed the grade, status was wrong
- **Fix:** Added `LEFT JOIN {assign_grades} ag` with fallback CASE WHEN that checks `ag.grade` directly when `gg.finalgrade` is NULL
- **Files:** `learnerprogression/index.php`, `learnerdashboard/index.php`, `learner/progressions.php`

### Late Submissions Blocked (Feb 3)
- **Problem:** Learners couldn't submit after the due date — completely blocked by hard cutoff
- **Cause:** `overrides_cron.php` and `duedates.php` set `cutoffdate = strtotime("+1 day", $duedate)` which blocked submissions after 24 hours
- **Fix:** Changed to `cutoffdate = 0` (no cutoff). Learners can submit late (marked as late, not blocked)
- **Added:** Late/On Time status column on marking pages with orange "LATE (X days)" or green "On Time" badges

### "Back to Normal" After Login As (Feb 3)
- **Problem:** After using "Login As" to impersonate a learner, the "Back to Normal" function didn't restore the admin session
- **Cause:** Moodle's core `loginas.php` wasn't setting `$SESSION->realuser` properly
- **Fix:** Changed all "Login As" links to use custom `loginasfun.php` which ensures `$SESSION->realuser` is set so `backurl.php` can restore the original session
- **Files:** `view.php`, `edit.php`, `inactiveview.php`, `tutorlearners.php`

### Database Prefix Errors (Feb 3)
- **Problem:** "Error reading from database" on production for tutor pages
- **Cause:** `tutor.php` and `tutorlearners.php` hardcoded `mdl_` prefix; production uses `r6ua_`
- **Fix:** Changed to `{table}` Moodle syntax

### SQL Injection in Learner Management (Feb 2)
- **Problem:** 3 duplicate code paths in `view.php` used string concatenation for SQL queries
- **Fix:** Consolidated into single parameterised query path using `{table}` syntax and `$DB->get_in_or_equal()`

### process.php No Authentication (Feb 2)
- **Problem:** AJAX endpoint for activating/deactivating users had no authentication
- **Fix:** Added `require_login()`, `require_sesskey()`, `require_capability()`, switched from `$_REQUEST` to `required_param()`/`optional_param()`

### Tutor Dashboard Double-Counting (Feb 2)
- **Problem:** Marking and resubmission counts were inflated
- **Fix:** Rewrote all 4 counting queries with proper JOIN filters (`sub.latest = 1`, `gr.attemptnumber = sub.attemptnumber`, grade sentinel `-1` check)

---

## Design & UI Updates

- Editorial design system applied across all custom plugins: Libre Baskerville headings, navy/blue/teal palette, `prog-` / `lv-` / `ld-` / `sb-` CSS namespaces
- Bootstrap Icons standardised (removed Font Awesome/Ionic dependencies)
- Styled CSS tooltips using `data-tooltip` attribute on all action icons
- Scoped `box-sizing: border-box` resets to prevent layout issues
- Full-width layouts (`max-width: 100%`) to properly fill Alpha theme content area
- KPI card styling: `border-left` accent, clickable cards linking to filtered views
- Scrollable tables with `overflow-x: auto` for narrow screens
- Toggle icon colours: green for active users, red for suspended users

---

## Files Changed Summary

| Plugin / Area | New Files | Modified Files |
|---------------|-----------|----------------|
| `local/learnerdashboard` | 8 | - |
| `local/draftfeedback` | 12 | - |
| `local/performance` | 10 | - |
| `local/sitebackup` | 10 | 2 |
| `local/learner` | 4 | 15+ |
| `local/learnerprogression` | - | 4 |
| `local/admindashboard` | - | 2 |
| `theme/alpha` | 1 | 3 |
| `mod/assign` | - | 1 |

---

*Last updated: 27 February 2026*
