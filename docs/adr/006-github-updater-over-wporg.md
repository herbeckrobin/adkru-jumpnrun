# ADR-006: GitHub-Releases statt WordPress.org als Update-Kanal

## Status

Akzeptiert — 2026-04-16

## Kontext

Das Plugin ist ein White-Label-Showcase fuer ADKRU — es wird nicht im offiziellen WordPress.org Plugin-Verzeichnis veroeffentlicht (keine Notwendigkeit, kein Reviewer-Prozess gewollt). Trotzdem soll Tomy (ADKRU) einfache 1-Klick-Updates bekommen wenn Robin eine neue Version pusht.

Die Alternativen:

- **Manuelles ZIP-Hochladen**: Jeder Update bedeutet ZIP-Download, WP-Admin → Plugin-Upload. Fehleranfaellig und aergerlich bei haeufigen Updates.
- **WP.org-Submission**: Mehrtaegiger Review, keine private Repos moeglich, kein Showcase-Wert.
- **Eigener Update-Server**: Infrastruktur, Wartung, noch eine Subdomain.

## Entscheidung

[YahnisElsts/plugin-update-checker (PUC)](https://github.com/YahnisElsts/plugin-update-checker) als Composer-Dependency + GitHub-Releases als Distribution.

- Plugin-Header enthaelt `Update URI: https://github.com/herbeckrobin/adkru-jumpnrun`
- PUC bei jedem Admin-Request checkt die GitHub-API auf neue Releases
- Ein Release = ein Tag (`vX.Y.Z`) + ein angehaengtes ZIP-Asset
- **GitHub Action** baut bei Tag-Push das ZIP und haengt es automatisch ans Release

Tomys Workflow:

1. **Einmalig**: ZIP von GitHub-Release herunterladen → WP-Admin → Plugin-Upload → Aktivieren
2. **Danach**: Robin pushed `git tag v0.3.0 && git push --tags` → Action baut ZIP → WP-Admin zeigt Update → 1-Klick

## Konsequenzen

**Positiv:**

- Showcase-Wert: Das Release-Workflow ist fuer Tomy sichtbar und nachvollziehbar (Commits, Tags, Action-Runs, Release-Notes)
- Keine Infrastruktur-Kosten, keine Subdomain
- PUC unterstuetzt sowohl Public als auch Private GitHub Repos (mit Access-Token)
- Release-Notes werden automatisch aus Commit-Messages generiert

**Negativ:**

- PUC als zusaetzliche Dependency (~400 KB Composer-Package mit Vendored-Code)
- Die PUC-Checks gehen an `api.github.com` — Tomys WP muss Outbound-HTTPS dorthin duerfen (bei 99% der Hoster kein Problem)
- Bei einer irgendwann doch erwuenschten WP.org-Einreichung muesste PUC rausfliegen

## Verification

Bestehende Build-Action unter [`.github/workflows/release.yml`](../../.github/workflows/release.yml). Manueller Test: Tag pushen → Action-Run pruefen → ZIP im Release pruefen → im WP-Admin "Updates" checken.
