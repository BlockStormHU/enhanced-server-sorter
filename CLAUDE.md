# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A plugin for the [Pelican Panel](https://pelican.dev) (Laravel + Filament). It is not a standalone app: there is no
`composer.json`, no test suite, no linter, and no local build. Code runs only inside a Pelican install, where classes
such as `App\Models\Server`, `App\Filament\App\Resources\Servers\Pages\ListServers`, `App\Enums\CustomizationKey`,
and helpers like `user()` and `plugin_path()` come from the host panel. To try a change, copy or symlink the repo
into a Pelican install's `plugins/enhanced-server-sorter` directory, or zip it and import it from the admin Plugins page.

`plugin.json` is the plugin manifest (namespace `Olivier\EnhancedServerSorter`, entry class `EnhancedServerSorterPlugin`,
panels `admin`/`app`/`server`, `api_version` 1). Leave `panel_version` null: Pelican compares it with
`version_compare`, so `^1.0.0` would reject 1.0.0-rc.1. Target platform: Pelican 1.0 (Filament 5, Laravel 13, PHP 8.4).

To run the plugin for real, use a Pelican checkout with `composer install` and a SQLite, MySQL, MariaDB or PostgreSQL
database, then `php artisan p:plugin:install enhanced-server-sorter`. Drive pages with `Livewire::actingAs($user)->test(ListServers::class)`
from a script that bootstraps the app. Pelican's own Pest suite skips plugins (`runningUnitTests()`). Test query
changes on PostgreSQL and MySQL as well as SQLite.

To check a change against the host APIs, get the Pelican source (`git clone --depth 1 --branch <tag>
https://github.com/pelican-dev/panel`) and the Filament version from its `composer.lock`.

## Release / packaging

`.github/workflows/package.yml` runs when a GitHub release is published (or manually with a tag). It:
1. Checks out the tag and builds `enhanced-server-sorter.zip` from it, with `plugin.json` `.version` (tag without a
   leading `v`) and `.update_url` set.
2. Attaches the zip to the release.
3. Commits the new version and an `update.json` containing the zip's `sha256` to `main`. Pelican checks that
   checksum during in-panel updates.

`.github/workflows/ci.yml` runs on every push and PR. It runs `php -l` with PHP 8.4, checks the `plugin.json`
fields, checks that `plugin.json` and `update.json` have the same version, and checks that every
`lang/*/messages.php` has the same keys as `lang/en`. Run `actionlint` after editing either workflow.

So don't bump `plugin.json`/`update.json` by hand for a release; publishing the release does it. `dist/` and `*.zip`
are gitignored local build output.

## Architecture

Three pieces must agree on one ordering: **folder `sort`, then the server's `position` in that folder, with unassigned
servers last**.

- **`src/Services/FolderService.php`**: all folder logic and DB access. It is bound as a `scoped` service, so its
  memoized values (`isReady()`, `defaultFolders()`) last for one request. Get it with `app(FolderService::class)`.
  - `saveUserFolders()` replaces a user's folders in one transaction: `sort = index * 10`, `position` = index in the
    select, server IDs limited to the user's accessible servers. Folders left out of the submission are deleted,
    except locked ones.
  - `syncDefaultFolders(?userId)` copies the admin's default folders into user folders, in batches of 200 users.
    Default and user folders are matched **by name**. An unlocked default folder is filled only when it is first
    created for a user. A locked one is reset to the admin's list on every sync, and its servers are taken out of
    the user's other folders. It runs when settings are saved (all users), on `User::created`, and when a user opens
    the folder modal.
  - `sortTenants()` orders servers for the server switcher.
- **`src/EnhancedServerSorterPlugin.php`**: Filament UI only. `register()` adds the "Manage folders" header action to
  the app panel's `ListServers` page. `HasPluginSettings` provides the admin default-folders form. Both call
  `FolderService`.
- **`src/Providers/EnhancedServerSorterServiceProvider.php`**. Pelican loads every class in `src/Providers`
  automatically, so plugin classes don't list providers. This provider:
  - adds a dynamic `enhancedFolderAssignment` relation to `Server`, scoped to the current user
  - uses `Table::configureUsing` to add a collapsible group by folder to the `ListServers` table. The group is
    ordered by correlated subqueries. `COALESCE(..., 2147483647)` puts unassigned servers last.
  - The base query `accessibleServers()` joins `subusers` and uses `distinct('servers.id')`, which PostgreSQL turns
    into `DISTINCT ON`. Any custom `ORDER BY` on it fails there ("SELECT DISTINCT ON expressions must match initial
    ORDER BY expressions"). `unwrapDistinct()` therefore rewrites it as `servers.* WHERE id IN (<original query>)`
    before ordering. Never `orderBy` on `accessibleServers()` directly; sort the collection in PHP instead.
  - prepends `resources/views/filament-panels` to the `filament-panels` view namespace, which is how the
    `tenant-menu.blade.php` override takes effect.
- **`resources/views/filament-panels/components/tenant-menu.blade.php`**: a copy of Filament v5.10.1's tenant menu
  with two small changes: `$menuTenants` (the current server plus `$tenants`, sorted by `sortTenants()`) replaces
  `$tenants` in the loop, and the current server is shown as a disabled item. After a Filament upgrade, diff it against `vendor/filament/panels/resources/views/components/tenant-menu.blade.php`.

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
