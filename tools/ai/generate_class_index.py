
import os,re
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[2]
SRC_DIR = BASE_DIR / "src"
OUT_FILE = BASE_DIR / "docs/ai/generated/class-index.md"

class_pattern = re.compile(r"class\s+(\w+)")
method_pattern = re.compile(r"public function\s+(\w+)\(")

classes=[]

for root,_,files in os.walk(SRC_DIR):
    for file in files:
        if file.endswith(".php"):
            path=Path(root)/file
            text=path.read_text(errors="ignore")
            cm=class_pattern.search(text)
            if not cm:
                continue
            methods=method_pattern.findall(text)
            classes.append((cm.group(1),path,methods))

OUT_FILE.parent.mkdir(parents=True,exist_ok=True)

with open(OUT_FILE,"w") as f:
    f.write("# Class Index\n\n")
    for c,p,m in classes:
        f.write(f"## {c}\nFile: `{p}`\n")
        for mm in m:
            f.write(f"- {mm}()\n")
        f.write("\n")
