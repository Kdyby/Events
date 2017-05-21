<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events;

use ArrayIterator;
use Kdyby\Events\Diagnostics\Panel;
use Nette\Reflection\ClassType as ClassTypeReflection;
use Nette\Utils\Callback;
use Traversable;

class Event implements \ArrayAccess, \IteratorAggregate, \Countable
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * Changes the order of listeners being invoked,
	 * The default is that the closures and listeners registered directly are first,
	 * but this property can change that, so the global is first.
	 *
	 * @var bool
	 */
	public $globalDispatchFirst = FALSE;

	/**
	 * @var callable[]
	 */
	private $listeners = [];

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $namespace;

	/**
	 * @var \Kdyby\Events\EventManager
	 */
	private $eventManager;

	/**
	 * @var string|NULL
	 */
	private $argsClass;

	/**
	 * @var \Kdyby\Events\Diagnostics\Panel
	 */
	private $panel;

	/**
	 * @param string|array $name
	 * @param array $defaults
	 * @param string $argsClass
	 */
	public function __construct($name, $defaults = [], $argsClass = NULL)
	{
		list($this->namespace, $this->name) = self::parseName($name);
		$this->argsClass = $argsClass;

		if (is_array($defaults) || $defaults instanceof Traversable) {
			foreach ($defaults as $listener) {
				$this->append($listener);
			}
		}
	}

	/**
	 * @internal
	 * @param \Kdyby\Events\Diagnostics\Panel $panel
	 */
	public function setPanel(Panel $panel)
	{
		$this->panel = $panel;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return ($this->namespace ? $this->namespace . '::' : '') . $this->name;
	}

	/**
	 * @param \Kdyby\Events\EventManager $eventManager
	 * @return static
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
	public function dispatch($args = [])
	{
		if (!is_array($args)) {
			$args = func_get_args();
		} else {
			$args = array_values($args);
		}

		foreach ($this->getListeners() as $handler) {
			if (call_user_func_array($handler, $args) === FALSE) {
				return;
			}
		}
	}

	/**
	 * @param callable $listener
	 * @return static
	 */
	public function append($listener)
	{
		Callback::check($listener, TRUE);
		$this->listeners[] = $listener;
		return $this;
	}

	/**
	 * @param callable $listener
	 * @return static
	 */
	public function prepend($listener)
	{
		Callback::check($listener, TRUE);
		array_unshift($this->listeners, $listener);
		return $this;
	}

	/**
	 * @return array|callable[]
	 */
	public function getListeners()
	{
		$listeners = $this->listeners;

		if ($this->panel) {
			$this->panel->inlineCallbacks($this->getName(), $listeners);

		} elseif (!$this->eventManager || !$this->eventManager->hasListeners($this->getName())) {
			return $listeners;
		}

		$name = $this->getName();
		$evm = $this->eventManager;
		$argsClass = $this->argsClass;
		$globalDispatch = function () use ($name, $evm, $argsClass) {
			if ($argsClass === NULL) {
				$args = new EventArgsList(func_get_args());

			} else {
				$args = ClassTypeReflection::from($argsClass)->newInstanceArgs(func_get_args());
			}

			$evm->dispatchEvent($name, $args);
		};

		if ($this->globalDispatchFirst) {
			array_unshift($listeners, $globalDispatch);

		} else {
			$listeners[] = $globalDispatch;
		}

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
	public static function parseName(&$name)
	{
		if (is_array($name)) {
			return $name;
		}

		if (preg_match('~^([^\w]?(?P<namespace>.*\w+)(?P<separator>[^\w]{1,2}))?(?P<name>[a-z]\w+)$~i', $name, $m)) {
			$name = ($m['namespace'] ? $m['namespace'] . $m['separator'] : '') . $m['name'];
			return [$m['namespace'] ?: NULL, $m['name'], $m['separator'] ?: NULL];

		} else {
			$name = ltrim($name, '\\');
		}

		return [NULL, $name, NULL];
	}

	/** @deprecated */
	public function add($listener)
	{
		return $this->append($listener);
	}

	/** @deprecated */
	public function unshift($listener)
	{
		return $this->prepend($listener);
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
		return new ArrayIterator($this->getListeners());
	}

	/********************* interface \ArrayAccess *********************/

	/**
	 * @param int|NULL $index
	 * @param callable $item
	 */
	public function offsetSet($index, $item)
	{
		Callback::check($item, TRUE);

		if ($index === NULL) { // append
			$this->listeners[] = $item;

		} else { // replace
			$this->listeners[$index] = $item;
		}
	}

	/**
	 * @param mixed $index
	 * @return callable
	 * @throws \Kdyby\Events\OutOfRangeException
	 */
	public function offsetGet($index)
	{
		if (!$this->offsetExists($index)) {
			throw new \Kdyby\Events\OutOfRangeException;
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
