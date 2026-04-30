# Translations

The plugin's text domain is `tainacan-journal-manager`. Translation files
follow the WordPress convention `tainacan-journal-manager-{locale}.po` /
`.mo`, where `{locale}` is the WordPress locale (e.g. `pt_BR`, `es_ES`,
`en_US`).

## Files in this directory

- `tainacan-journal-manager-pt_BR.po` — full Portuguese (Brazil) translation
- `tainacan-journal-manager-es_ES.po` — initial Spanish (Spain) translation
  (covers the most visible strings; extend as needed)

The compiled `.mo` files are NOT committed — generate them with msgfmt:

```bash
msgfmt languages/tainacan-journal-manager-pt_BR.po -o languages/tainacan-journal-manager-pt_BR.mo
msgfmt languages/tainacan-journal-manager-es_ES.po -o languages/tainacan-journal-manager-es_ES.mo
```

On Windows, msgfmt ships with Poedit (`Tools → Compile to MO`) or with
Git for Windows under `usr/bin/msgfmt.exe`.

WordPress loads the `.mo` matching the site language automatically because
`load_plugin_textdomain()` is called in the bootstrap.

## Adding a new language

1. Copy `tainacan-journal-manager-pt_BR.po` to a new file with the target
   locale, e.g. `tainacan-journal-manager-fr_FR.po`.
2. Translate each `msgstr ""` line.
3. Compile with `msgfmt` as shown above.
4. Set the site language in **Settings → General** to activate.

## Updating after code changes

When new translatable strings are added to the codebase, regenerate the
POT file (or update the existing PO files). The plugin does not yet
include a .pot template — generate one on demand with WP-CLI:

```bash
wp i18n make-pot . languages/tainacan-journal-manager.pot
```
