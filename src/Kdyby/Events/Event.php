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



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Event extends Nette\Object implements \ArrayAccess, \IteratorAggregate, \Countable
{

	/**
	 * @var Nette\Callback[]
	 */
	private $listeners = array();

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $namespace;

	/**
	 * @var EventManager
	 */
	private $eventManager;

	/**
	 * @var string
	 */
	private $argsClass;



	/**
	 * @param string $name
	 * @param array $defaults
	 * @param string $argsClass
	 */
	public function __construct($name, $defaults = array(), $argsClass = NULL)
	{
		list($this->namespace, $this->name) = self::parseName($name);
		$this->argsClass = $argsClass;

		if (is_array($defaults) || $defaults instanceof \Traversable) {
			foreach ($defaults as $listener) {
				$this->add($listener);
			}
		}
	}



	/**
	 * @return string
	 */
	public function getName()
	{
		return ($this->namespace ? $this->namespace . '::' : '') . $this->name;
	}



	/**
	 * @param EventManager $eventManager
	 * @return Event
	 */
	public function injectEventManager(EventManager $eventManager)
	{
		$this->eventManager = $eventManager;
		return $this;
	}



	/**
	 * Invokes the event.
	 *
	 * @param array $args
	 */
	public function dispatch(array $args = array())
	{
		foreach ($this->getListeners() as $handler) {
			if ($handler->invokeArgs(array_values($args)) === FALSE) {
				return;
			}
		}
	}



	/**
	 * @param callable $listener
	 * @return Event
	 */
	public function add($listener)
	{
		$this->listeners[] = callback($listener);
		return $this;
	}



	/**
	 * @return array|\callable[]|\Nette\Callback[]
	 */
	public function getListeners()
	{
		$listeners = $this->listeners;
		if (!$this->eventManager || !$this->eventManager->hasListeners($this->getName())) {
			return $listeners;
		}

		$name = $this->getName();
		$evm = $this->eventManager;
		$argsClass = $this->argsClass;
		$listeners[] = callback(function () use ($name, $evm, $argsClass) {
			if ($argsClass === NULL) {
				$args = new EventArgsList(func_get_args());

			} else {
				$args = Nette\Reflection\ClassType::from($argsClass)->newInstanceArgs(func_get_args());
			}

			$evm->dispatchEvent($name, $args);
		});

		return $listeners;
	}



	/**
	 * Invokes the event.
	 */
	public function __invoke()
	{
		$this->dispatch(func_get_args());
	}



	/**
	 * @param string $name
	 * @return array
	 */
	public static function parseName($name)
	{
		if (strpos($name, '::') === FALSE) {
			return array(NULL, $name);
		}

		return explode('::', $name);
	}



	/********************* interface \Countable *********************/



	/**
	 * @return int
	 */
	public function count()
	{
		return count($this->listeners);
	}



	/********************* interface \IteratorAggregate *********************/



	/**
	 * @return \ArrayIterator|\Traversable
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->getListeners());
	}



	/********************* interface \ArrayAccess *********************/



	/**
	 * @param int|NULL $index
	 * @param mixed $item
	 */
	public function offsetSet($index, $item)
	{
		if ($index === NULL) { // append
			$this->listeners[] = callback($item);

		} else { // replace
			$this->listeners[$index] = callback($item);
		}
	}



	/**
	 * @param mixed $index
	 * @return callable|mixed|\Nette\Callback
	 * @throws OutOfRangeException
	 */
	public function offsetGet($index)
	{
		if (!$this->offsetExists($index)) {
			throw new OutOfRangeException;
		}

		return $this->listeners[$index];
	}



	/**
	 * @param int $index
	 *
	 * @return bool
	 */
	public function offsetExists($index)
	{
		return isset($this->listeners[$index]);
	}



	/**
	 * @param int $index
	 */
	public function offsetUnset($index)
	{
		unset($this->listeners[$index]);
	}

}
