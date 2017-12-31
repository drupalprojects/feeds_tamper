<?php

namespace Drupal\feeds_tamper;

use Drupal\Core\Plugin\ObjectWithPluginCollectionInterface;
use Drupal\tamper\TamperInterface;

/**
 * Interface for managing tamper plugins for a feed type.
 */
interface FeedTypeTamperMetaInterface extends ObjectWithPluginCollectionInterface {

  /**
   * Returns a specific Tamper plugin.
   *
   * @param string $instance_id
   *   The tamper plugin instance ID.
   *
   * @return \Drupal\tamper\TamperInterface
   *   The tamper plugin instance.
   */
  public function getTamper($instance_id);

  /**
   * Returns the tamper plugin instances for this feed type.
   *
   * @return \Drupal\feeds_tamper\TamperPluginCollection|\Drupal\tamper\TamperInterface[]
   *   The tamper plugin collection.
   */
  public function getTampers();

  /**
   * Returns the tamper plugin instances for this feed type, keyed by source.
   *
   * @return \Drupal\tamper\TamperInterface[][]
   *   An associative array of plugin instances, keyed by source.
   */
  public function getTampersGroupedBySource();

  /**
   * Adds a tamper plugin instance for this feed type.
   *
   * @param array $configuration
   *   An array of tamper configuration.
   *
   * @return string
   *   The tamper plugin instance ID.
   */
  public function addTamper(array $configuration);

  /**
   * Removes a tamper plugin instance from this feed type.
   *
   * @param \Drupal\tamper\TamperInterface $tamper
   *   The tamper plugin instance
   *
   * @return $this
   */
  public function removeTamper(TamperInterface $tamper);

}
