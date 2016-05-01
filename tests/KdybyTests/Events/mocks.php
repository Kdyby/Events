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
use Tracy;



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
	public $onBar = [];

	/**
	 * @var array|callable[]|Event
	 */
	public $onMagic = [];

	/**
	 * @var array|callable[]|Event
	 */
	public $onStartup = [];

}


interface FooMockAccessor
{

	/**
	 * @return FooMock
	 */
	public function get();

}


interface FooMockFactory
{

	/**
	 * @return FooMock
	 */
	public function create();

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LoremListener extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];


	/**
	 * Returns an array of events this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'onMagic',
			'onStartup'
		];
	}



	/**
	 * @param FooMock $foo
	 * @param $int
	 */
	public function onMagic(FooMock $foo, $int)
	{
		$this->calls[] = [__METHOD__, func_get_args()];
	}



	/**
	 * @param StartupEventArgs $args
	 */
	public function onStartup(StartupEventArgs $args)
	{
		$this->calls[] = [__METHOD__, func_get_args()];
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
	public $calls = [];

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'onFoo',
			'onBar'
		];
	}



	public function onFoo(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}



	public function onBar(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventListenerMock2 extends Nette\Object implements Kdyby\Events\Subscriber
{

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
				'onFoo',
				'onBar'
		];
	}


	public function onFoo()
	{
	}


	public function onBar()
	{
	}

}



/**
 * @author Pavol Sivý <pavol@sivy.net>
 */
class EventListenerConstructorMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public function __construct(RouterFactory $routerFactory)
	{
	}



	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
				'onFoo',
		];
	}



	public function onFoo(EventArgsMock $args)
	{
		// pass
	}

}



/**
 * @author Pavol Sivý <pavol@sivy.net>
 */
class EventListenerConstructorMock2 extends Nette\Object implements Kdyby\Events\Subscriber
{

	public function __construct(RouterFactory  $routerFactory)
	{
	}



	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
				'onFoo',
		];
	}



	public function onFoo(EventArgsMock $args)
	{
		// pass
	}

}

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class MagicEventListenerMock extends Nette\Object implements Kdyby\Events\CallableSubscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'onQuux',
			'onCorge',
		];
	}

	public function __call($name, $arguments)
	{
		$args = $arguments[0];
		$args->calls[] = [__CLASS__ . '::' . $name, $arguments];
		$this->calls[] = [__CLASS__ . '::' . $name, $arguments];
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NamespacedEventListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'\App::onFoo'
		];
	}



	public function onFoo(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class MethodAliasListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'Article::onDiscard' => 'customMethod'
		];
	}



	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class PriorityMethodAliasListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'Article::onDiscard' => ['customMethod', 10]
		];
	}



	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class HigherPriorityMethodAliasListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'Article::onDiscard' => ['customMethod', 25]
		];
	}



	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class MultipleEventMethodsListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'Article::onDiscard' => [
				['firstMethod', 25],
				['secondMethod', 10],
			]
		];
	}



	public function firstMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}



	public function secondMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class CustomNamespacedEventListenerMock extends Nette\Object implements Kdyby\Events\Subscriber
{

	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'domain.users.updated'
		];
	}



	public function updated(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
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
		return [
			'onFoo'
		];
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
		return [
			'Application::onBar'
		];
	}

}



class ListenerWithoutInterface extends Nette\Object
{

	public $calls = [];

	public function onClear()
	{
		$this->calls[] = [__METHOD__, func_get_args()];
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

	public $onMatch = [];

	public $onConstruct = [];

}



class SampleExceptionHandler implements Kdyby\Events\IExceptionHandler
{
	public $exceptions = [];


	public function handleException(\Exception $exception)
	{
		$this->exceptions[] = $exception;
	}
}



class ParentClass extends Nette\Object
{
	public $onCreate = [];

	public function create($arg = NULL) {
		$this->onCreate($arg);
	}
}



class InheritedClass extends ParentClass
{
}



class LeafClass extends InheritedClass
{
}



class InheritSubscriber implements Kdyby\Events\Subscriber
{
	public $eventCalls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'KdybyTests\Events\LeafClass::onCreate',
			'KdybyTests\Events\ParentClass::onCreate',
		];
	}



	public function onCreate()
	{
		$backtrace = debug_backtrace();
		$event = $backtrace[2]['args'][0];
		$this->eventCalls[$event] = 1 + (isset($this->eventCalls[$event]) ? $this->eventCalls[$event] : 0);
	}
}



class SecondInheritSubscriber implements Kdyby\Events\Subscriber
{
	public $eventCalls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return [
			'KdybyTests\Events\ParentClass::onCreate',
		];
	}



	public function onCreate()
	{
		if (!$event = Tracy\Helpers::findTrace(debug_backtrace(), 'Kdyby\Events\EventManager::dispatchEvent')) {
			$this->eventCalls['unknown'] += 1;
		} else {
			$eventName = $event['args'][0];
			$this->eventCalls[$eventName] = 1 + (isset($this->eventCalls[$eventName]) ? $this->eventCalls[$eventName] : 0);
		}
	}
}



class ParentClassOnlyListener implements Kdyby\Events\Subscriber
{

	public $eventCalls = [];



	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return ['KdybyTests\Events\ParentClass::onCreate'];
	}



	public function onCreate()
	{
		$this->eventCalls[] = func_get_args();
	}
}



class InheritClassOnlyListener implements Kdyby\Events\Subscriber
{

	public $eventCalls = [];



	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return ['KdybyTests\Events\InheritedClass::onCreate'];
	}



	public function onCreate()
	{
		$this->eventCalls[] = func_get_args();
	}
}



class LeafClassOnlyListener implements Kdyby\Events\Subscriber
{

	public $eventCalls = [];



	/**
	 * @return array
	 */
	public function getSubscribedEvents()
	{
		return ['KdybyTests\Events\LeafClass::onCreate'];
	}



	public function onCreate()
	{
		$this->eventCalls[] = func_get_args();
	}
}



class DispatchOrderMock extends Nette\Object
{

	/**
	 * @globalDispatchFirst
	 * @var array|callable[]|Event
	 */
	public $onGlobalDispatchFirst = [];

	/**
	 * @globalDispatchFirst false
	 * @var array|callable[]|Event
	 */
	public $onGlobalDispatchLast = [];

	/**
	 * @var array|callable[]|Event
	 */
	public $onGlobalDispatchDefault = [];

}
