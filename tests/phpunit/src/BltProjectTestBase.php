<?php

namespace Acquia\Blt\Tests;

use Symfony\Component\Yaml\Yaml;

/**
 * Class BltProjectTestBase.
 *
 * Base class for all tests that are executed within a blt project.
 */
abstract class BltProjectTestBase extends \PHPUnit_Framework_TestCase {

  protected $projectDirectory;
  protected $drupalRoot;
  protected $sites = [];
  protected $config = [];

  /**
   * BltProjectTestBase constructor.
   *
   * @inheritdoc
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    parent::__construct($name, $data, $dataName);

    // We must consider that this package may be symlinked into its location.
    $repo_root_locations = [
      dirname($_SERVER['SCRIPT_FILENAME']) . '/../..',
      getcwd(),
    ];

    foreach ($repo_root_locations as $location) {
      if (file_exists($location . '/vendor/bin/blt')
        && file_exists($location . '/composer.json')) {
        if ($path = realpath($location)) {
          $this->projectDirectory = $path;
        }
        else {
          $this->projectDirectory = $location;
        }
        break;
      }
    }

    if (empty($this->projectDirectory)) {
      throw new \Exception("Could not find project root directory!");
    }

    $this->drupalRoot = $this->projectDirectory . '/docroot';
    if (file_exists("{$this->projectDirectory}/blt/project.yml")) {
      $this->config = Yaml::parse(file_get_contents("{$this->projectDirectory}/blt/project.yml"));
    }
    else {
      throw new \Exception("Could not find project.yml!");
    }
    if (file_exists("{$this->projectDirectory}/blt/project.local.yml")) {
      $this->config = array_replace_recursive($this->config, (array) Yaml::parse(file_get_contents("{$this->projectDirectory}/blt/project.local.yml")));
    }

    // Build sites list.
    $sites = [];
    $re_site_config = '/project\.(.*)\.yml/';
    $sites_config_dir = "$this->projectDirectory/blt/sites";
    passthru("ls $this->projectDirectory");
    passthru("ls $this->projectDirectory/blt");
    passthru("ls $sites_config_dir");
    if (is_dir($sites_config_dir)) {
      foreach (scandir($sites_config_dir) as $config) {
        $match = [];
        if (preg_match($re_site_config, $config, $match)) {
          $sites[] = $match[1];
        }
      }
    }

    // Ensure there is at least a default site,
    // which requires no specific configuration file.
    if (empty($sites)) {
      $sites[] = 'default';
    }

    echo $this->projectDirectory . PHP_EOL;

    $this->sites = $sites;
  }

}
