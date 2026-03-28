# Diagrams – Build and Generation Guide

This directory contains architecture, workflow and data model diagrams
used throughout the documentation.

Some diagrams are manually maintained, while others are
auto-generated from source files such as DCA definitions.

---

# Generated Files

Files located in:

docs/diagrams/generated/

are auto-generated.

Do not edit these files manually.

They are recreated during the build process and any manual changes
will be overwritten.

Source of truth:

- contao/dca/*
- docs/diagrams/source/*
- helper scripts in tools/

---

# How to Regenerate Diagrams

You should regenerate diagrams after:

- changing DCA files
- adding new database tables
- modifying diagram sources
- updating architecture diagrams
- changing workflow definitions

---

# Prerequisites

Required tools:

- Docker
- PHP (CLI)

Verify:

docker --version
php --version

---

# Generate All Diagrams

Run from project root:

tools/build-diagrams.sh

This script:

1. Generates data model .puml files from DCA
2. Runs PlantUML
3. Generates Mermaid diagrams
4. Writes output into generated/

---

# Expected Output Files

docs/diagrams/generated/

Typical output:

- data-model-generated.puml
- data-model-generated.png
- architecture.png
- workflow.png

---

# When to Regenerate

Regenerate diagrams when:

- DCA structure changes
- database schema changes
- workflow states change
- architecture changes
- new diagram sources are added

---

# Important Rules

Never manually edit:

docs/diagrams/generated/*

These files:

- are build artifacts
- are reproducible
- should remain consistent

Fix source → regenerate → commit.

---

# Troubleshooting

Docker not found:

Install Docker:
https://docs.docker.com/get-docker/

Permission denied:

chmod +x tools/build-diagrams.sh

Diagram not updated:

rm docs/diagrams/generated/*
tools/build-diagrams.sh

---

# Directory Structure

docs/diagrams/

source/
    architecture.puml
    workflow.puml
    *.mmd

generated/
    data-model-generated.puml
    *.png
    *.svg

README.md

---

# Developer Notes

The generation process is deterministic.

Same input → same diagrams.

Generated files are reproducible and safe for CI/CD use.
