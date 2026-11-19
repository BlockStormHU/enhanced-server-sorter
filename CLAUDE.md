# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A plugin for the [Pelican Panel](https://pelican.dev) (Laravel + Filament). It is not a standalone app: there is no
`composer.json`, no test suite, no linter, and no local build. Code runs only inside a Pelican install, where classes
such as `App\Models\Server`, `App\Filament\App\Resources\Servers\Pages\ListServers`, `App\Enums\CustomizationKey`,
and helpers like `user()` and `plugin_path()` come from the host panel. To try a change, copy or symlink the repo
into a Pelican install's `plugins/enhanced-server-sorter` directory, or zip it and import it from the admin Plugins page.

`plugin.json` is the plugin manifest (namespace `Olivier\EnhancedServerSorter`, entry class `EnhancedServerSorterPlugin`,
panels `admin`/`app`, `api_version` 1). Leave `panel_version` null: Pelican compares it with
`version_compare`, so `^1.0.0` would reject 1.0.0-rc.1. Target platform: Pelican 1.0 (Filament 5, Laravel 13, PHP 8.4).

To run the plugin for real, use a Pelican checkout with `composer install` and a SQLite, MySQL, MariaDB or PostgreSQL
database, then `php artisan p:plugin:install enhanced-server-sorter`. Drive pages with `Livewire::actingAs($user)->test(ListServers::class)`
from a script that bootstraps the app. Pelican's own Pest suite skips plugins (`runningUnitTests()`). Test query
changes on PostgreSQL and MySQL as well as SQLite.

To check a change against the host APIs, get the Pelican source (`git clone --depth 1 --branch <tag>
https://github.com/pelican-dev/panel`) and the Filament version from its `composer.lock`.

## Release / packaging

`.github/workflows/package.yml` (Actions → Release → Run workflow on `main`, with a version such as `v1.4.0`):
1. Refuses a malformed version, a non-`main` run and a version that already exists.
2. Builds `enhanced-server-sorter.zip` from the current commit, with `plugin.json` `.version` (version without a
   leading `v`) and `.update_url` set, then publishes it as an immutable release (draft, attach, publish).
3. Commits the new version and an `update.json` containing the zip's `sha256` to `main`. Pelican checks that
   checksum during in-panel updates.

`.github/workflows/ci.yml` runs on pull requests into `main` (not on docs-only changes) and by hand. The `.githooks/pre-push` hook runs the same checks before every push; enable it once per clone with `git config core.hooksPath .githooks`. It runs `php -l` with PHP 8.4, checks the `plugin.json`
fields, checks that `plugin.json` and `update.json` have the same version, and checks that every
`lang/*/messages.php` has the same keys as `lang/en`. Run `actionlint` after editing either workflow.

So don't bump `plugin.json`/`update.json` by hand for a release; the Release workflow does it. Never publish a release by hand on GitHub. `dist/` and `*.zip`
are gitignored local build output.

The CI lint step can be run locally before pushing:

```sh
find src database lang -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Architecture

Three pieces must agree on one ordering: **folder `sort`, then the server's `position` in that folder, with unassigned
servers last**.

- **`src/Services/FolderService.php`**: all folder logic and DB access. It is bound as a `scoped` service, so its
  memoized values (`isReady()`, `defaultFolders()`) last for one request. Get it with `app(FolderService::class)`.
  - `saveUserFolders()` replaces a user's folders in one transaction: `sort = index * 10`, `position` = index in the
    select, server IDs limited to the user's accessible servers. Folders left out of the submission are deleted,
    except locked ones. The folder order is then normalized (see below). The modal refuses duplicate folder names
    (case-insensitive, `duplicateFolderNames()`) and duplicate servers, and halts so the user keeps their edits; the
    admin settings form refuses duplicate default folder names.
  - `syncDefaultFolders(?userId)` copies the admin's default folders into user folders, in batches of 200 users.
    Default and user folders are matched **by exact name** in PHP (not in SQL, so MySQL's case-insensitive collation
    can't match a different folder). If a user has two folders with a default's name, the first one by sort is the
    default. An unlocked default folder is filled only when it is first created for a user. A locked one is reset to
    the admin's list on every sync, and its servers are taken out of the user's other folders. It runs when settings
    are saved (all users), on `User::created`, when a user opens the folder modal, and through `ensureUserSynced()`.
  - **Folder order: the admin order wins.** `orderFolders()` keeps the user's own folders where they are and puts the
    default folders back into the admin's order within the slots they already hold. A newly created default folder
    goes right after the previous default folder (admin order), else before the next one, else at the end. Every
    user folder is then renumbered `index * 10`, so sort values never tie.
  - `ensureUserSynced(userId)` runs on the `ListServers` table and in `sortTenants()`. It compares a per-user cache
    entry with `defaultsSignature()` (a hash of `SYNC_VERSION` and every default folder's name, sort, lock and servers)
    and re-syncs that user once when they differ, under a per-user cache lock. So an admin change, or a release that
    bumps `SYNC_VERSION`, reaches every user on their next page load. Errors are reported, never thrown.
  - `sortTenants()` orders servers for the server switcher: folder sort, folder id, position, then name and id.
- **`src/EnhancedServerSorterPlugin.php`**: Filament UI only. `register()` adds the "Manage folders" header action to
  the app panel's `ListServers` page. `HasPluginSettings` provides the admin default-folders form. Both call
  `FolderService`.
- **`src/Providers/EnhancedServerSorterServiceProvider.php`**. Pelican loads every class in `src/Providers`
  automatically, so plugin classes don't list providers. This provider:
  - adds a dynamic `enhancedFolderAssignment` relation to `Server`, scoped to the current user
  - uses `Table::configureUsing` to add a collapsible group by folder to the `ListServers` table. The group is
    ordered by correlated subqueries (folder sort, folder id, position), then server name and id.
    `COALESCE(..., 2147483647)` puts unassigned servers last.
  - The base query `accessibleServers()` joins `subusers` and uses `distinct('servers.id')`, which PostgreSQL turns
    into `DISTINCT ON`. Any custom `ORDER BY` on it fails there ("SELECT DISTINCT ON expressions must match initial
    ORDER BY expressions"). `unwrapDistinct()` therefore rewrites it as `servers.* WHERE id IN (<original query>)`
    before ordering. Never `orderBy` on `accessibleServers()` directly; sort the collection in PHP instead.
  - prepends `resources/views/filament-panels` to the `filament-panels` view namespace, which is how the
    `tenant-menu.blade.php` override takes effect.
- **`resources/views/filament-panels/components/tenant-menu.blade.php`**: a copy of Filament v5.10.1's tenant menu
  with three small changes: `$menuTenants` (the current server plus `$tenants`, sorted by `sortTenants()`) replaces
  `$tenants` in the loop, the current server is shown as a disabled item, and the collapsed-sidebar tooltip uses
  `x-tooltip` instead of `x-tooltip.html`, so a server name is shown as text, never as HTML (Filament 5.10.2 fixed the
  same thing upstream). Checked statically against Filament 5.7.5 (Pelican beta36): every method and component it uses
  exists there too. After a Filament upgrade, diff it against
  `vendor/filament/panels/resources/views/components/tenant-menu.blade.php`.
- The server switcher is sorted only through this view. Don't rebind Filament's `filament` manager to sort tenants;
  the 1.4.0 merge brought that back by mistake, without its `use` import, so it would throw class-not-found whenever
  `filament` is resolved.

### Data model (`database/migrations`)

- `enhanced_server_folders` (per-user folders: `user_id`, `name`, `sort`)
- `enhanced_server_folder_server` (assignments: `folder_id`, `user_id`, `server_id`, `position`; unique on
  `user_id, server_id`, so a server can be in only one of a user's folders)
- `enhanced_server_default_folders` (`name`, `sort`, `is_locked`)
- `enhanced_server_default_folder_servers` (`default_folder_id`, `server_id`, `position`)

Pelican's `users.id` and `servers.id` are `increments` (unsigned int); the plugin's own ids are `id()` (unsigned
bigint). Foreign key columns must match, or MySQL/MariaDB reject the constraint. Migrations are guarded with
`Schema::hasTable`/`hasIndex`. Released migrations are never edited for schema changes; add a new migration instead.
Code that touches these tables checks `FolderService::isReady()` first, so the panel keeps working before the
migrations run.

## Code style

The source has no comments or docblocks, and new code should follow that.

## Translations

All UI strings go through `trans('enhanced-server-sorter::messages.<key>')`. `lang/en/messages.php` is the source;
the other ~30 locales in `lang/*/messages.php` have the same keys. Locale folder names must match Pelican's `lang/`
folder names (`de`, `hu`, `pt_BR`, `zh_CN`, not `de-DE`), because the user's language setting is used as the locale. When adding or renaming a key, update every locale
file.
