#!/usr/bin/env python3
"""Alice narration toolchain maintenance helper (TTS_AND_DICTIONARY.md §3.9).

Designed to be driven by the admin Status page's "Dependencies" tab (via the
configured interpreter in NARRATION_LOCAL_GENERATOR) and by the authoring
machine's hand. Every mode is safe to run: nothing here mutates installed
packages — version queries, consistency checks, an audio self-test, and a
"known-good" lockfile snapshot are all it does.

Modes:

    python toolkit.py --json versions       installed toolchain versions
    python toolkit.py --json check          pip check + installed-vs-lockfile
    python toolkit.py --json self-test      synthesize one fixture line, report ok/duration
    python toolkit.py --json lock           rewrite requirements.lock.txt from pip freeze

`--json` always emits one JSON document on stdout (consumed by PHP). Without
it, output is human-readable text. No output line is longer than ~2 KB and
stderr is echoed verbatim so a failing runner stays diagnosable.
"""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
import traceback
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
TOOLS_DIR = Path(__file__).resolve().parent
REQUIREMENTS = TOOLS_DIR / "requirements.txt"
LOCKFILE = TOOLS_DIR / "requirements.lock.txt"
FIXTURE = "Alice's voice is working well today, and sounds just right."


def parse_pins(path: Path) -> dict[str, str]:
    """Parse `name==version` pins out of requirements.txt (skip URLs/comments)."""
    pins: dict[str, str] = {}
    if not path.exists():
        return pins
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if " @ " in line or "==" not in line:
            continue
        name, version = line.split("==", 1)
        if name and version:
            pins[name.strip()] = version.strip()
    return pins


def importlib_versions(names: list[str]) -> dict[str, str]:
    from importlib import metadata

    found: dict[str, str] = {}
    for name in names:
        try:
            found[name] = metadata.version(name)
        except metadata.PackageNotFoundError as error:  # pragma: no cover - env dependent
            found[name] = f"missing ({error})"
    return found


def torch_details() -> dict[str, str]:
    """Best-effort torch version + device info (imports torch, so slow-ish)."""
    try:
        import torch

        info: dict[str, str] = {"version": torch.__version__}
        try:
            info["cuda"] = str(torch.version.cuda or "")
            info["device"] = "cuda" if torch.cuda.is_available() else "cpu"
            if torch.cuda.is_available():
                info["gpu"] = torch.cuda.get_device_name(0)
        except Exception:  # noqa: BLE001 - degraded report is fine
            info["device"] = "unknown"
        return info
    except Exception as error:  # noqa: BLE001
        return {"version": f"uninstalled/missing ({error})"}


def espeak_status() -> dict[str, bool]:
    try:
        import espeakng_loader

        lib = espeakng_loader.get_library_path()
        data = espeakng_loader.get_data_path()
        return {
            "library": bool(lib and Path(lib).exists()),
            "data": bool(data and Path(data).exists()),
        }
    except Exception:  # noqa: BLE001
        return {"library": False, "data": False}


def cmd_versions() -> dict:
    pins = parse_pins(REQUIREMENTS)
    names = sorted(pins)
    installed = importlib_versions(names)
    torch = torch_details()
    espeak = espeak_status()
    return {
        "interpreter": sys.version.split()[0],
        "interpreter_path": sys.executable,
        "packages": installed,
        "torch": torch,
        "espeak": espeak,
    }


def lockfile_pins() -> dict[str, str]:
    pins: dict[str, str] = {}
    if not LOCKFILE.exists():
        return pins
    for raw in LOCKFILE.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if " @ " in line:
            name = line.split(" @ ")[0].strip()
            pins[name] = "wheel"
            continue
        if "==" in line:
            name, version = line.split("==", 1)
            pins[name.strip()] = version.strip()
        elif line.endswith(".whl"):
            # bare wheel filename line (no name== so skip)
            continue
    return pins


def pip_check() -> list[str]:
    return pip_check_broken()


def pip_check_broken() -> list[str]:
    """A `pip check` run with the known kokoro declaration noise filtered out."""
    try:
        result = subprocess.run(
            [sys.executable, "-m", "pip", "check"],
            capture_output=True, text=True, timeout=120,
        )
        broken = []
        for line in result.stdout.splitlines():
            if not line.strip():
                continue
            if is_kokoro_decl_noise(line):
                continue
            broken.append(line)
        return broken
    except subprocess.TimeoutExpired:  # pragma: no cover
        return ["pip check timed out"]
    except Exception as error:  # pragma: no cover
        return [str(error)]


def pip_health_note() -> str:
    """One-line note explaining why `pip check` complaints about kokoro's own
    metadata are expected and not genuine breakage. Empty when there is nothing
    of the kind to say."""
    try:
        result = subprocess.run(
            [sys.executable, "-m", "pip", "check"],
            capture_output=True, text=True, timeout=120,
        )
        lines = result.stdout.splitlines()
    except Exception:  # noqa: BLE001 - degraded report is fine here
        return ""
    if any(is_kokoro_decl_noise(line) for line in lines):
        return ("kokoro's metadata asks for misaki>=0.7.16 / numpy==1.26.4, but PyPI's newest misaki is "
                "0.7.4 and the tree pins numpy 2.5.2 - it is installed --no-deps on purpose and is the "
                "latest compatible set, so these `pip check` lines are expected, not breakage.")
    return ""


def is_kokoro_decl_noise(line: str) -> bool:
    """True when a `pip check` complaint is the known kokoro metadata conflict
    (misaki>=0.7.x floor above any published misaki, or numpy==1.26.4 against
    the hand-pinned tree) rather than genuine installed breakage."""
    if "kokoro " not in line:
        return False
    if "has requirement misaki" in line:
        return True
    if "has requirement numpy==1.26.4" in line:
        return True
    return False


def pip_freeze() -> dict[str, str]:
    """The real installed tree (`pip freeze`), keys PEP 503-normalised — the
    full transitive set, so lock_status can compare lockfile → installed on an
    equal footing instead of pinning every transitive package as 'missing'."""
    try:
        result = subprocess.run(
            [sys.executable, "-m", "pip", "freeze"],
            capture_output=True, text=True, timeout=120,
        )
    except Exception:  # noqa: BLE001 - degraded report is fine
        return {}

    versions: dict[str, str] = {}
    for raw in result.stdout.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if " @ " in line:
            versions[normalise(line.split(" @ ")[0])] = "wheel"
            continue
        if "==" not in line:
            continue
        name, version = line.split("==", 1)
        versions[normalise(name)] = version.strip()
    return versions


def normalise(name: str) -> str:
    """PEP 503 canonical name: lowercase and '_' and '-' are equivalent."""
    return name.strip().lower().replace("_", "-")


def cmd_check() -> dict:
    pins = parse_pins(REQUIREMENTS)
    frozen = importlib_versions(sorted(pins)) if pins else {}
    pinned = [[name, pins[name], frozen.get(name, "missing")] for name in sorted(pins)]
    mismatched = [entry for entry in pinned if entry[1] != entry[2]]

    lock = lockfile_pins()
    installed = pip_freeze()
    lock_missing = [
        name for name in sorted(lock)
        if installed.get(normalise(name)) in (None, "", "missing")
    ]
    lock_mismatch = [
        name for name in sorted(lock)
        if lock.get(name, "") not in ("wheel", "")
        and installed.get(normalise(name)) not in (None, "")
        and lock[name] != installed[normalise(name)]
    ]

    return {
        "pinned": pinned,
        "mismatched": mismatched,
        "pip_broken": pip_check_broken(),
        "pip_health_note": pip_health_note(),
        "lock_status": {
            "present": LOCKFILE.exists(),
            "missing": lock_missing,
            "mismatched": lock_mismatch,
        },
    }


def cmd_self_test() -> dict:
    """Real synthesis of a one-line fixture through the same pipeline the
    generator uses - the true health check for the whole Kokoro stack."""
    sys.path.insert(0, str(TOOLS_DIR))
    try:
        from generate_narration import TOUR_VOICE, synthesize

        pieces = synthesize(FIXTURE, TOUR_VOICE)
        total = sum(len(p) for p in pieces)
        if total == 0:
            raise RuntimeError("synthesis returned no samples")
        import numpy as np

        samples = np.concatenate(pieces)
        duration = round(len(samples) / 24_000, 2)
        return {
            "ok": True,
            "voice": TOUR_VOICE,
            "duration_ms": round(duration * 1000),
            "detail": "Kokoro synthesized the fixture line successfully.",
        }
    except Exception:
        return {
            "ok": False,
            "duration_ms": 0,
            "detail": _error_slice(),
        }


def cmd_lock() -> dict:
    try:
        result = subprocess.run(
            [sys.executable, "-m", "pip", "freeze"],
            capture_output=True, text=True, timeout=120,
        )
        if result.returncode != 0:
            return {"ok": False, "detail": (result.stderr or result.stdout)[:400]}
        payload = ("# ALICE narration toolchain - full known-good freeze (regenerated by\n"
                   "#   python tools/narration/toolkit.py --lock after a green self-test).\n"
                   f"# python {sys.version.split()[0]} at {sys.executable}\n"
                   + result.stdout)
        tmp = LOCKFILE.with_suffix(".lock.tmp")
        tmp.write_text(payload, encoding="utf-8")
        tmp.replace(LOCKFILE)
        return {"ok": True, "detail": f"Rewrote {LOCKFILE.name} ({len(result.stdout.splitlines())} packages)."}
    except Exception:
        return {"ok": False, "detail": _error_slice()}


def _error_slice() -> str:
    """The failing frame, not just the message — keeps diagnosis possible when
    a child dies deep in the stack (e.g. WinError 10106 on socket init)."""
    return traceback.format_exc()[-400:] or "unknown python error"


def human(block: dict) -> None:
    # Keep human output short - the admin UI consumes --json, this is for shells.
    if "packages" in block:
        for name, version in block["packages"].items():
            print(f"  {name} {version}")
        torch = block.get("torch", {})
        print(f"  torch {torch.get('version', '?')} ({torch.get('device', '?')})")
        espeak = block.get("espeak", {})
        if espeak:
            print(f"  espeak-ng library={espeak.get('library')} data={espeak.get('data')}")
    elif block.get("pinned") is not None:
        for pin in block["pinned"]:
            flag = "" if pin[1] == pin[2] else "  <-- MISMATCH"
            print(f"  {pin[0]} declared={pin[1]} installed={pin[2]}{flag}")
    elif "ok" in block:
        print(f"  {'OK' if block['ok'] else 'FAILED'} - {block.get('detail', '')}")
    else:
        print(json.dumps(block, indent=2))


def main() -> int:
    parser = argparse.ArgumentParser(description="Alice narration toolchain maintenance (see module docstring)")
    parser.add_argument("mode", choices=["versions", "check", "self-test", "lock"], help="toolkit operation")
    parser.add_argument("--json", action="store_true", help="emit one JSON document on stdout")
    args = parser.parse_args()

    handlers = {
        "versions": cmd_versions,
        "check": cmd_check,
        "self-test": cmd_self_test,
        "lock": cmd_lock,
    }
    block = handlers[args.mode]()
    if args.json:
        print(json.dumps(block, indent=2, ensure_ascii=False))
    else:
        human(block)
    return 0


if __name__ == "__main__":
    sys.exit(main())