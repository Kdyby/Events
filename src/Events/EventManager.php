<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events;

use Closure;
use Doctrine\Common\EventArgs as DoctrineEventArgs;
use Doctrine\Common\EventSubscriber;
use Kdyby\Events\Diagnostics\Panel;

/**
 * Registry of system-wide listeners that get's invoked, when the event, that they are listening to, is dispatched.
 */
class EventManager extends \Doctrine\Common\EventManager
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * [Event => [Priority => [[Listener, method], Subscriber, Subscriber, ...]]]
	 *
	 * @var array[]
	 */
	private $listeners = [];

	/**
	 * [Event => Subscriber|callable]
	 *
	 * @var \Doctrine\Common\EventSubscriber[][]|callable[][]
	 */
	private $sorted = [];

	/**
	 * [SubscriberHash => Subscriber]
	 *
	 * @var \Doctrine\Common\EventSubscriber[]
	 */
	private $subscribers = [];

	/**
	 * @var \Kdyby\Events\Diagnostics\Panel
	 */
	private $panel;

	/**
	 * @var \Kdyby\Events\IExceptionHandler
	 */
	private $exceptionHandler;

	/**
	 * @internal
	 * @param \Kdyby\Events\Diagnostics\Panel $panel
	 */
	public function setPanel(Panel $panel)
	{
		$this->panel = $panel;
	}

	/**
	 * @param \Kdyby\Events\IExceptionHandler $exceptionHandler
	 */
	public function setExceptionHandler(IExceptionHandler $exceptionHandler)
	{
		$this->exceptionHandler = $exceptionHandler;
	}

	/**
	 * Dispatches an event to all registered listeners.
	 *
	 * @param string $eventName The name of the event to dispatch. The name of the event is the name of the method that is invoked on listeners.
	 * @param \Doctrine\Common\EventArgs $eventArgs The event arguments to pass to the event handlers/listeners. If not supplied, the single empty EventArgs instance is used
	 */
	public function dispatchEvent($eventName, DoctrineEventArgs $eventArgs = NULL)
	{
		if ($this->panel) {
			$this->panel->eventDispatch($eventName, $eventArgs);
		}

		list($namespace, $event) = Event::parseName($eventName);
		foreach ($this->getListeners($eventName) as $listener) {
			try {
				if ($listener instanceof EventSubscriber) {
					$listener = [$listener, $event];
				}

				if ($eventArgs instanceof EventArgsList) {
					/** @var \Kdyby\Events\EventArgsList $eventArgs */
					call_user_func_array($listener, $eventArgs->getArgs());

				} else {
					call_user_func($listener, $eventArgs);
				}

			} catch (\Exception $e) {
				if ($this->exceptionHandler) {
					$this->exceptionHandler->handleException($e);
				} else {
					throw $e;
				}
			}
		}

		if ($this->panel) {
			$this->panel->eventDispatched($eventName, $eventArgs);
		}
	}

	/**
	 * Gets the listeners of a specific event or all listeners.
	 *
	 * @param string $eventName
	 * @return \Doctrine\Common\EventSubscriber[]|callable[]|\Doctrine\Common\EventSubscriber[][]|callable[][]
	 */
	public function getListeners($eventName = NULL)
	{
		if ($eventName !== NULL) {
			if (!isset($this->sorted[$eventName])) {
				$this->sortListeners($eventName);
			}

			return $this->sorted[$eventName];
		}

		foreach ($this->listeners as $event => $prioritized) {
			if (!isset($this->sorted[$event])) {
				$this->sortListeners($event);
			}
		}

		return array_filter($this->sorted);
	}

	/**
	 * Checks whether an event has any registered listeners.
	 *
	 * @param string $eventName
	 * @return bool TRUE if the specified event has any listeners, FALSE otherwise.
	 */
	public function hasListeners($eventName)
	{
		return (bool) count($this->getListeners($eventName));
	}

	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string|array $events The event(s) to listen on.
	 * @param \Doctrine\Common\EventSubscriber|\Closure|array $subscriber The listener object.
	 * @param int $priority
	 *
	 * @throws \Kdyby\Events\InvalidListenerException
	 */
	public function addEventListener($events, $subscriber, $priority = 0)
	{
		foreach ((array) $events as $eventName) {
			list($namespace, $event) = Event::parseName($eventName);

			if (!$subscriber instanceof Closure) {
				$callback = !is_array($subscriber) ? [$subscriber, $event] : $subscriber;
				if ($callback[0] instanceof CallableSubscriber) {
					if (!is_callable($callback)) {
						throw new \Kdyby\Events\InvalidListenerException(sprintf('Event listener "%s" is not callable.', $callback[0]));
					}

				} elseif (!method_exists($callback[0], $callback[1])) {
					throw new \Kdyby\Events\InvalidListenerException(sprintf('Event listener "%s" has no method "%s"', get_class($callback[0]), $callback[1]));
				}
			}

			$this->listeners[$eventName][$priority][] = $subscriber;
			unset($this->sorted[$eventName]);
		}
	}

	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param \Doctrine\Common\EventSubscriber|\Closure|array|string $unsubscribe
	 * @param \Doctrine\Common\EventSubscriber|\Closure|array $subscriber
	 */
	public function removeEventListener($unsubscribe, $subscriber = NULL)
	{
		if ($unsubscribe instanceof EventSubscriber) {
			list($unsubscribe, $subscriber) = $this->extractSubscriber($unsubscribe);
		} elseif ($unsubscribe instanceof Closure) {
			list($unsubscribe, $subscriber) = $this->extractCallable($unsubscribe);
		}

		foreach ((array) $unsubscribe as $eventName) {
			$eventName = ltrim($eventName, '\\');
			foreach ($this->listeners[$eventName] as $priority => $listeners) {
				$key = NULL;
				foreach ($listeners as $k => $listener) {
					if (!($listener === $subscriber || (is_array($listener) && $listener[0] === $subscriber))) {
						continue;
					}
					$key = $k;
					break;
				}

				if ($key === NULL) {
					continue;
				}

				unset($this->listeners[$eventName][$priority][$key]);
				if (empty($this->listeners[$eventName][$priority])) {
					unset($this->listeners[$eventName][$priority]);
				}
				if (empty($this->listeners[$eventName])) {
					unset($this->listeners[$eventName]);
					// there are no listeners for this specific event, so no reason to call sort on next dispatch
					$this->sorted[$eventName] = [];
				} else {
					// otherwise it needs to be sorted again
					unset($this->sorted[$eventName]);
				}
			}
		}
	}

	/**
	 * @param \Doctrine\Common\EventSubscriber $subscriber
	 * @return array
	 */
	protected function extractSubscriber(EventSubscriber $subscriber)
	{
		$unsubscribe = [];

		foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
			if ((is_array($params) && is_array($params[0])) || !is_numeric($eventName)) {
				// [EventName => [[method, priority], ...], ...]
				// [EventName => [method, priority], ...] && [EventName => method, .
				$unsubscribe[] = $eventName;

			} else { // [EventName, ...]
				$unsubscribe[] = $params;
			}
		}

		unset($this->subscribers[spl_object_hash($subscriber)]);

		return [$unsubscribe, $subscriber];
	}

	/**
	 * @param callable $subscriber
	 * @return array
	 */
	protected function extractCallable(callable $subscriber)
	{
		$unsubscribe = [];

		foreach ($this->listeners as $event => $prioritized) {
			foreach ($prioritized as $listeners) {
				foreach ($listeners as $listener) {
					if ($listener === $subscriber) {
						$unsubscribe[] = $event;
					}
				}
			}
		}

		return [$unsubscribe, $subscriber];
	}

	/**
	 * {@inheritdoc}
	 */
	public function addEventSubscriber(EventSubscriber $subscriber)
	{
		$hash = spl_object_hash($subscriber);
		if (isset($this->subscribers[$hash])) {
			return;
		}
		$this->subscribers[$hash] = $subscriber;

		foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
			if (is_numeric($eventName) && is_string($params)) { // [EventName, ...]
				$this->addEventListener($params, $subscriber);

			} elseif (is_string($eventName)) { // [EventName => ???, ...]
				if (is_string($params)) { // [EventName => method, ...]
					$this->addEventListener($eventName, [$subscriber, $params]);

				} elseif (is_string($params[0])) { // [EventName => [method, priority], ...]
					$this->addEventListener($eventName, [$subscriber, $params[0]], isset($params[1]) ? $params[1] : 0);

				} else {
					foreach ($params as $listener) { // [EventName => [[method, priority], ...], ...]
						$this->addEventListener($eventName, [$subscriber, $listener[0]], isset($listener[1]) ? $listener[1] : 0);
					}
				}
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function removeEventSubscriber(EventSubscriber $subscriber)
	{
		$this->removeEventListener($subscriber);
	}

	/**
	 * @param string|array $name
	 * @param array $defaults
	 * @param string $argsClass
	 * @param bool $globalDispatchFirst
	 * @return \Kdyby\Events\Event
	 */
	public function createEvent($name, $defaults = [], $argsClass = NULL, $globalDispatchFirst = FALSE)
	{
		$event = new Event($name, $defaults, $argsClass);
		$event->globalDispatchFirst = $globalDispatchFirst;
		$event->injectEventManager($this);

		if ($this->panel) {
			$this->panel->registerEvent($event);
		}

		return $event;
	}

	private function sortListeners($eventName)
	{
		$this->sorted[$eventName] = [];

		$available = [];
		list($namespace, $event, $separator) = Event::parseName($eventName);
		$className = $namespace;
		do {
			$key = ($className ? $className . $separator : '') . $event;
			if (empty($this->listeners[$key])) {
				continue;
			}

			$available = $available + array_fill_keys(array_keys($this->listeners[$key]), []);
			foreach ($this->listeners[$key] as $priority => $listeners) {
				foreach ($listeners as $listener) {
					if ($className === $namespace && in_array($listener, $available[$priority], TRUE)) {
						continue;
					}

					$available[$priority][] = $listener;
				}
			}

		} while ($className && class_exists($className) && ($className = get_parent_class($className)));

		if (empty($available)) {
			return;
		}

		krsort($available); // [priority => [[listener, ...], ...]
		$sorted = call_user_func_array('array_merge', $available);

		$this->sorted[$eventName] = array_map(function ($callable) use ($event) {
			if ($callable instanceof EventSubscriber) {
				return $callable;
			}

			if (is_object($callable) && method_exists($callable, $event)) {
				$callable = [$callable, $event];
			}

			return $callable;
		}, $sorted); // [callback, ...]
	}

}
