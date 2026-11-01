#!/usr/bin/env python3
"""Extract translatable strings (text domain tbe-child) from the theme PHP files into languages/tbe-child.pot.
Admin-only settings screens are left in English."""
import re, glob, os, sys
ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
SKIP = {"settings.php"}
STR = r"'((?:[^'\\]|\\.)*)'"
FUNCS = r"(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)"
singular = re.compile(FUNCS + r"\(\s*" + STR + r"\s*,\s*'tbe-child'")
plural = re.compile(r"_n\(\s*" + STR + r"\s*,\s*" + STR + r"\s*,[^,]+,\s*'tbe-child'")
def unescape(s): return s.replace("\\'", "'").replace("\\\\", "\\")
entries = {}
for path in sorted(glob.glob(ROOT + "/inc/*.php")):
    if os.path.basename(path) in SKIP: continue
    src = open(path, encoding="utf8").read()
    for m in singular.finditer(src): entries.setdefault((unescape(m.group(1)), None), None)
    for m in plural.finditer(src): entries[(unescape(m.group(1)), unescape(m.group(2)))] = None
def q(s): return '"' + s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n") + '"'
out = ['msgid ""', 'msgstr ""', r'"Project-Id-Version: TBE\n"', r'"Content-Type: text/plain; charset=UTF-8\n"', ""]
for (sing, plur) in sorted(entries, key=lambda k: k[0].lower()):
    out.append("msgid " + q(sing))
    if plur: out += ["msgid_plural " + q(plur), 'msgstr[0] ""', 'msgstr[1] ""']
    else: out.append('msgstr ""')
    out.append("")
open(ROOT + "/languages/tbe-child.pot", "w", encoding="utf8").write("\n".join(out))
print(len(entries), "strings")
