# Release-Workflow — Ein Tag = Ein Update

## Wie es funktioniert

1. Robin pusht einen Tag der mit `v` beginnt, z.B. `v1.0.1`
2. GitHub Action `.github/workflows/release.yml` wird getriggert
3. Action baut Engine + Renderer + Plugin-Bundle, installiert PHP-Runtime-Deps, erstellt ZIP
4. ZIP wird als Asset an ein neues GitHub Release gehaengt
5. Das installierte WP-Plugin pollt via [Plugin-Update-Checker](https://github.com/YahnisElsts/plugin-update-checker) und zeigt das Update im WP-Admin
6. Tomy klickt "Aktualisieren" — Plugin ist auf neuem Stand

## Als Entwickler einen Release machen

```bash
cd Code/adkru-jumpnrun

# Version in 3 Stellen aktualisieren:
# - package.json
# - jumpnrun.php (Version-Header + JUMPNRUN_VERSION)
# - readme.txt (Stable tag + Changelog)

git add -A
git commit -m "release v1.0.1"
git tag v1.0.1
git push origin main
git push origin v1.0.1
```

## Lokal als Tomy testen

```bash
bun run package-plugin
# Erzeugt release/jumpnrun-0.0.1.zip
```

Die ZIP kann in jedes WordPress hochgeladen werden: **Plugins → Hinzufuegen → Plugin hochladen**.

## Troubleshooting

- **Update wird nicht angezeigt:** Nach dem Tag ca. 12 Stunden warten (WP-Update-Check-Intervall), oder im WP-Admin `wp plugin update --all` via WP-CLI
- **Composer-Abhaengigkeit fehlt:** `composer install --no-dev` muss in der Release-Action vor dem Packaging laufen
- **ZIP-Inhalt pruefen:** `unzip -l release/jumpnrun-<version>.zip` — darf nur `jumpnrun.php`, `readme.txt`, `composer.json`, `src-php/`, `assets/`, `views/`, `languages/`, `vendor/` enthalten
