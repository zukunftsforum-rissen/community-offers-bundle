
# App Troubleshooting Guide

This document contains practical troubleshooting steps
for the `/app` frontend of the Community Offers Bundle.

It focuses on real-world failure scenarios that have
occurred during development and production deployment.

---

# Quick Diagnostic Flow

If `/app` does not load:

1. Check redirect loop
2. Check Twig recursion
3. Check logs
4. Check filesystem conflicts
5. Clear cache

---

# Problem: Redirect Loop `/app ↔ /app/`

## Symptoms

- Browser shows error page
- `/app` never loads
- `curl -IL` alternates between URLs

Example:

```
/app  → /app/
/app/ → /app
/app  → /app/
...
```

## Diagnosis

Run:

```
curl -IL https://example.org/app
```

If output alternates between:

```
/app
/app/
```

then:

```
public/app exists
```

## Fix

Delete directory:

```
rm -rf public/app
```

Then:

```
php vendor/bin/contao-console cache:clear
rm -rf var/cache/*
```

---

# Problem: OutOfMemoryError

## Symptoms

- HTTP 503
- Memory exhausted
- Twig-related stack trace

Example log:

```
OutOfMemoryError:
Allowed memory size exhausted
```

## Cause

Twig template recursion.

Usually caused by:

```
{% extends "@CommunityOffers/..." %}
```

instead of:

```
{% extends "@!CommunityOffers/..." %}
```

## Fix

Add `!` to Twig extends.

---

# Problem: Template Not Found

## Symptoms

Log shows:

```
Template "... not defined"
```

## Diagnosis

Run:

```
tail -n 50 var/logs/prod-*.log
```

## Fix

Verify template path:

```
templates/bundles/CommunityOffersBundle/app/index.html.twig
```

---

# Problem: Manifest Not Loaded

## Symptoms

- App installs with wrong icon
- Browser uses default icon

## Diagnosis

Open:

```
https://example.org/files/layout/manifest.webmanifest
```

If 404:

Manifest location is wrong.

## Fix

Ensure file exists:

```
public/files/layout/manifest.webmanifest
```

---

# Problem: App Loads but UI Missing

## Symptoms

- Blank page
- Partial UI
- JS errors

## Diagnosis

Open browser console:

```
F12 → Console
```

Look for:

- JavaScript errors
- Missing files
- CORS issues

---

# Problem: Cache Problems

## Symptoms

- Changes not visible
- Old content shown

## Fix

Clear cache:

```
php vendor/bin/contao-console cache:clear
rm -rf var/cache/*
```

---

# Recommended Debug Commands

Useful commands:

```
curl -IL https://example.org/app
tail -n 50 var/logs/prod-*.log
ls -ld public/app
```

---

# Preventive Measures

Always:

- Use `@!` in Twig overrides
- Keep `/app` as route only
- Store assets under `/files/`
- Avoid creating route directories
- Commit overrides to Git

---

# Real Lessons Learned

These issues have occurred in production:

- Twig recursion caused memory exhaustion
- Directory conflict caused redirect loops
- Manifest path errors caused PWA failure
- Cached templates caused misleading errors

Proper documentation prevents recurrence.

---

# Status

This troubleshooting workflow has been validated
in production environments using:

- Contao 5
- Symfony 7
- Apache
- Twig template overrides
