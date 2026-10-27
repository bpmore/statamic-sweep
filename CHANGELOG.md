# Changelog

What changed in each release, and what you have to do about it.

Versions are `MAJOR.MINOR.PATCH`.

## 1.0.0 - 2026-10-27

The first release.

- **Search and replace across the site**: entries, taxonomy terms, globals,
  navigation and asset fields, by site, collection, entry status and field.
  Exact text, whole words, or regular expressions with capture groups, with
  or without matching case, and an option to keep the case of what is
  replaced.
- **Every change previewed** with the words around it, with any match or item
  left out before applying.
- **Bard, Replicator, Grid and Group stay intact.** Only text changes. A match
  split across formatting is listed for editing by hand.
- **Applied as one batch**, skipping anything edited since the preview and any
  entry with unpublished changes, saved through Statamic with a revision
  where the collection keeps them, and one Git commit with Git automation on.
- **Undo for 30 days**, byte for byte, wherever nobody has edited the text
  since; anything else is listed as a conflict.
- **History** of every sweep, and **presets**: domain change, WordPress
  shortcode cleanup and rebrand built in, and your own, shared as YAML.
- **`sweep:run`** for the command line and CI, and **`sweep:prune`** on the
  daily schedule.
- Four permissions: Use Sweep, Apply sweeps, Undo sweeps, Manage presets.
- Works with flat files and with the Eloquent driver.
