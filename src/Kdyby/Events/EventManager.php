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
	 * @var array|object[]
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
		if (!isset($this->listeners[$eventName])) {
			return;
		}

		foreach ($this->listeners[$eventName] as $listener) {
			$cb = callback($listener, $eventName);
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
	 * @param string $eventName The name of the event.
	 * @return array The event listeners for the specified event, or all event listeners.
	 */
	public function getListeners($eventName = NULL)
	{
		if ($eventName !== NULL) {
			if (!isset($this->listeners[$eventName])) {
				return array();
			}

			return $this->listeners[$eventName];
		}

		return array_unique(Arrays::flatten($this->listeners), SORT_REGULAR);
	}



	/**
	 * Checks whether an event has any registered listeners.
	 *
	 * @param string $eventName
	 * @return boolean TRUE if the specified event has any listeners, FALSE otherwise.
	 */
	public function hasListeners($eventName)
	{
		return isset($this->listeners[$eventName]) && $this->listeners[$eventName];
	}



	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string|array $events The event(s) to listen on.
	 * @param Subscriber $listener The listener object.
	 *
	 * @throws InvalidListenerException
	 */
	public function addEventListener($events, $listener)
	{
		$hash = spl_object_hash($listener);
		foreach ((array)$events as $eventName) {
			if (!method_exists($listener, $eventName)) {
				throw new InvalidListenerException("Event listener '" . get_class($listener) . "' has no method '" . $eventName . "'");
			}

			$this->listeners[$eventName][$hash] = $listener;
		}
	}



	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string|array $events
	 * @param Subscriber $listener
	 */
	public function removeEventListener($events, $listener = NULL)
	{
		if (is_object($events)) {
			$listener = $events;
			$events = array();
		}

		$hash = spl_object_hash($listener);
		foreach ((array)$events ?: array_keys($this->listeners) as $event) {
			if (isset($this->listeners[$event][$hash])) {
				unset($this->listeners[$event][$hash]);
			}
		}
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
