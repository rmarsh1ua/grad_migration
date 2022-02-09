# Grad College Quickstart 2 Migration

This is a custom migration module intended to bring over content from Grad College Drupal 7 sites, to the new Arizona Quickstart 2 Drupal 8/9 site. This consists of a series of migration files and a few custom Source PHP classes. This is dependent on the az_migration module, a custom module inclued in Quickstart meant to transfer files between QS1 and QS2 sites, as well as a few other custom modules packaged with Quickstart 2.

# Instructions

To use this module follow the steps below:

1. Configure your environment, install AZQS2. Follow the [instructions here](https://github.com/az-digital/az_quickstart/blob/main/CONTRIBUTING.md#local-development) to setup a local lando stack if working in a local environment.

2. For the sake of simplicity, get a dump of the existing database and import it into the same database server as the new environment's db server. You ultimately want a "drupal9" database, for the new QS2 site, and the old system's data on the same server.

3. Add a connection for the old database dump. This should go in /sites/default/settings.php

  ```
  $databases['migrate']['default'] = [
    'driver' => 'mysql',
    'namespace' => 'Drupal\Core\Database\Driver\mysql',
    'database' => 'databasename',
    'username' => 'databaseusername',
    'password' => 'databasepassword',
    'port' => 'databaseport',
    'host' => 'localhost',
    'prefix' => '',
  ];
  ```

4. The AZ migration module assumes that content is being migrated from a D7 QS1 website and accordingly assumes that certain modules are installed which assumes additional database schema in the OLD database that may not strictly be present. Because our sites are not based on QS1, there may be some tables/modules missing that will cause errors to appear while checking migration status. The easiest way to fix this is to simply fill stub schema into the database dump. For the gcstandard site, there's an example of all the necessary SQL to bridge the database in the non-qs-schema-fix.sql file in this repo. This may vary from site to site.

5. `git clone` this repo into the modules/custom folder of the site. Install the gc_migrate module. This can be done through the website's admin interface or using drush.
`drush en gc_migration`

6. Install supporting modules. Enabling gc_migration in the last step should enable a series of dependent modules. You must also enable the Quickstart Paragraphs - HTML submodule (az_paragraphs_html). There are a number of other modules that make the migration process easier that can be installed:
```
rm composer.lock
composer require drupal/migrate_tools
composer require drupal/migrate_devel:*
drush en migrate_devel
```

7. Update the migration configuration settings by using the following console commands. This will allow for the migration framework to correctly process file downloads handled through a migration script. Update these settings to reflect the site being migrated. Answer 'yes' to adding these to the gc_migration.settings.config.
```
drush cset gc_migration.settings migrate_d7_protocol "https"
drush cset gc_migration.settings migrate_d7_filebasepath "kronos.grad.arizona.edu/gcstandard"
drush cset gc_migration.settings migrate_d7_public_path "sites/default/files"
```

8. The site is now ready to begin the migration. Users have to be migrated before anything else:
```
drush migrate-import az_user
```
Once that's complete, then the grad college content can be migrated. The following command can be used to take in everything at once:
```
drush migrate-import --group gc_migration --migrate-debug --continue-on-failure
```

Or migrations can be run individually:
```
drush migrate-import ua_gc_paragraph --migrate-debug
```

The debug flag is of course optional. Using the `--group` flag must be used in conjunction with the `--continue-on-failure` flag, as the migration for redirects fails to import several entries by design.
