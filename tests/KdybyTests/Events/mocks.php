<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace KdybyTests\Events;

use Nette;
use Kdyby;
use Kdyby\Events\Event;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onBar($lorem)
 * @method onMagic(FooMock $foo, $int)
 * @method onStartup(FooMock $foo, $int)
 */
class FooMock extends Nette\Object
{

	/**
	 * @var array|callable[]|Event
	 */
	public $onBar = array();

	/**
	 * @var array|callable[]|Event
	 */
	public $onMagic = array();

	/**
	 * @var array|callable[]|Event
	 */
	public $onStartup = array();

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LoremListener extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();


	/**
	 * Returns an array of events this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'onMagic',
			'onStartup'
		);
	}



	/**
	 * @param FooMock $foo
	 * @param $int
	 */
	public function onMagic(FooMock $foo, $int)
	{
		$this->calls[] = array(__METHOD__, func_get_args());
	}



	/**
	 * @param StartupEventArgs $args
	 */
	public function onStartup(StartupEventArgs $args)
	{
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class StartupEventArgs extends Kdyby\Events\EventArgs
{

	/**
	 * @var FooMock
	 */
	public $foo;

	/**
	 * @var
	 */
	public $int;



	/**
	 * @param FooMock $foo
	 * @param $int
	 */
	public function __construct(FooMock $foo, $int)
	{
		$this->foo = $foo;
		$this->int = $int;
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventArgsMock extends Kdyby\Events\EventArgs
{

	/**
	 * @var array
	 */
	public $calls = array();

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'onFoo',
			'onBar'
		);
	}



	public function onFoo(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}



	public function onBar(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NamespacedEventListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'\App::onFoo'
		);
	}



	public function onFoo(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class MethodAliasListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'Article::onDiscard' => 'customMethod'
		);
	}



	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class PriorityMethodAliasListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'Article::onDiscard' => array('customMethod', 10)
		);
	}



	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class HigherPriorityMethodAliasListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'Article::onDiscard' => array('customMethod', 25)
		);
	}



	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class MultipleEventMethodsListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'Article::onDiscard' => array(
				array('firstMethod', 25),
				array('secondMethod', 10),
			)
		);
	}



	public function firstMethod(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}



	public function secondMethod(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class CustomNamespacedEventListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = array();

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'domain.users.updated'
		);
	}



	public function updated(EventArgsMock $args)
	{
		$args->calls[] = array(__METHOD__, func_get_args());
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class FirstInvalidListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'onFoo'
		);
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SecondInvalidListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return array(
			'Application::onBar'
		);
	}

}


class ListenerWithoutInterface extends Nette\Object
{

	public $calls = array();

	public function onClear()
	{
		$this->calls[] = array(__METHOD__, func_get_args());
	}

}


class RouterFactory extends Nette\Object
{

	/**
	 * @return \KdybyTests\Events\SampleRouter
	 */
	public function createRouter()
	{
		return new SampleRouter('nemam');
	}

}

class SampleRouter extends Nette\Application\Routers\Route
{

	public $onMatch = array();

	public $onConstruct = array();

}

class SampleExceptionHandler implements Kdyby\Events\IExceptionHandler
{
	public $exceptions = array();


	public function handleException(\Exception $exception)
	{
		$this->exceptions[] = $exception;
	}

}
