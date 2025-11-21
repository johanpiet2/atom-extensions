<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Event dispatcher interface.
 *
 * Provides event-driven communication between extensions and core.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
interface EventDispatcherInterface
{
    /**
     * Register an event listener.
     *
     * @param string   $eventName Event name to listen for
     * @param callable $listener  Callback function
     * @param int      $priority  Higher priority listeners execute first
     */
    public function listen(string $eventName, callable $listener, int $priority = 0): void;

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param string $eventName Event name
     * @param array  $payload   Event data
     *
     * @return EventInterface The dispatched event
     */
    public function dispatch(string $eventName, array $payload = []): EventInterface;

    /**
     * Check if an event has listeners.
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Remove a specific listener.
     *
     * @param string   $eventName Event name
     * @param callable $listener  The listener to remove
     */
    public function removeListener(string $eventName, callable $listener): void;

    /**
     * Remove all listeners for an event.
     *
     * @param string|null $eventName Event name, or null to remove all
     */
    public function removeAllListeners(?string $eventName = null): void;
}

/**
 * Event interface.
 *
 * Represents a dispatched event with payload data.
 */
interface EventInterface
{
    /**
     * Get the event name.
     */
    public function getName(): string;

    /**
     * Get the event subject (primary object).
     *
     * @return object|null Main object this event is about
     */
    public function getSubject(): ?object;

    /**
     * Get all event payload data.
     */
    public function getPayload(): array;

    /**
     * Get a specific payload value.
     *
     * @param string $key     Payload key
     * @param mixed  $default Default value if key not found
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if payload has a key.
     */
    public function has(string $key): bool;

    /**
     * Check if event propagation has been stopped.
     */
    public function isPropagationStopped(): bool;

    /**
     * Stop event propagation to remaining listeners.
     */
    public function stopPropagation(): void;
}
