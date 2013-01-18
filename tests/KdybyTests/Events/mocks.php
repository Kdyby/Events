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
		echo $int * 2;
	}



	/**
	 * @param StartupEventArgs $args
	 */
	public function onStartup(StartupEventArgs $args)
	{
		echo $args->int * 10;
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

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	/**
	 * @var array
	 */
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



	/**
	 * @param \Kdyby\Events\EventArgs $args
	 */
	public function onFoo(Kdyby\Events\EventArgs $args)
	{
		$this->calls[] = array(__METHOD__, func_get_args());
	}



	/**
	 * @param \Kdyby\Events\EventArgs $args
	 */
	public function onBar(Kdyby\Events\EventArgs $args)
	{
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
