# AI Onboarding Documentation

This directory contains documentation intended for:

- AI assistants
- Code analysis tools
- New developers needing a fast system overview

---

# Purpose

This documentation provides:

- A fast conceptual overview of the system
- Navigation hints for the codebase
- Explanation of domain concepts
- Overview of workflows and services
- References to important controllers, services, and tables

This documentation is intentionally:

- high-level
- navigation-oriented
- implementation-aware

---

# Canonical Source of Truth

⚠️ Important:

The **codebase is always the canonical source of truth**.

This documentation:

- may lag behind implementation
- may simplify technical details
- must never replace code inspection

Whenever precise details are required, always verify against:

- Controllers
- Services
- Database schema
- Tests

Especially for:

- API parameters
- State transitions
- Timing behavior
- Security logic

---

# Recommended Reading Order

For a new AI or developer:

1. architecture.md  
   → System structure and components

2. domain-model.md  
   → Core entities and relationships

3. api-flows.md  
   → Runtime interaction flows

4. glossary.md  
   → Domain terminology

5. code-navigation.md  
   → How to find relevant code

6. generated/class-index.md  
   → Full class reference

7. generated/test-map.md  
   → Test coverage map

---

# Important Behavioral Rules (for AI)

When analyzing or modifying the system:

Always:

- Read relevant services before suggesting changes
- Check workflow transitions carefully
- Verify API endpoints against controllers
- Confirm database usage patterns

Never:

- Assume undocumented behavior
- Invent endpoints
- Modify workflows without checking state transitions
- Suggest changes that break polling logic

---

# System Characteristics

Key architectural constraints:

- Device communication uses polling
- No inbound connections to devices
- Confirm window timing is critical
- Jobs follow a strict lifecycle
- Security is token-based

These rules must be preserved.

---

# Scope of This Directory

This directory supports:

- AI-assisted development
- Code review automation
- Developer onboarding
- Architectural understanding

It does NOT replace:

- Source code
- Tests
- Runtime logs

---

# Maintenance Guidelines

Whenever the system changes:

Update this documentation if:

- New services are introduced
- API endpoints change
- Workflow states change
- Database schema changes
- Authentication behavior changes

Consistency between:

- code
- tests
- documentation

is critical.

