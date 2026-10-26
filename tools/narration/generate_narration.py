#!/usr/bin/env python3
"""Offline story narration generator (TTS_AND_DICTIONARY.md §3.4).

Consumes the JSON handshake written by `php artisan alice:narration:export`
and produces, per published story:

    storage/app/public/audio/stories/{slug}/section-N.mp3   (64 kbps mono)
    storage/app/narration/manifests/{slug}.json             (hash contract)

The manifest records the SHA-256 of every section's source text; the reader
hides its play button whenever that hash no longer matches the page text it
is about to serve (stale audio is never played). Re-running this script
after a small edit only re-synthesizes sections whose hash changed.

DEV MACHINE ONLY - production never installs Python, Kokoro or ffmpeg.

Setup (Laragon's bundled interpreter works fine):

    python -m pip install lameenc soundfile loguru huggingface-hub numpy addict regex num2words espeakng-loader phonemizer-fork nltk jamo cn2an jieba ordered-set pypinyin scipy torch transformers spacy
    python -m pip install --no-deps kokoro==0.9.4 misaki==0.9.4

On Python 3.13 a plain `pip install kokoro` refuses to resolve (upstream caps
kokoro at <3.13 only because a transitive wheel is missing there), so the two
TTS packages go in without their dependency resolver; every real runtime
dependency is listed explicitly above. A CUDA build of torch makes synthesis
roughly 10x faster on NVIDIA machines; the plain CPU build also works.

The first run downloads Kokoro-82M weights (~350 MB) from Hugging Face and
then works fully offline.

Usage:

python tools/narration/generate_narration.py                # whole library
    python tools/narration/generate_narration.py --only slug-a,slug-b
    python tools/narration/generate_narration.py --limit 2      # first 2 pending sections
    python tools/narration/generate_narration.py --dry-run      # list pending work
    # Tour voice (public/audio/tour/*.mp3 + manifest.json; driver: tour.js):
    python tools/narration/generate_narration.py --tour
    python tools/narration/generate_narration.py --tour --dry-run
    python tools/narration/generate_narration.py --tour --check      # drift guard vs tour.js
    # Feed mode - once Alice is hosted (TTS_AND_DICTIONARY.md ��3.6):
    python tools/narration/generate_narration.py --feed-url https://alice.example/tools/narration --feed-token TOKEN
"""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
import threading
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]

# Laravel's "local" disk root is storage/app/private on Laravel 11+.
EXPORT_PATH = REPO / "storage" / "app" / "private" / "narration" / "export.json"
AUDIO_ROOT = REPO / "storage" / "app" / "public" / "audio" / "stories"
MANIFEST_ROOT = REPO / "storage" / "app" / "private" / "narration" / "manifests"
PROGRESS_ROOT = REPO / "storage" / "app" / "private" / "narration" / "progress"
WANTED_PATH = REPO / "storage" / "app" / "private" / "narration" / "wanted.json"

SAMPLE_RATE = 24_000
MP3_BITRATE = 64          # kbps mono - speech quality per spec ��3.4
MAX_CHUNK_WORDS = 280     # Kokoro handles ~a paragraph per pass comfortably

# "Meet Alice" tour voice:  public/audio/tour/{key}.mp3 (+ manifest.json).
# Keys must match the key arg of say(text, key) in resources/js/tour/tour.js.
# --check compares TOUR_LINES against the JS so a copy edit in one place is
# caught instead of silently drifting.
TOUR_AUDIO_ROOT = REPO / "public" / "audio" / "tour"
TOUR_VOICE = "af_heart"
TOUR_MODEL = "kokoro-v1.0"
TOUR_LINES = [
    ("hub-intro-0", "Hi! I'm Alice. This is the Hub. It's your home base on ALICE."),
    ("hub-tabs-0", "These are your five tabs. Watch them light up as I tap them."),
    ("classwork-0", "This is Classwork. Your teacher's assignments and your classes all live on this page."),
    ("hub-go-0", "Now let's find you a story. Off to the Library!"),
    ("library-shelves-0", "Stories here are sorted just for you. Search up top for a title or an author, or you can filter by a specific genre that you like."),
    ("library-drawer-0", "These are the Filters. Feel free to explore what specific story you would like with the options here."),
    ("study-0", "This is your Study corner. You can look up words here with our dictionary, save them and practice them with our flashcard feature. Or you can practice saved words from your past readings."),
    ("games-0", "Here is the mini game section. Currently we have three games which are Word Search, Memory and Sentence Builder."),
    ("games-1", "Each game has its own help button to help you understand how each game's mechanics work."),
    ("farewell-0", "That's the whole tour. You know your way around now."),
    ("farewell-1", "Tap Library up top to start a story and have fun. I'll be here any time you tap the question mark."),
]


def log(message: str) -> None:
    print(message, flush=True)


def sha256(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def split_into_chunks(text: str) -> list[str]:
    """Sentence-bounded chunks of at most MAX_CHUNK_WORDS words."""
    import re

    paragraphs = [p.strip() for p in re.split(r"\n{2,}", text) if p.strip()]
    chunks: list[str] = []
    current: list[str] = []
    current_words = 0

    for paragraph in paragraphs:
        sentences = re.split(r"(?<=[.!?…])\s+", " ".join(paragraph.split()))
        sentences = [s for s in (s.strip() for s in sentences) if s]

        if not sentences:
            continue

        # Paragraphs that fit whole stay together with what's queued.
        paragraph_words = sum(len(s.split()) for s in sentences)

        if paragraph_words <= MAX_CHUNK_WORDS:
            if current_words + paragraph_words > MAX_CHUNK_WORDS and current:
                chunks.append(" ".join(current))
                current, current_words = [], 0
            current.append(paragraph)
            current_words += paragraph_words
            continue

        for sentence in sentences:
            words = len(sentence.split())

            if current_words + words > MAX_CHUNK_WORDS and current:
                chunks.append(" ".join(current))
                current, current_words = [], 0

            current.append(sentence)
            current_words += words

    if current:
        chunks.append(" ".join(current))

    return chunks


def build_sentence_offsets(text: str) -> list[dict]:
    """Compute per-sentence character start/end offsets within *text*.

    Uses the same sentence-boundary heuristic the reader JS uses (a
    sentence ends at '.', '!', or '?' followed by whitespace).  The
    offsets map character positions in the original text to time ranges
    during playback, enabling much more precise highlight sync than the
    old proportional-word-count approach.

    Returns a list of {"s": start, "e": end} dicts where values are
    0-based character indices (end is exclusive of the trailing space).
    """
    import re

    # Split into sentences on terminal punctuation + whitespace.
    boundaries = [m.end() for m in re.finditer(r"[.!?…]\s*", text)]

    if not boundaries:
        # No terminal punctuation — the whole text is one sentence.
        return [{"s": 0, "e": len(text.rstrip())}]

    sentences: list[dict] = []
    start = 0
    for end_pos in boundaries:
        # Trim trailing whitespace from the sentence slice.
        segment = text[start:end_pos].rstrip()
        if segment:
            sentences.append({"s": start, "e": start + len(segment)})
        start = end_pos

    # Remainder after the last terminal punctuation.
    tail = text[start:].rstrip()
    if tail:
        sentences.append({"s": start, "e": start + len(tail)})

    return sentences


def encode_mp3(samples, bitrate: int = MP3_BITRATE) -> bytes:
    import lameenc
    import numpy as np

    pcm = np.clip(np.asarray(samples, dtype=np.float32), -1.0, 1.0)
    pcm16 = (pcm * 32_767.0).astype("<i2").tobytes()

    encoder = lameenc.Encoder()
    encoder.set_bit_rate(bitrate)
    encoder.set_in_sample_rate(SAMPLE_RATE)
    encoder.set_channels(1)
    encoder.set_quality(2)  # high

    data = bytes(encoder.encode(pcm16))
    data += bytes(encoder.flush())
    return data


_PIPELINE = None


def atomic_write_text(path: Path, text: str) -> None:
    """Write + rename so a concurrent status reader never sees a torn file."""
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(text, encoding="utf-8")
    tmp.replace(path)


def write_progress(slug: str, state: str, done: int, total: int, *, error: str = "") -> None:
    """Local heartbeat + mailbox for the server status endpoint.

    fields mirror what Laravel's Narration::progress() reads:
    state running|done|error, done/total section counters, ISO timestamps.
    """
    payload = {
        "slug": slug,
        "state": state,
        "done": int(done),
        "total": int(total),
        "current_page": int(done),
        "started_at": _STARTED_AT,
        "updated_at": datetime.now(timezone.utc).isoformat(),
    }
    if error:
        payload["error"] = error
    PROGRESS_ROOT.mkdir(parents=True, exist_ok=True)
    atomic_write_text(PROGRESS_ROOT / f"{slug}.json", json.dumps(payload, indent=2) + "\n")


def clear_progress(slug: str) -> None:
    """A partial/interrupted run leaves no heartbeat so the server reverts
    to the honest queued/partial coverage state instead of a stuck run."""
    path = PROGRESS_ROOT / f"{slug}.json"
    if path.exists():
        path.unlink(missing_ok=True)


def feed_request(method: str, url: str, token: str, body: bytes | None = None) -> tuple[int, object]:
    req = urllib.request.Request(url, data=body, method=method)
    req.add_header("Authorization", f"Bearer {token}")
    req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            raw = resp.read().decode("utf-8")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as error:
        return error.code, {}


def feed_fetch_wanted(url: str, token: str) -> dict | None:
    """GET {url}/wanted-export. 404/401/403 - the token is wrong or the site
    is off - means there is simply nothing to pull; treat it as an empty run."""
    status, payload = feed_request("GET", url.rstrip("/") + "/wanted-export", token)
    return payload if status == 200 else None


def post_progress(url: str, token: str, payload: dict) -> bool:
    status, _ = feed_request(
        "POST",
        url.rstrip("/") + "/progress",
        token,
        json.dumps(payload).encode("utf-8"),
    )
    return status == 200


def current_progress_payload() -> dict | None:
    if _LAST_PROGRESS_PATH is None or not _LAST_PROGRESS_PATH.exists():
        return None
    try:
        return json.loads(_LAST_PROGRESS_PATH.read_text(encoding="utf-8"))
    except ValueError:
        return None


def heartbeat_loop(url: str, token: str, stop: threading.Event) -> None:
    """Daemon that re-posts the latest local heartbeat every ~28s so a long
    run keeps the hosted status endpoint honest while it synthesizes."""
    while not stop.wait(28):
        payload = current_progress_payload()
        if payload is not None and payload.get("state") == "running":
            post_progress(url, token, payload)


_STARTED_AT = datetime.now(timezone.utc).isoformat()
_LAST_PROGRESS_PATH: Path | None = None


def get_pipeline():
    """Build the Kokoro pipeline once per process (model load is expensive).

    phonemizer is pointed at espeak-ng's bundled library first: without that,
    Kokoro's out-of-vocabulary fallback silently disables itself and any word
    missing from misaki's lexicon (Rumpelstiltskin!) crashes synthesis.
    """
    global _PIPELINE
    if _PIPELINE is None:
        import espeakng_loader
        from kokoro import KPipeline
        from phonemizer.backend.espeak.wrapper import EspeakWrapper

        EspeakWrapper.set_library(espeakng_loader.get_library_path())
        EspeakWrapper.set_data_path(espeakng_loader.get_data_path())

        _PIPELINE = KPipeline(lang_code="a")   # American English; auto-uses CUDA when available
        device = next(_PIPELINE.model.parameters()).device
        log(f"kokoro pipeline ready on {str(device).upper()} (espeak-ng fallback active)")

    return _PIPELINE


def synthesize(text: str, voice: str):
    """Yield concatenated PCM for one section, chunk by sentence groups."""
    pipeline = get_pipeline()
    pieces = []

    for chunk in split_into_chunks(text):
        for result in pipeline(chunk, voice=voice):
            piece = getattr(result, "audio", None)
            if piece is None:
                continue
            pieces.append(piece)

    if not pieces:
        raise RuntimeError("Kokoro produced no audio")

    return pieces


def toolkit_stamp() -> dict:
    """Reproducibility stamp for artifacts (TTS_AND_DICTIONARY.md §3.9).

    Cheap (metadata-only - never imports torch) so stamping is free next to
    synthesis. torch's version string carries the build tag (e.g. +cu126), so
    the CUDA variant is already encoded.
    """
    from importlib import metadata

    stamp = {"python": sys.version.split()[0]}
    for name in ("kokoro", "misaki", "torch"):
        try:
            stamp[name] = metadata.version(name)
        except Exception:  # noqa: BLE001
            stamp[name] = "unknown"
    return stamp


def _write_story_manifest(path: Path, voice: str, model: str, fresh_entries: dict) -> None:
    out = {
        "voice": voice,
        "model": model,
        "toolkit": toolkit_stamp(),
        "sections": [fresh_entries[n] for n in sorted(fresh_entries)],
    }
    MANIFEST_ROOT.mkdir(parents=True, exist_ok=True)
    atomic_write_text(path, json.dumps(out, indent=2, ensure_ascii=False) + "\n")


def _valid_entry(entry: dict | None, text: str) -> bool:
    if not isinstance(entry, dict) or not isinstance(entry.get("hash"), str):
        return False
    return entry.get("hash") == sha256(text) and entry.get("mp3") == f"section-{entry.get('n')}.mp3"


def _check_tour_drift() -> list[str]:
    """Return any TOUR_LINES texts missing from resources/js/tour/tour.js."""
    js_path = REPO / "resources" / "js" / "tour" / "tour.js"
    js = js_path.read_text(encoding="utf-8")
    return [text for _, text in TOUR_LINES if text not in js]


def run_tour(args) -> int:
    """Synthesize the tour's per-line clips + manifest (or --check / --dry-run)."""
    if args.check:
        missing = _check_tour_drift()
        if missing:
            log(f"Drift detected: {len(missing)} tour line(s) missing from tour.js:")
            for text in missing:
                log(f"  - {text[:60]}...")
            return 1
        log("Tour lines match tour.js - no drift.")
        return 0

    TOUR_AUDIO_ROOT.mkdir(parents=True, exist_ok=True)
    manifest_path = TOUR_AUDIO_ROOT / "manifest.json"
    manifest: dict = {}
    if manifest_path.exists():
        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        except ValueError:
            manifest = {}
    previous = manifest.get("lines", {})
    fresh: dict[str, dict] = {}
    generated = skipped = 0

    for key, text in TOUR_LINES:
        digest = sha256(text)
        mp3_name = f"{key}.mp3"
        old = previous.get(key, {})
        unchanged = (
            not args.force
            and old.get("hash") == digest
            and old.get("mp3") == mp3_name
            and (TOUR_AUDIO_ROOT / mp3_name).exists()
        )
        if unchanged:
            fresh[key] = old
            skipped += 1
            continue
        if args.dry_run:
            log(f"  would synthesize tour/{mp3_name} ({len(text.split())} words)")
            continue
        log(f"  synthesizing tour/{mp3_name} ({len(text.split())} words)...")
        try:
            pieces = synthesize(text, TOUR_VOICE)
            total_samples = sum(len(p) for p in pieces)
            mp3_bytes = encode_mp3(__import__("numpy").concatenate(pieces))
        except Exception as error:  # noqa: BLE001 - report and keep going
            log(f"    FAILED ({error}) - skipping {key}")
            continue
        (TOUR_AUDIO_ROOT / mp3_name).write_bytes(mp3_bytes)
        seconds = round(total_samples / SAMPLE_RATE, 1)
        fresh[key] = {
            "hash": digest,
            "mp3": mp3_name,
            "text": text,
            "duration_ms": round(seconds * 1000),
        }
        generated += 1

    if not args.dry_run:
        out = {
            "voice": TOUR_VOICE,
            "model": TOUR_MODEL,
            "toolkit": toolkit_stamp(),
            "lines": {k: fresh[k] for k in sorted(fresh)},
        }
        atomic_write_text(manifest_path, json.dumps(out, indent=2, ensure_ascii=False) + "\n")
        log(f"Wrote {manifest_path}")

    log(f"Tour audio: {generated} generated, {skipped} skipped" + (" (dry run)" if args.dry_run else ""))
    return 0


def main() -> int:
    if sys.platform == "win32":
        try:
            sys.stdout.reconfigure(encoding="utf-8")
        except Exception:
            pass

    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--export", type=Path, nargs="?", const=EXPORT_PATH, default=EXPORT_PATH)
    parser.add_argument("--only", default="", help="comma-separated slugs to include")
    parser.add_argument("--limit", type=int, default=0, help="max sections to synthesize this run")
    parser.add_argument("--force", action="store_true", help="re-synthesize even when hashes match")
    parser.add_argument("--dry-run", action="store_true", help="list pending sections without synthesizing")
    parser.add_argument("--tour", action="store_true", help="synthesize the Meet Alice tour clips + manifest.json")
    parser.add_argument("--check", action="store_true", help="with --tour: verify TOUR_LINES match tour.js and exit")
    parser.add_argument("--feed-url", default="", help="hosted /tools/narration base URL (feed mode)")
    parser.add_argument("--feed-token", default="", help="NARRATION_FEED_TOKEN of the hosted site")
    args = parser.parse_args()

    if args.tour or args.check:
        return run_tour(args)

    feed_url = args.feed_url.rstrip("/")
    feed_token = args.feed_token

    if bool(feed_url) != bool(feed_token):
        log("--feed-url and --feed-token must be given together.")
        return 1

    if feed_url:
        log(f"Feed mode: pulling wanted stories from {feed_url}")
        payload = feed_fetch_wanted(feed_url, feed_token)
        if payload is None:
            log("Feed unreachable or token rejected (404) - nothing to pull. Run again later.")
            return 0
        log("Feed reachable. Syncing heartbeat to it during the run.")

        # Only the stories the hosted site actually wants. When --only is
        # also passed it narrows further - both filters must agree.
        feed_wanted = {s for s in payload.get("wanted", []) if isinstance(s, str)}
        if feed_wanted:
            stories = [s for s in payload["export"].get("stories", []) if s["slug"] in feed_wanted]
        else:
            log("Nothing wanted on the feed.")
            return 0
    else:
        if not args.export.exists():
            log(f"No export file at {args.export}")
            log("Run: php artisan alice:narration:export")
            return 1
        payload = json.loads(args.export.read_text(encoding="utf-8"))
        wanted = {s.strip() for s in args.only.split(",") if s.strip()}
        stories = [s for s in payload.get("stories", []) if not wanted or s["slug"] in wanted]

    if not stories:
        log("Nothing to do: no matching stories in the export.")
        return 0

    voice = payload.get("voice", "af_heart")
    model = payload.get("model", "kokoro-v1.0")

    # Long library-wide runs drive the hosted feed with a heartbeat thread.
    stop_beating = threading.Event()
    hb = None
    if feed_url:
        hb = threading.Thread(target=heartbeat_loop, args=(feed_url, feed_token, stop_beating), daemon=True)
        hb.start()

    budget = args.limit
    generated = skipped = 0

    for story in stories:
        slug = story["slug"]
        global _LAST_PROGRESS_PATH
        _LAST_PROGRESS_PATH = PROGRESS_ROOT / f"{slug}.json"
        total = len(story["sections"])
        done = 0

        write_progress(slug, "running", 0, total) if not args.dry_run else None
        if feed_url and not args.dry_run:
            post_progress(feed_url, feed_token, current_progress_payload() or {})
        manifest_path = MANIFEST_ROOT / f"{slug}.json"

        manifest: dict = {}
        if manifest_path.exists():
            try:
                manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            except ValueError:
                manifest = {}

        entries = {int(s["n"]): s for s in manifest.get("sections", [])}
        fresh_entries: dict[int, dict] = {}

        for section in story["sections"]:
            n = int(section["n"])
            text = section["text"]
            digest = sha256(text)
            mp3_name = f"section-{n}.mp3"
            previous = entries.get(n, {})

            unchanged = (
                not args.force
                and previous.get("hash") == digest
                and previous.get("mp3") == mp3_name
                and (AUDIO_ROOT / slug / mp3_name).exists()
            )

            if unchanged:
                # Backfill sentence offsets for old manifests that lack them.
                if "sentences" not in previous:
                    previous["sentences"] = build_sentence_offsets(text)
                fresh_entries[n] = previous
                skipped += 1
                continue

            if budget and generated >= budget:
                # Keep prior valid entries so partial runs stay consistent.
                if previous.get("hash") == digest:
                    if "sentences" not in previous:
                        previous["sentences"] = build_sentence_offsets(text)
                    fresh_entries[n] = previous
                continue

            if args.dry_run:
                log(f"  would synthesize {slug} section-{n} ({len(text.split())} words)")
                continue

            started = time.time()
            log(f"  synthesizing {slug} section-{n} ({len(text.split())} words)...")

            try:
                pieces = synthesize(text, voice)
                total_samples = sum(len(p) for p in pieces)
                mp3_bytes = encode_mp3(__import__("numpy").concatenate(pieces))
            except Exception as error:  # noqa: BLE001 - report and keep going
                log(f"    FAILED ({error}) - keeping any previous entry")
                if previous.get("hash") == digest:
                    if "sentences" not in previous:
                        previous["sentences"] = build_sentence_offsets(text)
                    fresh_entries[n] = previous
                continue

            out_dir = AUDIO_ROOT / slug
            out_dir.mkdir(parents=True, exist_ok=True)
            (out_dir / mp3_name).write_bytes(mp3_bytes)

            seconds = round(total_samples / SAMPLE_RATE, 1)
            fresh_entries[n] = {
                "n": n,
                "hash": digest,
                "mp3": mp3_name,
                "seconds": seconds,
                "sentences": build_sentence_offsets(text),
            }
            generated += 1
            done = max(done, n)
            log(f"    done in {time.time() - started:.1f}s -> {seconds}s of audio")

            # The server's status pill polls mid-run: persist the manifest so
            # far (atomically) plus the progress heartbeat after each section.
            if not args.dry_run:
                write_progress(slug, "running", done, total)
                _write_story_manifest(manifest_path, voice, model, fresh_entries)
                if feed_url:
                    post_progress(feed_url, feed_token, current_progress_payload() or {})

        if args.dry_run:
            continue

        # Final manifest + heartbeat. Only a fully covered story reports
        # "done" (the server then moves it to awaiting-deploy); anything less
        # gets its heartbeat cleared so the pill shows the honest coverage.
        _write_story_manifest(manifest_path, voice, model, fresh_entries)
        full = len(fresh_entries) == total and all(_valid_entry(fresh_entries.get(n), story["sections"][n - 1]["text"]) for n in range(1, total + 1))
        if full:
            write_progress(slug, "done", total, total)
        else:
            clear_progress(slug)

        if feed_url:
            payload = current_progress_payload() or {}
            if not payload.get("state") == "done":
                payload = {"slug": slug, "state": "error", "done": done, "total": total,
                           "started_at": _STARTED_AT, "updated_at": datetime.now(timezone.utc).isoformat(),
                           "error": "section coverage incomplete after this run"}
            post_progress(feed_url, feed_token, payload)

    if hb is not None:
        stop_beating.set()

    if args.dry_run:
        log("Dry run complete - nothing was written.")
        return 0

    log(f"\nGenerated {generated} section(s), reused {skipped}. Next steps:")
    log("  1. php artisan alice:narration:sync        # audit coverage")
    log("  2. commit/deploy storage/app/public/audio + manifests")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
