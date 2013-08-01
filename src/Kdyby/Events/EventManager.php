<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events;

use Doctrine;
use Doctrine\Common\EventSubscriber;
use Kdyby;
use Nette;
use Nette\Utils\Arrays;



/**
 * Registry of system-wide listeners that get's invoked, when the event, that they are listening to, is dispatched.
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventManager extends Doctrine\Common\EventManager
{

	/**
	 * [Event => [Namespace => [Priority => [[Subscriber, method], ...]]]]
	 *
	 * @var array[]
	 */
	private $listeners = array();

	/**
	 * [Event => [Namespace => [callable]]]
	 *
	 * @var array[]
	 */
	private $sorted = array();

	/**
	 * [SubscriberHash => Subscriber]
	 *
	 * @var array[]
	 */
	private $subscribers = array();

	/**
	 * @var Diagnostics\Panel
	 */
	private $panel;



	/**
	 * @internal
	 * @param Diagnostics\Panel $panel
	 */
	public function setPanel(Diagnostics\Panel $panel)
	{
		$this->panel = $panel;
	}



	/**
	 * Dispatches an event to all registered listeners.
	 *
	 * @param string $eventName The name of the event to dispatch. The name of the event is the name of the method that is invoked on listeners.
	 * @param Doctrine\Common\EventArgs $eventArgs The event arguments to pass to the event handlers/listeners. If not supplied, the single empty EventArgs instance is used
	 */
	public function dispatchEvent($eventName, Doctrine\Common\EventArgs $eventArgs = NULL)
	{
		if ($this->panel) {
			$this->panel->eventDispatch($eventName, $eventArgs);
		}

		foreach ($this->getListeners($eventName, TRUE) as $listener) {
			if ($eventArgs instanceof EventArgsList) {
				/** @var EventArgsList $eventArgs */
				$listener->invokeArgs($eventArgs->getArgs());

			} else {
				$listener->invoke($eventArgs);
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
	 * @param bool $asCallbacks
	 * @return Doctrine\Common\EventSubscriber[]|Nette\Callback[]
	 */
	public function getListeners($eventName = NULL, $asCallbacks = FALSE)
	{
		if ($eventName !== NULL) {
			if (!isset($this->sorted[$eventName])) {
				$this->sortListeners($eventName);
			}

			return $asCallbacks ? $this->sorted[$eventName] : self::uniqueSubscribers($this->sorted[$eventName]);
		}

		foreach (array_keys($this->listeners) as $eventName) { // iterate without namespace
			if (!isset($this->sorted[$eventName])) {
				$this->sortListeners($eventName);
			}
		}

		return $asCallbacks ? Arrays::flatten($this->sorted) : self::uniqueSubscribers($this->sorted);
	}



	/**
	 * Checks whether an event has any registered listeners.
	 *
	 * @param string $eventName
	 * @return boolean TRUE if the specified event has any listeners, FALSE otherwise.
	 */
	public function hasListeners($eventName)
	{
		return (bool) count($this->getListeners($eventName));
	}



	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string|array $events The event(s) to listen on.
	 * @param Doctrine\Common\EventSubscriber|array $subscriber The listener object.
	 * @param int $priority
	 *
	 * @throws InvalidListenerException
	 */
	public function addEventListener($events, $subscriber, $priority = 0)
	{
		foreach ((array) $events as $eventName) {
			list($namespace, $event) = Event::parseName($eventName);
			$listener = !is_array($subscriber) ? array($subscriber, $event) : $subscriber;

			if (!method_exists($listener[0], $listener[1])) {
				throw new InvalidListenerException("Event listener '" . get_class($listener[0]) . "' has no method '" . $listener[1] . "'");
			}

			$this->listeners[$event][NULL][$priority][] = $listener;
			if ($namespace !== NULL) {
				$this->listeners[$event][$namespace][$priority][] = $listener;
			}

			unset($this->sorted[$event]);
			unset($this->sorted[$eventName]);
		}
	}



	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string|array $unsubscribe
	 * @param Doctrine\Common\EventSubscriber|array $subscriber
	 */
	public function removeEventListener($unsubscribe, $subscriber = NULL)
	{
		if ($unsubscribe instanceof EventSubscriber) {
			$this->removeEventSubscriber($unsubscribe);
			return;
		}

		foreach ((array) $unsubscribe as $eventName) {
			list($namespace, $event) = Event::parseName($eventName);
			$listener = !is_array($subscriber) ? array($subscriber, $event) : $subscriber;

			foreach ($this->listeners[$event] as $namespaces => $priorities) {
				foreach ($priorities as $priority => $listeners) {
					if (($key = array_search($listener, $listeners, TRUE)) !== FALSE) {
						unset($this->listeners[$event][$namespaces][$priority][$key], $this->sorted[$eventName]);
					}
				}
			}
		}
	}



	public function addEventSubscriber(EventSubscriber $subscriber)
	{
		if (isset($this->subscribers[$hash = spl_object_hash($subscriber)])) {
			return;
		}
		$this->subscribers[$hash] = $subscriber;

		foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
			if (is_numeric($eventName) && is_string($params)) { // [EventName, ...]
				$this->addEventListener($params, $subscriber);

			} elseif (is_string($eventName)) { // [EventName => ???, ...]
				if (is_string($params)) { // [EventName => method, ...]
					$this->addEventListener($eventName, array($subscriber, $params));

				} elseif (is_string($params[0])) { // [EventName => [method, priority], ...]
					$this->addEventListener($eventName, array($subscriber, $params[0]), isset($params[1]) ? $params[1] : 0);

				} else {
					foreach ($params as $listener) { // [EventName => [[method, priority], ...], ...]
						$this->addEventListener($eventName, array($subscriber, $listener[0]), isset($listener[1]) ? $listener[1] : 0);
					}
				}
			}
		}
	}



	public function removeEventSubscriber(EventSubscriber $subscriber)
	{
		foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
			if (is_array($params) && is_array($params[0])) { // [EventName => [[method, priority], ...], ...]
				foreach ($params as $listener) {
					$this->removeEventListener($eventName, array($subscriber, $listener[0]));
				}

			} elseif (!is_numeric($eventName)) { // [EventName => [method, priority], ...] && [EventName => method, ...]
				$this->removeEventListener($eventName, array($subscriber, is_string($params) ? $params : $params[0]));

			} else { // [EventName, ...]
				$this->removeEventListener($params, array($subscriber, $params));
			}
		}

		unset($this->subscribers[spl_object_hash($subscriber)]);
	}



	/**
	 * @param string|array $name
	 * @param array $defaults
	 * @param string $argsClass
	 * @return Event
	 */
	public function createEvent($name, $defaults = array(), $argsClass = NULL)
	{
		$event = new Event($name, $defaults, $argsClass);
		$event->injectEventManager($this);

		if ($this->panel) {
			$this->panel->registerEvent($event);
		}

		return $event;
	}



	private function sortListeners($eventName)
	{
		$this->sorted[$eventName] = array();

		list($namespace, $event) = Event::parseName($eventName);
		if (!isset($this->listeners[$event])) {
			return;
		}

		if ($namespace === NULL) {
			$available = array();
			foreach ($this->listeners[$event] as $namespaced) {
				$available += array_fill_keys(array_keys($namespaced), array());
				foreach ($namespaced as $priority => $listeners) {
					foreach ($listeners as $listener) {
						if (!in_array($listener, $available[$priority], TRUE)) {
							$available[$priority][] = $listener;
						}
					}
				}
			}

		} else {
			$available = !empty($this->listeners[$event][$namespace]) ? $this->listeners[$event][$namespace] : array();
		}

		if (empty($available)) {
			return;
		}

		krsort($available); // [priority => [[listener, method], ...], ...]
		$sorted = call_user_func_array('array_merge', $available); // [[listener, method], ...]
		$this->sorted[$eventName] = array_map('callback', $sorted); // [callback, ...]
	}



	/**
	 * @param array $array
	 * @return array
	 */
	private static function uniqueSubscribers(array $array)
	{
		$res = array();
		array_walk_recursive($array, function ($a) use (& $res) {
			if (!$a instanceof Nette\Callback) {
				return;
			}

			if (!in_array($a = $a->native[0], $res, TRUE)) {
				$res[] = $a;
			}
		});

		return $res;
	}



	/*************************** Nette\Object ***************************/



	/**
	 * Access to reflection.
	 * @return \Nette\Reflection\ClassType
	 */
	public static function getReflection()
	{
		return new Nette\Reflection\ClassType(get_called_class());
	}



	/**
	 * Call to undefined method.
	 *
	 * @param string $name
	 * @param array $args
	 *
	 * @throws \Nette\MemberAccessException
	 * @return mixed
	 */
	public function __call($name, $args)
	{
		return Nette\ObjectMixin::call($this, $name, $args);
	}



	/**
	 * Call to undefined static method.
	 *
	 * @param string $name
	 * @param array $args
	 *
	 * @throws \Nette\MemberAccessException
	 * @return mixed
	 */
	public static function __callStatic($name, $args)
	{
		Nette\ObjectMixin::callStatic(get_called_class(), $name, $args);
	}



	/**
	 * Adding method to class.
	 *
	 * @param $name
	 * @param null $callback
	 *
	 * @throws \Nette\MemberAccessException
	 * @return callable|null
	 */
	public static function extensionMethod($name, $callback = NULL)
	{
		if (strpos($name, '::') === FALSE) {
			$class = get_called_class();
		} else {
			list($class, $name) = explode('::', $name);
		}
		if ($callback === NULL) {
			return Nette\ObjectMixin::getExtensionMethod($class, $name);
		} else {
			Nette\ObjectMixin::setExtensionMethod($class, $name, $callback);
		}
	}



	/**
	 * Returns property value. Do not call directly.
	 *
	 * @param string $name
	 *
	 * @throws \Nette\MemberAccessException
	 * @return mixed
	 */
	public function &__get($name)
	{
		return Nette\ObjectMixin::get($this, $name);
	}



	/**
	 * Sets value of a property. Do not call directly.
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @throws \Nette\MemberAccessException
	 * @return void
	 */
	public function __set($name, $value)
	{
		Nette\ObjectMixin::set($this, $name, $value);
	}



	/**
	 * Is property defined?
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function __isset($name)
	{
		return Nette\ObjectMixin::has($this, $name);
	}



	/**
	 * Access to undeclared property.
	 *
	 * @param string $name
	 *
	 * @throws \Nette\MemberAccessException
	 * @return void
	 */
	public function __unset($name)
	{
		Nette\ObjectMixin::remove($this, $name);
	}

}
