<?php

namespace Acquia\Blt\Robo\Commands\Saml;

use Acquia\Blt\Robo\BltTasks;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "simplesamlphp:*" namespace.
 */
class SimpleSamlPhpCommand extends BltTasks {

  protected $bltRoot;
  protected $repoRoot;

  /**
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   */
  public function initialize() {
    $this->bltRoot = $this->getConfigValue('blt.root');
    $this->repoRoot = $this->getConfigValue('repo.root');
  }

  /**
   * Initializes SimpleSAMLphp for project.
   *
   * @return \Robo\Result
   * @throws \Exception
   *
   * @command simplesamlphp:init
   */
  public function simpleSamlPhpInit() {
    if (!$this->isSimpleSamlPhpInstalled()) {
      $result = $this->simpleSamlPhpLibInit();
      $result = $this->simpleSamlPhpConfigInit();
      $result = $this->setSimpleSamlPhpInstalled();
      $result = $this->symlinkDocrootToLibDir();
    }
    else {
      $this->say('SimpleSAMLphp has already been initialized by BLT.');
    }
    $this->simpleSamlPhpInitComplete();
    if (isset($result)) {
      return $result;
    }
  }

  /**
   * Adds simplesamlphp_auth as a dependency.
   *
   * @return \Robo\Result
   * @throws \Exception
   */
  protected function simpleSamlPhpLibInit() {
    $this->say('Adding SimpleSAMLphp Auth module as a dependency.');

    $result = $this->taskExec("composer require")
      ->arg('drupal/simplesamlphp_auth:^3.0')
      ->printOutput(TRUE)
      ->detectInteractive()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->dir($this->getConfigValue('repo.root'))
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Unable to install drupal/simplesamlphp_auth package.");
    }

    return $result;
  }

  /**
   * Copies the configuration templates from the library to a simplesamlphp directory located in the project root.
   *
   * @return \Robo\Result
   *
   * @command simplesamlphp:config:init
   */
  public function simpleSamlPhpConfigInit() {
    $destinationDirectory = "{$this->repoRoot}/simplesamlphp/config";

    $this->say("Copying config files to ${destinationDirectory}.");
    $result = $this->taskFileSystemStack()
      ->copy("{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/config-templates/authsources.php", "${destinationDirectory}/authsources.php")
      ->copy("{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/config-templates/config.php", "${destinationDirectory}/config.php")
      ->copy("{$this->bltRoot}/scripts/simplesamlphp/acquia_config.php", "${destinationDirectory}/acquia_config.php")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    $result = $this->taskWriteToFile("{$this->repoRoot}/simplesamlphp/config/config.php")
      ->text("include 'acquia_config.php';")
      ->append()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    $this->say("Copying config files to {$this->repoRoot}/simplesamlphp/metadata.");
    $result = $this->taskFileSystemStack()
      ->copy("{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/metadata-templates/saml20-idp-remote.php", "{$this->repoRoot}/simplesamlphp/metadata/saml20-idp-remote.php")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    return $result;
  }

  /**
   * Copies customized config files into the library on deployments.
   *
   * @return \Robo\Result
   *
   * @command simplesamlphp:deploy:config
   */
  public function simpleSamlPhpDeployConfig() {
    $this->say('Copying config files to the appropriate place in simplesamlphp library in the deploy artifact.');
    $result = $this->taskCopyDir(["{$this->repoRoot}/simplesamlphp" => "{$this->repoRoot}/deploy/vendor/simplesamlphp/simplesamlphp"])
      ->overwrite(TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    $result = $this->taskFileSystemStack()
      ->copy("{$this->bltRoot}/scripts/simplesamlphp/gitignore.txt", "{$this->repoRoot}/deploy/vendor/simplesamlphp/simplesamlphp/.gitignore", TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    return $result;
  }

  /**
   * Sets a value in project.yml to let other targets know simplesamlphp is installed.
   *
   * @return \Robo\Result
   */
  protected function setSimpleSamlPhpInstalled() {
    $composerBin = $this->getConfigValue('composer.bin');
    $projectConfigFile = $this->getConfigValue('blt.config-files.project');
    $this->say("Updating ${projectConfigFile}...");
    $result = $this->taskExec("{$composerBin}/yaml-cli update:value")
      ->arg("${projectConfigFile}")
      ->arg('simplesamlphp')
      ->arg('TRUE')
      ->printOutput(TRUE)
      ->detectInteractive()
      ->dir($this->getConfigValue('repo.root'))
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    return $result;
  }

  /**
   * Checks if simplesamlphp has already been setup by BLT.
   *
   * @return bool
   */
  public function isSimpleSamlPhpInstalled() {
    return $this->getConfig()->has('simplesamlphp') && $this->getConfigValue('simplesamlphp');
  }

  /**
   * Creates a symlink from the docroot to the web accessible library dir.
   *
   * @return \Robo\Result
   */
  protected function symlinkDocrootToLibDir() {
    $docroot = $this->getConfigValue('docroot');

    $this->say("Creating a symbolic link from ${docroot}/simplesaml to web accessible directory in the simplesamlphp library");
    $result = $this->taskFileSystemStack()
      ->symlink("../vendor/simplesamlphp/simplesamlphp/www", "${docroot}/simplesaml")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    return $result;
  }

  /**
   * Copies customized config files into the library on builds.
   *
   * @return \Robo\Result
   *
   * @command simplesamlphp:build:config
   */
  public function simpleSamlPhpBuildConfig() {
    $this->say('Copying config files to the appropriate place in simplesamlphp library.');
    $result = $this->taskCopyDir(["{$this->repoRoot}/simplesamlphp" => "{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp"])
      ->overwrite(TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    $result = $this->taskFileSystemStack()
      ->copy("{$this->bltRoot}/scripts/simplesamlphp/gitignore.txt", "{$this->repoRoot}/vendor/simplesamlphp/simplesamlphp/.gitignore")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    return $result;
  }

  /**
   * Outputs a message to edit the new config files.
   */
  protected function simpleSamlPhpInitComplete() {
    $docroot = $this->getConfigValue('docroot');
    $this->say("


    ============================================================================
      To complete the setup you must manually modify several files.
    ============================================================================

      * ${docroot}/.htaccess
      * {$this->repoRoot}/simplesamlphp/config/acquia_config.php
      * {$this->repoRoot}/simplesamlphp/config/authsources.php
      * {$this->repoRoot}/simplesamlphp/metadata/saml20-idp-remote.php


      After editing these files execute the following command to copy the
      modified files to the correct location in the SimpleSAMLphp library

      'blt simplesamlphp:build:config'

    ============================================================================
      See http://blt.readthedocs.io/en/latest/readme/simplesamlphp-setup/
      for details on how to modify the files.
    ============================================================================



    ");
  }
}
