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

- [ ] Matcher: `literal`, `whole word`, `regex` with capture groups (`$1`, `${1}`), case sensitivity. An invalid regex is refused with a message, never at apply time. Acceptance 8.
- [ ] Case-preserving replace for the rebrand preset: "Acme" to "Apex", "ACME" to "APEX", "acme" to "apex"
- [ ] Plain strings: one change per field, every match listed with its offset and a short context for the diff
- [ ] Bard (ProseMirror JSON): replace inside `text` node strings, `link.href` and `image.alt` only; node types, marks and set data untouched. Acceptance 1.
- [ ] Split matches: a match spanning two adjacent text nodes is reported, never replaced. Acceptance 2.
- [ ] Field walker: a value plus a plain-array field tree, recursing into Replicator, Grid, Group and Bard sets, producing a field path per change (`content.2.attrs.values.rows.0.title`). Sibling fields untouched. Acceptance 3.
- [ ] Field type rules: searched (text, textarea, markdown, bard, link, any plain-string type), code off by default, never searched (handles, IDs, slugs unless opted in, dates, relationships)

## Phase 3: Statamic content (in `src/Content`)

- [ ] Blueprint to field tree, with imported fieldsets and nested sets resolved
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
