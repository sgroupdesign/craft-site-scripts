# Site Scripts
A collection of scripts for Craft CMS development.

## Setup
We call `./craft site-scripts/setup` to start an opinionated install. Basically this just presets a few of the prompts to save us time.

- Generates a new user with a one-time, secure password.
- Updates the site name.
- Upadates for SEOmatic meta.

## Pull
These commands are for pulling down from Forge-based servers.

- Pull assets from a server to your machine
- Pull the database from a server to your machine, backing up any local copy

### Getting started
Before you get started, make sure you populate the following in your `.env` file:
- `REMOTE_LOGIN`
- `REMOTE_CRAFT_PATH`
- `REMOTE_DB_USER`
- `REMOTE_DB_PASSWORD`
- `REMOTE_DB_TABLE`

`./craft site-scripts/pull/db` - will login to the server and create a backup of the database provided, then download it to your machine in a temporary directory. It will then create a local gzipped backup of your local database and store in `storage/local-backup` of your project. It'll then import and overwrite your local database with the database from the server.

`./craft site-scripts/pull/assets` - will login to the server and fetch the `storage/rebrand`, `storage/userphotos` and entire `public_html/uploads` folder, and download it, overwriting any local files. It's also intelligent enough to know if there are any new files to download, so running this command multiple times will only download files that've changed on the server.

`./craft site-scripts/pull` will do both.
