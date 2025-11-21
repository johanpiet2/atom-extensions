<?php

declare(strict_types=1);

namespace AtomExtensions\Adapters;

use AtomExtensions\Contracts\EventDispatcherInterface;
use AtomExtensions\Contracts\EventInterface;

/**
 * Symfony event dispatcher adapter.
 *
 * Bridges to Symfony 1.4's event dispatcher.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class SymfonyEventDispatcher implements EventDispatcherInterface
{
    private \sfEventDispatcher $dispatcher;
    private array $wrappedListeners = [];

    public function __construct(\sfEventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        // Wrap the modern callable in a Symfony-compatible listener
        $wrappedListener = function (\sfEvent $sfEvent) use ($listener) {
            $event = new Event(
                $sfEvent->getName(),
                $sfEvent->getSubject(),
                $sfEvent->getParameters()
            );

            $listener($event);

            // Handle propagation stopping
            if ($event->isPropagationStopped() && method_exists($sfEvent, 'setReturnValue')) {
                $sfEvent->setReturnValue(true);
            }
        };

        $this->wrappedListeners[$eventName][] = [
            'original' => $listener,
            'wrapped' => $wrappedListener,
        ];

        $this->dispatcher->connect($eventName, $wrappedListener);
    }

    public function dispatch(string $eventName, array $payload = []): EventInterface
    {
        $subject = $payload['subject'] ?? null;
        unset($payload['subject']);

        $sfEvent = new \sfEvent($subject, $eventName, $payload);
        $this->dispatcher->notify($sfEvent);

        return new Event($eventName, $subject, $payload);
    }

    public function hasListeners(string $eventName): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        if (!isset($this->wrappedListeners[$eventName])) {
            return;
        }

        foreach ($this->wrappedListeners[$eventName] as $key => $item) {
            if ($item['original'] === $listener) {
                $this->dispatcher->disconnect($eventName, $item['wrapped']);
                unset($this->wrappedListeners[$eventName][$key]);
                break;
            }
        }
    }

    public function removeAllListeners(?string $eventName = null): void
    {
        if (null === $eventName) {
            foreach (array_keys($this->wrappedListeners) as $event) {
                $this->removeAllListeners($event);
            }

            return;
        }

        if (!isset($this->wrappedListeners[$eventName])) {
            return;
        }

        foreach ($this->wrappedListeners[$eventName] as $item) {
            $this->dispatcher->disconnect($eventName, $item['wrapped']);
        }

        unset($this->wrappedListeners[$eventName]);
    }
}

/**
 * Event implementation.
 */
class Event implements EventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        private string $name,
        private ?object $subject = null,
        private array $payload = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubject(): ?object
    {
        return $this->subject;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
