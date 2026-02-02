# Tutor-Learner Assignment Feature

## Goal
"Can I add learners to the tutors please and they can only see their assigned learners"

## Current State

The system already uses **Moodle groups** for tutor-learner pairing:
- Tutors and learners are placed in the same group within a course
- `tutordash.php`, `markallocation.php`, `inactive.php` already filter by group membership
- **But**: No UI to assign learners to tutors during registration, the reassign page has hardcoded tutor IDs, and `view.php` (main learner list) shows ALL learners regardless of tutor

## What Needs to Change

### 1. Add Tutor Assignment to Learner Registration (`users.php`)

**File:** `local/learner/users.php`

Currently: Manager creates user, enrolls in courses, sends email. No group/tutor assignment.

Change: Add a "Assign to Tutor" dropdown to the registration form. After enrollment, automatically add the learner to the selected tutor's group in each enrolled course.

**Steps:**
- Add tutor dropdown to `filters_sales_form` in `local/learner/filter_form.php`
  - Query: Get all users with `teacher` role in the selected courses
  - Use Moodle autocomplete element for the dropdown
- After course enrollment in `users.php`, for each enrolled course:
  1. Find the group where the selected tutor is a member (in that course)
  2. If no group exists, create one named "{Tutor Firstname} {Tutor Lastname}'s Group"
  3. Add the new learner to that group via `groups_add_member()`

### 2. Fix Reassign Page — Dynamic Tutor Dropdown (`reassign.php`)

**File:** `local/tutors/reassign.php`

Currently: Lines 213-223 have 8 hardcoded tutor user IDs in the dropdown.

Change: Query tutors dynamically from `role_assignments` where `role.shortname = 'teacher'`.

**Steps:**
- Replace hardcoded `<option>` list with a dynamic query:
  ```sql
  SELECT DISTINCT u.id, u.firstname, u.lastname
  FROM {role_assignments} ra
  JOIN {role} r ON r.id = ra.roleid
  JOIN {user} u ON u.id = ra.userid
  WHERE r.shortname = 'teacher' AND u.deleted = 0
  ORDER BY u.firstname, u.lastname
  ```
- Build `<option>` tags from query results
- Fix SQL injection: parameterize all queries (lines 74, 84, 86, 101, 114, 188)

### 3. Filter Main Learner List by Tutor (`view.php`)

**File:** `local/learner/view.php`

Currently: Shows ALL learners to everyone with `local/learner:view` capability.

Change: When a tutor (teacher role) accesses the page, filter to only show their assigned learners (via group membership). Managers continue to see all learners.

**Steps:**
- Check if current user has `teacher` role (not manager)
- If tutor: Add group membership JOIN to the learner query:
  ```sql
  JOIN {groups_members} gm_t ON gm_t.groupid = gm_s.groupid
  WHERE gm_t.userid = :tutorid
  ```
- If manager: No filter change (see all learners as before)
- Add a "Tutor" filter dropdown to `filter_form` so managers can filter by tutor

### 4. Add Tutor Column to Learner List

**File:** `local/learner/view.php` + its template/DataTable

Show which tutor each learner is assigned to in the main list. This helps managers see unassigned learners.

**Steps:**
- Add a "Tutor" column to the DataTable
- Query tutor name via group membership JOIN
- Show "Unassigned" badge for learners not in any tutor's group (helps managers catch gaps)

### 5. Grant Tutors Access to Sidebar Navigation

**File:** `local/learner/lib.php`

Currently: Only users with `local/learner:view` capability see sidebar links (managers only).

Change: Add sidebar links for tutors (teacher role) pointing to:
- My Dashboard (`tutordash.php`)
- Awaiting Review (`markallocation.php?action=mark`)
- My Learners (`view.php` — which now filters to their learners)

**Steps:**
- In `local_learner_extend_navigation()`, check for teacher role
- Add navigation nodes for tutor-specific pages
- Keep existing manager links unchanged

## Files to Modify

| File | Change |
|------|--------|
| `local/learner/users.php` | Add group assignment after enrollment |
| `local/learner/filter_form.php` | Add tutor dropdown to registration form |
| `local/tutors/reassign.php` | Replace hardcoded tutors with dynamic query, fix SQL injection |
| `local/learner/view.php` | Add tutor filter for tutor role, add tutor column |
| `local/learner/lib.php` | Add tutor sidebar navigation |
| `local/learner/lang/en/local_learner.php` | New strings: assigntutor, unassigned, mylearners, etc. |

## Implementation Order

1. **reassign.php** — Fix hardcoded tutors (quick win, immediately useful)
2. **filter_form.php + users.php** — Add tutor assignment to registration
3. **view.php** — Tutor filtering + tutor column
4. **lib.php** — Tutor sidebar navigation
5. **lang strings** — Throughout

## Verification

1. Register a new learner with a tutor selected — confirm learner appears in tutor's group in all enrolled courses
2. Log in as tutor — confirm `view.php` only shows assigned learners
3. Log in as manager — confirm `view.php` shows all learners with tutor column
4. Open `reassign.php` — confirm tutor dropdown is dynamic (no hardcoded IDs)
5. Reassign a learner to different tutor — confirm group membership updates
6. Check `tutordash.php`, `markallocation.php`, `inactive.php` still work correctly for tutor
7. Test with learner assigned to multiple courses with same tutor — all groups created
8. Test unassigned learner shows "Unassigned" in manager view
