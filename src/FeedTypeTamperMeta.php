<?php

namespace Drupal\feeds_tamper;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\tamper\TamperInterface;
use Drupal\tamper\TamperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for managing tamper plugins for a feed type.
 */
class FeedTypeTamperMeta implements FeedTypeTamperMetaInterface {

  /**
   * The Uuid generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The Tamper plugin manager.
   *
   * @var \Drupal\tamper\TamperManagerInterface
   */
  protected $tamperManager;

  /**
   * The feed type to manage tamper plugins for.
   *
   * @var \Drupal\feeds\Entity\FeedType
   */
  protected $feedType;

  /**
   * Holds the collection of tampers that are used by the feed type.
   *
   * @var \Drupal\feeds_tamper\TamperPluginCollection
   */
  protected $tamperCollection;

  /**
   * FeedTypeTamperMeta object constructor.
   *
   * @param \Drupal\feeds\FeedTypeInterface
   *   The feed type to manage tamper plugins for.
   */
  public function __construct(UuidInterface $uuid_generator, TamperManagerInterface $tamper_manager, FeedTypeInterface $feed_type) {
    $this->uuidGenerator = $uuid_generator;
    $this->tamperManager = $tamper_manager;
    $this->feedType = $feed_type;
  }

  /**
   * Creates a new instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \Drupal\feeds\FeedTypeInterface
   *   The feed type to manage tamper plugins for.
   *
   * @return static
   *   A new FeedTypeTamperMeta instance.
   */
  public static function create(ContainerInterface $container, FeedTypeInterface $feed_type) {
    return new static(
      $container->get('uuid'),
      $container->get('plugin.manager.tamper'),
      $feed_type
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTamper($instance_id) {
    return $this->getTampers()->get($instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTampers() {
    if (!isset($this->tamperCollection)) {
      $tampers = $this->feedType->getThirdPartySetting('feeds_tamper', 'tampers');
      $tampers = empty($tampers) ? [] : $tampers;
      $this->tamperCollection = new TamperPluginCollection($this->tamperManager, $tampers);
      $this->tamperCollection->sort();
    }
    return $this->tamperCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getTampersGroupedBySource() {
    $grouped_tampers = [];
    $tampers = $this->getTampers();
    $dit = $this;
    foreach ($this->getTampers() as $id => $tamper) {
      $grouped_tampers[(string) $tamper->getSetting('source')][$id] = $tamper;
    }
    return $grouped_tampers;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['tampers' => $this->getTampers()];
  }

  /**
   * {@inheritdoc}
   */
  public function addTamper(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator->generate();
    $this->getTampers()->addInstanceId($configuration['uuid'], $configuration);
    $this->updateFeedType();
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateTamper(TamperInterface $tamper, array $configuration) {
    $configuration['uuid'] = $tamper->getSetting('uuid');
    $this->getTampers()->setInstanceConfiguration($tamper->getSetting('uuid'), $configuration);
    $this->updateFeedType();
    return $tamper;
  }

  /**
   * {@inheritdoc}
   */
  public function removeTamper(TamperInterface $tamper) {
    $this->getTampers()->removeInstanceId($tamper->getSetting('uuid'));
    $this->updateFeedType();
    $this->feedType->save();
    return $this;
  }

  /**
   * Writes tampers back on the feed type.
   */
  protected function updateFeedType() {
    foreach ($this->getPluginCollections() as $plugin_config_key => $plugin_collection) {
      $this->feedType->setThirdPartySetting('feeds_tamper', $plugin_config_key, $plugin_collection->getConfiguration());
    }
  }

}
