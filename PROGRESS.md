# PROGRESS: Sweep

**Spec:** `docs/SPECS.md` · **Conventions:** `docs/CONVENTIONS.md` · **Package:** `bpmore/statamic-sweep` · **$39**
**Rule:** do the next unfinished task only, then update this file, verify the build, and commit.

## Decisions waiting on Brent

Each one changes a task below. Until it is answered, its task says **(waits on Dn)** and the default is built.

- **D1. Free tier.** Paid only, or free with a 25-item apply limit? Default built: paid only, one edition. The apply limit is one check in one place, so adding it later is small.
- **D2. Redirects.** Offer to create redirects through a detected redirect addon when slugs change, or stay out of URLs? Default built: stay out of URLs. The slug option warns and links to the docs page on redirects.
- **D3. Per-site asset alt text (acceptance 9).** Statamic 6.38 has no per-site asset metadata: `Statamic\Assets\Asset` and `AssetContainer` never mention a site or locale, and an asset's alt text is one value shown on every site. So "replaced per site when scoped to one site" cannot happen with core Statamic. Default built: a sweep scoped to some sites leaves assets out entirely, and the search screen will say why. Other options: (b) support a per-site field naming convention, such as `alt_fr`, mapped in config; (c) include assets in site-scoped sweeps anyway, with a warning that the change shows on every site. Recommended: the default, and rewrite acceptance 9 to "A sweep scoped to one site never changes asset metadata, which every site shares."

## Phase 1: Foundations

- [x] Scaffold: package, service provider, config, Pest on `AddonTestCase`, arch test, CI matrix, Vite build as in Lifecycle, CLAUDE.md
- [x] Permissions (`use sweep`, `apply sweep`, `undo sweep`, `manage sweep presets`) and a CP nav item under Tools, gated by `use sweep`

## Phase 2: The engine (plain PHP in `src/Engine`, no Statamic)

- [x] Matcher: `literal`, `whole word`, `regex` with capture groups (`$1`, `${1}`), case sensitivity. An invalid regex is refused with a message, never at apply time. Acceptance 8.
- [x] Case-preserving replace for the rebrand preset: "Acme" to "Apex", "ACME" to "APEX", "acme" to "apex"
- [x] Plain strings: one change per field, every match listed with its offset and a short context for the diff
- [x] Bard (ProseMirror JSON): replace inside `text` node strings, `link.href` and `image.alt` only; node types, marks and set data untouched. Acceptance 1.
- [x] Split matches: a match spanning two adjacent text nodes is reported, never replaced. Acceptance 2.
- [x] Field walker: a value plus a plain-array field tree, recursing into Replicator, Grid, Group and Bard sets, producing a field path per change (`content.2.attrs.values.rows.0.title`). Sibling fields untouched. Acceptance 3.
- [x] Field type rules: searched (text, textarea, markdown, bard, link, any plain-string type), code off by default, never searched (handles, IDs, slugs unless opted in, dates, relationships)

## Phase 3: Statamic content (in `src/Content`)

- [x] Blueprint to field tree, with imported fieldsets and nested sets resolved
- [x] Entries: sites, collections, status (published, draft, scheduled), field handles
- [x] Taxonomy terms, per site
- [x] Globals, per site
- [x] Navigation: branch titles and URLs, and nav branch fields
- [x] Asset metadata: alt, title, custom fields. Researched: Statamic 6 has no per-site asset metadata. Built site-neutral; acceptance 9 **(waits on D3)**.
- [x] Permission filter: results only include items the user can edit. Acceptance 6.
- [x] Slugs, opt-in, with a warning about URLs and redirects (content side done: entries and terms; the warning itself is in the search screen task)

## Phase 4: Preview

- [x] Sweep store: one YAML file per sweep in `storage/sweep/<id>.yaml` (items, field paths, before, after, selection, counts, status, who, when). Items are in YAML chunks beside it, see Notes.
- [x] Size check: a preview with 50,000 matches stays under 50 MB (33.6 MB in the worst case: every match in its own 10 KB field)
- [x] Search runs as a queued job in chunks, writing results as each chunk finishes, with progress
- [x] Counts: items matched, matches total, split matches, items skipped for permission
- [x] `sweep:run --find= --replace= [--regex] [--scope=collection:blog] [--dry-run]` writes a preview and prints counts

## Phase 5: Apply

- [x] Apply selected changes as a queued batch with progress
- [x] Re-read each item before writing; skip and flag "changed since preview" when the before value differs. Acceptance 4.
- [x] Save through Statamic (validation, events, search index); a revision with `Sweep: <find> → <replace>` where revisions are on
- [x] Git automation: one commit for the batch. Approach found in Statamic 6.38: `Statamic\Git\Subscriber::withoutListeners()` around the saves, then one `Git::as($user)->dispatchCommit($message)`. Every other listener still runs. Acceptance 7.
- [x] Static cache: the changed items' URLs are invalidated
- [x] `sweep:run --apply`

## Phase 6: Undo and history

- [x] Undo: restore before values only where the current value still equals the after value; list the rest as conflicts. Byte-identical restore. Acceptance 5.
- [x] Retention: undo available for 30 days (config), pruned on a schedule
- [x] Status per sweep: previewed, applied, undone, partially undone

## Phase 7: Control panel (WCAG 2.2 AA is a release blocker)

- [x] Search screen: find, replace, mode, case, scope filters, a live status message
- [x] Results: grouped by item, inline diff per match, deselect a match or an item, paginated, split matches with a link to the entry
- [x] Apply and undo with a progress bar and an announced result
- [x] History list: who, when, find, replace, scope, counts, status
- [x] Presets: stored as YAML, the three built-ins (domain change, WordPress shortcodes, rebrand), create, edit, share as YAML, gated by `manage sweep presets`

## Phase 8: Ship

- [ ] Fixtures: multisite, Eloquent driver, Bard with nested sets, Replicator inside Grid inside Bard
- [ ] Performance: 10,000 entries searched in under 60 seconds
- [ ] axe scan and a keyboard-only walk of every CP screen on `statamic-dev`
- [ ] README, editor docs in `docs/`, CHANGELOG, `docs/marketplace.md`, the em dash grep
- [ ] Install on `greenhouse` with the other bpmore addons: no shared route names, permission handles, nav sections or JS globals

## Notes
<!-- Record surprises, decisions and blockers here. If a task is wrong or blocked, write why and stop. -->

### 2026-10-26: Scaffold
- Layout follows `bpmore/statamic-lifecycle`: Pest on `AddonTestCase` with `PreventsSavingStacheItemsToDisk`, Pint (laravel preset), Vite via `@statamic/cms/vite-plugin`, built bundle committed under `resources/dist/build`.
- Namespace is `Bpmore\Sweep\`, as `docs/CONVENTIONS.md` says and as Copydesk and Multisite Drift do. Lifecycle's `Bpmore\StatamicLifecycle\` is the odd one out.
- Resolved `statamic/cms` 6.38.0 and Laravel 13 on PHP 8.4. The conventions say Laravel 12; Statamic 6 allows both, and PHP 8.2 resolves Laravel 12. CI runs both through the PHP matrix and a lowest-dependencies job.
- Sweeps are YAML on disk, as the spec says, so Sweep needs no database at all.
- Static cache: Statamic's own `Statamic\StaticCaching\Invalidate` listener is queued and runs on `EntrySaved`, honouring the site's invalidation rules. Keeping it running (rather than suppressing it and invalidating a URL list ourselves) is likely the safer route. Decide in the static cache task.

### 2026-10-26: Permissions and nav
- `apply sweep`, `undo sweep` and `manage sweep presets` are children of `use sweep` in the roles screen. `Auth\Permissions` still checks `use sweep` alongside each, since a role file edited by hand can hold a child alone.
- UI strings live in `lang/en/messages.php` as `sweep::messages.<key>`. Statamic's translator sends namespaced addon strings to the CP, so Vue uses the same keys. The test reading the permission group label as "Sweep" proves the file is loaded.
- `Nav::build()` is empty under Testbench outside a real CP request; the nav test runs the extension against a fresh Nav, as Lifecycle's does.

### 2026-10-26: Matcher and case-preserving replace
- Every mode compiles to one PCRE pattern with `u`, so offsets are UTF-8 bytes and case folding works past ASCII.
- Whole word guards only an end that is itself a word character, so `[caption` as a whole word still matches after a letter.
- Regex replacements follow preg_replace exactly, checked against it in a test: `$10` is group 10, not group 1 then 0 (an early guess was wrong). `${1}0` is the way to write group 1 then a 0.
- Literal and whole-word replacements never expand `$1`.
- Empty matches are dropped (`x*`, `^`), so a pattern cannot insert text at every position. To be stated in the editor docs.
- An invalid pattern is refused when the `Query` is built, with PCRE's own reason and never Sweep's `/` delimiter.
- Case preserving knows three shapes (CAPS, lower, Title); anything else gets the replacement as typed.

### 2026-10-26: The walker (plain strings, Bard, split matches, nesting, type rules)
- One `Engine\Walker` does all five tasks: they share one recursion, and splitting them would mean three copies of it. Mutation-checked: removing split detection, link hrefs, Grid support, per-node offsets or run joining each fails tests.
- Every change is one string at one exact path (`content.0.content.1.text`). Apply and undo only ever set that string, which is how marks, set data and siblings stay untouched.
- A change keeps `before` and its hits; `after` is worked out from them. That keeps the preview file smaller and makes the stored after value impossible to get out of step.
- Bard text nodes side by side are searched as one string, so "Acme" bold followed by plain "s" is "Acmes" (not a whole word) and a phrase across a mark boundary is a split match. A hard break, image or any other inline node ends a run.
- Labels are structured parts (`field`, `row`, `set`, `block`, `link`, `alt`), not English, so the engine stays free of `__()` and the CP words them.
- Searched types: text, textarea, markdown, video, link (URLs only, not `entry::`, `asset::`, `@child`), list, table cells, array values, bard, plus config-named extras. Slug and code only when opted in. Everything else, including select, radio and checkboxes (their values are option keys), is never searched.
- A Bard field saved as an HTML string is skipped: replacing inside raw HTML can break tags. Rare; to be listed in the docs.
- For acceptance 4, the plan is an item-level fingerprint at preview time: any edit to the item between preview and apply skips it. Stricter than per-field, and matches the criterion's wording ("changes an entry"). Decide for good in that task.

### 2026-10-26: Content (terms, globals, nav, assets, permissions)
- Every kind is an `Item` read and written through Statamic's facades, found again by a reference string (`entry::<id>`, `term::<taxonomy>::<slug>::<site>`, `global::<handle>::<site>`, `nav::<handle>::<site>`, `asset::<container>::<path>`).
- A term's id is its slug in the taxonomy's first site, so a sweep that changes that slug changes the reference. The apply task must record the reference after writing, for undo.
- Navigation trees are offered to the engine as a Grid called `tree` (title, url, blueprint fields under `data`, `children` as another Grid down to the nav's max depth, default 10). No engine change was needed.
- Asset metadata is shared by every site in Statamic 6 (D3). A site-scoped sweep leaves assets out.
- `Content\Search` checks `mayEdit` before reading an item; items a person cannot edit are counted, never walked (acceptance 6). With no person (Artisan) everything in scope is searched.
- Found and fixed: `FieldTree` loaded every field's fieldtype, so one blueprint field from an uninstalled addon threw and stopped the whole search. It now loads a fieldtype only for Grid, Group, Replicator and Bard.
- Third-party plain-text fieldtypes are opt-in through `sweep.fieldtypes`.

### 2026-10-26: Sweep store and size
- **Differs from the spec's letter, on purpose.** The spec says one YAML file, `storage/sweep/<id>.yaml`. That file exists and holds the sweep (query, scope, counts, status, who, when), but the items go in `storage/sweep/<id>/items-0001.yaml`, 100 to a file. Measured: 50,000 matches is about 22 MB of YAML, and Symfony's parser takes 5.1 s and 415 MB of memory to read it. A site on PHP's usual 128 MB limit could not open its own preview, and every page of a paginated preview would wait 5 s. Chunks make a page one small file. Still YAML, still under `storage/sweep`.
- **Before values are hashed, not always stored.** Keeping every before string whole makes size grow with field length: 50,000 matches in 10 KB markdown fields would be about 500 MB. A change keeps a SHA-256 of its before string, 40 bytes of context each side of each hit, and the whole string only when it is under 4 KB. Apply works on the current value once its hash matches; undo rebuilds the before value from the current one with `Matcher::revert` and checks it against the hash. Byte-identical by construction and by check. Worst case measured at 33.6 MB.
- YAML strings round-trip byte for byte, including CRLF, trailing newlines, NUL, numeric-looking strings and `null`/`~`/`yes` (test). Literal blocks also round-tripped in Symfony 7; quoted forms are kept anyway.
- Ids are lowercase ULIDs, and the store refuses anything else, so an id from a URL can never reach another path.

### 2026-10-26: Search job, counts, sweep:run
- `Sweeps\Sweeps` is the one way in for the CP and Artisan. `start()` records the sweep and queues `Jobs\SearchSweep`; `now: true` runs it inline for Artisan.
- A queued search runs as the person who started it (permission filter). If that account is deleted before the job runs, the sweep fails instead of searching as nobody, which would show them everything.
- Progress is written every 50 items and each chunk of 100 found items as it fills, so the CP can show early results. No total is known up front: counting first would read the site twice.
- `sweep:run` without `--apply` is a dry run, whether or not `--dry-run` is passed. `--word` was added beside `--regex`, plus `--ignore-case`, `--preserve-case`, `--slugs`, `--code`; `--scope` repeats. `--apply` arrives with Phase 5.

### 2026-10-26: Apply
- `Sweeps\Applier` re-reads each item, compares its whole-data fingerprint with the preview's (acceptance 4: an edit to any field skips the item), then checks each string's hash before replacing only the selected hits.
- Git (acceptance 7): `Statamic\Git\Subscriber::disable()` / `enable()` in a `try`/`finally` around the batch, then one `Git::as($user)->dispatchCommit('Sweep: <find> → <replace>')`. Not `withoutListeners()`: it has no `finally`, so one failed save would leave a queue worker never committing again. Mutation-checked both halves. Every other listener still runs.
- Static cache: Statamic's own queued `Invalidate` listener is left running, so each changed item is refreshed by the site's own invalidation rules. Tested: exactly the changed entries are refreshed. This is one invalidation per changed item, not one for the batch as the spec's Git paragraph suggests; collecting URLs ourselves would lose the site's rules. Raise with Brent only if a site reports cache churn.
- Found: an entry with a working copy (unpublished changes, revisions on) would have the sweep wiped when those changes are published. Such entries are skipped as `working_copy` and listed for a person.
- Saves go through `updateLastModified($user)`, so `updated_by` names the person who applied the sweep, and a revision with the sweep's message is made where the collection keeps revisions.
- Apply is guarded by a cache lock so two people pressing Apply at once apply it once. A second apply of the same sweep is refused.
- `sweep:run --apply` searches and applies in one run; `sweep:run --sweep=<id> --apply` applies an earlier preview.
- Testing note: `vendor/bin/pest | grep` hid a fatal error once (two test files declared the same helper). Check the exit code, not just the summary line.

### 2026-10-26: Undo and history
- Undo works string by string: a string is put back only if its hash still equals the after hash; the before value is rebuilt with `Matcher::revert` and must hash to the preview's before hash. Anything else is a conflict and is left alone. An item with some strings restored and some not is `partial`, and the sweep is then `partially_undone`.
- Acceptance 5 tested with CRLF, trailing whitespace, tabs, numeric-looking text, `null`/`~`/`yes`, emoji, Bard marks and link hrefs: every untouched item's field values come back identical.
- Undo finds an item by `reference_after`, so a term whose first-site slug the sweep changed is found under its new slug.
- One Git commit for an undo too, with `Undo sweep: <find> → <replace>`.
- `sweep:prune` runs daily: past `undo_days` (from apply, or from creation for a preview never applied) it deletes a sweep's items and keeps the sweep record, so the history still shows who, when, what and counts. Undo is then unavailable. This differs from the earlier config comment, which said the history would forget it.
- Not tested here, noted for the CP: a renamed term's old id lingered in the test Stache after `slug()` + `save()`; the CP renames terms the same way, so this is Statamic's behaviour under `PreventsSavingStacheItemsToDisk`, not Sweep's. Worth a look on `statamic-dev` with real files.

### 2026-10-26: Control panel (search, results, apply, undo, history)
- Checked in a real browser on a throwaway Statamic 6.38 site (not `statamic-dev`: its lock file is on 6.31 while Lifecycle needs 6.34, so adding Sweep there needs `composer update -W` on the whole dev site, which is Brent's call). Search, preview with inline diffs, apply (61 entries, bold marks kept on disk), undo (61 restored, file identical), history: all worked. No console errors.
- Statamic's CP stylesheet only holds the utility classes core uses. Five of Sweep's were missing (the summary grid, the option columns, `break-words`); they are now in `resources/css/cp.css` under a `sweep-` prefix.
- A sweep's items (and apply, undo) open only for its starter and super admins (`Permissions::mayOpen`), because the results show content its starter may edit. Everyone with `use sweep` sees the history and counts.
- Found in the browser: a bold word in Bard showed with no words around it, since context came from the one text node. Context now comes from the whole run of text, offset to the node.
- After changing the built assets, a site needs `php artisan vendor:publish --tag=sweep --force`; Statamic does this on `composer update` of the addon.
- **Not done yet:** the axe scan. Loading axe-core from a CDN into the page was refused by this session's permissions. Phase 8 task; run it with `@axe-core/playwright` installed locally, or Brent's usual tool.

### 2026-10-26: Presets
- Built-ins ship in `resources/presets/*.yaml` and cannot be edited or deleted; a site's own are YAML in `resources/sweep/presets` (config `sweep.presets_path`), so they go into the site's Git. A site preset with a built-in's handle takes its place.
- A preset is a search only (find, replace, mode, case options, title, description). Where to look is chosen each time.
- Every built-in is tested on sample text. The domain preset ends in `\b`, so `old.example.community` is not touched; the shortcode preset removes `[caption ...]`, `[/caption]`, `[embed]`, `[gallery ...]` and keeps what is between.
- Share: download as YAML from the Presets screen; import by pasting YAML. Saving, importing and deleting need `manage sweep presets`; viewing and downloading need `use sweep`.
- Checked in the browser: choosing a preset fills in find, replace and mode.
