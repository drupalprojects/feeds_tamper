<?php

namespace Drupal\Tests\feeds_tamper\Functional;

use Drupal\Tests\feeds\Functional\FeedsBrowserTestBase;

/**
 * Provides a base class for Feeds Tamper functional tests.
 */
abstract class FeedsTamperBrowserTestBase extends FeedsBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'feeds',
    'feeds_tamper',
    'node',
    'user',
  ];

}
