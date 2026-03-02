#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OUT_DIR="docs/diagrams"
DCA_DIR="contao/dca"

ARCH_SRC="$OUT_DIR/architecture.mmd"
ARCH_PNG="$OUT_DIR/architecture.png"
ARCH_PDF="$OUT_DIR/architecture.pdf"

PUML_GEN="$OUT_DIR/data-model.generated.puml"

echo "==> Generating PlantUML from DCA..."
php tools/generate_data_model_puml.php \
	--dca-dir "$DCA_DIR" \
	--out "$PUML_GEN"

echo "==> Rendering PlantUML (PNG/PDF) via Docker..."
docker run --rm \
	-u "$(id -u):$(id -g)" \
	-v "$PWD":/work \
	-w /work \
	plantuml/plantuml \
	-tpng "$PUML_GEN"

docker run --rm \
	-u "$(id -u):$(id -g)" \
	-v "$PWD":/work \
	-w /work \
	plantuml/plantuml \
	-tpdf "$PUML_GEN"
	
echo "==> Rendering Mermaid (PNG/PDF) via Docker..."
test -f "$ARCH_SRC"

# Mermaid CLI container
docker run --rm \
	-u "$(id -u):$(id -g)" \
	-v "$PWD":/work \
	-w /work \
	minlag/mermaid-cli \
	-i "$ARCH_SRC" \
	-o "$ARCH_PNG"

docker run --rm \
	-u "$(id -u):$(id -g)" \
	-v "$PWD":/work \
	-w /work \
	minlag/mermaid-cli \
	-i "$ARCH_SRC" \
	-o "$ARCH_PDF"

echo "==> Done."
echo "  - $ARCH_PNG"
echo "  - $ARCH_PDF"
echo "  - ${PUML_GEN%.puml}.png"
echo "  - ${PUML_GEN%.puml}.pdf"
