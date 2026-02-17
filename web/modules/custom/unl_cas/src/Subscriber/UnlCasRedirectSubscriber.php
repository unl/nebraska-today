<?php

namespace Drupal\unl_cas\Subscriber;

use Drupal\cas\Subscriber\CasLoginRedirectSubscriber;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens to KernelEvents::RESPONSE events.
 */
class UnlCasRedirectSubscriber extends CasLoginRedirectSubscriber {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
        KernelEvents::RESPONSE => [
          // Act before DomainRedirectResponseSubscriber::checkRedirectUrl() which has a
          // lower priority (10).
          // @see \Drupal\domain\EventSubscriber\DomainRedirectResponseSubscriber::checkRedirectUrl()
            ['onRedirectResponse', 20],
        ],
    ];
  }

}

