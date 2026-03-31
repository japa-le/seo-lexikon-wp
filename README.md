# Lexikon Manager

WordPress-Plugin für ein zentrales Insolvenz-Lexikon (CPT `lexikon`) mit Gutenberg-Block, Shortcodes, Suche/Tabs und Schema.org-Ausgabe (`DefinedTermSet`).

## Features

- Gutenberg-Block (`lm/lexikon`) mit serverseitigem Rendering (`render_callback` in PHP)
- Lexikon-Ausgabe mit alphabetischer Gruppierung, Tabs und Suchfeld
- AJAX-Suchshortcodes für Verbraucher/Regel/Firmen/Global
- Ressourcen pro Begriff (Blog-Artikel, Video, Download, Prozessdiagramm)
- SEO-Schema via `wp_head` als `DefinedTermSet` + `DefinedTerm`
- Kurzdefinition optional; Fallback auf gekürzten Lexikontext

## Wichtige Meta-Felder

- `_lm_kurzdefinition`
- `_lm_blog_post_id` (kanonischer Key)
- `_lm_schema_blog_url`

Legacy-Fallback für ältere Daten auf `_lexikon_blog_post_id` ist weiterhin aktiv.

## Shortcodes

- `[lexikon_display type="verbraucher|regel|firmen"]`
- `[lexikon_search type="verbraucher|regel|firmen"]`
- `[verbraucher_search]`
- `[regel_search]`
- `[firmen_search]`
- `[lexikon_global_search]`

## Entwicklung

Im Plugin-Verzeichnis:

```bash
npm install
npm run start
```

Production Build:

```bash
npm run build
```

## Lexikon Import/Export (data.txt)

Es gibt jetzt wieder ein Hilfsskript:

- `import-lexikon-data.php`
- `data.txt`

### data.txt Format

- Ein einzelner Buchstabe als Abschnitt (`A`, `B`, `C`, ...)
- Danach Begriff-Zeile: `Titel` oder `Titel [verbraucher,regel,firmen]`
- Danach der Inhaltstext (mehrzeilig möglich)
- Leere Zeilen trennen Begriffe

Beispiel:

```txt
A

Abtretung [verbraucher,regel]
Die Abtretung bezeichnet die Übertragung einer Forderung.
```

### Browser (Admin-only, mit Schutz)

- Dry Run:  
  `/wp-content/plugins/lexikonmanager/import-lexikon-data.php?lm_lexikon_import=1&confirm=1&dry_run=1`
- Import:  
  `/wp-content/plugins/lexikonmanager/import-lexikon-data.php?lm_lexikon_import=1&confirm=1`
- Export vorhandener Begriffe nach `data.txt`:  
  `/wp-content/plugins/lexikonmanager/import-lexikon-data.php?lm_lexikon_export=1&confirm=1`

Voraussetzung Browser-Ausführung: eingeloggter Admin.

### CLI

```bash
php import-lexikon-data.php --import
php import-lexikon-data.php --import --dry-run
php import-lexikon-data.php --export
```

### Verhalten

- Idempotent per Titel: vorhandene Begriffe werden **aktualisiert**, sonst neu angelegt
- `_lexikon_buchstabe` wird gesetzt
- `_lexikon_tabs` wird als String gespeichert (z. B. `verbraucher,regel`)