# 93digital Migrate

***v1.0***

***Author:*** *Vic Lobins*

This toolkit provides the functionality to export and import posts using a JSON or CSV file. The aim is to automate and simplify as much of the content migration process and give devs the ability to inject code during this process.

The plugin can be installed as normal by either uploading the folder to the plugins directory or by packing up as a zip and adding directly in WordPress. Once installed an admin settings page will be automatically added here: `[SITE URL]/wp-admin/admin.php?page=nine3-migrate-page`

On this page you can reset plugin settings, this will purge any saved settings as well as any plugin generated files. You can also delete all migrated posts, as the label suggests this will permanently delete ***all*** posts which have been previously imported.

## Plugin Tabs

For more information on each of the tabs see:

- [Export Posts](docs/export-posts.md)
- [Export Terms](docs/export-terms.md)
- [Import Posts](docs/import-posts.md)
- [Import Terms](docs/import-terms.md)
- [HubSpot](docs/hubspot.md)

## Hooks

For more information on the various hooks available in the plugin see: [Hooks](docs/hooks.md)

## Helpers

There are various helper functions which can be used outside of the plugin, more info here: [Helpers](docs/helpers.md)