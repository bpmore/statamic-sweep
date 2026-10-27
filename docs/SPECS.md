# Spec: Sweep (structure-aware search and replace)

Package: `bpmore/statamic-sweep`.

## Summary

Sweep finds and replaces text across a whole Statamic site without breaking structured content. It previews every change as a diff, applies changes as one undoable batch, and handles Bard, Replicator, Grid and Markdown safely.

## Users and jobs

- **Agency after a migration:** "Clean up leftover WordPress shortcodes and old URLs on 2,000 entries."
- **Marketing team after a rebrand:** "Change the product name everywhere, but let me see each change first."
- **Developer moving domains:** "Switch staging URLs to production URLs in content and globals."

## Scope: v1

### Search

- Inputs: find text, replace text, mode (`literal`, `whole word`, `regex` with capture groups), case sensitivity.
- Scope filters: sites, collections, taxonomies, globals, navigation, asset metadata (alt, title, custom fields), field handles, entry status (published, draft, scheduled).
- Field types searched: text, textarea, markdown, bard (text nodes and link `href` attributes), code (off by default), link, plus any fieldtype that stores a plain string, recursing into Replicator, Grid, Group and Bard sets by reading the blueprint.
- Never searched: handles, IDs, slugs (unless the user turns on "slugs," with a warning about URLs and redirects), dates, relationship values.

### Bard safety

- Operate on ProseMirror JSON, not rendered HTML. Replace only inside `text` node strings and explicitly allowed attributes (`link.href`, `image.alt`).
- Never change node types, marks or set data structure.
- A match that spans two text nodes (for example, half bold) is not replaced; it is listed as "split match, edit by hand" with a link to the entry.

### Preview (dry run)

- Running a search creates a **sweep**: a stored change set (YAML in `storage/sweep/<id>.yaml`) listing every item, field path, before value and after value.
- CP shows results grouped by item with an inline diff per match. User can deselect individual matches or whole items.
- Counts: items matched, matches total, split matches, items skipped for permission.

### Apply

- Applies selected changes as a queued batch with a progress bar.
- Before writing each item, re-reads it; if the field no longer equals the preview's before value, that item is skipped and marked "changed since preview."
- Saves through Statamic (so validation, events and search indexing run). If Statamic Pro revisions are enabled for the collection, a revision is created with the message `Sweep: <find> → <replace>`.
- Git automation: avoid one commit per entry. Save with events suppressed where safe, then trigger one commit and one cache invalidation for the batch. Verify the Statamic 6 Git and static cache APIs for this and document the approach in code comments; if a single commit is not achievable, document the behavior in the README.
- Static cache: invalidate the URLs of changed items.

### Undo

- Each applied sweep stores before and after values per item and field path.
- Undo restores before values only where the current value still equals the after value; others are listed as conflicts.
- Undo available for 30 days (config).

### Presets

Saved searches with names, shareable as YAML:

- Domain change (old origin to new origin, http to https).
- WordPress shortcode cleanup (`[caption ...]`, `[embed]`, `[gallery]`) with regex shown and editable.
- Rebrand (whole word, case-preserving option: "Acme" to "Apex", "ACME" to "APEX", "acme" to "apex").

### History

CP list of sweeps: who, when, find, replace, scope, counts, status (previewed, applied, undone, partially undone).

### Artisan

`sweep:run --find= --replace= [--regex] [--scope=collection:blog] [--dry-run]` writes a preview file and prints counts; `--apply` applies it. For CI and large sites.

## Permissions

- `use sweep` (search and preview; results only include items the user can edit)
- `apply sweep`
- `undo sweep`
- `manage sweep presets`

## Non-goals

- Editing template files, config or YAML outside content.
- Changing entry slugs or URIs without the explicit slug option.
- Automatic redirects (point to the redirect addon in docs).

## Performance

- Search 10,000 entries in under 60 seconds on the queue, streaming results into the CP as chunks finish.
- Preview file for 50,000 matches stays under 50 MB; paginate the CP view.

## Acceptance criteria

1. Replacing "Acme" with "Apex" inside a Bard paragraph that has a bold "Acme" changes only the text and keeps the bold mark.
2. A phrase split across a link and plain text is reported as a split match and not changed.
3. Replace inside a Text field inside a Replicator set inside a Grid row works and leaves sibling fields untouched.
4. If an editor changes an entry between preview and apply, that entry is skipped and flagged.
5. Undo restores every untouched item exactly (byte-identical field values) and reports conflicts for edited ones.
6. A user without edit permission on a collection never sees its entries in results.
7. On a site with Git automation on, applying a 200-item sweep produces one commit (or the README documents why not).
8. Regex capture groups work: find `(\d{3})-(\d{4})`, replace `$1.$2`.
9. Asset alt text in a multisite container is replaced per site when scoped to one site.

## Editions and price

Single paid edition, $39. (Optionally free with a 25-item apply limit, if Brent wants a funnel.)

## Open questions for Brent

1. Free tier with an item limit, or paid only?
2. Should Sweep offer to create redirects through a detected redirect addon when slugs change, or stay out of URLs entirely?
