<?php

namespace Acquia\Blt\Robo\Commands\Source;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Common\RandomString;
use Acquia\Blt\Robo\Config\ConfigInitializer;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Defines commands in the "blt:init:settings" namespace.
 */
class SettingsCommand extends BltTasks {

  /**
   * Settings warning.
   *
   * @var string
   * Warning text added to the end of settings.php to point people to the BLT
   * docs on how to include settings.
   */
  private $settingsWarning = <<<WARNING
/**
 * IMPORTANT.
 *
 * Do not include additional settings here. Instead, add them to settings
 * included by `blt.settings.php`. See BLT's documentation for more detail.
 *
 * @link https://docs.acquia.com/blt/
 */
WARNING;

  /**
   * Generates default settings files for Drupal and drush.
   *
   * @command source:build:settings
   *
   * @aliases blt:init:settings bis settings setup:settings
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function generateSiteConfigFiles() {
    $this->generateLocalConfigFile();

    // Reload config.
    $config_initializer = new ConfigInitializer($this->getConfigValue('repo.root'), $this->input());
    $new_config = $config_initializer->initialize();

    // Replaces config.
    $this->getConfig()->replace($new_config->export());

    // Generate hash file in salt.txt.
    $this->hashSalt();

    $default_multisite_dir = $this->getConfigValue('docroot') . "/sites/default";
    $default_project_default_settings_file = "$default_multisite_dir/default.settings.php";

    $multisites = $this->getConfigValue('multisites');
    $initial_site = $this->getConfigValue('site');
    $current_site = $initial_site;

    $this->logger->debug("Multisites found: " . implode(',', $multisites));
    $this->logger->debug("Initial site: $initial_site");

    foreach ($multisites as $multisite) {
      if ($current_site != $multisite) {
        $this->switchSiteContext($multisite);
        $current_site = $multisite;
      }

      // Generate settings.php.
      $multisite_dir = $this->getConfigValue('docroot') . "/sites/$multisite";
      $project_default_settings_file = "$multisite_dir/default.settings.php";
      $project_settings_file = "$multisite_dir/settings.php";

      // Generate local.settings.php.
      $blt_local_settings_file = $this->getConfigValue('blt.root') . '/settings/default.local.settings.php';
      $default_local_settings_file = "$multisite_dir/settings/default.local.settings.php";
      $project_local_settings_file = "$multisite_dir/settings/local.settings.php";

      // Generate default.includes.settings.php.
      $blt_includes_settings_file = $this->getConfigValue('blt.root') . '/settings/default.includes.settings.php';
      $default_includes_settings_file = "$multisite_dir/settings/default.includes.settings.php";

      // Generate sites/settings/default.global.settings.php.
      $blt_glob_settings_file = $this->getConfigValue('blt.root') . '/settings/default.global.settings.php';
      $default_glob_settings_file = $this->getConfigValue('docroot') . "/sites/settings/default.global.settings.php";
      $global_settings_file = $this->getConfigValue('docroot') . "/sites/settings/global.settings.php";

      // Generate local.drush.yml.
      $blt_local_drush_file = $this->getConfigValue('blt.root') . '/settings/default.local.drush.yml';
      $default_local_drush_file = "$multisite_dir/default.local.drush.yml";
      $project_local_drush_file = "$multisite_dir/local.drush.yml";

      $copy_map = [
        $blt_local_settings_file => $default_local_settings_file,
        $default_local_settings_file => $project_local_settings_file,
        $blt_includes_settings_file => $default_includes_settings_file,
        $blt_local_drush_file => $default_local_drush_file,
        $default_local_drush_file => $project_local_drush_file,
      ];
      // Define an array of files that require property expansion.
      $expand_map = [
        $default_local_settings_file => $project_local_settings_file,
        $default_local_drush_file => $project_local_drush_file,
      ];

      // Add default.global.settings.php if global.settings.php does not exist.
      if (!file_exists($global_settings_file)) {
        $copy_map[$blt_glob_settings_file] = $default_glob_settings_file;
      }

      // Only add the settings file if the default exists.
      if (file_exists($default_project_default_settings_file)) {
        $copy_map[$default_project_default_settings_file] = $project_default_settings_file;
        $copy_map[$project_default_settings_file] = $project_settings_file;
      }
      elseif (!file_exists($project_settings_file)) {
        $this->logger->warning("No $default_project_default_settings_file file found.");
      }

      $task = $this->taskFilesystemStack()
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->chmod($multisite_dir, 0777);

      if (file_exists($project_settings_file)) {
        $task->chmod($project_settings_file, 0777);
      }

      // Copy files without overwriting.
      foreach ($copy_map as $from => $to) {
        if (!file_exists($to)) {
          $task->copy($from, $to);
        }
      }

      $result = $task->run();

      foreach ($expand_map as $from => $to) {
        $this->getConfig()->expandFileProperties($to);
      }

      if (!$result->wasSuccessful()) {
        throw new BltException("Unable to copy files settings files from BLT into your repository.");
      }

      $result = $this->taskWriteToFile($project_settings_file)
        ->appendUnlessMatches('#vendor/acquia/blt/settings/blt.settings.php#', 'require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";' . "\n")
        ->appendUnlessMatches('#Do not include additional settings here#', $this->settingsWarning . "\n")
        ->append(TRUE)
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();

      if (!$result->wasSuccessful()) {
        throw new BltException("Unable to modify $project_settings_file.");
      }

      $result = $this->taskFilesystemStack()
        ->chmod($project_settings_file, 0644)
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();

      if (!$result->wasSuccessful()) {
        $this->getInspector()->getFs()->makePathRelative($project_settings_file, $this->getConfigValue('repo.root'));
        throw new BltException("Unable to set permissions on $project_settings_file.");
      }
    }

    if ($current_site != $initial_site) {
      $this->switchSiteContext($initial_site);
    }
  }

  /**
   * Installs BLT git hooks to local .git/hooks directory.
   *
   * @command blt:init:git-hooks
   * @aliases big setup:git-hooks
   */
  public function gitHooks() {
    foreach ($this->getConfigValue('git.hooks') as $hook => $path) {
      $this->installGitHook($hook);
    }
  }

  /**
   * Installs a given git hook.
   *
   * This symlinks the hook into the project's .git/hooks directory.
   *
   * @param string $hook
   *   The git hook to install, e.g., 'pre-commit'.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  protected function installGitHook($hook) {
    $fs = new Filesystem();
    $project_hook_directory = $this->getConfigValue('repo.root') . "/.git/hooks";
    $project_hook = $project_hook_directory . "/$hook";
    if ($this->getConfigValue('git.hooks.' . $hook)) {
      $this->say("Installing $hook git hook...");
      $hook_source = $this->getConfigValue('git.hooks.' . $hook) . "/$hook";
      $path_to_hook_source = rtrim($fs->makePathRelative($hook_source, $project_hook_directory), '/');

      $result = $this->taskFilesystemStack()
        ->mkdir($this->getConfigValue('repo.root') . '/.git/hooks')
        ->remove($project_hook)
        // phpcs:ignore
        ->symlink($path_to_hook_source, $project_hook)
        ->stopOnFail()
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();

      if (!$result->wasSuccessful()) {
        throw new BltException("Unable to install $hook git hook.");
      }
    }
    else {
      if (file_exists($project_hook)) {
        $this->say("Removing disabled $hook git hook...");
        $result = $this->taskFilesystemStack()
          ->remove($project_hook)
          ->stopOnFail()
          ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
          ->run();

        if (!$result->wasSuccessful()) {
          throw new BltException("Unable to remove disabled $hook git hook");
        }
      }
      else {
        $this->say("Skipping installation of $hook git hook...");
      }
    }
  }

  /**
   * Writes a hash salt to ${repo.root}/salt.txt if one does not exist.
   *
   * @command drupal:hash-salt:init
   * @aliases dhsi setup:hash-salt
   *
   * @return int
   *   A CLI exit code.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function hashSalt() {
    $hash_salt_file = $this->getConfigValue('repo.root') . '/salt.txt';
    if (!file_exists($hash_salt_file)) {
      $this->say("Generating hash salt...");
      $result = $this->taskWriteToFile($hash_salt_file)
        ->line(RandomString::string(55))
        ->run();

      if (!$result->wasSuccessful()) {
        $filepath = $this->getInspector()->getFs()->makePathRelative($hash_salt_file, $this->getConfigValue('repo.root'));
        throw new BltException("Unable to write hash salt to $filepath.");
      }

      return $result->getExitCode();
    }
    else {
      $this->say("Hash salt already exists.");
      return 0;
    }
  }

  /**
   * Writes a deployment_identifier to ${repo.root}/deployment_identifier.
   *
   * @command drupal:deployment-identifier:init
   * @aliases ddii
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function createDeployId($options = ['id' => InputOption::VALUE_REQUIRED]) {
    if (!$options['id']) {
      $options['id'] = RandomString::string(8);
    }
    $deployment_identifier_file = $this->getConfigValue('repo.root') . '/deployment_identifier';
    $this->say("Generating deployment identifier...");
    $result = $this->taskWriteToFile($deployment_identifier_file)
      ->line($options['id'])
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      $filepath = $this->getInspector()->getFs()->makePathRelative($deployment_identifier_file, $this->getConfigValue('repo.root'));
      throw new BltException("Unable to write deployment identifier to $filepath.");
    }
  }

  /**
   * Generates local.blt.yml from example.local.blt.yml.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  private function generateLocalConfigFile() {
    $localConfigFile = $this->getConfigValue('blt.config-files.local');
    $exampleLocalConfigFile = $this->getConfigValue('blt.config-files.example-local');
    $localConfigFilepath = $this->getInspector()
      ->getFs()
      ->makePathRelative($localConfigFile, $this->getConfigValue('repo.root'));
    $exampleLocalConfigFilepath = $this->getInspector()
      ->getFs()
      ->makePathRelative($exampleLocalConfigFile, $this->getConfigValue('repo.root'));

    if (file_exists($localConfigFile)) {
      // Don't overwrite an existing local.blt.yml.
      return;
    }

    if (!file_exists($exampleLocalConfigFile)) {
      $this->say("Could not find $exampleLocalConfigFilepath. Create and commit this file if you'd like to automatically generate $localConfigFilepath based on this template.");
      return;
    }

    $result = $this->taskFilesystemStack()
      ->copy($exampleLocalConfigFile, $localConfigFile)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to create $localConfigFilepath.");
    }
  }

}
