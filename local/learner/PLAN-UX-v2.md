# Integer Training LMS — UX Overhaul Plan

**Focus areas (in order):** Navigation → Grading → Ease of Learning → Clear Language

---

## What's Actually Wrong

After auditing the codebase, the problems fall into four categories:

### 1. Nobody can find anything
- Tutors have **zero sidebar links**. They must know URLs or stumble onto the dashboard.
- Learners see stat counts ("3 Due Assignments") but **can't click them to see which ones**.
- Features like mark allocation, inactive learners, and due dates are buried behind dashboard cards with no visual affordance that they're clickable.
- (Historical — fixed at fork) Breadcrumbs were hardcoded to a single domain — fragile and not dynamic.

### 2. Grading is fragmented
- Two parallel grading workflows exist: custom mark allocation table AND Moodle's native grader. Tutors don't know which to use.
- The "Grade" button in the mark allocation table is **an icon with no label** — just an edit icon that opens Moodle's grading interface in a confusing context switch.
- No "days waiting" column, so tutors can't prioritize. No urgency colors.
- After grading in Moodle's interface, there's no way back to the custom list — dead end.

### 3. The language is confusing
- The plugin name is literally misspelled: **"Leaner Management"** instead of "Learner Management" (`lang/en/local_learner.php:25`).
- Learner dashboard shows "Awaiting Marking" — but **learners don't mark things**. This is tutor jargon shown to students.
- "Imminent (3 days)" is jargon. "Refer" as a grade result is unexplained. "Suspend/Unsuspend Log Report" is developer-speak.
- Inconsistent: "learner" vs "student", "tutor" vs "teacher", "programme" vs "course".

### 4. Critical security holes
- **SQL injection** in at least 6 locations — `$fromform->user` concatenated directly into SQL strings without parameterization.
- This is the most urgent fix regardless of UX plans.

---

## The Plan

### Phase 1: Navigation — "Everyone can find everything"

**Principle:** If a feature exists, it should be reachable in 2 clicks or fewer from the sidebar.

#### 1A. Role-Based Sidebar Navigation

**File:** `local/learner/lib.php` — rewrite `local_learner_extend_navigation()`

Current state: Only managers see 2 links (Learners, Admin Dashboard).

New structure:

**For Managers (admin role):**
```
📋 Learner Management
   ├── All Learners          → /local/learner/view.php
   ├── Register New Learner  → /local/learner/users.php
   ├── Inactive Learners     → /local/learner/inactive.php
   └── Admin Dashboard       → /local/learner/admindash.php

👥 Tutor Management
   ├── All Tutors            → /local/learner/tutor.php
   ├── Marking History       → /local/tutors/view.php
   └── Reassign Learners     → /local/tutors/reassign.php
```

**For Tutors (teacher/editingteacher):**
```
📊 My Dashboard              → /local/learner/tutordash.php
📝 Awaiting My Review        → /local/learner/markallocation.php?action=mark
⏰ Overdue Submissions       → /local/learner/markallocation.php?action=overdue
👥 My Learners               → /local/learner/tutorlearners.php?id={USERID}
```

**For Learners (student):**
```
🏠 My Dashboard              → /local/learner/mydash.php
📬 My Submissions            → (new page — see Phase 2)
📊 My Grades                 → (new page — see Phase 2)
💬 Messages                  → /local/mail/view.php
📞 Contact Support           → /local/learner/contact.php
```

**Implementation:**
- Add capability checks per role in `lib.php`
- Use `$PAGE->navigation->add()` with proper `navigation_node::TYPE_CUSTOM`
- Each link has a clear, action-oriented label (not just nouns)

#### 1B. Make Dashboard Stats Clickable

**Files:** `mydash.mustache`, `tutordash.mustache`

Every stat card becomes a link:

| Card | Currently | Links To |
|------|-----------|----------|
| Learner: "Due Assignments" | Read-only number | `/mod/assign/index.php` filtered to user's pending |
| Learner: "Completed" | Read-only number | Gradebook filtered to completed |
| Learner: "Awaiting Feedback" | Read-only number | Submissions list filtered to submitted |
| Tutor: "Awaiting Review" | Hidden link (data-url) | `/local/learner/markallocation.php?action=mark` |
| Tutor: "Overdue" | Hidden link | `/local/learner/markallocation.php?action=overdue` |

Add visual affordance: `cursor: pointer`, hover lift effect, right-arrow icon on hover.

#### 1C. Fix Breadcrumbs

**Files:** All templates with hardcoded breadcrumbs

Replace:
```html
<a href="{{{wwwroot}}}/local/learner/view.php">Learners</a>
```

With:
```mustache
<a href="{{{learners_url}}}">All Learners</a> › {{{fullname}}}
```

Pass `{{{learners_url}}}` from PHP using `(new moodle_url(...))->out(false)`.

---

### Phase 2: Grading — "One clear workflow"

**Principle:** A tutor should be able to see what needs grading, grade it, and move to the next one — without leaving the flow.

#### 2A. Redesign Mark Allocation Table

**File:** `local/learner/markallocation.php`

Current problems: no sorting, search is hidden, icon-only buttons, no urgency indicators.

New table design:

| Priority | Learner | Course | Assignment | Submitted | Waiting | Action |
|----------|---------|--------|------------|-----------|---------|--------|
| 🔴 | Jane Smith | L3 Business | Unit 2 Report | 15 Jan | 17 days | [Grade →] |
| 🟡 | Tom Brown | L3 IT | Project Brief | 29 Jan | 3 days | [Grade →] |
| 🟢 | Alex Lee | L3 Health | Reflection | 31 Jan | 1 day | [Grade →] |

Changes:
1. **Add "Days Waiting" column** — calculated from `assign_submission.timecreated`
2. **Add priority indicator** — Red (>14 days), Yellow (3–14 days), Green (<3 days)
3. **Default sort by "Days Waiting" descending** — oldest first
4. **Enable search** — remove the line hiding DataTables filter (line 281: `$('.dataTables_filter').css('display','none')`)
5. **Replace icon button with text** — `[Grade →]` instead of ambiguous edit icon
6. **Add "Return to List" link** — pass a return URL to Moodle's grading interface so tutors can come back
7. **Increase default page size** — 25 instead of 15

#### 2B. Add "Days Waiting" Calculation

```php
$waiting = floor((time() - $submission->timecreated) / 86400);
$priority = 'green';
if ($waiting > 14) $priority = 'red';
else if ($waiting >= 3) $priority = 'yellow';
```

#### 2C. Learner Grades View

**New page:** `local/learner/mygrades.php`

Learners currently have no clear way to see all their grades across courses. Create a simple page:

| Course | Assignment | Submitted | Result | Feedback |
|--------|------------|-----------|--------|----------|
| L3 Business | Unit 2 Report | 15 Jan | ✅ Pass | [View Feedback] |
| L3 IT | Project Brief | — | ⏳ Pending | Submitted 29 Jan |
| L3 Health | Reflection | — | 📝 Needs Resubmit | [View Feedback] |

This pulls from `assign_grades` + `assign_submission` tables. The "Result" column shows the scale value (Pass/Refer) in clear, learner-friendly language.

#### 2D. Replace "Refer" with Human Language

In the grades display, translate grading jargon:

| Internal Term | What Learners See |
|---------------|-------------------|
| Pass | ✅ Pass |
| Refer | 📝 Needs Revision — please resubmit |
| N/A | ⏳ Pending Review |
| No submission | — Not yet submitted |

---

### Phase 3: Ease of Learning — "The student never feels lost"

**Principle:** At any point, a learner should know: Where am I? What should I do next? How am I doing?

#### 3A. Learner Dashboard Redesign

**Files:** `mydash.php`, `mydash.mustache`

Current dashboard is a wall of numbers with no actionable context. Redesign as three sections:

**Section 1: "What needs your attention"** (top priority)
```
┌──────────────────────────────────────────────────┐
│  📌 2 assignments due this week                  │
│                                                  │
│  Unit 2 Report — L3 Business — Due in 3 days     │
│  [Go to Assignment →]                            │
│                                                  │
│  Reflection Essay — L3 Health — Due tomorrow      │
│  [Go to Assignment →]                            │
└──────────────────────────────────────────────────┘
```

**Section 2: "Your progress"** (motivation)
```
┌──────────────────────────────────────────────────┐
│  L3 Business          ████████████░░  8/12 done  │
│  L3 IT                ██████░░░░░░░░  5/12 done  │
│  L3 Health            ██████████████  Complete ✓  │
└──────────────────────────────────────────────────┘
```

**Section 3: "Recent results"** (feedback loop)
```
┌──────────────────────────────────────────────────┐
│  ✅ Unit 1 Essay — Pass — Marked 28 Jan          │
│  📝 Project Brief — Needs Revision — 25 Jan      │
│     "Good analysis but missing conclusion..."     │
│     [View Full Feedback →]  [Resubmit →]         │
└──────────────────────────────────────────────────┘
```

This replaces the current generic stats cards + unhelpful chart.

#### 3B. Kill Misleading Metrics

The "Hours Spent" chart (`mydash.php:154`) only counts H5P activity — not actual study time. This misleads learners into thinking they've spent X hours when it's only tracking one module type.

**Remove the hours chart entirely** or replace it with accurate data:
- Total assignment submissions this month
- Days since last login
- Completion percentage across all courses

#### 3C. Course Progress Bars

For each enrolled course on the dashboard, show:
- Total activities / completed activities
- Next upcoming deadline
- Tutor name (so learner knows who to contact)

Use Moodle's `completion_info` API:
```php
$completion = new completion_info($course);
$activities = $completion->get_activities();
$completed = count(array_filter($activities, fn($a) =>
    $completion->get_data($a, true, $USER->id)->completionstate > 0
));
$total = count($activities);
$pct = $total > 0 ? round(($completed / $total) * 100) : 0;
```

#### 3D. Mobile-First Templates

Current templates use hardcoded `margin-right: 700px` and don't stack on mobile.

- Replace all fixed margins with responsive Bootstrap utilities
- Use `col-12 col-md-6 col-lg-3` patterns for stat cards
- Tables get horizontal scroll wrapper on mobile: `<div class="table-responsive">`
- Form fields stack vertically on small screens

---

### Phase 4: Clear Language — "Every word earns its place"

**Principle:** Write for a 16-year-old learner who's never used an LMS before. No jargon. No ambiguity. No developer-speak.

#### 4A. Terminology Standardization

Decide once, use everywhere:

| Concept | Old (inconsistent) | New (standard) |
|---------|-------------------|----------------|
| Person studying | learner / student / user | **Learner** |
| Person teaching | tutor / teacher / grader / assessor | **Tutor** |
| Course of study | course / programme / approved programme | **Course** |
| Work submitted | assignment / submission / assessment | **Assignment** |
| Grade given | grade / mark / result / score | **Result** |
| Needs redo | Refer / fail / referred | **Needs Revision** |
| Inactive | suspended / unsuspended / inactive | **On Hold** |

#### 4B. Language String Rewrites

**File:** `local/learner/lang/en/local_learner.php`

| Old | New | Why |
|-----|-----|-----|
| `'pluginname' = 'Leaner Management'` | `'pluginname' = 'Learner Management'` | Typo fix |
| `'imminent' = 'Imminent (3 days)'` | `'imminent' = 'Due Within 3 Days'` | Plain language |
| `'markallocation' = 'Mark Allocation'` | `'markallocation' = 'Work Awaiting Review'` | Student-friendly |
| `'overdue' = 'Overdue Assignments'` | `'overdue' = 'Overdue — Needs Attention'` | Action-oriented |
| (tutor dashboard) "Awaiting Marking" | "Awaiting Your Review" | Clarifies whose action is needed |
| (learner dashboard) "Awaiting Marking" | "Submitted — Awaiting Feedback" | Tells learner their part is done |
| "Suspend/Unsuspend Log Report" | "Status Change History" | Plain language |
| "Login As" button | "View as This Learner" | Clarifies impersonation action |
| "Refer" grade | "Needs Revision" | Non-punitive, action-oriented |

#### 4C. Error Messages & Empty States

Replace generic errors with helpful guidance:

| Situation | Old | New |
|-----------|-----|-----|
| No assignments due | (empty space) | "You're all caught up. No assignments due right now." |
| No grades yet | (empty table) | "Your tutor hasn't reviewed your work yet. You'll see results here once they do." |
| No courses enrolled | (empty space) | "You're not enrolled in any courses. Contact support if you think this is wrong." |
| Form validation | (no feedback) | Red border + "This field is required" inline message |

#### 4D. Button Labels

Every button should complete the sentence "I want to..."

| Old | New |
|-----|-----|
| Icon-only edit button | "Grade This Assignment" |
| "Submit" (generic) | "Save Changes" or "Submit Assignment" |
| "Cancel" (does nothing) | "Go Back" (with actual navigation) |
| "Email" | "Send Login Details" |
| "Inactive" filter | "Show Learners Not Active in 30+ Days" |

---

## Security Fixes (Do First)

These SQL injection vulnerabilities must be fixed before any UX work, as they're exploitable:

| File | Line | Current | Fix |
|------|------|---------|-----|
| `mydash.php` | 91 | `implode(',',$all_assigns)` | `$DB->get_in_or_equal($all_assigns)` |
| `tutordash.php` | 255 | `implode(',',$allcourses)` | `$DB->get_in_or_equal($allcourses)` |
| `markallocation.php` | 82 | `userid =".$fromform->user."` | Parameterized: `userid = ?` |
| `tutors/view.php` | 82 | `userid =".$fromform->user."` | Parameterized: `userid = ?` |
| `usergrade.php` | 65 | `userid='.$USER->id` | Parameterized: `userid = ?` |
| `mydash.php` | 155 | `userid=".$USER->id` | Parameterized: `userid = ?` |

---

## Implementation Order

```
Week 1:  Security fixes (SQL injection — all 6 files)
         Fix "Leaner" → "Learner" typo
         Add tutor sidebar navigation (lib.php)

Week 2:  Mark allocation table redesign
           - Days waiting column
           - Priority colors
           - Search enabled
           - Text button labels
           - Default sort

Week 3:  Dashboard language rewrite
           - Learner dashboard: "Awaiting Feedback" not "Awaiting Marking"
           - Tutor dashboard: "Awaiting Your Review"
           - All empty states
           - All button labels

Week 4:  Learner dashboard redesign
           - "What needs attention" section
           - Course progress bars
           - Recent results section
           - Remove misleading hours chart

Week 5:  New "My Grades" page for learners
         New "My Submissions" page for learners
         Clickable stat cards on all dashboards

Week 6:  Mobile responsiveness pass
         Breadcrumb fixes
         Form validation
         Accessibility audit (WCAG AA)
```

Each week delivers a visible, testable improvement. Nothing depends on future weeks — each is independently valuable.
