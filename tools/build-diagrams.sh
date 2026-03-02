#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# -------- config --------
OUT_DIR="docs/diagrams"
DCA_DIR="contao/dca"

ARCH_SRC="$OUT_DIR/source/architecture.mmd"
ARCH_PNG="$OUT_DIR/generated/architecture.png"
ARCH_PDF="$OUT_DIR/generated/architecture.pdf"

PUML_GEN="$OUT_DIR/generated/data-model.generated.puml"
PUML_PNG="$OUT_DIR/generated/data-model.generated.png"
PUML_PDF="$OUT_DIR/generated/data-model.generated.pdf"

# Docker images
IMG_MERMAID="minlag/mermaid-cli"
IMG_PLANTUML="plantuml/plantuml"

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

# -------- preflight --------
command -v docker >/dev/null 2>&1 || die "docker not found"
command -v php >/dev/null 2>&1 || die "php not found"

ensure_dir "$OUT_DIR"

test -f "$ARCH_SRC" || die "Missing Mermaid source: $ARCH_SRC"
test -d "$DCA_DIR" || die "Missing DCA dir: $DCA_DIR (adjust DCA_DIR in script)"

# If previous runs created root-owned files, fix them (best effort)
if [ -e "$OUT_DIR" ]; then
	# If you prefer manual: sudo chown -R $USER:$USER docs/diagrams
	if ! touch "$OUT_DIR/.write_test" 2>/dev/null; then
		info "docs/diagrams not writable. Trying to fix permissions (may require sudo)..."
		sudo chown -R "$USER:$USER" "$OUT_DIR" || true
	else
		rm -f "$OUT_DIR/.write_test"
	fi
fi

# -------- build --------
info "Generating PlantUML from DCA -> $PUML_GEN"
php tools/generate_data_model_puml.php \
	--dca-dir "$DCA_DIR" \
	--out "$PUML_GEN"

info "Rendering PlantUML (PNG) via Docker"
docker_run "$IMG_PLANTUML" -tpng "$PUML_GEN"

info "Building PlantUML PDF from PNG (ImageMagick, Docker-only)"
docker run --rm \
	-u "$(id -u):$(id -g)" \
	-v "$PWD":/work \
	-w /work \
	dpokidov/imagemagick \
	convert -density 150 "$PUML_PNG" "$PUML_PDF"
info "Rendering Mermaid (PNG/PDF) via Docker"
docker_run "$IMG_MERMAID" -i "$ARCH_SRC" -o "$ARCH_PNG"
docker_run "$IMG_MERMAID" -i "$ARCH_SRC" -o "$ARCH_PDF"

# -------- result --------
info "Done. Generated:"
[ -f "$ARCH_PNG" ] && echo "  - $ARCH_PNG"
[ -f "$ARCH_PDF" ] && echo "  - $ARCH_PDF"
[ -f "$PUML_GEN" ] && echo "  - $PUML_GEN"
[ -f "$PUML_PNG" ] && echo "  - $PUML_PNG"
[ -f "$PUML_PDF" ] && echo "  - $PUML_PDF"
