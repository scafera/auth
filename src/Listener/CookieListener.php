<?php

declare(strict_types=1);

namespace Scafera\Auth\Listener;

use Scafera\Auth\CookieJar;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal Applies pending cookies from the CookieJar to the Symfony response.
 */
final class CookieListener implements EventSubscriberInterface
{
    public function __construct(private readonly CookieJar $cookieJar) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse', 0]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        foreach ($this->cookieJar->consumePending() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }
}
