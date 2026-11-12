"""
Compare two .pot files by (msgctxt, msgid, msgid_plural).

A regenerated pot is only safe to install if everything it drops is
genuinely gone from the source. This prints both directions so the
losses can be checked one by one rather than assumed.
"""
import io
import re
import sys


def parse(path):
    """Return {key: (ctxt, msgid, plural, [refs])} for every entry but the header."""
    text = io.open(path, encoding="utf-8").read()
    blocks = re.split(r"\n\s*\n", text)
    out = {}
    for b in blocks:
        if "msgid " not in b:
            continue
        refs = []
        ctxt = plural = None
        msgid = None
        cur = None
        for line in b.split("\n"):
            line = line.rstrip("\r")
            if line.startswith("#:"):
                refs.extend(line[2:].split())
                continue
            if line.startswith("#"):
                continue
            m = re.match(r'(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?) "(.*)"$', line)
            if m:
                cur = m.group(1)
                val = m.group(2)
                if cur == "msgctxt":
                    ctxt = val
                elif cur == "msgid":
                    msgid = val
                elif cur == "msgid_plural":
                    plural = val
                continue
            m = re.match(r'"(.*)"$', line)
            if m and cur:
                if cur == "msgctxt":
                    ctxt += m.group(1)
                elif cur == "msgid":
                    msgid += m.group(1)
                elif cur == "msgid_plural":
                    plural += m.group(1)
        if msgid is None or msgid == "":
            continue
        out[(ctxt, msgid, plural)] = refs
    return out


def main():
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    old, new = parse(sys.argv[1]), parse(sys.argv[2])
    ok, nk = set(old), set(new)

    # Match on msgid alone too, so a pure "gained a plural form" shows as that
    # rather than as one loss plus one gain.
    old_by_id = {}
    for (c, m, p) in ok:
        old_by_id.setdefault((c, m), []).append(p)
    new_by_id = {}
    for (c, m, p) in nk:
        new_by_id.setdefault((c, m), []).append(p)

    lost = sorted(k for k in old_by_id if k not in new_by_id)
    gained = sorted(k for k in new_by_id if k not in old_by_id)
    gained_plural = sorted(
        k for k in old_by_id
        if k in new_by_id
        and None in old_by_id[k] and None not in new_by_id[k]
    )

    print("old entries: %d   new entries: %d" % (len(ok), len(nk)))
    print("\nLOST (in old, msgid absent from new): %d" % len(lost))
    for c, m in lost:
        print("  - %s%s   [%s]" % ("%s|" % c if c else "", m[:88],
                                   ", ".join(old[(c, m, old_by_id[(c, m)][0])][:2])))
    print("\nGAINED PLURAL FORM: %d" % len(gained_plural))
    for c, m in gained_plural:
        print("  ~ %s" % m[:88])
    print("\nGAINED (new msgids): %d" % len(gained))
    for c, m in gained[:400]:
        print("  + %s%s   [%s]" % ("%s|" % c if c else "", m[:88],
                                   ", ".join(new[(c, m, new_by_id[(c, m)][0])][:2])))


main()
