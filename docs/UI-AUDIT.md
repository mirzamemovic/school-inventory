# UI audit

A full pass over the interface once the features were in: what was wrong, and
what changed. Same system, same workflows, same rules. Every screen was checked
at 320px, 375px and 1280px wide with no console errors, and the test suite stayed
green throughout.

---

## 1. Findings and what was done

Categories: Cosmetic · Consistency · Usability · Accessibility ·
Mobile · Information hierarchy. Every item below was fixed unless marked otherwise.

### Accessibility

| Finding | Fix |
|---|---|
| Secondary grey text (`grey-500`, `#82827d`) was 3.9:1 on white, below the 4.5:1 minimum. It was used across the whole app. | Token darkened to `#6b6b66`: 5.4:1 on white, 4.6:1 on grey chips. `grey-600` shifted to keep the scale. |
| Keyboard focus ring was navy, so it was invisible on the navy header (search box, sign out, school name). | A white ring inside the header (`.on-dark`). |
| The paging control used the default Tailwind palette, which this app clears, and `focus:outline-none`. Its buttons were unstyled and keyboard focus was invisible. | A replacement paging view: Previous · Page X of Y · Next. |
| Search results removed the focus outline. | An inset focus ring instead. |
| Room, category, status and class filters had no labels, only placeholders. The Move, Assign and Change status selects had none either. The retire input used "Notes" as a placeholder label. | Every control has a real label (visible or screen-reader only). |
| Field errors and help text were not linked to their inputs. | `aria-invalid` plus `aria-describedby` on input, select and textarea inside `<x-field>`. |
| Needs-attention rows showed urgency with a coloured dot only. | Each row carries a word ("Report", "Warranty", "License"…) in a status-coloured badge. |
| The class share link input had no label. | Labelled by its heading. |
| Label start-position cells were 36px tall. | 44px, and shaped like the real 70 × 37 mm sticker. |

### Mobile

| Finding | Fix |
|---|---|
| The five-item nav wrapped, putting "Manage" on its own row. The header's school name took two lines. | One row down to 320px. The school name truncates. |
| The search results panel hung off the left edge of the screen on phones. | The panel spans the header on phones and anchors under the box from `sm` up. |
| Supplies rows collapsed into one word per line. | Name and location first, then − / count / + on their own line. |
| People, licence, room, title and category rows crushed the name beside the actions. | Name block first. Actions wrap below it. |
| The report queue truncated the asset name to about 6 characters. | Asset name on its own line; badges on the right. |
| Report detail put a long history above the three buttons. | Phone order: problem → actions → item → history. Desktop keeps two columns. |
| Asset detail put status and condition at the bottom of a long list, and actions after everything. | Phone order: where, who and state → actions → details → label. |
| The class issue table scrolled sideways and lost the student names. | Name column is sticky. |
| The report tab strip showed a stray scrollbar and clipped at 320px. | Fixed. |
| Search input text under 16px makes iOS zoom in. | 16px on phones. |

### Information hierarchy

- **Asset detail:** room, who is answerable for it, status and condition sit in one panel at the top. Status and condition are side by side and labelled, never merged. Then the actions. Then "What it is". Then "What it cost", with "Not known" funding hidden so an empty section says so honestly. Retirement date and method now show for retired items. The tag is in the page header.
- **Report detail:** status, reference and the plain-words timing ("Reported 4 days ago · Seen after 6 hours · Still open") are in the page header. The description is large. The note box and both closing buttons are grouped under "Close this report". The item card shows condition as well as status.
- **Report queue:** each row shows the first line of the problem ("It does not turn on"), plus "Not seen yet" for reports no admin has opened. Both are existing data.
- **Home:** the open-report banner says what tapping it does. The four totals open the matching filtered asset list. "Nothing needs your attention" is shown as a green goal state, not a grey placeholder.
- **Asset list:** condition shows when it is not "Good". Make and model only show when the name doesn't already say them.
- **Licence list:** seats and renewal type join the vendor line. The expiry badge is solid for expired and expiring soon.

### Usability

| Finding | Fix |
|---|---|
| **Data loss:** a note typed on the report page was silently dropped when closing with *Not a problem*. It only went with *Fixed*, although the server accepts it for both. | One form; *Not a problem* uses `formaction`. Covered by a new test. |
| A validation error inside the collapsed Purchase group was invisible. | The group opens itself when one of its fields has an error. |
| Collapsible groups had no sign they could open (Chrome drops the triangle on `display:flex` summaries). | A chevron on every disclosure. |
| No sub-page of Manage highlighted Manage in the nav; intake and labels didn't highlight Assets. | The highlight follows the section. Covered by a new test. |
| No way back up from sub-pages except the top nav. | A "← Rooms", "← Assets" link in the same place on every sub-page. |
| On slow wifi, buttons that seem to do nothing get tapped repeatedly. | `forms.js`: the first tap marks the button busy ("Sending…", "Saving…") and ignores repeats. It unlocks after 8 s so PDF downloads never leave a dead button. HTMX forms are untouched. |
| Returns & debts had no print button, although the `books.print` string existed. | "Print this list" (ticks become ☐ boxes on paper). |
| Books, Supplies and Categories each showed an always-open "add" form competing with the list for primary. | Folded into a disclosure. It opens itself when the list is empty or the last attempt failed. |
| The label page had two navy primaries. | Whichever job is waiting is the primary: print queue if there is one, otherwise blank labels. |

### Consistency

- One button vocabulary in `app.css` (`btn-primary`, `btn-secondary`, `btn-danger`, `link`, `link-action`). It lives in the components layer, so utilities written next to it always win. It replaces about 40 copies of the same long class strings.
- `<x-textarea>` replaces five hand-styled textareas. `<x-empty-state>` now accepts a class and wraps action links.
- The required marker is grey, not red. Red means *broken* in this app (spec §9).
- Retire and Delete sit last, apart, in red. Never navy.
- The Books class picker button says "Show class" instead of "Search".

### Copy (meaning unchanged)

- `reports.queue_empty` was *"Nothing has been reported. That is the goal state, not a placeholder."* That is a developer comment that reached users, and it was wrong on the In progress and Closed tabs. Each tab now has its own sentence.
- "This item has been reported before" appeared above "This is the first report for this item". The heading now reads "Earlier reports for this item".
- The public report form label "your name" (the lowercase validation attribute) now reads "Your name".
- "1 years" is now "1 year". Expected life shows as "5 years" rather than "5".

---

## 2. Questions — possible workflow changes, NOT implemented

Each of these would change behaviour, not just presentation, so it waits for a decision.

1. **"Who are you?" is free text; the spec says "a name chosen from a list".** §3.1 and §7A describe a searchable list of people with a free-text fallback. The build asks for a typed name only. A list on a public, unauthenticated page would show every staff and student name to anyone who scans a sticker. That runs against the enumeration defence in §13 and the data protection rules in §17. Keep free text, or build the list?
2. **"Print this one now" always starts at sticker 1.** §8 says "sheet-position picker on every print". Adding the picker here puts a step in the one-off print path. Add it, or keep one tap?
3. **"Working on it" stays visible on a report that is already In progress.** Pressing it again logs a second "started working on it" event. The page now says *"Marked as being worked on 3 days ago"* above it, but the button is still there. Hide it once the report is in progress?
4. **Overdue inspections has no permanent way in.** It is only reachable from Home when something is overdue. Add it to the Manage list, or link it from Assets?
5. **Asset list rows have no thumbnail.** §8 lists "thumbnail" first in the row. Photos are served full size (up to 1600px), so 50 thumbnails is heavy on school wifi without a resized copy. Add thumbnails (and a small resized image), or leave them out?
6. **No public "search by tag" box.** §6 says "always provide a search-by-tag input" for scratched stickers. Today that exists only for admins (header search, bulk intake). A public lookup would be a new public route. Wanted?
