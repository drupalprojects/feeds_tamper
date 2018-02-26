<?php

namespace Drupal\feeds_tamper\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Trait TamperFormTrait.
 *
 * Provides helper methods for the Tamper forms.
 *
 * @package Drupal\feeds_tamper\Form
 */
trait TamperFormTrait {

  /**
   * Checks access for the form page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(RouteMatchInterface $route_match, AccountInterface $account) {
    if ($account->hasPermission('administer feeds_tamper')) {
      return AccessResult::allowed();
    }

    /** @var \Drupal\feeds\Entity\FeedType $feed_type */
    $feed_type = $route_match->getParameter('feeds_feed_type');
    return AccessResult::allowedIf($account->hasPermission('tamper ' . $feed_type->id()))
      ->addCacheableDependency($feed_type);
  }

}
