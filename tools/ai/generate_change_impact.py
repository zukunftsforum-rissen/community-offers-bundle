
import os
from pathlib import Path
from collections import defaultdict

OUT=Path("docs/ai/generated/change-impact.md")

index=defaultdict(list)

for root,_,files in os.walk("."):
    for file in files:
        if file.endswith((".php",".js",".yaml",".yml",".twig")):
            path=Path(root)/file
            text=path.read_text(errors="ignore")
            for symbol in ["DoorJobService","DeviceController","confirmJob"]:
                if symbol in text:
                    index[symbol].append(str(path))

OUT.parent.mkdir(parents=True,exist_ok=True)

with open(OUT,"w") as f:
    f.write("# Change Impact Map\n\n")
    for k in index:
        f.write(f"## {k}\n")
        for p in index[k]:
            f.write(f"- `{p}`\n")
        f.write("\n")
