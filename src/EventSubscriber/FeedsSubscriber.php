<?php

namespace Drupal\feeds_tamper\EventSubscriber;

use Drupal\feeds\Event\FeedsEvents;
use Drupal\feeds\Event\ParseEvent;
use Drupal\feeds_tamper\Adapter\TamperableFeedItemAdapter;
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
   * @param \Drupal\feeds_tamper\FeedTypeTamperManagerInterface $tamper_manager
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
      $tamperable_item = new TamperableFeedItemAdapter($item);
      foreach ($tampers_by_source as $source => $tampers) {
        // Get the value for a source.
        $item_value = $item->get($source);
        $multiple = is_array($item_value);

        /** @var \Drupal\tamper\TamperInterface $tamper */
        foreach ($tampers as $tamper) {
          // @todo if the item was unset by the previous plugin, jump ahead.
          if (!isset($item)) {
            break 2;
          }

          $definition = $tamper->getPluginDefinition();
          // Many plugins expect a scalar value but the current value of the
          // pipeline might be multiple scalars and in this case the current
          // value needs to be iterated and each scalar separately transformed.
          if ($multiple && !$definition['handle_multiples']) {
            $new_value = [];
            // @todo throw exception if $item_value is not an array.
            foreach ($item_value as $scalar_value) {
              $new_value[] = $tamper->tamper($scalar_value, $tamperable_item);
            }
            $item_value = $new_value;
          }
          else {
            $item_value = $tamper->tamper($item_value, $tamperable_item);
            $multiple = $tamper->multiple();
          }
        }

        // Write the changed value.
        $item->set($source, $item_value);
      }
    }
  }

}
