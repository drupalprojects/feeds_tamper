<?php

namespace Drupal\Tests\feeds_tamper\Unit\EventSubscriber;

use Drupal\feeds\Event\ParseEvent;
use Drupal\feeds\Feeds\Item\DynamicItem;
use Drupal\feeds\Result\FetcherResultInterface;
use Drupal\feeds\Result\ParserResult;
use Drupal\feeds_tamper\EventSubscriber\FeedsSubscriber;
use Drupal\feeds_tamper\FeedTypeTamperManagerInterface;
use Drupal\feeds_tamper\FeedTypeTamperMetaInterface;
use Drupal\tamper\TamperInterface;
use Drupal\Tests\feeds_tamper\Unit\FeedsTamperTestCase;

/**
 * @coversDefaultClass \Drupal\feeds_tamper\EventSubscriber\FeedsSubscriber
 * @group feeds_tamper
 */
class FeedsSubscriberTest extends FeedsTamperTestCase {

  /**
   * The subscriber under test.
   *
   * @var \Drupal\feeds_tamper\EventSubscriber\FeedsSubscriber
   */
  protected $subscriber;

  /**
   * The parse event.
   *
   * @var \Drupal\feeds\Event\ParseEvent
   */
  protected $event;

  /**
   * The tamper meta.
   */
  protected $tamperMeta;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create parse event.
    $this->event = new ParseEvent($this->getMockFeed(), $this->getMock(FetcherResultInterface::class));
    $this->event->setParserResult(new ParserResult());

    // Create tamper meta.
    $this->tamperMeta = $this->getMock(FeedTypeTamperMetaInterface::class);

    // Create feed type tamper manager.
    $tamper_manager = $this->getMock(FeedTypeTamperManagerInterface::class);
    $tamper_manager->expects($this->any())
      ->method('getTamperMeta')
      ->will($this->returnValue($this->tamperMeta));

    // And finally, create the subscriber to test.
    $this->subscriber = new FeedsSubscriber($tamper_manager);
  }

  /**
   * Creates a tamper mock with a return value for the tamper() method.
   *
   * @param mixed $return_value
   *   (optional) The value that the tamper plugin must return when tamper() gets
   *   called on it.
   *
   * @return \Drupal\tamper\TamperInterface
   *   A mocked tamper plugin.
   */
  protected function createTamperMock($return_value = NULL) {
    $tamper = $this->getMock(TamperInterface::class);
    $tamper->expects($this->any())
      ->method('tamper')
      ->will($this->returnValue($return_value));

    return $tamper;
  }

  /**
   * @covers ::afterParse
   */
  public function testAfterParse() {
    $tamper = $this->getMock(TamperInterface::class);
    $tamper->expects($this->any())
      ->method('tamper')
      ->will($this->returnValue('Foo'));

    $this->tamperMeta->expects($this->once())
      ->method('getTampersGroupedBySource')
      ->will($this->returnValue([
        'alpha' => [
          $this->createTamperMock('Foo'),
        ],
      ]));

    // Add an item to the parser result.
    $item = new DynamicItem();
    $item->set('alpha', 'Bar');
    $this->event->getParserResult()->addItem($item);

    $this->subscriber->afterParse($this->event);
    $this->assertEquals('Foo', $item->get('alpha'));
  }

  /**
   * @covers ::afterParse
   */
  public function testAfterParseWithNoItems() {
    $this->tamperMeta->expects($this->once())
      ->method('getTampersGroupedBySource')
      ->will($this->returnValue([
        'alpha' => [
          $this->createTamperMock('Foo'),
        ],
      ]));

    $this->subscriber->afterParse($this->event);
  }

  /**
   * @covers ::afterParse
   */
  public function testAfterParseWithNoTampers() {
    $this->tamperMeta->expects($this->once())
      ->method('getTampersGroupedBySource')
      ->will($this->returnValue([]));

    // Add an item to the parser result.
    $item = new DynamicItem();
    $item->set('alpha', 'Bar');
    $this->event->getParserResult()->addItem($item);

    // Run event callback.
    $this->subscriber->afterParse($this->event);
    $this->assertEquals('Bar', $item->get('alpha'));
  }

}
