#!/usr/bin/env python3
"""Check JSON-LD against the official schema.org vocabulary.

Verifies, for every node in every @graph:
  - each @type exists in schema.org
  - each property exists and is allowed on that type (or one of its parent types)
  - each value is of an accepted kind (text, number, URL, date, or a node of an accepted type)
  - every {"@id": ...} reference points to a node in the same graph (or is an absolute URL)
  - no @id is defined twice

Usage:  python3 tools/check_schema.py page1.json page2.json ...
        (downloads the vocabulary to tools/.cache/ on first use)
"""
import json, os, re, sys, urllib.request

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
VOCAB_URL = "https://schema.org/version/latest/schemaorg-current-https.jsonld"
VOCAB = os.path.join(ROOT, "tools", ".cache", "schemaorg.jsonld")
KEYWORDS = {"@context", "@graph", "@id", "@type", "@language", "@value"}
TEXTY = {"Text", "URL", "Date", "DateTime", "Time", "CssSelectorType", "PronounceableText", "XPathType"}
NUMBERY = {"Number", "Integer", "Float"}
# Properties used by consumers (Google) that schema.org itself does not define: type -> property.
EXTENSIONS = {("SearchAction", "query-input")}


def as_list(x):
    return x if isinstance(x, list) else ([] if x is None else [x])


def short(i):
    return i.split(":", 1)[1] if isinstance(i, str) and i.startswith("schema:") else i


def load_vocab():
    if not os.path.exists(VOCAB):
        os.makedirs(os.path.dirname(VOCAB), exist_ok=True)
        with urllib.request.urlopen(VOCAB_URL, timeout=90) as r, open(VOCAB, "wb") as f:
            f.write(r.read())
    graph = json.load(open(VOCAB, encoding="utf8"))["@graph"]
    parents, domains, ranges, kinds = {}, {}, {}, {}
    for item in graph:
        name = short(item["@id"])
        types = [short(t) for t in as_list(item.get("@type"))]
        kinds[name] = types
        for p in as_list(item.get("rdfs:subClassOf")):
            parents.setdefault(name, []).append(short(p["@id"]))
        if "rdf:Property" in types or "schema:Property" in types:
            domains[name] = {short(d["@id"]) for d in as_list(item.get("schema:domainIncludes"))}
            ranges[name] = {short(d["@id"]) for d in as_list(item.get("schema:rangeIncludes"))}
    return parents, domains, ranges, kinds


def ancestors(t, parents, seen=None):
    seen = seen if seen is not None else set()
    if t in seen:
        return seen
    seen.add(t)
    for p in parents.get(t, []):
        ancestors(p, parents, seen)
    return seen


def main(paths):
    parents, domains, ranges, kinds = load_vocab()
    errors, nodes_total = [], 0
    for path in paths:
        doc = json.load(open(path, encoding="utf8"))
        graph = doc.get("@graph", [doc])
        defined = {}
        for node in graph:
            nid = node.get("@id")
            if nid:
                if nid in defined:
                    errors.append(f"{path}: duplicate @id {nid}")
                defined[nid] = node

        def check_node(node, where):
            nonlocal nodes_total
            nodes_total += 1
            types = [t for t in as_list(node.get("@type"))]
            for t in types:
                if t not in kinds:
                    errors.append(f"{where}: unknown type {t}")
            allowed = set()
            for t in types:
                allowed |= ancestors(t, parents)
            for prop, value in node.items():
                if prop in KEYWORDS:
                    continue
                if prop not in domains and any((t, prop) in EXTENSIONS for t in types):
                    continue
                if prop not in domains:
                    errors.append(f"{where} ({'/'.join(types) or '?'}): unknown property '{prop}'")
                    continue
                if types and not (domains[prop] & allowed):
                    errors.append(f"{where} ({'/'.join(types)}): property '{prop}' is not valid on this type (valid on: {sorted(domains[prop])[:4]}...)")
                for v in as_list(value):
                    check_value(prop, v, where)

        def check_value(prop, v, where):
            rng = ranges[prop]
            if isinstance(v, bool):
                if "Boolean" not in rng:
                    errors.append(f"{where}: '{prop}' got a boolean but accepts {sorted(rng)}")
            elif isinstance(v, (int, float)):
                if not (rng & NUMBERY) and "Text" not in rng:
                    errors.append(f"{where}: '{prop}' got a number but accepts {sorted(rng)}")
            elif isinstance(v, str):
                if not (rng & TEXTY) and not (rng & {"Boolean"}):
                    errors.append(f"{where}: '{prop}' got text but accepts {sorted(rng)}")
            elif isinstance(v, dict):
                if set(v) == {"@id"}:
                    target = defined.get(v["@id"])
                    if target is None:
                        if not re.match(r"^https?://", v["@id"]):
                            errors.append(f"{where}: '{prop}' references missing node {v['@id']}")
                        elif v["@id"].startswith("http") and v["@id"] not in defined and "#" in v["@id"]:
                            errors.append(f"{where}: '{prop}' references undefined node {v['@id']}")
                    else:
                        tt = set()
                        for t in as_list(target.get("@type")):
                            tt |= ancestors(t, parents)
                        if not (tt & rng) and "Thing" not in rng:
                            errors.append(f"{where}: '{prop}' -> {v['@id']} is {as_list(target.get('@type'))} but accepts {sorted(rng)[:5]}")
                    return
                if "@type" not in v:
                    errors.append(f"{where}: '{prop}' has an object with no @type")
                    return
                tt = set()
                for t in as_list(v["@type"]):
                    tt |= ancestors(t, parents)
                if not (tt & rng) and "Thing" not in rng:
                    errors.append(f"{where}: '{prop}' got {v['@type']} but accepts {sorted(rng)[:5]}")
                check_node(v, f"{where}/{prop}")

        for node in graph:
            check_node(node, f"{os.path.basename(path)}:{node.get('@id', node.get('@type'))}")
    print(f"checked {len(paths)} page(s), {nodes_total} nodes")
    for e in errors[:60]:
        print("  ERROR", e)
    if len(errors) > 60:
        print(f"  ... and {len(errors) - 60} more")
    print("RESULT:", "no errors" if not errors else f"{len(errors)} error(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    sys.exit(main(sys.argv[1:]))
