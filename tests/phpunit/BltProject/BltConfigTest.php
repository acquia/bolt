<?php

namespace Acquia\Blt\Tests\Blt;

use Acquia\Blt\Tests\BltProjectTestBase;

/**
 * Class BltConfigTest.
 */
class BltConfigTest extends BltProjectTestBase {

  /**
   * Tests config:dump command.
   */
  public function testPipelinesInit() {
    list($status_code, $output) = $this->blt('config:dump', []);
    list($status_code, $output) = $this->blt('config:dump', [
      '--site' => 'site2',
    ]);
    $this->assertContains('site2', $output);
    list($status_code, $output) = $this->blt('config:dump', [
      '--environment' => 'local',
    ]);
    list($status_code, $output) = $this->blt('config:dump', [
      '--environment' => 'ci',
    ]);
  }

}
