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
use Kdyby;
use Nette;
use Nette\Utils\Arrays;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventManager extends Doctrine\Common\EventManager
{

	/**
	 * @var array|array[]|object[]
	 */
	private $listeners = array();



	/**
	 * Dispatches an event to all registered listeners.
	 *
	 * @param string $eventName The name of the event to dispatch. The name of the event is the name of the method that is invoked on listeners.
	 * @param Doctrine\Common\EventArgs $eventArgs The event arguments to pass to the event handlers/listeners. If not supplied, the single empty EventArgs instance is used
	 */
	public function dispatchEvent($eventName, Doctrine\Common\EventArgs $eventArgs = NULL)
	{
		list(, $event) = Kdyby\Events\Event::parseName($eventName);
		foreach ($this->getListeners($eventName) as $listener) {
			$cb = callback($listener, $event);
			if ($eventArgs instanceof EventArgsList) {
				/** @var EventArgsList $eventArgs */
				$cb->invokeArgs($eventArgs->getArgs());

			} else {
				$cb->invoke($eventArgs);
			}
		}
	}



	/**
	 * Gets the listeners of a specific event or all listeners.
	 *
	 * @param string $eventName
	 * @return Doctrine\Common\EventSubscriber[]
	 */
	public function getListeners($eventName = NULL)
	{
		if ($eventName === NULL) {
			return self::unique($this->listeners);
		}

		list($namespace, $event) = Kdyby\Events\Event::parseName($eventName);

		if ($namespace === NULL) {
			if (!isset($this->listeners[$event])) {
				return array();
			}

			return self::unique($this->listeners[$event]);
		}

		if (!isset($this->listeners[$event][$namespace])) {
			return array();
		}

		return $this->listeners[$event][$namespace];
	}



	/**
	 * Checks whether an event has any registered listeners.
	 *
	 * @param string $eventName
	 * @return boolean TRUE if the specified event has any listeners, FALSE otherwise.
	 */
	public function hasListeners($eventName)
	{
		return (bool) $this->getListeners($eventName);
	}



	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string|array $events The event(s) to listen on.
	 * @param Doctrine\Common\EventSubscriber $listener The listener object.
	 *
	 * @throws InvalidListenerException
	 */
	public function addEventListener($events, $listener)
	{
		$hash = spl_object_hash($listener);
		foreach ((array) $events as $eventName) {
			list($namespace, $event) = Kdyby\Events\Event::parseName($eventName);
			if (!method_exists($listener, $event)) {
				throw new InvalidListenerException("Event listener '" . get_class($listener) . "' has no method '" . $event . "'");
			}

			$this->listeners[$event][][$hash] = $listener;
			if ($namespace !== NULL) {
				$this->listeners[$event][$namespace][$hash] = $listener;
			}
		}
	}



	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string|array $unsubscribe
	 * @param Doctrine\Common\EventSubscriber $listener
	 */
	public function removeEventListener($unsubscribe, $listener = NULL)
	{
		if (is_object($unsubscribe)) {
			$listener = $unsubscribe;
			$unsubscribe = array();
		}

		$events = array();
		foreach ((array) $unsubscribe as $eventName) {
			list($namespace, $event) = Kdyby\Events\Event::parseName($eventName);
			$events[$event][] = $namespace;
		}

		if (!$events) {
			$events = array_fill_keys(array_keys($this->listeners), array());
		}

		$hash = spl_object_hash($listener);
		foreach ((array) $events as $event => $namespaces) {
			foreach ($this->listeners[$event] as $namespace => &$listeners) {
				if ($namespaces && !in_array($namespace, $namespaces)) {
					continue;
				}

				unset($listeners[$hash]);
			}
		}
	}



	/**
	 * @param array $array
	 * @return array
	 */
	private function unique(array $array)
	{
		$res = array();
		array_walk_recursive($array, function ($a) use (& $res) {
			if (!in_array($a, $res, TRUE)) {
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
