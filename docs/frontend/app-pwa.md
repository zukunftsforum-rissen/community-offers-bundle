
# App PWA Setup & Template Override

This document describes how the `/app` frontend integrates a PWA manifest
and how Twig template overrides must be implemented safely.

This section exists because incorrect overrides or routing conflicts
can lead to redirect loops or memory exhaustion.

---

# Overview

The `/app` frontend is implemented as a Symfony route,
not as a physical directory.

Important:

/app   → Symfony route  
NOT    → public/app directory

Static assets (manifest, icons) must be placed under:

public/files/layout/

---

# Template Override

Location in host project:

templates/bundles/CommunityOffersBundle/app/index.html.twig

Minimal example:

{% extends "@!CommunityOffers/app/index.html.twig" %}

{% block community_offers_manifest %}
    <link rel="manifest" href="/files/layout/manifest.webmanifest?v=2">
{% endblock %}

{% block community_offers_icons %}
    <link rel="apple-touch-icon" href="/files/layout/favicon-192.png?v=2">
{% endblock %}

---

# Critical Rule: Always use `@!`

Correct:

{% extends "@!CommunityOffers/app/index.html.twig" %}

Wrong:

{% extends "@CommunityOffers/app/index.html.twig" %}

Without `!`, Twig loads the override recursively,
which results in:

- infinite recursion
- OutOfMemoryError
- HTTP 503 errors

---

# Manifest Location

Correct:

public/files/layout/manifest.webmanifest

Wrong:

public/app/manifest.webmanifest

---

# DO NOT create `public/app`

If a directory exists:

public/app

and a Symfony route exists:

/app

Apache may enforce trailing slashes.

This creates a redirect loop:

/app  → /app/  
/app/ → /app  

Result:

- App unreachable
- Browser shows error page
- Manifest not loaded

---

# Recommended `.gitignore`

/public/app/

---

# Example Manifest

File:

public/files/layout/manifest.webmanifest

Content:

{
  "name": "Community Offers",
  "short_name": "Zugänge",
  "id": "/app",
  "start_url": "/app?v=2",
  "scope": "/app",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#2d6cdf",
  "lang": "de-DE",
  "icons": [
    {
      "src": "/files/layout/favicon-192.png?v=2",
      "sizes": "192x192",
      "type": "image/png"
    },
    {
      "src": "/files/layout/favicon-512.png?v=2",
      "sizes": "512x512",
      "type": "image/png"
    }
  ]
}

---

# Debugging Checklist

If `/app` is not reachable:

## Check for redirect loop

curl -IL https://example.org/app

If output alternates between:

/app  
/app/

then:

public/app exists

Delete it.

---

## Check Twig recursion

Error:

OutOfMemoryError

Most likely cause:

Missing `!` in Twig `extends`.

---

## Check logs

tail -n 50 var/logs/prod-*.log

---

# Lessons Learned

From real production debugging:

- Template overrides must use `@!`
- Symfony routes must not collide with directories
- Manifest must not be placed under `/app`
- Redirect loops are often caused by filesystem paths
- Twig recursion can cause memory exhaustion

---

# Architecture Note

The `/app` endpoint is:

Symfony route  
NOT filesystem directory

Static assets must remain under:

/files/

---

# Status

This setup has been validated in production with:

- Contao 5
- Symfony 7
- Apache
- Twig overrides
- PWA manifest
