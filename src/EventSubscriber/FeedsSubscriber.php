<?php

namespace Drupal\feeds_tamper\EventSubscriber;

use Drupal\feeds\Event\FeedsEvents;
use Drupal\feeds\Event\ParseEvent;
use Drupal\feeds_tamper\FeedTypeTamperManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber to Feeds events.
 *
 * This is where Tamper plugins are applied to the Feeds parser result, which
 * will modify the feed items. This happens after parsing and before going
 * into processing.
 */
class FeedsSubscriber implements EventSubscriberInterface {

  /**
   * A feed type meta object.
   *
   * @var \Drupal\feeds_tamper\FeedTypeTamperManagerInterface
   */
  protected $tamperManager;

  /**
   * Constructs a new FeedsSubscriber object.
   *
   * @param \Drupal\feeds_tamper\FeedTypeTamperManagerInterface
   *   A feed type meta object.
   */
  public function __construct(FeedTypeTamperManagerInterface $tamper_manager) {
    $this->tamperManager = $tamper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[FeedsEvents::PARSE][] = ['afterParse', FeedsEvents::AFTER];
    return $events;
  }

  /**
   * Acts on parser result.
   */
  public function afterParse(ParseEvent $event) {
    /** @var \Drupal\feeds\FeedInterface $feed */
    $feed = $event->getFeed();
    /** @var \Drupal\feeds\Result\ParserResultInterface $parser_result */
    $parser_result = $event->getParserResult();

    /** @var \Drupal\feeds_tamper\FeedTypeTamperMetaInterface $tamper_meta */
    $tamper_meta = $this->tamperManager->getTamperMeta($feed->getType());

    // Load the tamper plugins that need to be applied to Feeds.
    $tampers_by_source = $tamper_meta->getTampersGroupedBySource();

    // Abort if there are no tampers to apply on the current feed.
    if (empty($tampers_by_source)) {
      return;
    }

    /** @var \Drupal\feeds\Feeds\Item\ItemInterface $item */
    foreach ($parser_result as $item) {
      foreach ($tampers_by_source as $source => $tampers) {
        // Get the value for a source.
        $item_value = $item->get($source);

        /** @var Drupal\tamper\TamperInterface $tamper */
        foreach ($tampers as $tamper) {
          // @todo if the item was unset by the previous plugin, jump ahead.
          if (!isset($item)) {
            break 2;
          }

          // Array-ness can change depending on what the plugin is doing.
          $is_array = is_array($item_value);

          // Hard-coded.
          // @todo replace later.
          $plugin = [
            'multi' => 'direct',
            'single' => NULL,
          ];

          if ($is_array && $plugin['multi'] === 'loop') {
            foreach ($item_value as &$i) {
              $i = $tamper->tamper($i);
            }
          }
          else {
            $item_value = $tamper->tamper($item_value);
          }
        }

        // Write the changed value.
        $item->set($source, $item_value);
      }
    }
  }

}
