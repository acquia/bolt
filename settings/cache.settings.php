<?php

/**
 * @file
 * Contains caching configuration.
 */

use Composer\Autoload\ClassLoader;

if ($is_prod_env || $is_stage_env) {
  $config['system.logging']['error_level'] = 'hide';
}

/**
 * Example: Override required core defaults to use alternative cache backends.
 *
 * The bootstrap, discovery, and config bins use the chainedfast backend by
 * default and since the core service definition for these bins sets
 * the expire to CacheBackendInterface::CACHE_PERMANENT, these objects
 * are cached permanently and may result in stale configuration when
 * rebuilding the service container on deploymemnts and cache rebuilds.
 * Uncomment the relevant lines for each bin below and configure the desired
 *
 * @see https://www.drupal.org/node/2754947
 */

// $settings['cache']['bins']['bootstrap'] = 'cache.backend.null';
// $settings['cache']['bins']['discovery'] = 'cache.backend.null';
// $settings['cache']['bins']['config'] = 'cache.backend.null';.
/**
 * Use memcache as cache backend.
 *
 * Autoload memcache classes and service container in case module is not
 * installed. Avoids the need to patch core and allows for overriding the
 * default backend when installing Drupal.
 *
 * @see https://www.drupal.org/node/2766509
 */
if ($is_ah_env) {
  switch ($ah_env) {
    case 'test':
    case 'prod':

      // Check for PHP Memcached libraries.
      $memcache_exists = class_exists('Memcache', FALSE);
      $memcached_exists = class_exists('Memcached', FALSE);
      $memcache_module_is_present = file_exists(DRUPAL_ROOT . '/modules/contrib/memcache/memcache.services.yml');
      if ($memcache_module_is_present && ($memcache_exists || $memcached_exists)) {
        // Use Memcached extension if available.
        if ($memcached_exists) {
          $settings['memcache']['extension'] = 'Memcached';
        }

        if (class_exists(ClassLoader::class)) {
          $class_loader = new ClassLoader();
          $class_loader->addPsr4('Drupal\\memcache\\', 'modules/contrib/memcache/src');
          $class_loader->register();

          $settings['container_yamls'][] = DRUPAL_ROOT . '/modules/contrib/memcache/memcache.services.yml';

          // Bootstrap cache.container with memcache rather than database.
          $settings['bootstrap_container_definition'] = [
            'parameters' => [],
            'services' => [
              'database' => [
                'class' => 'Drupal\Core\Database\Connection',
                'factory' => 'Drupal\Core\Database\Database::getConnection',
                'arguments' => ['default'],
              ],
              'settings' => [
                'class' => 'Drupal\Core\Site\Settings',
                'factory' => 'Drupal\Core\Site\Settings::getInstance',
              ],
              'memcache.config' => [
                'class' => 'Drupal\memcache\DrupalMemcacheConfig',
                'arguments' => ['@settings'],
              ],
              'memcache.backend.cache.factory' => [
                'class' => 'Drupal\memcache\DrupalMemcacheFactory',
                'arguments' => ['@memcache.config'],
              ],
              'memcache.backend.cache.container' => [
                'class' => 'Drupal\memcache\DrupalMemcacheFactory',
                'factory' => ['@memcache.backend.cache.factory', 'get'],
                'arguments' => ['container'],
              ],
              'lock.container' => [
                'class' => 'Drupal\memcache\Lock\MemcacheLockBackend',
                'arguments' => ['container', '@memcache.backend.cache.container'],
              ],
              'cache_tags_provider.container' => [
                'class' => 'Drupal\Core\Cache\DatabaseCacheTagsChecksum',
                'arguments' => ['@database'],
              ],
              'cache.container' => [
                'class' => 'Drupal\memcache\MemcacheBackend',
                'arguments' => [
                  'container',
                  '@memcache.backend.cache.container',
                  '@lock.container',
                  '@memcache.config',
                  '@cache_tags_provider.container',
                ],
              ],
            ],
          ];

          // Override default fastchained backend for static bins.
          // @see https://www.drupal.org/node/2754947
          $settings['cache']['bins']['bootstrap'] = 'cache.backend.memcache';
          $settings['cache']['bins']['discovery'] = 'cache.backend.memcache';
          $settings['cache']['bins']['config'] = 'cache.backend.memcache';

          // Use memcache as the default bin.
          $settings['cache']['default'] = 'cache.backend.memcache';

          // Enable stampede protection.
          $settings['memcache']['stampede_protection'] = TRUE;

          // Move locks to memcache.
          $settings['container_yamls'][] = DRUPAL_ROOT . '/../vendor/acquia/blt/settings/memcache.yml';
        }
      }
      break;
  }
}
