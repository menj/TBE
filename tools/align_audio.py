#!/usr/bin/env python3
"""Line a recording of a chapter up with the Hebrew words of that chapter.

We already know the exact words and their order (data/interlinear), so instead of trusting a
transcription, this script uses a speech model only to find *when* words are spoken, then matches
what it heard against the known words (a global alignment) and writes a start/end time for every
Hebrew word, with a confidence flag so doubtful words can be checked by ear.

Needs (not part of the theme): faster-whisper, numpy and ffmpeg.
    python3 -m venv .venv && .venv/bin/pip install faster-whisper
    .venv/bin/python tools/align_audio.py genesis 1 recording.mp3

Outputs:
    data/audio/{he|gr}/{book}-{chapter}.json   word timings (he = Hebrew, gr = Greek)
    assets/audio/{he|gr}/{book}-{chapter}.mp3  web copy of the recording (mono, 96 kbps)
"""
import json, os, re, subprocess, sys, time
from difflib import SequenceMatcher

import numpy as np

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
POINTS = re.compile(r"[֑-ׇ]")          # vowel points, cantillation, marks
FINALS = str.maketrans("ךםןףץ", "כמנפצ")
WEAK = re.compile(r"[וי]")                        # vowel letters spelled differently in modern text
WEAK_GR = re.compile(r"[aeiou]")                  # Greek: vowels are the part recordings and spelling disagree on


GREEK = False   # set from the command line: Greek is matched by sound, not by spelling


def skeleton(text):
    """Hebrew: consonants only, final forms folded. Greek: a rough modern-Greek sound spelling,
    because the recording is read with modern pronunciation while the text is spelled the ancient way."""
    if GREEK:
        return greek_sound(text)
    text = POINTS.sub("", text).translate(FINALS)
    return re.sub(r"[^א-ת]", "", text)


def greek_sound(text):
    import unicodedata
    t = unicodedata.normalize("NFD", text.lower())
    t = "".join(c for c in t if not unicodedata.combining(c)).replace("ς", "σ")
    for a, b in (("ει", "i"), ("οι", "i"), ("υι", "i"), ("αι", "e"), ("ου", "u"), ("μπ", "b"), ("ντ", "d"), ("γκ", "g"),
                 ("η", "i"), ("υ", "i"), ("ι", "i"), ("ε", "e"), ("ο", "o"), ("ω", "o"), ("α", "a"),
                 ("β", "v"), ("γ", "g"), ("δ", "d"), ("ζ", "z"), ("θ", "t"), ("κ", "k"), ("λ", "l"), ("μ", "m"), ("ν", "n"),
                 ("ξ", "x"), ("π", "p"), ("ρ", "r"), ("σ", "s"), ("τ", "t"), ("φ", "f"), ("χ", "h"), ("ψ", "p")):
        t = t.replace(a, b)
    return re.sub(r"[^a-z]", "", t)


def similarity(a, b):
    """0..1 similarity of two Hebrew words, forgiving of plene/defective spelling."""
    if not a or not b:
        return 0.0
    strong = SequenceMatcher(None, a, b).ratio()
    weak = WEAK_GR if GREEK else WEAK
    core_a, core_b = weak.sub("", a), weak.sub("", b)
    if core_a and core_b:
        strong = max(strong, SequenceMatcher(None, core_a, core_b).ratio() * 0.95)
    return strong


def transcribe(src, model_name="medium", language="he"):
    from faster_whisper import WhisperModel
    raw = subprocess.run(["ffmpeg", "-nostdin", "-v", "error", "-i", src, "-ac", "1", "-ar", "16000", "-f", "f32le", "-"],
                         capture_output=True, check=True).stdout
    audio = np.frombuffer(raw, dtype=np.float32)
    model = WhisperModel(model_name, device="cpu", compute_type="int8", cpu_threads=os.cpu_count() or 4)
    segments, info = model.transcribe(audio, language=language, word_timestamps=True, beam_size=5,
                                      condition_on_previous_text=False, vad_filter=False, temperature=0.0)
    words = []
    for seg in segments:
        for w in seg.words or []:
            words.append({"w": w.word.strip(), "s": round(w.start, 3), "e": round(w.end, 3), "p": round(w.probability, 3)})
        print("  %.0fs / %.0fs" % (seg.end, info.duration), flush=True)
    return {"duration": info.duration, "words": words}


def align(known, heard):
    """Needleman-Wunsch between known words and heard words. Returns {known_index: heard_index}."""
    n, m = len(known), len(heard)
    GAP = -0.45
    score = np.zeros((n + 1, m + 1))
    back = np.zeros((n + 1, m + 1), dtype=np.uint8)   # 1 diag, 2 up (skip known), 3 left (skip heard)
    score[:, 0] = np.arange(n + 1) * GAP
    score[0, :] = np.arange(m + 1) * GAP
    back[1:, 0], back[0, 1:] = 2, 3
    sims = np.zeros((n, m))
    for i in range(n):
        for j in range(m):
            sims[i, j] = similarity(known[i], heard[j])
    for i in range(1, n + 1):
        for j in range(1, m + 1):
            diag = score[i - 1, j - 1] + (2.0 * sims[i - 1, j - 1] - 0.9)
            up = score[i - 1, j] + GAP
            left = score[i, j - 1] + GAP
            best = max(diag, up, left)
            score[i, j] = best
            back[i, j] = 1 if best == diag else (2 if best == up else 3)
    pairs, i, j = {}, n, m
    while i > 0 or j > 0:
        move = back[i, j]
        if move == 1:
            pairs[i - 1] = j - 1
            i, j = i - 1, j - 1
        elif move == 2:
            i -= 1
        else:
            j -= 1
    return pairs, sims


def snap_boundaries(times, audio_path, window=0.15):
    """Move each word boundary to the quietest instant nearby, so words are not clipped."""
    raw = subprocess.run(["ffmpeg", "-nostdin", "-v", "error", "-i", audio_path, "-ac", "1", "-ar", "8000", "-f", "f32le", "-"],
                         capture_output=True, check=True).stdout
    samples = np.frombuffer(raw, dtype=np.float32)
    frame = 80  # 10 ms at 8 kHz
    usable = len(samples) // frame * frame
    rms = np.sqrt((samples[:usable].reshape(-1, frame) ** 2).mean(axis=1))
    for k in range(len(times) - 1):
        end, nxt = times[k][1], times[k + 1][0]
        if nxt - end > 0.25:           # a real pause: leave it
            continue
        lo = max(0, int((min(end, nxt) - window) * 100))
        hi = min(len(rms) - 1, int((max(end, nxt) + window) * 100))
        if hi <= lo:
            continue
        cut = (lo + int(np.argmin(rms[lo:hi + 1]))) / 100.0
        if times[k][0] < cut < times[k + 1][1]:
            times[k][1] = times[k + 1][0] = round(cut, 3)
    return times


def main():
    if len(sys.argv) < 4:
        sys.exit(__doc__)
    slug, chapter, src = sys.argv[1], int(sys.argv[2]), sys.argv[3]
    cache = sys.argv[4] if len(sys.argv) > 4 else None
    doc = json.load(open(os.path.join(ROOT, "data", "interlinear", slug, "%d.json" % chapter), encoding="utf8"))
    global GREEK
    GREEK = doc.get("lang") != "he"
    folder = "gr" if GREEK else "he"
    data = doc["v"]
    verse_numbers = sorted(int(v) for v in data)
    known, owner = [], []
    for v in verse_numbers:
        for idx, w in enumerate(data[str(v)]):
            known.append(skeleton(w[0]))
            owner.append((v, idx))

    if cache and os.path.exists(cache):
        heard_doc = json.load(open(cache, encoding="utf8"))
    else:
        t0 = time.time()
        heard_doc = transcribe(src, language="el" if GREEK else "he")
        print("transcribed in %.0fs" % (time.time() - t0))
        if cache:
            json.dump(heard_doc, open(cache, "w", encoding="utf8"), ensure_ascii=False)
    heard = heard_doc["words"]
    duration = heard_doc["duration"]
    heard_skel = [skeleton(w["w"]) for w in heard]

    pairs, sims = align(known, heard_skel)
    n = len(known)
    start, end, conf = [None] * n, [None] * n, [0.0] * n
    for i, j in pairs.items():
        s = sims[i, j]
        if s >= 0.34:
            start[i], end[i], conf[i] = heard[j]["s"], heard[j]["e"], s

    # Words with no match: spread them evenly between the nearest matched neighbours.
    anchors = [i for i in range(n) if start[i] is not None]
    if not anchors:
        sys.exit("No words could be matched; check the recording and the chapter.")
    i = 0
    while i < n:
        if start[i] is not None:
            i += 1
            continue
        j = i
        while j < n and start[j] is None:
            j += 1
        left = end[i - 1] if i > 0 else max(0.0, start[j] - 0.9 * (j - i)) if j < n else 0.0
        right = start[j] if j < n else min(duration, left + 0.9 * (j - i))
        weights = [max(2, len(known[k])) for k in range(i, j)]
        span, total, t = max(0.05, right - left), float(sum(weights)), left
        for k, wgt in zip(range(i, j), weights):
            share = span * wgt / total
            start[k], end[k], conf[k] = round(t, 3), round(t + share, 3), 0.0
            t += share
        i = j

    times = [[start[k], end[k]] for k in range(n)]
    times = snap_boundaries(times, src)
    for k in range(1, n):                      # never let a word start before the previous one
        if times[k][0] < times[k - 1][0]:
            times[k][0] = times[k - 1][0]
        if times[k][1] < times[k][0]:
            times[k][1] = round(times[k][0] + 0.03, 3)

    out = {"file": "%s-%d.mp3" % (slug, chapter), "duration": round(duration, 2), "credit": "", "words": {}}
    flat = 0
    for k, (v, idx) in enumerate(owner):
        out["words"].setdefault(str(v), []).append([times[k][0], times[k][1], 1 if conf[k] >= 0.6 else 0])
        flat += conf[k] >= 0.6
    os.makedirs(os.path.join(ROOT, "data", "audio", folder), exist_ok=True)
    os.makedirs(os.path.join(ROOT, "assets", "audio", folder), exist_ok=True)
    json.dump(out, open(os.path.join(ROOT, "data", "audio", folder, "%s-%d.json" % (slug, chapter)), "w", encoding="utf8"), ensure_ascii=False, separators=(",", ":"))
    subprocess.run(["ffmpeg", "-nostdin", "-y", "-v", "error", "-i", src, "-ac", "1", "-b:a", "96k", os.path.join(ROOT, "assets", "audio", folder, out["file"])], check=True)
    matched = sum(1 for k in range(n) if conf[k] >= 0.34)
    print("words: %d | matched by sound: %d (%.0f%%) | confident: %d (%.0f%%) | estimated: %d" %
          (n, matched, 100 * matched / n, flat, 100 * flat / n, n - matched))


if __name__ == "__main__":
    main()
