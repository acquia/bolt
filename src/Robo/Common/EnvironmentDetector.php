<?php

namespace Acquia\Blt\Robo\Common;

use Acquia\Blt\Robo\Exceptions\BltException;

/**
 * Class EnvironmentDetector
 * @package Acquia\Blt\Robo\Common
 *
 * Attempts to detect various properties about the current hosting environment.
 */
class EnvironmentDetector {

  public static function isAhEnv() {
    return (bool) self::getAhEnv();
  }

  /**
   * @param null $ah_group
   *   The Acquia Hosting site / group name (e.g. my_subscription).
   * @param null $ah_env
   *   The Acquia Hosting environment name (e.g. 01dev).
   * @return bool
   * @throws BltException
   */
  public static function isAcsfEnv($ah_group = NULL, $ah_env = NULL) {
    if (is_null($ah_group)) {
      $ah_group = self::getAhGroup();
    }

    if (is_null($ah_env)) {
      $ah_env = self::getAhEnv();
    }

    if (empty($ah_group) || empty($ah_env)) {
      return FALSE;
    }

    $is_acsf_json = file_exists("/mnt/files/$ah_group.$ah_env/files-private/sites.json");
    $is_acsf_env_name = preg_match('/01(dev|test|live|update)(up)?/', $ah_env);

    if ($is_acsf_json != $is_acsf_env_name) {
      throw new BltException("Cannot determine if this is an ACSF environment or not.");
    }

    return ($is_acsf_env_name && $is_acsf_json);
  }

  public static function isAhProdEnv() {
    $ah_env = self::getAhEnv();
    // ACE prod is 'prod'; ACSF can be '01live', '02live', ...
    return $ah_env == 'prod' || preg_match('/^\d*live$/', $ah_env);
  }

  public static function isAhStageEnv() {
    $ah_env = self::getAhEnv();
    // ACE staging is 'test' or 'stg'; ACSF is '01test', '02test', ...
    return preg_match('/^\d*test$/', $ah_env) || $ah_env == 'stg';
  }

  public static function isAhDevEnv() {
    // ACE dev is 'dev', 'dev1', ...; ACSF dev is '01dev', '02dev', ...
    return (preg_match('/^\d*dev\d*$/', self::getAhEnv()));
  }

  public static function isAhOdeEnv($ah_env = NULL) {
    if (is_null($ah_env)) {
      $ah_env = self::getAhEnv();
    }
    // CDEs (formerly 'ODEs') can be 'ode1', 'ode2', ...
    return (preg_match('/^ode\d*$/', $ah_env));
  }

  public static function isAhDevCloud() {
    return (!empty($_SERVER['HTTP_HOST']) && strstr($_SERVER['HTTP_HOST'], 'devcloud'));
  }

  public static function getAhGroup() {
    return isset($_ENV['AH_SITE_GROUP']) ? $_ENV['AH_SITE_GROUP'] : NULL;
  }

  public static function getAhEnv() {
    return isset($_ENV['AH_SITE_ENVIRONMENT']) ? $_ENV['AH_SITE_ENVIRONMENT'] : NULL;
  }

  public static function getCiEnv() {
    $mapping = [
      'TRAVIS' => 'travis',
      'PIPELINE_ENV' => 'pipelines',
      'PROBO_ENVIRONMENT' => 'probo',
      'TUGBOAT_URL' => 'tugboat',
      'GITLAB_CI' => 'gitlab',
    ];
    foreach ($mapping as $env_var => $ci_name) {
      if (isset($_ENV[$env_var])) {
        return $ci_name;
      }
    }
    return FALSE;
  }

  public static function isCiEnv() {
    return self::getCiEnv() || isset($_ENV['CI']);
  }

  public static function isPantheonEnv() {
    return isset($_ENV['PANTHEON_ENVIRONMENT']);
  }

  public static function getPantheonEnv() {
    return self::isPantheonEnv() ? $_ENV['PANTHEON_ENVIRONMENT'] : NULL;
  }

  public static function isPantheonDevEnv() {
    return self::getPantheonEnv() == 'dev';
  }

  public static function isPantheonStageEnv() {
    return self::getPantheonEnv() == 'test';
  }

  public static function isPantheonProdEnv() {
    return self::getPantheonEnv() == 'live';
  }

  public static function isLocalEnv() {
    return !self::isAhEnv() && !self::isPantheonEnv() && !self::isCiEnv();
  }

  public static function isDevEnv() {
    return self::isAhDevEnv() || self::isPantheonDevEnv();
  }

  public static function isStageEnv() {
    return self::isAhStageEnv() || self::isPantheonStageEnv();
  }

  public static function isProdEnv() {
    return self::isAhProdEnv() || self::isPantheonProdEnv();
  }

  public static function getAhFilesRoot() {
    return '/mnt/files/' . self::getAhGroup() . '.' . self::getAhEnv();
  }

  public static function isAcsfInited() {
    return file_exists(DRUPAL_ROOT . "/sites/g");
  }

  /**
   * Get a standardized name of the current OS.
   *
   * @return string
   */
  public static function getOsName() {
    // Typically Linux, Darwin, or Windows.
    return PHP_OS;
  }

  /**
   * Get a unique ID for the current running environment.
   *
   * Should conform to UUIDs generated by sebhildebrandt/systeminformation.
   * @see https://github.com/sebhildebrandt/systeminformation/blob/master/lib/osinfo.js
   *
   * @return string|null
   */
  public static function getMachineUuid() {
    switch (self::getOsName()) {
      case 'Linux':
        return shell_exec('( cat /var/lib/dbus/machine-id /etc/machine-id 2> /dev/null || hostname ) | head -n 1 || :');

      case 'Darwin':
        $output = shell_exec('ioreg -rd1 -c IOPlatformExpertDevice | grep IOPlatformUUID');
        $parts = explode('=', str_replace('"', '', $output));
        return strtolower(trim($parts[1]));

      case 'Windows':
        return shell_exec('%windir%\\System32\\reg query "HKEY_LOCAL_MACHINE\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid');

      default:
        return NULL;
    }
  }

  /**
   * @return string|null
   *   ACSF db name.
   * @throws BltException
   */
  public static function getAcsfDbName() {
    return isset($GLOBALS['gardens_site_settings']) && self::isAcsfEnv() ? $GLOBALS['gardens_site_settings']['conf']['acsf_db_name'] : NULL;
  }

}
