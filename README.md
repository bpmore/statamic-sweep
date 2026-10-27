# Sweep

**Search and replace across a whole Statamic site, without breaking anything.**

Sweep finds text in entries, terms, globals, navigation and asset fields,
shows every change as a diff before anything is written, applies the ones you
keep as one batch, and puts them back if you change your mind. It works inside
Bard, Replicator, Grid and Group fields without touching their structure: a
bold word stays bold, a set stays a set, and a field beside the one you
changed stays exactly as it was.

Statamic 6 · PHP 8.2+ · no database required · flat files or the Eloquent driver

```
php please sweep:run --find="staging.example.com" --replace="www.example.com"
```

## What it does

- **Every change is previewed first.** A search makes a preview: each item,
  each field, each match with the words around it, crossed out and replaced.
  Leave out any match or any item before applying.
- **Structure stays intact.** In Bard, Sweep changes the text inside text
  nodes, link addresses and image alt text, and nothing else. Marks, node
  types and set data are never touched.
- **A phrase split by formatting is listed, not guessed at.** "Acme
  Corporation" with "Acme" inside a link is reported as a split match with a
  link to the entry, because replacing it would mean choosing whose
  formatting the new words get.
- **Nothing edited since the preview is overwritten.** Each item is read
  again just before it is written. If anyone has changed it, it is skipped
  and flagged.
- **One undo for the whole batch.** Undo puts back exactly the text that was
  there, byte for byte, wherever nobody has edited it since, and lists the
  rest as conflicts. Available for 30 days.
- **One Git commit, not 200.** With Git automation on, a sweep is one commit
  with the message `Sweep: <find> → <replace>`.
- **Saved through Statamic.** Events, search indexing and static cache
  invalidation run as they would for an editor's save, and collections with
  revisions get a revision with the sweep's message.
- **Presets.** Domain change, WordPress shortcode cleanup and a
  case-preserving rebrand come built in; save your own and share them as
  YAML.
- **People only see what they can edit.** Results never include an item the
  person running the search may not edit.

## Documentation

For the people who run sweeps: [Using Sweep](docs/using-sweep.md). Searching,
reading the preview, applying, undoing, presets and regular expressions.

## Installing

```
composer require bpmore/statamic-sweep
```

Sweep appears under **Tools → Sweep** for super admins straight away. Give
other people the permissions below.

### Run a queue worker on large sites

Searching, applying and undoing are queued jobs. On a site whose queue is
`sync` (Laravel's default) they run inside the web request, which is fine for
a few thousand items and can time out on more. For a large site, use a real
queue connection and run a worker:

```
php artisan queue:work
```

The control panel shows each job's progress while it runs.

### Run the scheduler

`sweep:prune` runs daily to remove undo data older than the undo period. If
the site does not already run Laravel's scheduler, add it:

```
* * * * * cd /path-to-your-site && php artisan schedule:run >> /dev/null 2>&1
```

## Permissions

Nobody but super admins has any of these until a role is given them.

| Permission | What it allows |
|---|---|
| **Use Sweep** (`use sweep`) | Search and preview. Results only list items this person may edit. |
| **Apply sweeps** (`apply sweep`) | Write a previewed sweep to the site. |
| **Undo sweeps** (`undo sweep`) | Put an applied sweep back. |
| **Manage presets** (`manage sweep presets`) | Save, import and delete presets. |

The last three sit under **Use Sweep** in the roles screen. A sweep's results,
and applying or undoing it, are open to the person who started it and to super
admins; everyone with **Use Sweep** sees the history.

## Configuration

Publish the config file to change it:

```
php artisan vendor:publish --tag=sweep-config
```

| Key | Default | What it does |
|---|---|---|
| `path` | `storage/sweep` | Where sweeps are kept: one YAML file per sweep, its items in YAML files beside it. Machine data, not for Git. |
| `presets_path` | `resources/sweep/presets` | Where presets people save are kept, one YAML file each. Commit these. |
| `undo_days` | `30` | How long an applied sweep can be undone. After that `sweep:prune` removes its undo data and keeps it in the history. |
| `fieldtypes` | `[]` | Handles of addon fieldtypes that store plain text, to search like a text field. Sweep cannot tell text from a key or an id, so only list fieldtypes whose saved value is something a person wrote. |

## What is searched

Text, Textarea, Markdown, Bard, Link (when it holds a URL, not a link to an
entry or asset), Video, List, Table and Array values, inside Replicator, Grid,
Group and Bard sets at any depth, in:

- entries, per site, by collection and status (published, draft, scheduled,
  expired)
- taxonomy terms, per site
- globals, per site
- navigation: branch titles, URLs and the nav blueprint's fields
- asset metadata: alt text, title and the container blueprint's fields

Code fields and slugs are searched only when asked. Changing a slug changes a
URL; Sweep does not create redirects.

Never searched: handles, ids, dates, select and checkbox values (those are
keys), and relationship fields.

**Asset fields are shared by every site** in Statamic 6, so a sweep limited to
some sites leaves assets out.

**An entry with unpublished changes** (revisions on, with a working copy) is
skipped when applying, because publishing those changes later would undo the
sweep. It is listed so someone can edit it by hand.

## Commands

```
php please sweep:run --find=TEXT [--replace=TEXT] [--regex | --word]
                     [--ignore-case] [--preserve-case]
                     [--scope=collection:blog --scope=site:en ...]
                     [--slugs] [--code] [--dry-run | --apply]
php please sweep:run --sweep=ID --apply
php please sweep:prune
```

`sweep:run` writes a preview and prints its counts. It changes nothing unless
`--apply` is given: with `--find`, it searches and applies in one run; with
`--sweep=ID`, it applies an earlier preview. It searches everything in scope,
since there is no signed-in person to limit it.

`--scope` takes `collection:<handle>`, `taxonomy:<handle>`, `global:<handle>`,
`nav:<handle>`, `assets:<container>`, each with `*` for all of that kind, plus
`site:<handle>`, `status:<status>` and `field:<handle>`. Naming any kind limits
the sweep to the kinds named; naming none searches every kind.

## Git automation and static caching

With Statamic's Git automation on, Sweep switches Statamic's Git listener off
while it writes a batch and dispatches one commit at the end, with the sweep's
message. Every other listener still runs on every save, including static cache
invalidation, so each changed item is invalidated by the site's own rules.

## Screenshots

1. The search screen, with a preset chosen.
2. A preview: matches with the words around them, one left out.
3. Split matches, listed for editing by hand.
4. An applied sweep with its outcomes, and the Undo button.
5. The history.
6. Presets, with one downloaded as YAML.

## Requirements

PHP 8.2+ and Statamic 6.31 or newer. No database is needed. Content in a
database through `statamic/eloquent-driver` works too.

## Support

Through [GitHub issues](https://github.com/bpmore/statamic-sweep/issues). Say
which Statamic and Sweep versions you are on, and for a bug, the search that
caused it.

## Development

```
composer install
vendor/bin/pest                          # tests
vendor/bin/pest --group=performance      # 10,000 entries; takes minutes
vendor/bin/pint                          # formatting
npm install && npm run build             # control panel assets (the build is committed)
```

## Licence

Proprietary. One licence per production site; local development and CI are
free. See [LICENSE.md](LICENSE.md), in particular what it says about reading
the preview before applying it.
