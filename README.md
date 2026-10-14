<div align="center">

# Enhanced Server Sorter

<<<<<<< Updated upstream
Advanced server organization system for the admin dashboard with folder management and server assignment.

## Features

- Create custom folders to organize servers in the admin dashboard
- Drag-and-drop server assignment to folders
- Default folders system for automatic server categorization
- Lock/unlock folders to prevent accidental modifications
- Search and filter servers within folders
- Bulk operations support

## Installation

1. Download the plugin ZIP file from the [Releases](https://github.com/Olivier-D-Pelican-Plugins/Enhanced-Server-Sorter/releases) page
2. Go to your Pelican Panel admin area
3. Navigate to the Plugins page
4. Click on "Import file"
5. Select the downloaded ZIP file
6. Click "Import"

## Usage

After installation, access the folder management interface from the admin panel to:
- Create and organize server folders
- Assign servers to specific folders
- Set up default folder rules for automatic categorization
- Lock important folders to prevent changes
=======
**Folders for your servers in [Pelican Panel](https://pelican.dev).**

Group servers into folders and put them in your own order. The server list and the server switcher both follow
that order.

[![Latest release](https://img.shields.io/github/v/release/StormSolutions-HU/enhanced-server-sorter?include_prereleases&label=release)](https://github.com/StormSolutions-HU/enhanced-server-sorter/releases/latest)
[![CI](https://github.com/StormSolutions-HU/enhanced-server-sorter/actions/workflows/ci.yml/badge.svg)](https://github.com/StormSolutions-HU/enhanced-server-sorter/actions/workflows/ci.yml)
[![Pelican](https://img.shields.io/badge/Pelican-1.0-0e7490)](https://pelican.dev)
[![License: GPL-3.0](https://img.shields.io/badge/license-GPL--3.0-blue)](LICENSE)

</div>

---

## Contents

- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [How sorting works](#how-sorting-works)
- [Default folders](#default-folders)
- [Translations](#translations)
- [Development](#development)
- [Credits](#credits)

## Features

| | |
|---|---|
| 📁 **Personal folders** | Each user creates, renames, reorders and deletes their own folders from the **Manage folders** button on the server list. |
| 🗂️ **Grouped server list** | Servers are grouped by folder in collapsible sections. Servers that aren't in a folder go into an **Unassigned** group. |
| 🔀 **Ordered server switcher** | The server switcher uses the same order as the list. The server you're on is shown greyed out. |
| 🛠️ **Default folders** | Admins set up folders that every user gets automatically, already filled with servers. |
| 🔒 **Locked folders** | Admins can lock a default folder so users can't change what's in it. |
| ✅ **Safe assignments** | A server can be in only one folder, and users can only add servers they have access to. |
| 🌍 **Translated** | Comes in 32 languages and uses each user's panel language. |
| 🧯 **Keeps the panel working** | If the plugin's database tables are missing, the panel still works and the folder dialog says what's wrong. |

## Installation

**Requirements:** Pelican Panel **1.0.0-rc.1 or newer**.

1. In the admin area, open **Plugins**.
2. Choose **Import from URL** and paste:

   ```
   https://github.com/StormSolutions-HU/enhanced-server-sorter/releases/latest/download/enhanced-server-sorter.zip
   ```

   Or download the zip from the [releases page](https://github.com/StormSolutions-HU/enhanced-server-sorter/releases)
   and use **Import from File**.
3. Click **Install** on the plugin. This creates the plugin's database tables.

### Updating

The plugin checks this repository for new versions. When one is available, the Plugins page shows an **Update**
button. Pelican checks the download against a SHA-256 checksum before it installs anything.

## Usage

### For users

On the server list, click **Manage folders**:

- **Add folder** creates a folder. Pick its servers from the searchable list.
- The **up and down arrows** reorder folders. Folder order sets the order of the groups on the server list and in
  the switcher.
- The order you pick servers in sets their order inside the folder.
- Click a group header on the server list to collapse or expand it.

Locked folders appear with a note. You can't edit their servers or name, and if you delete one it comes back when
you save.

### For administrators

Open **Plugins**, then this plugin's **Settings**, to manage **default folders**:

- Add folders and choose their servers. A server can be in only one default folder.
- Reorder them to set the order new users start with.
- Turn on **Lock folder** for any folder whose contents users shouldn't change.
>>>>>>> Stashed changes

Saving pushes the changes to every user straight away.

## How sorting works

Servers are sorted the same way everywhere in the panel:

<<<<<<< Updated upstream
Made by **olivierdti** maintained by Pelican Plugins Community
=======
1. **By folder**, in the order the user set.
2. **Then by position** inside the folder.
3. **Unassigned servers come last**, in alphabetical order.

## Default folders

|                                  | Unlocked default folder                     | Locked default folder                                   |
|----------------------------------|---------------------------------------------|---------------------------------------------------------|
| When it's created for a user     | Filled with the admin's servers             | Filled with the admin's servers                         |
| Users can rename or delete it    | ✅                                           | ❌                                                       |
| Users can change its servers     | ✅                                           | ❌                                                       |
| Admin changes its servers later  | Only users who don't have the folder yet get the change | Every user's copy is updated, and those servers are taken out of their other folders |

Default folders are given to existing users when the settings are saved, and to new users when their account is
created. A user who already has folders gets the new default folders added after their own.

> [!NOTE]
> Default folders are linked to user folders **by name**. If you rename a default folder, users get a new folder
> with the new name, and their old folder becomes a normal, unlocked folder.

## Translations

Available in: Arabic, Belarusian, Bulgarian, Chinese (Simplified & Traditional), Czech, Danish, Dutch, English,
Finnish, French, German, Greek, Hebrew, Hungarian, Indonesian, Italian, Japanese, Korean, Lithuanian, Norwegian,
Polish, Portuguese (Portugal & Brazil), Romanian, Russian, Serbian, Slovak, Spanish, Swedish, Turkish and Ukrainian.

The text lives in `lang/<locale>/messages.php`. Folder names follow Pelican's language codes (`de`, `pt_BR`,
`zh_CN`, …). To add a language, copy `lang/en/messages.php` into a new folder named after the Pelican language
code and translate the values.

## Development

The plugin runs inside a Pelican install. To work on it, clone the repository into `plugins/enhanced-server-sorter`
of a development panel, then install it from the Plugins page.

```
src/
├── EnhancedServerSorterPlugin.php      Filament UI: "Manage folders" action and admin settings form
├── Providers/                          Server list grouping and ordering, switcher view override
├── Services/FolderService.php          All folder logic: saving, default-folder sync, sorting
└── Models/                             Eloquent models for the four plugin tables
resources/views/filament-panels/        Override of Filament's tenant menu (the server switcher)
database/migrations/                    Plugin tables and indexes
lang/                                   Translations
```

Every push and pull request runs [CI](.github/workflows/ci.yml): a PHP 8.4 syntax check, checks on `plugin.json`
and `update.json`, and a check that every language has the same keys.

### Releasing

Publish a GitHub release with a version tag (for example `v1.4.0`). The [release workflow](.github/workflows/package.yml)
then:

1. builds `enhanced-server-sorter.zip` from the tagged commit, with the version in `plugin.json` set to the tag,
2. attaches the zip to the release,
3. commits the new version and the zip's SHA-256 to `plugin.json` and `update.json` on `main`, which is what
   panels check for updates.

Don't change the version in `plugin.json` or `update.json` by hand.

## Credits

Created by **Olivier D.** (olivierdti). Maintained by the
[Pelican Plugins Community](https://github.com/pelican-plugins-community) and
[StormSolutions](https://github.com/StormSolutions-HU).

Licensed under the [GNU General Public License v3.0](LICENSE).
>>>>>>> Stashed changes
