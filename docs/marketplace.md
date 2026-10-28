# Marketplace listing copy

Copy for the Statamic Marketplace listing.

---

## Name

**Sweep**

> **Sweep: Search and Replace for Statamic, with Preview and Undo**

"Search and replace" is what people type when they need this. "Preview" and
"undo" are the two words that answer their fear of running it.

## Price

$29 per site. One edition.

## One-liner

Search and replace across a whole Statamic site, without breaking anything.

## Summary (marketplace card)

Find and replace text across the whole site. Preview every change, apply it as one batch, undo it if you need to.

113 characters.

## Long description

Every site eventually needs the same change made in a hundred places: a
domain after a launch, a product name after a rebrand, leftover WordPress
shortcodes after a migration. Doing it by hand takes days. Doing it in the
files is fast and dangerous: Bard content is structured data, and a
careless replace can break formatting, sets and links on hundreds of pages
at once.

Sweep does it safely.

**See every change before it happens.** A search makes a preview, not an
edit. Every match is shown with the words around it, the old text struck
through and the new text underlined. Leave out any match or any page you do
not want changed.

**Structure stays intact.** Sweep changes the text inside Bard, Replicator,
Grid and Group fields and nothing else. A bold word stays bold. A link stays a
link. A set stays a set. A field next to the one you changed is left exactly
as it was.

**It will not guess.** When a phrase runs across two kinds of formatting, half
of it bold or half inside a link, Sweep lists it for you to edit by hand
instead of deciding whose formatting the new words get.

**It will not overwrite anyone's work.** Each page is read again just before
it is written. If someone edited it after your preview, or it has a draft
waiting to be published, it is skipped and listed.

**One undo for the whole batch.** For 30 days, one button puts back exactly
the text that was there, wherever nobody has edited it since.

**It behaves like an editor's save.** Search indexes and cached pages update.
Collections with revisions get a revision with the sweep's message. With Git
automation on, the whole sweep is one commit, not one per page.

**Everywhere text lives.** Entries, taxonomy terms, globals, navigation and
asset alt text, by site, collection, status and field. Exact text, whole
words, or regular expressions with capture groups. An option to keep the
case of what is replaced: Acme to Apex, ACME to APEX, acme to apex.

**Presets for the jobs everyone has.** Domain change, WordPress shortcode
cleanup and rebrand come built in. Save your own and share them with other
sites as YAML.

**People only see what they may edit.** Results never include a page the
person searching cannot edit, and applying and undoing are separate
permissions.

**For large sites and CI.** Searches run on the queue and show results as they
come in; 10,000 entries take seconds. `sweep:run` does the same from the
command line.

Every screen works by keyboard and passes an axe accessibility scan in light
and dark mode.

## Features (short list)

- Preview every change as an inline diff; leave out any match or item
- Bard, Replicator, Grid and Group stay intact; split matches listed, never guessed
- Skips anything edited since the preview
- One-batch apply, one Git commit, one undo
- Entries, terms, globals, navigation and asset fields; multisite aware
- Exact text, whole words, regex with capture groups, case-preserving replace
- Built-in presets for domain changes, WordPress shortcodes and rebrands
- Permission-aware results; separate apply and undo permissions
- History of every sweep; `sweep:run` for the command line
- Flat files or the Eloquent driver

## Requirements

Statamic 6.31+, PHP 8.2+. No database required.

## Screenshots

1. The search screen, with the Rebrand preset chosen.
2. A preview: matches in context, one left out.
3. Split matches, listed for editing by hand.
4. An applied sweep with its outcomes and the Undo button.
5. The history.
6. Presets.

## Not in this release

- Creating redirects when slugs change. Sweep warns before changing a slug and
  leaves redirects to a redirect addon.
- Per-site asset alt text. Statamic 6 keeps one set of asset fields for every
  site, so a sweep limited to some sites leaves assets out.
