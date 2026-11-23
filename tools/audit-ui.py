#!/usr/bin/env python3
"""Audit statis kualitas antarmuka (Blade) — SIMANTAP.

Menghasilkan metrik dan daftar temuan yang bisa diukur ulang kapan saja:

    python3 tools/audit-ui.py            # ringkasan + temuan
    python3 tools/audit-ui.py --json     # keluaran JSON untuk dibandingkan antar commit

Yang diperiksa:
  * route-hilang / route-param-kurang : route() menunjuk nama tak terdaftar atau
    tidak diberi parameter wajib (memicu UrlGenerationException saat render).
  * form-tanpa-csrf, tombol-tanpa-type, tombol-tanpa-nama, tombol-tanpa-aksi
  * img-tanpa-alt, field-tanpa-name, label-tanpa-for, label-for-hilang
  * anchor-hilang, target-id-hilang, kolom-tabel-beda, tabel-tanpa-wrapper
  * blade-tak-seimbang, stack-tanpa-render, section-tanpa-yield
  * metrik konsistensi: inline style, warna hardcoded, !important, kelas tombol

Guard regresi hal-hal yang sudah bersih ada di tests/Unit/KualitasAntarmukaBladeTest.php.
Skrip ini untuk mengukur sisa utang UI/UX, bukan pengganti test tersebut.
"""
from __future__ import annotations

import argparse
import collections
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
VIEWS = ROOT / "resources" / "views"
ATTR = r"(?:(?:->)|[^>])*"  # atribut tag, tahan terhadap "-> " di dalam {{ $x->y }}


# --------------------------------------------------------------------------- route
def strip_php_comments(src: str) -> str:
    src = re.sub(r"/\*.*?\*/", "", src, flags=re.S)
    out = []
    for line in src.split("\n"):
        m = re.search(r"(?<![:\"'\\])//", line)
        out.append(line[: m.start()] if m else line)
    return "\n".join(out)


STANDARD = {
    "index": ("GET", ".index"),
    "create": ("GET", ".create"),
    "store": ("POST", ".store"),
    "show": ("GET", ".show"),
    "edit": ("GET", ".edit"),
    "update": ("PUT", ".update"),
    "destroy": ("DELETE", ".destroy"),
}


def parse_routes(path: pathlib.Path) -> dict[str, dict]:
    src = strip_php_comments(path.read_text(errors="ignore"))
    routes: dict[str, dict] = {}
    stack: list[str] = []
    buf, i, line_no, expr_line = "", 0, 1, 1
    quote = None

    while i < len(src):
        ch = src[i]
        if quote:
            buf += ch
            if ch == "\\" and i + 1 < len(src):
                buf += src[i + 1]
                i += 2
                continue
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            buf += ch
            i += 1
            continue
        if ch == "\n":
            line_no += 1
            buf += ch
            i += 1
            continue
        if ch == "{":
            names = re.findall(r"->name\(\s*'([^']*)'\s*\)", buf)
            stack.append(names[-1] if names else "")
            buf, expr_line = "", line_no
            i += 1
            continue
        if ch == "}":
            if stack:
                stack.pop()
            buf = ""
            i += 1
            continue
        if ch == ";":
            expr, prefix, add = buf.strip(), "".join(stack), {}
            m = re.match(r"Route::(get|post|put|patch|delete|any)\(\s*'([^']*)'", expr)
            if m:
                verb, uri = m.group(1), m.group(2)
                names = re.findall(r"->name\(\s*'([^']*)'\s*\)", expr)
                if names:
                    methods = {verb.upper()} if verb != "any" else {"GET", "POST", "PUT", "PATCH", "DELETE"}
                    add[prefix + names[-1]] = (methods, uri)
            m = re.match(r"Route::(?:resource|apiResource)\(\s*'([^']+)'", expr)
            if m:
                uri = m.group(1)
                base = re.sub(r"/+", ".", uri.strip("/"))
                only = re.findall(r"->(?:only|except)\(\s*\[([^\]]*)\]", expr)
                listed = re.findall(r"'([^']+)'", only[0]) if only else None
                is_except = "->except(" in expr
                for act, (verb, suffix) in STANDARD.items():
                    if listed is not None:
                        if is_except and act in listed:
                            continue
                        if not is_except and act not in listed:
                            continue
                    add[prefix + base + suffix] = ({verb}, uri)
            for name, (methods, uri) in add.items():
                routes.setdefault(name, {"methods": set(), "uri": uri, "line": expr_line})
                routes[name]["methods"] |= methods
            buf, expr_line = "", line_no
            i += 1
            continue
        buf += ch
        i += 1
    return routes


def load_routes() -> dict[str, dict]:
    routes: dict[str, dict] = {}
    for path in (ROOT / "routes" / "web.php", ROOT / "routes" / "api.php"):
        if not path.exists():
            continue
        for name, info in parse_routes(path).items():
            routes.setdefault(name, {"methods": set(), "uri": info["uri"]})
            routes[name]["methods"] |= info["methods"]
    return routes


# --------------------------------------------------------------------------- view
def view_path(name: str) -> pathlib.Path | None:
    rel = name.replace(".", "/")
    for cand in (VIEWS / f"{rel}.blade.php", VIEWS / rel / "index.blade.php"):
        if cand.exists():
            return cand
    return None


def reads(path: pathlib.Path) -> str:
    try:
        return path.read_text(errors="ignore")
    except OSError:
        return ""


def includes_of(text: str) -> list[str]:
    names = re.findall(r"@(?:include|includeIf|includeWhen|extends|component)\(\s*'([^']+)'", text)
    names += [c for c in re.findall(r"<x-([a-z0-9\-\.]+)", text)]
    return names


def closure_files(path: pathlib.Path, seen: set | None = None) -> list[pathlib.Path]:
    seen = seen if seen is not None else set()
    if path in seen:
        return []
    seen.add(path)
    out = [path]
    for inc in includes_of(reads(path)):
        sub = view_path(inc) or (VIEWS / "components" / f"{inc}.blade.php")
        if sub and sub.exists():
            out += closure_files(sub, seen)
    return out


def clean(text: str) -> str:
    text = re.sub(r"\{\{--.*?--\}\}", "", text, flags=re.S)
    text = re.sub(r"<!--.*?-->", "", text, flags=re.S)
    return re.sub(r"@php\b.*?@endphp", "", text, flags=re.S)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--json", action="store_true", help="keluarkan JSON")
    args = parser.parse_args()

    routes = load_routes()
    findings: dict[str, list] = collections.defaultdict(list)
    metrics = collections.Counter()

    def add(kind: str, rel: str, line: int, detail: str = "") -> None:
        findings[kind].append({"file": rel, "line": line, "detail": detail})

    views = sorted(VIEWS.rglob("*.blade.php"))

    for path in views:
        rel = str(path).replace(str(ROOT) + "/", "")
        if "/vendor/" in rel:
            continue
        text = clean(reads(path))
        line_of = lambda idx: text.count("\n", 0, idx) + 1  # noqa: E731

        # route()
        for m in re.finditer(r"route\(\s*'([^']+)'", text):
            name = m.group(1)
            if re.search(r"\$\{|\.\s*\$|\$[a-zA-Z_]", name):
                continue
            if name not in routes:
                add("route-hilang", rel, line_of(m.start()), name)
                continue
            need = len(re.findall(r"\{[^}]+\}", routes[name]["uri"] or ""))
            if need and not re.match(r"\s*,", text[m.end(): m.end() + 200]):
                before = text[max(0, m.start() - 160): m.start()]
                add("route-param-kurang", rel, line_of(m.start()), f"{name} butuh {need} parameter"
                    f" (guarded={'Route::has' in before})")

        # form
        forms = list(re.finditer(r"<form\b(" + ATTR + r")>(.*?)</form>", text, re.S | re.I))
        spans = [(m.start(), m.end()) for m in forms]
        for fm in forms:
            attrs, body = fm.group(1), fm.group(2)
            method = re.search(r'method\s*=\s*"?(get|post)"?', attrs, re.I)
            if not method or method.group(1).lower() == "get":
                continue
            if "@csrf" not in body and "@csrf_field" not in body:
                add("form-tanpa-csrf", rel, line_of(fm.start()), attrs.strip()[:70])
            for bm in re.finditer(r"<button\b(" + ATTR + r")>", body, re.I):
                if not re.search(r"\btype\s*=", bm.group(1), re.I):
                    add("tombol-tanpa-type", rel, line_of(fm.start(2) + bm.start()), "")

        # tombol
        for bm in re.finditer(r"<button\b(" + ATTR + r")>(.*?)</button>", text, re.S | re.I):
            attrs, body = bm.group(1), bm.group(2)
            inside_form = any(a <= bm.start() <= b for a, b in spans)
            if not inside_form and not re.search(
                r"\btype\s*=|onclick|x-on:|@click|data-bs-|data-toggle|data-target|data-action|hx-|wire:",
                attrs, re.I,
            ):
                add("tombol-tanpa-aksi", rel, line_of(bm.start()), attrs.strip()[:60])
            if re.search(r"\baria-label\s*=|\btitle\s*=", attrs, re.I) or "{{" in body:
                continue
            teks = re.sub(r"<i\b" + ATTR + r">\s*</i>", "", body, flags=re.S | re.I)
            teks = re.sub(r"<svg\b.*?</svg>", "", teks, flags=re.S | re.I)
            teks = re.sub(r"<[^>]+>", "", teks, flags=re.S).strip()
            if not re.sub(r"[^A-Za-z0-9]", "", teks):
                add("tombol-tanpa-nama", rel, line_of(bm.start()), attrs.strip()[:60])

        # gambar & tautan
        for m in re.finditer(r"<img\b" + ATTR + r">", text, re.I):
            if not re.search(r"\balt\s*=", m.group(0), re.I):
                add("img-tanpa-alt", rel, line_of(m.start()), m.group(0)[:70])
        for m in re.finditer(r"<a\b(" + ATTR + r")>", text, re.I):
            if not re.search(r"\bhref\s*=", m.group(1), re.I):
                add("anchor-tanpa-href", rel, line_of(m.start()), "")
        for m in re.finditer(r'href\s*=\s*"#"', text):
            add("link-mati", rel, line_of(m.start()), 'href="#"')

        # label & field
        ids_here = set(re.findall(r'\bid\s*=\s*"([A-Za-z0-9_\-:.]+)"', text))
        for m in re.finditer(r"<label\b(" + ATTR + r")>(.*?)</label>", text, re.S | re.I):
            if re.search(r"\bfor\s*=", m.group(1), re.I):
                continue
            # label yang membungkus field tidak butuh for=
            if re.search(r"<(input|select|textarea)\b", m.group(2), re.I):
                continue
            add("label-tanpa-for", rel, line_of(m.start()), "")
        for m in re.finditer(r'<label\b[^>]*\bfor\s*=\s*"([A-Za-z0-9_\-:.]+)"', text, re.I):
            if m.group(1) not in ids_here:
                add("label-for-hilang", rel, line_of(m.start()), m.group(1))

        # tabel
        pdf_view = (
            "/pdf/" in rel
            or path.name in ("pdf.blade.php", "pdf-bulk.blade.php")
            or "pdf-" in path.name
            or "-pdf-" in path.name
            or path.name.endswith("-pdf.blade.php")
        )
        for tm in re.finditer(r"<table\b.*?</table>", text, re.S | re.I):
            block = tm.group(0)
            thead = re.search(r"<thead\b.*?</thead>", block, re.S | re.I)
            if not thead:
                continue
            def width(chunk: str, tags: tuple[str, ...]) -> int:
                total = 0
                for tag in tags:
                    for cell in re.findall(rf"<{tag}\b" + ATTR + r">", chunk, re.I):
                        found = re.search(r'colspan\s*=\s*"?(\d+)', cell, re.I)
                        total += int(found.group(1)) if found else 1
                return total
            th = max((width(r, ("th",)) for r in re.findall(r"<tr\b" + ATTR + r">(.*?)</tr>", thead.group(0), re.S | re.I)), default=0)
            rest = block[thead.end():]
            loop = re.search(r"@(foreach|forelse)\b", rest)
            row = re.search(r"<tr\b" + ATTR + r">(.*?)</tr>", rest[loop.start():] if loop else rest, re.S | re.I)
            if not row or re.search(r"@(if|else|elseif|endif|foreach|forelse)\b", row.group(1)):
                continue
            td = width(row.group(1), ("td",))
            if th and td and th != td:
                add("kolom-tabel-beda", rel, line_of(tm.start()), f"thead={th} td={td}")
        if not pdf_view:
            for m in re.finditer(r"<table\b", text):
                ctx = text[max(0, m.start() - 500): m.start()]
                if "table-wrapper" in ctx or "table-responsive" in ctx or "overflow" in ctx:
                    continue
                add("tabel-tanpa-wrapper", rel, line_of(m.start()), "")

            raw_no_comments = re.sub(r"\{\{--.*?--\}\}|<!--.*?-->", "", reads(path), flags=re.S)
            root_spans = [(m.start(), m.end()) for m in re.finditer(r":root\s*\{[^}]*\}", raw_no_comments, flags=re.S)]
            for m in re.finditer(r"#(?:B42318|991B1B|DC2626|D92D20|EF4444|DC3545|B91C1C)\b", raw_no_comments, re.I):
                if any(a <= m.start() <= b for a, b in root_spans):
                    continue
                add("warna-destruktif-hardcoded", rel, raw_no_comments.count("\n", 0, m.start()) + 1, m.group(0))

        # directive balance
        for name, op, cl in (
            ("if", r"@if\b", r"@endif\b"),
            ("foreach", r"@foreach\b", r"@endforeach\b"),
            ("forelse", r"@forelse\b", r"@endforelse\b"),
            ("section", r"@section\(\s*'[^']+'\s*\)", r"@endsection\b|@stop\b"),
            ("push", r"@push\(\s*'[^']+'\s*\)", r"@endpush\b"),
        ):
            o, c = len(re.findall(op, text)), len(re.findall(cl, text))
            if o != c:
                add("blade-tak-seimbang", rel, 1, f"@{name} buka={o} tutup={c}")

        # metrik konsistensi
        metrics["style_inline"] += len(re.findall(r"style\s*=", text))
        metrics["important"] += len(re.findall(r"!important", text))
        metrics["hex_color"] += len(re.findall(r"#[0-9a-fA-F]{6}\b", text))
        metrics["token_bad"] += len(re.findall(r"var\(--bad\)|getComputedStyle\([^)]*\)\.getPropertyValue\('--bad'\)", reads(path)))
        metrics["aria_label"] += len(re.findall(r"aria-label", text))
        metrics["label"] += len(re.findall(r"<label\b", text))
        metrics["input"] += len(re.findall(r"<(input|select|textarea)\b", text))
        metrics["form_control"] += len(re.findall(r'class="[^"]*form-control', text))
        metrics["tabel"] += len(re.findall(r"<table\b", text))
        metrics["tabel_wrapper"] += len(re.findall(r"table-wrapper", text))
        metrics["tabel_responsive"] += len(re.findall(r"table-responsive", text))
        metrics["tombol"] += len(re.findall(r"<button\b", text))
        for k in ("btn-primary", "btn-ghost", "btn-outline", "btn-secondary", "btn-danger"):
            metrics["kelas_" + k] += len(re.findall(r"\b" + k + r"\b", text))
        metrics["ikon_fontawesome"] += len(re.findall(r"\b(fas|far|fab)\b", text))
        metrics["ikon_lucide"] += len(re.findall(r"<x-lucide-", text))

    # anchor target lintas partial
    for path in views:
        rel = str(path).replace(str(ROOT) + "/", "")
        if "/vendor/" in rel:
            continue
        text = clean(reads(path))
        ids = set()
        for f in closure_files(path):
            ids |= set(re.findall(r'\bid\s*=\s*"([A-Za-z0-9_\-:.]+)"', reads(f)))
        for m in re.finditer(r'href\s*=\s*"#([A-Za-z0-9_\-]+)"', text):
            if m.group(1) not in ids and "{{" not in text[max(0, m.start() - 30): m.start()]:
                add("anchor-hilang", rel, text.count("\n", 0, m.start()) + 1, "#" + m.group(1))

    # stack/section lintas berkas
    stacks, yields = set(), set()
    for path in views:
        raw = reads(path)
        stacks |= set(re.findall(r"@stack\(\s*'([^']+)'", raw))
        yields |= set(re.findall(r"@yield\(\s*'([^']+)'", raw))
    for path in views:
        rel = str(path).replace(str(ROOT) + "/", "")
        text = clean(reads(path))
        for name in set(re.findall(r"@push\(\s*'([^']+)'", text)):
            if name not in stacks:
                add("stack-tanpa-render", rel, 1, name)
        for name in set(re.findall(r"@section\(\s*'([^']+)'", text)):
            if name not in yields:
                add("section-tanpa-yield", rel, 1, name)

    if args.json:
        print(json.dumps({"findings": findings, "metrics": metrics}, indent=1))
        return 0

    print("=== RINGKASAN TEMUAN ===")
    for kind, items in sorted(findings.items(), key=lambda kv: -len(kv[1])):
        print(f"{len(items):6d}  {kind}")
    print("\n=== METRIK KONSISTENSI ===")
    for key, value in metrics.items():
        print(f"{value:6d}  {key}")
    print(f"\n{len(views)} berkas Blade dipindai, {len(routes)} nama route terdaftar.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
