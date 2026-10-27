# Shared Conventions for bpmore Statamic Addons

Every spec in this folder assumes these conventions. Read this file first, then the addon spec.

## Platform

- Statamic 6 (`statamic/cms: ^6.27`), PHP `^8.2`, Laravel 12 as shipped with Statamic 6.
- Control Panel UI in Vue 3 using Statamic 6 UI components. Match the frontend setup (Vite config, build output, `$vite` in the service provider) used in `bpmore/statamic-lifecycle`.
- Flat-file first. Anything editors author (rules, settings, schedules) is YAML on disk so it can live in Git. High-volume machine data (logs, metrics, query logs) goes to a SQLite file under `storage/` by default, with an option to use the app's default database connection.
- Must work with Statamic's Eloquent driver as well as flat files. Never read content files directly; always go through Statamic facades and repositories (`Entry`, `GlobalSet`, `Term`, `Asset`, `Nav`, `Form`).
- Multisite aware from day one: every query, report and setting either takes a site or explicitly covers all sites.
- Static caching safe: anything that must run per request (beacons, logging) uses an action route (`/!/<addon>/...`) excluded from the static cache, and documents what happens under full (file) static caching where PHP never runs.

## Packaging

- Packagist vendor `bpmore`, package `bpmore/statamic-<slug>`, PSR-4 namespace `Bpmore\<StudlyName>\` mapped to `src/`.
- Repo `github.com/bpmore/statamic-<slug>`, private until Marketplace approval, then public.
- `composer.json` `extra.statamic` includes `name`, `slug`, `description`, and `editions` when the addon has free and paid tiers. Gate paid features with `$this->addon->edition()` (or the equivalent Statamic 6 editions API).
- License: `proprietary` for paid addons, `MIT` for free ones, matching existing addons.
- Ship a `README.md` (install, config, permissions, screenshots list), `CHANGELOG.md`, and a `docs/` folder written for editors, not developers.

## Code standards

- Service provider extends `Statamic\Providers\AddonServiceProvider`. Register tags, fieldtypes, widgets, routes, listeners, commands, schedules and permissions there.
- Permissions are explicit and granular (view, manage, approve). Super admins get everything; nobody else gets anything by default.
- Domain logic lives in plain PHP classes with no Statamic dependency where practical, so it can be unit tested and, where noted, shared with WordPress versions.
- Long work (site-wide scans, batch edits, rollups) runs as queued jobs with progress reporting in the CP, and also has an Artisan command for CI and cron.
- No personal data in logs unless the spec says so. No IP addresses stored unless the spec says so. No cookies set on the front end unless the spec says so.
- Config file `config/<slug>.php`, published with `php artisan vendor:publish --tag=<slug>-config`. Every config key documented inline.

## Writing rules for UI text and docs

- No em dashes anywhere: code comments, UI strings, docs, changelog. Use a colon, comma, parentheses, or two sentences.
- Plain, direct sentences. Error messages say what happened and what to do next.
- Localizable: all UI strings through `__()` with an `en` lang file.

## Accessibility of the addon itself

- Every CP screen meets WCAG 2.2 AA: keyboard operable, visible focus, labeled controls, status messages announced (`aria-live`), no information by color alone, charts paired with a data table.
- These addons are sold to accessibility-minded buyers; an inaccessible CP screen is a release blocker.

## Testing

- Pest with `Statamic\Testing\AddonTestCase` (orchestra/testbench).
- Unit tests for every rule, parser and calculation. Feature tests for every CP route, permission check, event listener and Artisan command.
- Fixtures cover: single site, multisite, flat file, Eloquent driver, Bard with nested sets, Replicator inside Grid inside Bard.
- GitHub Actions matrix: PHP 8.2, 8.3, 8.4.
- Before Marketplace submission, install on the `greenhouse` testbed alongside every other bpmore addon and confirm no conflicts (shared route names, permission handles, nav sections, JS globals).

## Cross-addon integration

Integrations are soft: detect the other addon at runtime (`class_exists`, or `Addon::get('bpmore/...')`) and degrade quietly when it is absent. Never add another bpmore addon as a hard Composer dependency.

Known integration points (inspect each repo before relying on its API):

- **Site Weather** (`bpmore/statamic-site-weather`): reads health signals from other addons. Find its provider or signal contract and implement it where a spec says "report to Site Weather."
- **A11y Gate** (`bpmore/statamic-a11y-gate`): blocks publishing on failed checks. Find its extension point for adding checks; if none exists, propose one in that repo rather than duplicating the gate.
- **Lifecycle** (`bpmore/statamic-lifecycle`): owners, review dates and expiry per entry. Read its data through its public API, not its files.

## Definition of done (every addon)

1. All acceptance criteria in the spec pass, with tests.
2. Works on a fresh Statamic 6 install and on `greenhouse`.
3. CP screens pass an axe scan and a keyboard-only walkthrough.
4. README, editor docs and changelog written; no em dashes in code comments, UI strings or docs (a grep for U+2014 finds only rule definitions that detect the character, such as Copydesk's punctuation pack, and these specs).
5. Marketplace listing copy drafted in `docs/marketplace.md`.
