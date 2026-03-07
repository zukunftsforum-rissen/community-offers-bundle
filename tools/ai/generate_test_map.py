import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[2]
SRC = BASE_DIR / "src"
TEST = BASE_DIR / "tests"
OUT = BASE_DIR / "docs/ai/generated/test-map.md"

classes = []
for root, _, files in os.walk(SRC):
    for file in files:
        if file.endswith(".php"):
            classes.append(file.replace(".php", ""))

tests = []
for root, _, files in os.walk(TEST):
    for file in files:
        if file.endswith("Test.php"):
            tests.append(file.replace("Test.php", ""))

OUT.parent.mkdir(parents=True, exist_ok=True)

with open(OUT, "w", encoding="utf-8") as f:
    f.write("# Test Coverage Map\n\n")
    for c in sorted(classes):
        status = "tested" if c in tests else "missing tests"
        f.write(f"- {c}: {status}\n")