import os
import re
from pathlib import Path
from collections import defaultdict

BASE_DIR = Path(__file__).resolve().parents[2]
SRC_DIR = BASE_DIR / "src"
TEST_DIR = BASE_DIR / "tests"
OUT_FILE = BASE_DIR / "docs/ai/generated/permission-matrix.md"

AREA_CANDIDATES = [
    "workshop",
    "sharing",
    "swap-house",
    "swap_house",
    "depot",
]

RULE_PATTERNS = {
    "adult-check": [
        r"18",
        r"age",
        r"adult",
        r"vollj",
        r"underage",
    ],
    "crate-subscription": [
        r"crate",
        r"kisten",
        r"abo",
        r"subscription",
    ],
    "member-check": [
        r"member",
        r"mitglied",
        r"authenticated",
        r"logged",
        r"user",
    ],
    "permission-check": [
        r"allow",
        r"allowed",
        r"deny",
        r"denied",
        r"access",
        r"permission",
        r"authorize",
    ],
}

AREA_STRING_PATTERN = re.compile(r"""['"]([^'"]+)['"]""")
METHOD_PATTERN = re.compile(r"public function\s+(\w+)\(")


def read_text(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return path.read_text(encoding="utf-8", errors="ignore")


def collect_files():
    files = []
    for root_dir in (SRC_DIR, TEST_DIR):
        if not root_dir.exists():
            continue

        for root, _, names in os.walk(root_dir):
            for name in names:
                if name.endswith((".php", ".yaml", ".yml", ".twig", ".js", ".md")):
                    files.append(Path(root) / name)

    return files


def detect_areas(text: str) -> set[str]:
    found = set()
    for match in AREA_STRING_PATTERN.findall(text):
        value = match.strip().lower()
        if value in AREA_CANDIDATES:
            found.add(value)
    return found


def detect_rule_tags(text: str) -> set[str]:
    tags = set()
    for tag, patterns in RULE_PATTERNS.items():
        for pattern in patterns:
            if re.search(pattern, text, re.IGNORECASE):
                tags.add(tag)
                break
    return tags


def guess_kind(path: Path) -> str:
    path_str = str(path).replace("\\", "/")
    if "/Controller/" in path_str:
        return "controller"
    if "/Service/" in path_str:
        return "service"
    if "/Entity/" in path_str:
        return "entity"
    if "/Repository/" in path_str:
        return "repository"
    if "/tests/" in path_str.lower():
        return "test"
    return "other"


def extract_methods(text: str) -> list[str]:
    return METHOD_PATTERN.findall(text)


def build_area_map():
    area_map = defaultdict(list)

    for path in collect_files():
        text = read_text(path)
        areas = detect_areas(text)
        if not areas:
            continue

        tags = detect_rule_tags(text)
        methods = extract_methods(text)
        kind = guess_kind(path)

        for area in sorted(areas):
            area_map[area].append({
                "path": str(path.relative_to(BASE_DIR)).replace("\\", "/"),
                "kind": kind,
                "tags": sorted(tags),
                "methods": methods,
            })

    return area_map


def summarize_expected_rule(area: str) -> str:
    if area == "workshop":
        return "members age 18+"
    if area == "depot":
        return "members with active crate subscription"
    if area in {"sharing", "swap-house", "swap_house"}:
        return "authorized members"
    return "unknown"


def write_markdown(area_map):
    OUT_FILE.parent.mkdir(parents=True, exist_ok=True)

    with OUT_FILE.open("w", encoding="utf-8") as f:
        f.write("# Permission Matrix\n\n")
        f.write("This file is auto-generated from the repository.\n\n")

        f.write("## Expected Rules\n\n")
        for area in AREA_CANDIDATES:
            f.write(f"- `{area}` → {summarize_expected_rule(area)}\n")
        f.write("\n")

        f.write("## Detected Permission References by Area\n\n")

        for area in AREA_CANDIDATES:
            f.write(f"### {area}\n\n")
            f.write(f"Expected rule: **{summarize_expected_rule(area)}**\n\n")

            entries = area_map.get(area, [])
            if not entries:
                f.write("- No code references detected for this area.\n\n")
                continue

            for entry in sorted(entries, key=lambda x: (x["kind"], x["path"])):
                f.write(f"- `{entry['path']}` ({entry['kind']})\n")

                if entry["tags"]:
                    f.write(f"  - detected rule tags: {', '.join(entry['tags'])}\n")

                if entry["methods"]:
                    method_list = ", ".join(f"`{m}()`" for m in entry["methods"])
                    f.write(f"  - public methods: {method_list}\n")

            f.write("\n")

        f.write("## Coverage Hints\n\n")
        for area in AREA_CANDIDATES:
            entries = area_map.get(area, [])
            has_test = any(entry["kind"] == "test" for entry in entries)
            has_service = any(entry["kind"] == "service" for entry in entries)
            has_controller = any(entry["kind"] == "controller" for entry in entries)

            f.write(f"### {area}\n")
            f.write(f"- service reference detected: {'yes' if has_service else 'no'}\n")
            f.write(f"- controller reference detected: {'yes' if has_controller else 'no'}\n")
            f.write(f"- test reference detected: {'yes' if has_test else 'no'}\n\n")

        f.write("## Review Questions\n\n")
        f.write("- Are all configured areas covered by explicit permission logic?\n")
        f.write("- Is permission logic centralized or scattered?\n")
        f.write("- Do tests exist for positive and negative cases per area?\n")
        f.write("- Do detected code references match the expected domain rules?\n")


def main():
    area_map = build_area_map()
    write_markdown(area_map)
    print(f"Wrote {OUT_FILE}")


if __name__ == "__main__":
    main()