#!/usr/bin/env python3
"""Add a content-hash version to local CSS/JS links in every HTML page,
e.g. css/style.css -> css/style.css?v=1a2b3c4d, so browsers fetch the new
file after each change instead of a stale cached copy.

Run after editing CSS/JS or after the build scripts:
    python3 scripts/cache_bust.py
"""
import hashlib
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LINK = re.compile(r'((?:href|src)=")((?:\.\./)?(?:css|js)/[\w.-]+\.(?:css|js))(?:\?v=[0-9a-f]+)?(")')


def digest(path):
    with open(path, "rb") as f:
        return hashlib.sha1(f.read()).hexdigest()[:8]


def main():
    changed = 0
    for dirpath, _, files in os.walk(ROOT):
        if "/.git" in dirpath or "/assets" in dirpath:
            continue
        for name in files:
            if not name.endswith(".html"):
                continue
            page = os.path.join(dirpath, name)
            src = open(page, encoding="utf-8").read()

            def ver(m):
                target = os.path.normpath(os.path.join(dirpath, m.group(2)))
                if not os.path.exists(target):
                    return m.group(0)
                return "%s%s?v=%s%s" % (m.group(1), m.group(2), digest(target), m.group(3))

            out = LINK.sub(ver, src)
            if out != src:
                open(page, "w", encoding="utf-8").write(out)
                changed += 1
    print("versioned assets in %d page(s)" % changed)


if __name__ == "__main__":
    main()
