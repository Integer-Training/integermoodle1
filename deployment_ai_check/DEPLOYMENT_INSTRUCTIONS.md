# AI Check System Deployment Guide

**Version:** 1.2.2
**Date:** February 2026
**Package:** `ai_check_system_v1.2.2.zip`

---

## Features Included

1. **Manual AI Check Button** - Tutors can scan submissions on-demand (conserves API quota)
2. **Detailed AI Report Page** - Sentence-level highlighting with AI probability
3. **View Report Button** - Quick access to existing scan results
4. **Download Report** - Print/PDF export of AI detection reports
5. **GPTZero Word Usage Tracking** - Monitor API quota on admin dashboard
6. **AI Checks Panel** - Admin dashboard shows all AI scans with View Report buttons
7. **Standard API Integration** - Works with any GPTZero API key

## Bug Fixes in 1.2.2

1. **Fixed "Error reading from database"** - Fixed hardcoded `mdl_` table prefix in `tutor.php` and `tutorlearners.php` that caused database errors on production (which uses `r6ua_` prefix)

---

## Pre-Deployment Checklist

- [ ] GPTZero API key configured in Moodle
- [ ] Backup of current files (optional but recommended)
- [ ] Access to Hostinger File Manager or FTP

---

## Deployment Steps

### Step 1: Upload Files

Upload via **Hostinger File Manager** to `public_html/`:

| Local File | Upload To |
|------------|-----------|
| `local/learner/aicheck.php` | `public_html/local/learner/aicheck.php` |
| `local/learner/aireport.php` | `public_html/local/learner/aireport.php` (NEW) |
| `local/learner/markallocation.php` | `public_html/local/learner/markallocation.php` |
| `local/learner/tutor.php` | `public_html/local/learner/tutor.php` (FIXED) |
| `local/learner/tutorlearners.php` | `public_html/local/learner/tutorlearners.php` (FIXED) |
| `local/learner/version.php` | `public_html/local/learner/version.php` |
| `local/admindashboard/index.php` | `public_html/local/admindashboard/index.php` |
| `local/admindashboard/templates/admindash.mustache` | `public_html/local/admindashboard/templates/admindash.mustache` |
| `plagiarism/gptzero/classes/api.php` | `public_html/plagiarism/gptzero/classes/api.php` |

### Step 2: Run Database Upgrade

Navigate to:
```
https://epearlacademy.com/admin/index.php
```
Moodle will detect the version change and prompt for upgrade. Click **Upgrade**.

Or run via SSH:
```bash
php admin/cli/upgrade.php --non-interactive
```

### Step 3: Purge Caches

Navigate to:
```
Site Administration > Development > Purge all caches
```
Click **Purge all caches**.

Or via SSH:
```bash
php admin/cli/purge_caches.php
```

### Step 4: Initialize Word Usage Counter

Set the current GPTZero usage (from your GPTZero dashboard):

Via SSH:
```bash
php admin/cli/cfg.php --component=plagiarism_gptzero --name=words_used --set=1968
php admin/cli/cfg.php --component=plagiarism_gptzero --name=scans_count --set=4
```

Replace `1968` and `4` with your actual current usage from https://app.gptzero.me

---

## Verification Checklist

After deployment, verify:

- [ ] **Marking Page** (`/local/learner/markallocation.php?action=mark`)
  - AI Check button appears for unscanned submissions
  - View Report button appears for previously scanned submissions
  - Clicking AI Check shows confirmation dialog, then scans
  - Result badge shows (Human/AI/Mixed with percentage)

- [ ] **AI Report Page** (`/local/learner/aireport.php?id={submissionid}`)
  - Page loads without errors
  - Shows learner name, assignment, course
  - Displays AI probability score circle
  - Shows sentence-level highlighting
  - Download Report button visible and functional (opens print dialog)

- [ ] **Admin Dashboard** (`/local/admindashboard/`)
  - GPTZero quota card visible in alerts section
  - Shows words used / 300,000
  - Shows scan count and last scan time
  - Progress bar reflects usage percentage
  - AI Detection Checks panel shows all scans with View Report buttons

---

## GPTZero API Configuration

If not already configured:

1. Go to: Site Administration > Plugins > Plagiarism > GPTZero
2. Enable the plugin
3. Enter API Key: `6f40c006c9a6486a988ea57d981a1aff`
4. Save changes

---

## Monthly Quota Reset

GPTZero resets quotas monthly. When reset occurs, run:

```bash
php admin/cli/cfg.php --component=plagiarism_gptzero --name=words_used --set=0
php admin/cli/cfg.php --component=plagiarism_gptzero --name=scans_count --set=0
```

---

## Troubleshooting

### "Error reading from database" on Tutor Page
Fixed in v1.2.2. The error was caused by hardcoded `mdl_` table prefixes in `tutor.php` and `tutorlearners.php` that didn't match production's `r6ua_` prefix. Upload the fixed files and purge caches.

### "Course does not correspond to cm" Error
Fixed in this version. If still occurring, purge caches.

### AI Check Returns Error
- Check GPTZero API key is valid
- Check internet connectivity from server
- Review Moodle debug log for details

### Word Count Shows 0
Run Step 4 to initialize from your GPTZero dashboard.

### Report Page Blank
- Check submission exists
- Check user has `mod/assign:grade` capability
- Purge caches

---

## File Summary

| File | Purpose |
|------|---------|
| `aicheck.php` | AJAX endpoint for manual AI scanning |
| `aireport.php` | Detailed report with sentence highlighting + Download/Print |
| `markallocation.php` | Marking page with AI Check/View Report buttons |
| `tutor.php` | Tutor management page (FIXED: database prefix) |
| `tutorlearners.php` | Tutor's learners page (FIXED: database prefix) |
| `version.php` | Plugin version (1.2.2) |
| `admindashboard/index.php` | Dashboard with GPTZero quota card |
| `admindash.mustache` | Dashboard template with quota UI |
| `api.php` | GPTZero API wrapper (standard endpoints) |

---

## Support

For issues, contact the development team or check GitHub:
https://github.com/Integer-Training/moodlebackup
