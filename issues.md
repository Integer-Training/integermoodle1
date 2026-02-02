# Epearlmoodle — Known Issues & Diagnostics

Last updated: 2026-02-01

---

## ISSUE #0: PDF Not Generated When Grading Assignments

**Status:** FIXED (local dev) | PARTIALLY WORKING (production)
**Severity:** CRITICAL
**Symptom:** Grading page stuck on "Generating the PDF..." forever, then "Cannot open the PDF" error

### Part A: GhostScript (PDF → page images)

**Root cause:** GhostScript (`gs`) was not installed on local dev. Moodle's `assignfeedback_editpdf` plugin requires GhostScript to convert PDFs into page images for the annotation/grading interface.

**Fix applied (local):**
```bash
brew install ghostscript
# Installed at /opt/homebrew/bin/gs
```
Then updated Moodle config:
```php
set_config("pathtogs", "/opt/homebrew/bin/gs");
```

**Production status:** GhostScript IS installed at `/usr/bin/gs` (v9.27). Path configured correctly. Working.

### Part B: DOCX → PDF conversion (blank page when grading Word submissions)

86% of submissions are `.docx` files. Moodle needs a document converter to turn Word files into PDF before the annotation UI can display them.

`unoconv` is deprecated and incompatible with LibreOffice 25.8 (hangs on `--version`).

#### Local dev fix (WORKING):

Created a wrapper script that makes Moodle's **built-in** `fileconverter_unoconv` work by translating `unoconv` CLI calls to `soffice --headless` — no new plugins or external servers needed.

1. Installed LibreOffice: `brew install --cask libreoffice`
2. Created wrapper script at `/Users/aman/unoconv-wrapper.sh` — handles `--version`, `--show`, and `-f <format> -o <output> <input>` by calling `soffice --headless --convert-to`
3. Configured Moodle: `set_config('pathtounoconv', '/Users/aman/unoconv-wrapper.sh')`
4. Cleared failed `{file_conversion}` records so Moodle retries with the now-working converter
5. Verified: 142KB docx converted to 212KB PDF through Moodle's conversion API

#### Production fix attempt (PARTIALLY WORKING):

Production is on **Hostinger shared hosting** (cPanel only, no SSH, no ability to install system packages). LibreOffice is NOT available on the server. The unoconv wrapper approach cannot work.

**Alternative: Google Drive converter (`fileconverter_googledrive`)**

Set up the built-in Google Drive converter which sends DOCX files to Google Drive API for conversion:

1. Created Google Cloud project "Epearl Moodle" with Google Drive API enabled
2. Created OAuth 2.0 credentials (Web application type)
3. Configured OAuth consent screen (External, testing mode) with test user `D2D124@gmail.com`
4. Added scopes: `openid profile email https://www.googleapis.com/auth/drive`
5. Created OAuth 2 service "epearl" in Moodle (Site Admin > Server > OAuth 2 services)
6. Connected system account with `D2D124@gmail.com`
7. Configured Google Drive converter to use the "epearl" OAuth 2 service
8. Converter test page (`/files/converter/googledrive/test.php`) works — successfully converts test document to PDF

**Current issue:** Despite the converter test working, the actual assignment grading page still shows "Cannot open the PDF" error. The `{file_conversion}` records show `status=2` (COMPLETE) with `converter=NULL`, suggesting the conversion pipeline may not be properly connecting the Google Drive converter to the editpdf annotation system. Debug mode needs to be enabled to get detailed error messages.

**Server diagnostics (Hostinger shared hosting):**
- GhostScript: `/usr/bin/gs` v9.27 — INSTALLED, WORKING
- LibreOffice/soffice: NOT INSTALLED (not available on shared hosting)
- unoconv: NOT INSTALLED
- PHP exec/shell_exec: AVAILABLE (check_soffice.php works)
- Database prefix: `r6ua_` (not standard `mdl_`)

**Diagnosis script:** Deployed at `admin/check_soffice.php` on production — checks for LibreOffice, GhostScript, and current config values. Requires admin login + `moodle/site:config` capability. Should be removed after debugging is complete.

**Recommended resolution:** Move to a VPS/dedicated server where LibreOffice can be installed, then deploy the unoconv wrapper script. The Google Drive converter may also work once the editpdf pipeline issue is debugged — enable `DEVELOPER` debug mode and check error logs.

**Other converters tried and rejected:**
- `fileconverter_flasksoffice` (already installed as plugin) — Has a bug in `converter.php:230` where `ltrim($lastelement, $contenthashf7)` mangles filenames. Also requires running a separate Flask server.
- Custom `fileconverter_soffice` plugin — Created and tested locally, works, but requires LibreOffice on server.
- `fileconverter_unoserver` (community plugin) — Requires Python unoserver package installed in LibreOffice's Python environment.

**Still optional (not installed):**
- PHP imagick extension — optional, improves PDF rendering quality

---

## ISSUE #1: Learners Blocked From Submitting Assignments

**Status:** Diagnosed
**Severity:** HIGH
**Affected users:** 1,626 per-user overrides with past cutoff dates (out of 8,996 total)

**Root cause:** The system creates per-user assignment overrides (`{assign_overrides}` table) with cascading deadlines based on enrollment date — 15 days per unit, with a 1-day grace cutoff. Once the cutoff passes, Moodle blocks submissions for that specific user on that specific assignment.

**Created by:** `local/learner/overrides_cron.php` and `local/learner/duedates.php`

**Pattern:**
- Unit 1: due 15 days after enrollment, cutoff 16 days
- Unit 2: due 30 days after enrollment, cutoff 31 days
- Unit 3: due 45 days after enrollment, cutoff 46 days
- etc.

**Decision needed:** Should learners be allowed to submit after the cutoff? Options:
1. Remove all cutoff dates from overrides (allow late submissions)
2. Extend cutoff dates
3. Remove the override system entirely (manual deadlines only)

---

## ISSUE #2: `local/assignrelative` — Global Assignment Date Clobbering

**Status:** Diagnosed
**Severity:** CRITICAL (potential — may not be actively causing issues if overrides take precedence)
**File:** `local/assignrelative/classes/observer.php` (lines 46-51)

**What it does:** Every time ANY student views an assignment, it recalculates open/due/cutoff dates based on that student's enrollment `timestart` and overwrites the GLOBAL `{assign}` record — affecting ALL students.

**Why it's dangerous:**
- Last student to view overwrites dates for everyone
- If any student has `timestart = 0` (424 enrollments do), dates calculate to 1970 epoch
- Per-user overrides (Issue #1) currently mask this because overrides take precedence over global dates
- If overrides are ever removed, this plugin would immediately break all assignments

**Recommendation:** Uninstall this plugin. If relative deadlines are needed, rewrite to use `{assign_overrides}` per-user table.

---

## ISSUE #3: 424 Enrollments With `timestart = 0`

**Status:** Diagnosed
**Severity:** MEDIUM
**Affected:** Mostly admin/staff accounts (Admin User, Janani, Rimjhim, Jasbir, etc.)

**What it means:** These users were enrolled without a start date. The `assignrelative` plugin would calculate their dates from Unix epoch (1970) if they view assignments. Currently masked by per-user overrides.

**Also found:** 1 expired enrollment (Rimjhim Sharma, expired 2025-09-11)

---

## ISSUE #4: 41 Suspended Users

**Status:** Noted
**Severity:** LOW (working as designed)

Suspended users cannot log in. Their enrollments are NOT removed — they remain enrolled but can't access courses. If reactivated, they regain access immediately.

---

## ISSUE #5: `local/learner/overrides_cron.php` — Conflicting Deadline System

**Status:** Diagnosed
**Severity:** HIGH
**File:** `local/learner/overrides_cron.php` (lines 34-119)

**Problems:**
- Uses `user.firstaccess` (first login) instead of `user_enrolments.timestart` (enrollment date)
- Hardcoded `mdl_` table prefix instead of `{table}` Moodle syntax
- SQL injection vulnerabilities (direct concatenation)
- Hardcoded `OVERRIDE_DAYS = 15` constant
- Not registered as a scheduled task in `db/tasks.php` — may not run reliably
- Conflicts with `local/assignrelative` — two systems fighting over deadline dates

---

## ISSUE #6: `local/learner/process.php` — No Authentication

**Status:** Diagnosed
**Severity:** CRITICAL (security)
**File:** `local/learner/process.php` (lines 26-94)

**Problems:**
- No `require_login()` — unauthenticated access possible
- No `sesskey` validation — no CSRF protection
- No `require_capability()` — no permission check
- Directly activates/deactivates users via `$_REQUEST`
- Any unauthenticated request could suspend any user account

---

## ISSUE #7: SQL Injection Vulnerabilities

**Status:** Diagnosed
**Severity:** CRITICAL (security)

| File | Line(s) | Issue |
|------|---------|-------|
| `local/learner/sessions.php` | 124 | `$fromform->cms` directly concatenated into SQL |
| `local/learner/markallocation.php` | 91, 139, 144 | `$USER->id` concatenated (low risk but bad practice) |
| `local/tutors/reassign.php` | 74-86 | `$_POST` data directly in SQL WHERE clause |
| `local/learner/overrides_cron.php` | 61 | Hardcoded `mdl_` prefix |

---

## ISSUE #8: Hardcoded Table Prefixes (`mdl_`)

**Status:** Diagnosed
**Severity:** MEDIUM (breaks if DB prefix changes; production uses `r6ua_`)

**Affected files:**
- `local/learner/edit.php` (lines 142-150)
- `local/learner/inactive.php` (lines 75-166)
- `local/learner/markallocation.php` (multiple lines)
- `local/learner/overrides_cron.php` (line 61)
- `local/tutors/reassign.php` (lines 69-125)

All use `mdl_` instead of Moodle's `{table}` syntax. Local dev uses `mdl_` prefix so it works, but production uses `r6ua_` — these queries will fail on production.

---

## ISSUE #9: `local/tutors/usergrade.php` — No Capability Check

**Status:** Diagnosed
**Severity:** HIGH (security)
**File:** `local/tutors/usergrade.php` (lines 29-35)

Only calls `require_login()` — any logged-in user can view any course's gradebook. Missing `require_capability()` and uses `context_system` instead of course context.

---

## ISSUE #10: 365-Day Enrollment Expiration

**Status:** Diagnosed
**Severity:** MEDIUM (not currently affecting users since no one is >1 year old)
**File:** `local/learner/users.php` (line 103)

```php
$enrolplugin->enrol_user($instance, $newuserobj->id, 5, time(), strtotime("+365 days", time()));
```

Enrollments hard-expire after 365 days. Moodle's cron silently unenrolls expired users. Will become a problem as users approach 1-year mark.

---

## ISSUE #11: `kopere_dashboard` Wildcard Event Observer

**Status:** Noted
**Severity:** LOW (performance)
**File:** `local/kopere_dashboard/db/events.php`

Listens to ALL Moodle events (`"eventname" => "*"`). Could have performance impact and may silently swallow errors in event processing.

---

## ISSUE #12: Feedback Session Flag

**Status:** Diagnosed
**Severity:** MEDIUM
**File:** `mod/feedback/classes/completion.php` (line 699)

The `$SESSION->feedback->is_started` flag can be lost between viewing the feedback form and submitting it (session timeout, browser refresh, navigate away and return). When lost, submission throws a generic "error" and redirects to course page with no explanation.

This is core Moodle code — fix by ensuring adequate session timeout in `config.php`.

---

## ISSUE #13: Production Server Limitations (Hostinger Shared Hosting)

**Status:** Noted
**Severity:** HIGH (blocks multiple fixes)

**Current hosting:** Hostinger shared hosting, cPanel access only, no SSH/terminal access.

**Limitations that block fixes:**
- Cannot install system packages (LibreOffice, unoconv, etc.)
- Cannot run background processes (Flask server, unoserver, etc.)
- Cannot install PHP extensions (imagick)
- Cannot run custom cron scripts reliably
- Limited debugging capability (no access to PHP error logs directly)
- File system access only through cPanel File Manager

**Impact:**
- DOCX→PDF conversion partially blocked (Google Drive converter test works but grading pipeline has issues)
- Cannot deploy the unoconv wrapper script (no LibreOffice)
- Cannot run custom scheduled tasks or cron jobs

**Recommended action:** Migrate to a VPS (e.g. DigitalOcean, Linode, or Hostinger VPS) where:
1. LibreOffice can be installed for native DOCX→PDF conversion
2. The unoconv wrapper script can be deployed (proven working on local dev)
3. SSH access enables proper debugging and maintenance
4. Custom cron jobs can run reliably
5. An error logger plugin can be built to capture and display system errors

**Planned for after migration:**
- Deploy unoconv wrapper script with LibreOffice
- Build error logger plugin that captures PHP errors, conversion failures, and cron issues into a Moodle-accessible admin dashboard
- Fix all hardcoded `mdl_` table prefixes (Issue #8)
- Fix SQL injection vulnerabilities (Issue #7)
- Fix security issues (Issues #6, #9)

---

## Database Statistics (2026-01-31)

| Metric | Count |
|--------|-------|
| Total assignments | 212 |
| Total per-user overrides | 8,996 |
| Overrides with past cutoff (BLOCKED) | 1,626 |
| Overrides with future cutoff (OK) | 7,370 |
| Enrollments with timestart=0 | 424 |
| Expired enrollments | 1 |
| Suspended users | 41 |
