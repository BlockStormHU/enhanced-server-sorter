# Enhanced Server Sorter

Advanced server organization system for the Pelican Panel with folder management, server assignment and
folder-aware sorting across the client area.

## Features

- Create custom folders to organize your servers, managed per user from the "Manage Folders" button on the server
  list
- Assign servers to folders with a searchable multi-select, with folders reorderable using the up/down buttons
- Folder order and the position of a server inside its folder define the sorting everywhere in the panel
- Server list grouped by folder, with collapsible groups and an "Unassigned" group for servers without a folder
- Server switcher dropdown ordered by folder, so the switcher matches the order of the server list
- The server you are currently on is shown as disabled in the switcher dropdown
- Default folders system for automatic server categorization, configured by admins in the plugin settings
- Default folders are pushed to every existing user as soon as the settings are saved, and re-synced for a user
  when they open their folder manager
- Lock/unlock default folders to prevent users from changing their contents
- Duplicate protection: a server can only live in one folder, and an explicit warning is shown if the same server
  is assigned twice
- Only servers the user actually has access to can be assigned or kept in a folder
- Fully translatable interface through the plugin language files
- Safe fallback behaviour: if the plugin migrations have not run yet, the panel keeps working and a message
  explains what is missing

## Installation

1. Download the plugin ZIP file from
   the [Releases](https://github.com/Olivier-D-Pelican-Plugins/Enhanced-Server-Sorter/releases) page
2. Go to your Pelican Panel admin area
3. Navigate to the Plugins page
4. Click on "Import file"
5. Select the downloaded ZIP file
6. Click "Import"

## Usage

### As a user

Open the server list and use the "Manage Folders" button to:

- Create and organize your server folders
- Assign servers to specific folders
- Reorder folders to control how servers are grouped and sorted, both in the server list and in the server
  switcher
- Collapse or expand folder groups on the server list

Folders that were locked by an administrator are shown but their server list cannot be edited.

### As an administrator

Open the plugin settings from the Plugins page to:

- Set up default folders for automatic categorization
- Pre-assign servers to those default folders
- Lock important folders to prevent users from changing them
- Reorder default folders to define the order every user starts with

## Requirements

- Pelican Panel v1.0+

## Author

Made by **olivierdti** maintained by Pelican Plugins Community & StormSolutions
