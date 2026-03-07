
import os
from pathlib import Path

SRC=Path("src")
TEST=Path("tests")
OUT=Path("docs/ai/generated/test-map.md")

classes=[f.replace(".php","") for f in os.listdir(SRC) if f.endswith(".php")]
tests=[f.replace("Test.php","") for f in os.listdir(TEST) if f.endswith("Test.php")]

OUT.parent.mkdir(parents=True,exist_ok=True)

with open(OUT,"w") as f:
    f.write("# Test Coverage Map\n\n")
    for c in classes:
        status="tested" if c in tests else "missing tests"
        f.write(f"- {c}: {status}\n")
