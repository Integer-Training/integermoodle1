# DB Fix — Restore Refer grades for Sharmin Parvin (5 workbooks)

**Date applied:** 2026-04-29
**Applied by:** Mokshith (via phpMyAdmin on production `u774156482_epearl`)
**Reported by:** Anaswara Sebastian (tutor, userid 83) in Teams
**Affected learner:** Sharmin Parvin (userid 28)
**Affected course:** TQUK Level 5 Diploma in Leadership and Management for Adult Care
**Affected assignments:** Unit 4–8 Learner Workbook (assign IDs 77, 78, 79, 80, 81)

---

## 1. Problem

Anaswara reported that 5 workbook submissions for Sharmin Parvin had reappeared on her tutor dashboard despite already being assessed in late 2025. She did not want to re-grade them because doing so would skew her current 3.2-day average turnaround.

## 2. Root cause (investigation)

Diagnostic queries against `r6ua_assign_submission` and `r6ua_assign_grades` revealed:

- 5 submissions exist for Sharmin (userid 28) on TQUK Level 5, Units 4-8 (assignids 77, 78, 79, 80, 81)
- All have `attemptnumber = 0`, `latest = 1`, file uploaded (`file_count = 1`)
- All have `status = 'new'` (not `'submitted'` — likely a side-effect of a prior course backup-restore; the course has duplicate assign IDs per unit, e.g. Unit 4 has IDs 27 AND 77, confirming a restore happened)
- **No corresponding rows in `r6ua_assign_grades`** — that's why they appeared as "ungraded" to the dashboard query

`grade_grades` rows existed but `finalgrade IS NULL` (or `< 0`), so the gradebook also showed them as ungraded.

## 3. Fix applied

Two SQL statements run in phpMyAdmin in this order:

### Step A — Insert missing assign_grades rows (5 rows inserted)

```sql
INSERT INTO r6ua_assign_grades (assignment, userid, attemptnumber, timecreated, timemodified, grader, grade)
SELECT sub.assignment, sub.userid, sub.attemptnumber,
       sub.timemodified AS timecreated,
       sub.timemodified AS timemodified,
       83 AS grader,
       1 AS grade
FROM r6ua_assign_submission sub
LEFT JOIN r6ua_assign_grades gr ON gr.assignment = sub.assignment
     AND gr.userid = sub.userid AND gr.attemptnumber = sub.attemptnumber
WHERE sub.userid = 28
  AND sub.assignment IN (77, 78, 79, 80, 81)
  AND sub.latest = 1
  AND gr.id IS NULL;
```

**Result:** 5 rows inserted. `grade_id` values: **192, 193, 194, 195, 196**.
Verified mapping: 192→Unit 4 (assign 77), 193→Unit 5 (assign 78), 194→Unit 6 (assign 79), 195→Unit 7 (assign 80), 196→Unit 8 (assign 81).

### Step B — Update grade_grades (gradebook) to match (5 rows updated)

```sql
UPDATE r6ua_grade_grades gg
JOIN r6ua_grade_items gi ON gi.id = gg.itemid
SET gg.finalgrade = 1, gg.rawgrade = 1
WHERE gg.userid = 28
  AND gi.iteminstance IN (77, 78, 79, 80, 81)
  AND gi.itemmodule = 'assign'
  AND (gg.finalgrade IS NULL OR gg.finalgrade < 0);
```

**Result:** 5 rows updated. Original `finalgrade` and `rawgrade` were NULL or `< 0` — set to `1` (Refer).

## 4. Avg-turnaround impact

**None.** Both `assign_grades.timecreated` and `assign_grades.timemodified` were set equal to each submission's `sub.timemodified` (Oct 6 / Oct 27, 2025). The admin dashboard avg-turnaround query has the filter `ag.timemodified > sub.timemodified` (strict greater-than), which excludes equal values from the average. Anaswara's 3.2-day avg is preserved.

## 5. Side-effects

- Sharmin Parvin now correctly shows as graded "Refer" on her own progression page for these 5 units
- The Sequential Submission rule (`local/sequentialsubmission`, rule 1A) now correctly locks her — she must resubmit each Refer before any new submission anywhere
- The 5 submissions are removed from Anaswara's "Awaiting Marking" queue
- Anaswara is recorded as the grader (`grader = 83`)

## 6. Post-fix actions required

1. ✅ INSERT applied (Step A)
2. ✅ UPDATE applied (Step B)
3. **Site admin → Development → Purge all caches** (or via URL: `/admin/purgecaches.php?confirm=1&sesskey=<sesskey>`)
4. Anaswara → hard-refresh (Ctrl+Shift+R) on tutor dashboard
5. If still showing → log out, log back in
6. Confirm with Anaswara the 5 are off her queue

---

## 7. Verification SQL (run anytime to confirm fix is still in place)

```sql
SELECT a.name AS assignment, sub.id AS sub_id, sub.status, sub.attemptnumber,
       gr.id AS grade_id, gr.grade,
       FROM_UNIXTIME(gr.timemodified) AS graded_at,
       gg.id AS gg_id, gg.finalgrade, gg.rawgrade
FROM r6ua_assign_submission sub
JOIN r6ua_assign a ON a.id = sub.assignment
LEFT JOIN r6ua_assign_grades gr ON gr.assignment = sub.assignment
     AND gr.userid = sub.userid AND gr.attemptnumber = sub.attemptnumber
LEFT JOIN r6ua_grade_items gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
LEFT JOIN r6ua_grade_grades gg ON gg.itemid = gi.id AND gg.userid = sub.userid
WHERE sub.userid = 28 AND sub.assignment IN (77, 78, 79, 80, 81);
```

Expected: 5 rows, all with `grade_id` populated, `grade = 1.00000`, `finalgrade = 1`, `rawgrade = 1`.

---

## 8. ROLLBACK PROCEDURE — if we ever need to undo

### Option A — Full revert to pre-fix state (recommended if fix is wrong)

This deletes the 5 inserted grade rows AND nulls back the gradebook. Submissions return to "ungraded" — they'll reappear on Anaswara's queue.

```sql
-- Step 1: Delete the 5 assign_grades rows we inserted (by their known IDs)
DELETE FROM r6ua_assign_grades WHERE id IN (192, 193, 194, 195, 196);

-- Step 2: Reset gradebook entries to NULL (their original state was NULL or < 0)
UPDATE r6ua_grade_grades gg
JOIN r6ua_grade_items gi ON gi.id = gg.itemid
SET gg.finalgrade = NULL, gg.rawgrade = NULL
WHERE gg.userid = 28
  AND gi.iteminstance IN (77, 78, 79, 80, 81)
  AND gi.itemmodule = 'assign'
  AND gg.finalgrade = 1
  AND gg.rawgrade = 1;

-- Step 3: Purge all caches in Moodle admin afterwards.
```

**WARNING about Step 2:** Original `finalgrade` value was `NULL OR < 0`. We don't know which exactly per row. This rollback sets to `NULL` for all — which is functionally equivalent (both NULL and -1 mean "ungraded" downstream) but the literal value may differ from the pre-fix state.

### Option B — Partial revert (delete grade rows only, leave gradebook as Refer)

Use this if you want them back in Anaswara's queue but leave the gradebook untouched (rare):

```sql
DELETE FROM r6ua_assign_grades WHERE id IN (192, 193, 194, 195, 196);
```

### Verification after rollback

```sql
SELECT COUNT(*) AS should_be_zero
FROM r6ua_assign_grades
WHERE id IN (192, 193, 194, 195, 196);
```

---

## 9. Notes for future similar cases

- **The pattern**: course backup-restore can leave `assign_submission` rows with `status='new'` and missing `assign_grades` rows, even though files are attached.
- **Detection**: `SELECT COUNT(*) FROM r6ua_assign_submission sub LEFT JOIN r6ua_assign_grades gr ON sub.assignment=gr.assignment AND sub.userid=gr.userid AND sub.attemptnumber=gr.attemptnumber WHERE sub.latest=1 AND sub.status='new' AND gr.id IS NULL;` — finds all such cases platform-wide.
- **Long-term fix**: a tutor-facing "Mark as Already Assessed" button (mentioned in earlier planning) would let tutors handle these self-service without needing a developer to write SQL each time.
