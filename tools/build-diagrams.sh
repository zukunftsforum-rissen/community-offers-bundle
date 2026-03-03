#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# -------- config --------
OUT_DIR="docs/diagrams"
DCA_DIR="contao/dca"

ARCH_SRC="$OUT_DIR/source/architecture.mmd"
ARCH_PNG="$OUT_DIR/generated/architecture.png"
ARCH_SVG="$OUT_DIR/generated/architecture.svg"
ARCH_PDF="$OUT_DIR/generated/architecture.pdf"

PUML_GEN="$OUT_DIR/generated/data-model.generated.puml"
PUML_PNG="$OUT_DIR/generated/data-model.generated.png"
PUML_PDF="$OUT_DIR/generated/data-model.generated.pdf"

# Docker images
IMG_MERMAID="minlag/mermaid-cli"
IMG_PLANTUML="plantuml/plantuml"
IMG_IMAGEMAGICK="dpokidov/imagemagick"

# Run docker as current user to avoid root-owned outputs
DU="$(id -u):$(id -g)"

# -------- helpers --------
die() {
	echo "ERROR: $*" >&2
	exit 1
}
info() { echo "==> $*"; }
ensure_dir() { mkdir -p "$1"; }

docker_run() {
	# shellcheck disable=SC2068
	docker run --rm -u "$DU" -v "$PWD":/work -w /work "$@"
}

require_nonempty() {
	local f="$1"
	test -f "$f" || die "Missing file: $f"
	test -s "$f" || die "Empty file: $f"
}

# -------- preflight --------
command -v docker >/dev/null 2>&1 || die "docker not found"
command -v php >/dev/null 2>&1 || die "php not found"

ensure_dir "$OUT_DIR/source"
ensure_dir "$OUT_DIR/generated"
ensure_dir "$OUT_DIR/archive"

test -f "$ARCH_SRC" || die "Missing Mermaid source: $ARCH_SRC"
test -d "$DCA_DIR" || die "Missing DCA dir: $DCA_DIR (adjust DCA_DIR in script)"

# -------- build --------
info "Generating PlantUML from DCA -> $PUML_GEN"
php tools/generate_data_model_puml.php \
	--dca-dir "$DCA_DIR" \
	--out "$PUML_GEN"

info "Rendering PlantUML (PNG) via Docker"
docker_run "$IMG_PLANTUML" -tpng "$PUML_GEN"
require_nonempty "$PUML_PNG"

info "Building PlantUML PDF from PNG (ImageMagick, Docker-only)"
# dpokidov/imagemagick uses 'magick' as entrypoint; pass args only.
docker_run "$IMG_IMAGEMAGICK" \
	"$PUML_PNG" \
	-background white -alpha remove -alpha off -flatten \
	-density 300 \
	"$PUML_PDF"
require_nonempty "$PUML_PDF"

info "Rendering Mermaid via Docker (PNG, SVG, PDF)"
# PNG: raise scale for better readability even without SVG
docker_run "$IMG_MERMAID" -i "$ARCH_SRC" -o "$ARCH_PNG" --scale 2
docker_run "$IMG_MERMAID" -i "$ARCH_SRC" -o "$ARCH_SVG"
docker_run "$IMG_MERMAID" -i "$ARCH_SRC" -o "$ARCH_PDF"

require_nonempty "$ARCH_PNG"
require_nonempty "$ARCH_SVG"
require_nonempty "$ARCH_PDF"

# -------- result --------
info "Done. Generated:"
echo "  - $ARCH_PNG"
echo "  - $ARCH_SVG"
echo "  - $ARCH_PDF"
echo "  - $PUML_GEN"
echo "  - $PUML_PNG"
echo "  - $PUML_PDF"
