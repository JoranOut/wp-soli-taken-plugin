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

## Architecture

```
wp-soli-taken-plugin.php                 constants, textdomain, GitHub updater
├── includes/
│   ├── class-soli-taken-post-type.php   # CPT + editor script enqueue
│   └── class-soli-taken-visibility.php  # all logged-out hiding
└── src/index.js  →  build/index.js      # core/query block variation
```

## Visibility policy (authoritative)

All decisions live in `Visibility` (`includes/class-soli-taken-visibility.php`)
plus one flag in `Post_Type`. Do not re-implement checks elsewhere.

| Surface | Logged out | How |
|---|---|---|
| Query Loop / secondary WP_Query | empty | `pre_get_posts`: `post__in [0]`, or the post type is stripped from mixed-type queries |
| Single page | HTTP 403 | `template_redirect` guard (`wp_die`), event-plugin pattern |
| Archive | HTTP 403 | same guard |
| Site search | hidden | `exclude_from_search => ! is_user_logged_in()` in the CPT registration (per request) |
| REST collection + single | HTTP 403 | `rest_request_before_callbacks` on `/wp/v2/taken*` |

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

## Development

```bash
npm install
npm run build          # build src/index.js -> build/
npm run wp-env:start   # localhost:8894 (admin/password), tests on 8895
npm run test:e2e       # Playwright, see /e2e
npm run i18n:build     # pot -> mo -> json (requires running wp-env)
```

E2E fixtures are seeded by `e2e/fixtures/seed.php` via `wp eval-file`
(idempotent), avoiding wp-env argument-quoting issues.

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
