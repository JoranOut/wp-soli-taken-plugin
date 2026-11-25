# wp-soli-taken-plugin

Members-only tasks (taken) post type for Soli sites.

## Purpose

1. **Post type** — registers `soli_taak` (rewrite slug `taken`), a plain
   CPT with title, editor, excerpt, featured image and revisions. No custom tables,
   no options, no migrations.
2. **Members-only visibility** — a logged-out visitor can never see a taak,
   on any surface. Any logged-in user (any role) sees everything published.
3. **Query Loop integration** — `show_in_rest` plus a preconfigured "Taken"
   variation of `core/query`, so editors drop the latest taken on any page.
4. **Taken A–Z block** (`soli-taken/taken-index`) — the members' search page:
   a central search bar over an alphabetical index, paginated by letter.
5. **"Taak" block pattern** (`soli-taken/taak`) — the starting content of a
   new taak: an intro paragraph and a details group with a *when* and a
   *contact* paragraph. No custom fields; the index reads those two lines
   back from the content.

## Architecture

```
wp-soli-taken-plugin.php                 constants, textdomain, GitHub updater
├── includes/
│   ├── class-soli-taken-post-type.php   # CPT + editor script enqueue
│   ├── class-soli-taken-visibility.php  # all logged-out hiding
│   ├── class-soli-taken-pattern.php     # "Taak" pattern + reading its details back
│   └── class-soli-taken-index.php       # A–Z block: query, search, paging, markup
├── assets/css/taak-details.css          # details-group style, loaded with core/group
└── src/
    ├── index.js        → build/index.js        # core/query block variation
    └── taken-index/    → build/taken-index/    # block.json, editor (ServerSideRender), style, render.php
```

The build lists its two entries explicitly in `package.json`; adding a
`block.json` under `src/` would otherwise make wp-scripts drop `src/index.js`.

## Visibility policy (authoritative)

All decisions live in `Visibility` (`includes/class-soli-taken-visibility.php`)
plus one flag in `Post_Type`. Do not re-implement checks elsewhere.

| Surface | Logged out | How |
|---|---|---|
| Query Loop / secondary WP_Query | empty | `pre_get_posts`: `post__in [0]`, or the post type is stripped from mixed-type queries |
| Single page | HTTP 403 | `template_redirect` guard (`wp_die`), event-plugin pattern |
| Archive | disabled | `has_archive => false`; the pretty URL (`/taken/`) 404s, and a `?post_type=soli_taak` query still hits the same 403 guard |
| Site search | hidden | `exclude_from_search => ! is_user_logged_in()` in the CPT registration (per request) |
| REST collection + single | HTTP 403 | `rest_request_before_callbacks` on `/wp/v2/taken*` |
| Taken A–Z block | login prompt | its secondary query is emptied by `pre_get_posts` like any other; `Taken_Index::render()` checks login only to show a prompt instead of an empty index |

The main query for single/archive is deliberately *not* emptied in `pre_get_posts` —
that would turn the 403 into a silent 404.

Note: REST cookie auth without an `X-WP-Nonce` header counts as logged out
(WordPress core behaviour); the block editor always sends the nonce.

## Query Loop variation

`src/index.js` registers `soli-taken/taken-loop` on `core/query`
(post type preset, newest first, post-template with title/date/excerpt,
query-no-results). Built with `wp-scripts build` to `build/index.js`.

**`build/` and the compiled `languages/` files are committed** — so the tree is
installable as-is and the release zip can never miss them. The GitHub updater
installs the zip asset attached to the newest release in the installed version's
channel (see "Versioning & releases"), not a branch archive.
Run `npm run build` before committing JS changes.

## Taken A–Z block

Dynamic block; `render.php` hands off to `Taken_Index::render()`. Logged-out
visitors get a login prompt instead of any taak.

- **Search** is a plain GET form (`?taken_zoek=`), so it works without JS.
  Every word must occur in the title, excerpt or content;
  matching ignores case and accents. Matches are wrapped in `<mark>`.
- **Order** is alphabetical on the accent-free title, so `Één…` files under E.
  Titles not starting with A–Z go under `#`, first.
- **Paging** (`?taken_pagina=`) fills each page with whole letter groups up
  to the block's `perPage` (default 10). A letter is never split; one larger
  than `perPage` gets its own page. The letter bar links to the page that
  holds each letter.
- All taken are loaded once per render and filtered in PHP. Fine for the
  dozens Soli has; revisit if it ever reaches thousands.
- Each entry shows its title, a summary and a When / Contact line.
  `Pattern::details()` finds the two detail paragraphs by class name
  (`soli-taak-details__when`, `__contact`) and drops their bold label, so
  editors may reword the label or move the paragraphs. The summary is the
  hand-written excerpt, or else the first 30 words of every other block.
  A taak written without the pattern simply shows no When / Contact line.

## "Taak" pattern

Registered for `soli_taak` only and set as the post type's `template` (a
`core/pattern` block), so a new taak opens with it filled in. Its texts are
translatable PHP strings; the class names are the contract with the index,
so do not rename them without migrating existing taken.

## Development

```bash
npm install
npm run build          # build src/index.js -> build/
npm run wp-env:start   # localhost:8894 (admin/password), tests on 8895
npm run test:e2e       # Playwright, see /e2e
npm run i18n:build     # pot -> mo -> json (requires running wp-env)
```

E2E fixtures are seeded by `e2e/fixtures/seed.php` via `wp eval-file`
(idempotent), avoiding wp-env argument-quoting issues. The A–Z index tests
add `e2e/fixtures/seed-index.php`: ten taken dated 2020 (so the newest-first
Query Loop tests still see the base fixture) and a page with the block at
four per page. wp-cli runs logged out, so pass `--user=admin` to any
`wp post list` against taken, or the visibility filter hides them all.
`wp-env run` hands `$ids` over as one argument, so delete in bulk inside the
container: `npx @wordpress/env run tests-cli -- sh -c 'wp post delete $(wp post list --user=admin --post_type=soli_taak --post_status=any --format=ids) --force --user=admin'`.

### Coding Standards

- Namespace: `Soli\Taken`
- Function/hook prefix: `soli_taken_`
- Text domain: `soli-taken`
- Constants: `SOLI_TAKEN__*`
- Locales: `nl_NL` and `en_US`

### i18n gotcha

`i18n:make-pot` excludes `src/` on purpose: the JS string references must point
at `build/index.js` so the `make-json` file hashes match what
`wp_set_script_translations` looks up at runtime.

## Versioning & releases

Standard Soli flow (see repo-root CLAUDE.md): version synced in 4 places
(plugin header, `SOLI_TAKEN__PLUGIN_VERSION`, readme.md
`~Current Version:x.x.x~`, package.json), release branches, nightly builds,
GitHub updater channels.
