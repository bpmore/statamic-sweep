# PROGRESS: Sweep

**Spec:** `docs/SPECS.md` · **Conventions:** `docs/CONVENTIONS.md` · **Package:** `bpmore/statamic-sweep` · **$39**
**Rule:** do the next unfinished task only, then update this file, verify the build, and commit.

## Decisions waiting on Brent

Each one changes a task below. Until it is answered, its task says **(waits on Dn)** and the default is built.

- **D1. Free tier.** Paid only, or free with a 25-item apply limit? Default built: paid only, one edition. The apply limit is one check in one place, so adding it later is small.
- **D2. Redirects.** Offer to create redirects through a detected redirect addon when slugs change, or stay out of URLs? Default built: stay out of URLs. The slug option warns and links to the docs page on redirects.

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
- [ ] Entries: sites, collections, status (published, draft, scheduled), field handles
- [ ] Taxonomy terms, per site
- [ ] Globals, per site
- [ ] Navigation: branch titles and URLs, and nav branch fields
- [ ] Asset metadata: alt, title, custom fields. **Research first:** how Statamic 6 stores asset meta per site, for acceptance 9. Write the finding here before building.
- [ ] Permission filter: results only include items the user can edit. Acceptance 6.
- [ ] Slugs, opt-in, with a warning about URLs and redirects

## Phase 4: Preview

- [ ] Sweep store: one YAML file per sweep in `storage/sweep/<id>.yaml` (items, field paths, before, after, selection, counts, status, who, when)
- [ ] Size check: a preview with 50,000 matches stays under 50 MB
- [ ] Search runs as a queued job in chunks, writing results as each chunk finishes, with progress
- [ ] Counts: items matched, matches total, split matches, items skipped for permission
- [ ] `sweep:run --find= --replace= [--regex] [--scope=collection:blog] [--dry-run]` writes a preview and prints counts

## Phase 5: Apply

- [ ] Apply selected changes as a queued batch with progress
- [ ] Re-read each item before writing; skip and flag "changed since preview" when the before value differs. Acceptance 4.
- [ ] Save through Statamic (validation, events, search index); a revision with `Sweep: <find> → <replace>` where revisions are on
- [ ] Git automation: one commit for the batch. Approach found in Statamic 6.38: `Statamic\Git\Subscriber::withoutListeners()` around the saves, then one `Git::as($user)->dispatchCommit($message)`. Every other listener still runs. Acceptance 7.
- [ ] Static cache: the changed items' URLs are invalidated
- [ ] `sweep:run --apply`

## Phase 6: Undo and history

- [ ] Undo: restore before values only where the current value still equals the after value; list the rest as conflicts. Byte-identical restore. Acceptance 5.
- [ ] Retention: undo available for 30 days (config), pruned on a schedule
- [ ] Status per sweep: previewed, applied, undone, partially undone

## Phase 7: Control panel (WCAG 2.2 AA is a release blocker)

- [ ] Search screen: find, replace, mode, case, scope filters, a live status message
- [ ] Results: grouped by item, inline diff per match, deselect a match or an item, paginated, split matches with a link to the entry
- [ ] Apply and undo with a progress bar and an announced result
- [ ] History list: who, when, find, replace, scope, counts, status
- [ ] Presets: stored as YAML, the three built-ins (domain change, WordPress shortcodes, rebrand), create, edit, share as YAML, gated by `manage sweep presets`

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
