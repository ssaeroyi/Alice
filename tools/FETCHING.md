# FETCHING.md — Alice Library acquisition guide

Canonical reference for fetching **story sources** and **cover images** during
library curation. Every technique here was earned in a real batch; the
countermeasures exist because their absence caused failures. Read this before
writing any new fetcher — and search this folder plus ALICE_MASTER_PLAN.md for
prior art first (2026-08-23 lesson: a hand-rolled cover fetcher failed 0/10 by
missing one documented detail).

## Policy ladder (which method when)

**Workflow v3 (2026-08-23): ASSISTANT DOES EVERYTHING.** The user no longer
fetches sources or covers; manual condensation was slower than assistant work
because polishing drafts ate the time savings. The assistant runs the whole
pipeline end-to-end: slate → fetch → draft → build → verify → covers → ledger.

1. **Assistant scripts** — `fetch_story_sources.ps1` / `fetch_covers.ps1` in
   this folder are the default path. Rerunnable; they only fetch what is
   missing. Adapt freely for better results (that is what they are for).
2. **Wikisource route** — only for texts PG does not serve well (see below).
3. **Knowledge-first drafting** — last resort for over-long sources: draft
   from plot knowledge, verify ~3 canonical signature lines via targeted grep
   against the source (batch 4/5 strategy, ALICE_MASTER_PLAN :1923).
4. **User fetches manually** — now the EXCEPTION, only when the user offers or
   scripts fail outright.

---

## Stories

### Discovery & verification — gutendex.com

```powershell
# search free (public-domain) books by keyword
https://gutendex.com/books/?search=<urlencoded>&copyright=false
# verify one id's title/author before using it
https://gutendex.com/books/<id>
```

Pacing ~2s between searches, 1.5s between id lookups. Custom UA.

### Download ladder (`tools/fetch_story_sources.ps1`)

| # | URL pattern | Notes |
|---|---|---|
| 1 | `www.gutenberg.org/cache/epub/<id>/pg<id>.txt` | primary |
| 2 | `www.gutenberg.org/ebooks/<id>.txt.utf-8` | fallback |
| 3 | `aleph.gutenberg.org/<d>/<i>/<digits>/pg<id>.txt` | digit-split mirror |

**Throttle behavior:** PG throttles by IP. A burst of quick downloads
triggers 503/504 after ~2 successes; windows last many minutes. Counter:
45s spacing between books, EXISTS-SKIP idempotency (rerun after waiting),
≥20KB size floor + delete-on-fail so error pages never masquerade as books.

**Do not guess other mirrors.** aleph/pglaf/mirrorservice/IA digit-split
layouts were all tried in batch 3 — most 404/503, layouts drift. The ladder
above is what worked.

### Waterloo mirror (batch 9, 2026-08-23 — user-assisted default)

When PG throttles, deliver download links to the user instead of fighting the
throttle. Proven 4/4 in batch 9:

```
https://mirror.csclub.uwaterloo.ca/gutenberg/<split>/<id>/<id>-0.txt
```

`<split>` = ALL-BUT-LAST digit of the id as single dirs (`#12750` →
`/1/2/7/5/12750/`; single-digit-prefix ids collapse accordingly: `#1064` →
`/1/0/6/1064/`, `#2147` → `/2/1/4/2147/`). UK fallback swaps domain to
`www.mirrorservice.org/sites/ftp.ibiblio.org/pub/docs/books/gutenberg`.
Files are byte-identical PG texts (verify START marker + id header). This is
now the DEFAULT sourcing offer per user directive — see ALICE_MASTER_PLAN
RESUME QUEUE item 5.

### Wikisource route (texts PG serves badly)

Batch 3 winning pattern (:1781) for shorts missing from PG as standalone
files. Use `curl.exe`, NOT Invoke-WebRequest:

```powershell
$api = 'https://en.wikisource.org/w/api.php?action=parse&format=json&formatversion=2&prop=text&page=' + [Uri]::EscapeDataString($pageTitle)
curl.exe -sL -A $ua --max-time 60 $api        # then ConvertFrom-Json -> .parse.text
```

Strip `<style>`, `<script>`, HTML comments, `<sup>`; convert `</p>` to
newlines. Slice with a start sentinel regex and end markers (PD boilerplate,
"Retrieved from", "Authority control").

**Traps:** Versions-pages are disambiguation stubs (no text); `#REDIRECT`
pages are common (follow to Collected Works structure); nav junk varies per
page style — eyeball heads before trusting sentinels; transcriptions contain
hyphenation artifacts ("two- and-a-half") — fix at authoring.

### PS 5.1 quirks (all sources)

- `[Net.ServicePointManager]::SecurityProtocol = Tls12` always.
- `Invoke-WebRequest .Content` nulls out on raw/text endpoints → use
  `curl.exe` or `HttpWebRequest`.
- Diagnosing a dead host: `curl.exe -o NUL -w "%{http_code}" <url>` per host
  separates DNS/TCP/throttle/404 layers BEFORE retrying blind.
- `Get-Content` defaults to ANSI → UTF-8 files misread as mojibake (em-dash
  became `â€"`). Always `-Encoding UTF8` or
  `[IO.File]::ReadAllText($p, [Text.Encoding]::UTF8)`.
- PS single-hit `Where-Object` scalar trap → wrap pipeline results in `@()`.

---

## Covers

Policy = **Option B**: bytes on disk during curation, manifest stores local
path `covers/<slug>.jpg`, ZERO runtime external calls, never store OL http
URLs (ALICE_MASTER_PLAN :14).

### `tools/fetch_covers.ps1`

Self-healing whole-library script: lists every declared cover; existing files
EXISTS-SKIP; missing ones get fetched. Run any time, including after storage
wipes (batch 5 backfilled 42/42 this way).

Endpoint chain: `openlibrary.org/search.json?title=&author=&fields=cover_i`
→ candidate `docs[].cover_i` values → `covers.openlibrary.org/b/id/<id>-L.jpg?default=false`.

**The gotcha that cost a session:** results live under `$res.docs` — NOT
`.documents`. Wrong property = zero candidates = silent skip of every book.

Validation (rejects OL blank/stub scans): ≥10KB AND JPEG magic bytes
(FF D8) AND ≥180×250 px via System.Drawing.

Pacing: 600ms between candidates, 800ms between attempts. TLS12 + custom UA.

### Query craft

- Structured `?title=`+`&author=` beats generic `?q=`.
- Two-pass fallback: title+author, then title-only (or per-book `f` field).
- Story-level editions often lack scans — search the **parent collection**
  instead (b3 rescues: cop-anthem ← *The Four Million*, necklace ← *Original
  Short Stories of Maupassant*).
- Prefer the canonical edition title over our display title (wizard-oz ←
  *The Wonderful Wizard of Oz*).
- Max 2 attempts per source, then move on; log attempts in the batch record.

### Host reliability (measured, :1751)

| Host | Verdict |
|---|---|
| covers.openlibrary.org | reliable — primary |
| commons.wikimedia.org API | OK — fallback |
| upload.wikimedia.org | throttles hard even with UA+delays — last resort |

---

## Draft authoring rules (canonical since 2026-08-23)

STORY_GUIDELINES.md is DELETED; its still-true rules live here. The assistant
writes every draft itself, charter-clean from the first keystroke, so the old
reprocess pass shrinks to verification.

### Length doctrine — fit-to-story

- No word targets and no minimums. The ONLY ceiling: never go **way past
  ~20,000 characters** total per story.
- A 30k-word novel that stays true at 600 words wins at 600. A rich novel may
  need 2500. Whatever fits the story benefits Alice's users.
- Retelling IS allowed — but the retelling must retain the essence of the
  original AND how the author wrote it (voice, POV, iconic frame). A reader of
  the draft must feel they read THAT AUTHOR, not a summary.
- One continuous retelling per book; no chapter breaks.
- ACCURACY CLAUSE: verify who did what and when before writing. Never invent
  plot points, characters, or endings; never drop a major beat.

### Dialogue doctrine — speech keeps stories alive

- QUOTED DIALOGUE REQUIRED, and lean INTO it: keep character lines over
  narration whenever a cut must choose. Signature lines near-verbatim inside
  straight double quotes `"`.
- Straight quotes only. `Tag, " line."` / `"Line," tag.` both fine.

### Hard punctuation + spelling + sensitivity (charter-enforced)

- Allowed only: `. , ? ! ' "` plus hyphen inside compound words. NO semicolons,
  colons, em/en dashes, ellipses, curly marks — including inside quotes.
- American spellings throughout; never respell canonical proper names
  (Mina Murray, Van Helsing).
- Readers are grades 7-10: forbidden vocabulary (bosom, mistress, queer, gay,
  wench), softened violence wording, abstract phrasing for adult concepts,
  chaste romance. Rule-14 battery still runs at build.

### File format

```
SYNOPSIS Your one or two sentence hook goes here.
---
First paragraph as one line.

Second paragraph as one line.
```

Line 1 SYNOPSIS hook, line 2 exactly `---`, story from line 3. No titles or
bylines in the body, no internal separators mid-story, one paragraph per line,
plain UTF-8. Drafts land in the session temp dir (`draft-<slug>.txt`);
raw sources stay staging-only, never canonical.

---

## Cross-references

- Full acquisition sagas: ALICE_MASTER_PLAN b3 (:1781–:1823), b5 backfill
  (:1889–:1891), batch 6 record.
- In-app Gutenberg import (teacher feature) has its own rate-limit handling —
  separate concern from curation fetching.
