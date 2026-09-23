# Retention and erasure

Two commands. Both are run by hand, both explain themselves before doing
anything, and neither is ever scheduled.

```bash
php artisan data:enforce-retention --dry-run   # list what would go
php artisan data:enforce-retention             # list it, then ask, then do it
php artisan person:erase 42                    # erase one person by id
```

**Neither command is scheduled, deliberately.** Deleting personal records should
not happen quietly at three in the morning with nobody watching. Somebody reads
what is about to go and says yes. A wrong deletion is not recoverable from
inside the application, only from the weekly database backup.

---

## Rule 1 — returned book loans

**A returned loan is deleted at the end of the academic year following the one
it was issued in.**

A book issued in 2024/2025 and given back is kept through the whole of 2025/2026
and removed once that year ends on 15 June 2026.

**Why that long, and not longer.** A loan record answers one question: did this
student give the book back? That question stops mattering once the following
year has also finished, because by then the book has been reissued, counted and
returned again by somebody else. Keeping it beyond that stores a named record of
a child for no purpose anyone can state.

**Why not shorter.** Disputes surface late. A student who claims in October that
they returned a book last June needs the previous year still on file.

**An open loan is never deleted, at any age.** An unreturned book is a live debt
and the record is the only thing that says so. There is no age at which the
system forgets that somebody has school property.

## Rule 2 — people

**A person and all their loans are deleted one year after their leaving date.**

The leaving date is the `left_at` column on `people`. Nobody fills it in:
`PersonObserver` stamps it when somebody is deactivated and clears it if they
are made active again. If somebody leaves, comes back and leaves again, the
clock runs from the most recent departure.

**Why a column and not the audit log.** The log records `person.deactivated`
events and the date could be read from there, but a deletion rule that depends
on log completeness fails silently: a seeded record, a bulk import or a bug in
the deactivation path would mean the rule never fires for that person, with
nothing to indicate it did not. A column can be queried, indexed and seen on the
record. **The append-only log is evidence. It is not an operational data source
for irreversible actions.**

**Why a year.** It spans one full cycle of everything the school does annually —
a September intake, a June book return, an audit. After a year there is no
routine reason to look up somebody who has gone.

**Somebody who left but still holds a book is not deleted.** They are listed
separately under "Past their date, but kept". Deleting the person would cascade
to their loans, and rule 1 forbids deleting an open loan. The debt outlives the
retention period, and that is on purpose.

---

## Erasure

`person:erase {id}` is for a request to be forgotten. It is not deletion — it
replaces a person with `Removed person #{id}` everywhere their name appears.

| Where | What happens |
|---|---|
| `people` | Name becomes the pseudonym, `email` nulled, marked inactive |
| `book_loans` | **Kept**, now pointing at the pseudonymised person |
| `reports` | `reporter_name` replaced |
| `inspections` | `inspector_name` replaced — see the caveat below |
| `asset_events` | `actor_name` replaced, `actor_id` nulled, **`meta` JSON scrubbed** |
| Device tokens, IP addresses | Nulled on `asset_events`, `reports` and `inspections` |
| `blocked_devices` | **Left standing** |

### No audit row is ever deleted

Erasure rewrites columns. It never removes a row from `asset_events`.

Two reasons. A gap in an append-only log is indistinguishable from tampering —
once rows can vanish, the log stops being evidence for anything. And the log is
mostly about equipment: deleting the rows an erased person appears in would
destroy the history of a projector to satisfy a request about a person.

The `AssetEvent` model throws `AuditLogIsAppendOnly` on any update or delete, and
that guard is untouched for every other code path in the application.
`PersonEraser` writes through the query builder instead, which bypasses model
events. That is the one deliberate exception in the codebase, and by construction
it can only rewrite columns, never remove a row.

### The meta JSON is the part that gets missed

Replacing `actor_name` is not enough. Several events store a name *inside* the
JSON:

```
person.added        {"name":"Ana Berisha"}
person.deactivated  {"name":"Ana Berisha"}
asset.assigned      {"person":{"from":"Ana Berisha","to":"Behar Krasniqi"}}
```

Scrubbing is done **by value, never by key**. The eraser walks the structure and
replaces any string equal to the person's name, compared trimmed and
case-insensitively.

Keying on `name` would be wrong in the other direction, because these are also
real events:

```
asset.created       {"name":"Epson projector"}
licence.created     {"name":"Microsoft 365"}
```

Nulling those would destroy equipment and licence history. Both directions are
covered by tests.

The known cost of matching on value: if a person shares a name with an asset or
a licence, that record's name is scrubbed too. Erring towards over-scrubbing is
the right side to be wrong on.

### Inspector names are matched by string, and that is not airtight

`inspections.inspector_name` is free text with **no foreign key to `people`**.
Somebody signs off a fire extinguisher by typing their name. The only way to find
that person's inspections is an exact string match, which fails in both
directions:

- Two staff sharing a surname — the wrong rows match
- "A. Krasniqi" recorded on one visit and "Arben Krasniqi" on another — the right
  rows are missed

**So the command puts a human in the loop.** Before anything is written,
`person:erase` prints every matched inspection with its date, item and the exact
recorded name, and asks for confirmation. A silent fuzzy match on destructive
work would be the wrong design.

**An erasure request should be verified manually against the inspections list.**
If a person's name was recorded inconsistently, search the inspections for the
variants and correct them before running the command.

### Device tokens and IP addresses

Both are nulled. A device token singles out an individual in the same way a
cookie id does, and so does an IP address, which makes both personal data.
Leaving either behind keeps every action by that person linked together and makes
them re-identifiable if they use the same phone again — which would defeat the
erasure while still reporting it as done.

This loses the section 13 forensic trail **for erased people only**. Everybody
else's trail is untouched, and erasure requests will be rare.

**A block list entry keyed to the same token stays.** Blocking a device is a
security control on a device, with its own independent justification. It is not a
record about a person.

### What the erasure records

A `person.erased` event is appended at the end, with counts of what was rewritten
and **no name anywhere in it**. The subject id already identifies which record
was erased; repeating the name would undo the work.

---

## What this does not cover

- **Photos** attached to reports are not touched. Section 13 suggests purging
  photos from reports resolved more than 12 months ago; that is a separate job
  and does not exist yet.
- **Database backups.** Both commands act on the live database only. A person
  erased today is still named in last week's `mysqldump`. Backups age out on
  their own retention cycle, and that cycle is a hosting matter, not an
  application one.
