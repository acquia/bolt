<?php

namespace Acquia\Blt\Tests\BltProject;

use Acquia\Blt\Tests\BltProjectTestBase;

/**
 * Class ToggleModulesTest.
 *
 * Verifies that setup:toggle-modules behaves as expected.
 */
class ToggleModulesTest extends BltProjectTestBase {

  /**
   * Verifies the modules for a given environment were enabled as expected.
   *
   * In the event no environment is specified, this test will be skipped.
   *
   * @group blt-project
   */
  public function testModulesEnabled() {
    global $_blt_env;
    if (isset($_blt_env)) {
      $modules = $this->config['modules'][$_blt_env]['enable'];
      foreach ($modules as $module) {
        $this->assertModuleEnabled($module);
      }
    }
    else {
      $this->markTestSkipped('No BLT environment provided.');
    }
  }

  /**
   * Verifies the modules for a given environment were disabled or not found.
   *
   * In the event no environment is specified, this test will be skipped.
   *
   * @group blt-project
   */
  public function testModulesNotEnabled() {
    global $_blt_env;
    if (isset($_blt_env)) {
      $modules = $this->config['modules'][$_blt_env]['uninstall'];
      foreach ($modules as $module) {
        $this->assertModuleNotEnabled($module);
      }
    }
    else {
      $this->markTestSkipped('No BLT environment provided.');
    }
  }

  /**
   * Asserts that a module is not enabled.
   *
   * @param string $module
   *    The module to test.
   * @param string $alias
   *    An optional Drush alias string.
   */
  protected function assertModuleNotEnabled($module, $alias = '') {
    $enabled = $this->getModuleEnabledStatus($module, $alias);
    $this->assertFalse($enabled,
      "Expected $module to be either 'disabled,' 'not installed' or 'not found.'"
    );
  }

  /**
   * Asserts that a module is enabled.
   *
   * @param string $module
   *    The module to test.
   * @param string $alias
   *    An optional Drush alias string.
   */
  protected function assertModuleEnabled($module, $alias = '') {
    $enabled = $this->getModuleEnabledStatus($module, $alias);
    $this->assertTrue($enabled, "Expected $module to be enabled.");
  }

  /**
   * Gets a module's enabled status.
   *
   * @param string $module
   *    The module to test.
   * @param string $alias
   *    An optional Drush alias string.
   *
   * @throws \PHPUnit_Framework_MockObject_Stub_Exception
   *    If a module's status string cannot be parsed.
   *
   * @return bool
   *    TRUE if $module is enabled, FALSE if a module is either 'disabled,'
   *    'not installed' or 'not found.'
   */
  private function getModuleEnabledStatus($module, $alias = '') {
    $output = [];
    $enabled = FALSE;
    $drush_bin = $this->projectDirectory . '/vendor/bin/drush';

    // Use the project's default alias if no other alias is provided.
    $alias = !empty($alias) ? $alias : $this->config['drush']['default_alias'];

    // Get module status, it will be on the first line of output.
    exec("$drush_bin @$alias pmi $module --fields=status", $output);
    $status = $output[0];

    // Parse status strings, throw if parsing fails.
    if (preg_match('/enabled/', $status)) {
      $enabled = TRUE;
    }
    elseif (preg_match('/(?:disabled|not\sinstalled|not\sfound)/', $status)) {
      $enabled = FALSE;
    }
    else {
      $this->throwException(new \Exception("Unable to parse $module's status: $status'"));
    }

    // Return the module's true/false enabled status.
    return $enabled;
  }

}
