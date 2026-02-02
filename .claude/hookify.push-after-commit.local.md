---
name: push-after-commit
enabled: true
event: bash
pattern: git\s+commit
action: warn
---

**Reminder: Push to remote after committing**

You must run `git push origin main` after every commit to keep the GitHub backup in sync:
`https://github.com/Integer-Training/moodlebackup.git`

Do NOT finish the task without pushing.
