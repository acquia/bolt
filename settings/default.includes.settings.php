<?php

/**
 * @file
 * Serves as a central aggregation point for adding settings files.
 */

/**
 * Use this file to add any addition settings files which should be required by
 * your application. To use, rename this file to be `includes.settings.php and
 * add file references to the `additionalSettingsFiles` array below.
 *
 * Files required into this file are included into the blt.settings.php file
 * prior to the inclusion of the local.settings.php file, so that that file may
 * override any variable set up the chain. Taken in total, the points at which
 * files are included into the base settings.php file are...
 *
 * settings.php
 *   |
 *   ---- blt.settings.php
 *          |
 *          ---- includes.settings.php
 *          |       |
 *          |       ---- foo.settings.php
 *          |       ---- bar.settings.php
 *          |       ---- ....
 *          |
 *          ---- local.settings.php
 */

/**
 * Add settings using full file location and name.
 *
 * It is recommended that you use the DRUPAL_ROOT and $site_dir components to
 * provide full pathing to the file in a dynamic manner.
 */
$additionalSettingsFiles = [
  // DRUPAL_ROOT . "/sites/$site_dir/settings/foo.settings.php"
];

foreach ($additionalSettingsFiles as $settingsFile) {
  if (file_exists($settingsFile)) {
    require $settingsFile;
  }
}
