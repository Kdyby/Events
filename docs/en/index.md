Quickstart
==========

This extension is here to provide robust events system for Nette Framework.


Installation
-----------

The best way to install Kdyby/Events is using [Composer](http://getcomposer.org/):

```sh
$ composer require kdyby/events
```

And then you should enable the extension using your neon config.

```yml
extensions:
	events: Kdyby\Events\DI\EventsExtension
```


Nette Events
------------

By extending `Nette\Object`, you're obtaining a very simple event system. Every property prefixed by "on" is an event.

```php
namespace App;

use Nette;

class OrderProcess extends Nette\Object
{
	public $onSuccess = array();

	private $orders;

	public function __construct(Orders $orders)
	{
		$this->orders = $orders;
	}

	public function process($values)
	{
		if ($order = $this->orders->create($values)) {
			$this->onSuccess($this, $order);
		}
	}
}
```

Listeners are created by passing them to the event as callbacks

```php
$process = new OrderProcess($orders);
$process->onSuccess[] = function ($process, $order) {
    echo "You've spent ", $order->sum, ",-";
};
```

When invoked by calling the property, as if it were a method `->onSuccess($this, $order)`, `Nette\Object` will iterate those callbacks, and pass the given arguments to each one of them.

It is massively used in forms as for example success callbacks.

There is a section about them in [Nette Framework documentation](http://doc.nette.org/en/php-language-enhancements#toc-events).


Doctrine Events
---------------

Doctrine has its own event system. You basically have to create a class, that implements interface `Kdyby\Events\Subscriber`.
This interface requires you to implement method `getSubscribedEvents`, which should return array.
The array should contain list of events to which the listener subscribes. EventManager will call the listener when these events are invoked.


```php
namespace App;

use Nette;

class FooListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array('App\OrderProcess::onSuccess');
	}

	public function onSuccess(OrderProcess $process)
	{
		// this will get called on order process success
	}
}
```

Now when I invoke the event `onSuccess`, the listener method will get called

```php
namespace App;

use Kdyby\Events\EventManager;
use Kdyby\Events\EventArgsList;

class OrderProcess
{
	private $orders;
	private $evm;

	public function __construct(Orders $orders, EventManager $evm)
	{
		$this->orders = $orders;
		$this->evm = $evm;
	}

	public function process()
	{
		if ($order = $this->orders->create($values)) {
			$this->evm->dispatchEvent('App\OrderProcess::onSuccess', new EventArgsList(array($this)));
		}
	}
}

$evm = new EventManager();
$evm->addEventSubscriber(new FooListener());

$op = new OrderProcess($evm);
$op->process();
```

Now when you call the method `process()`, the event gets dispatched and it will call the listener with given arguments.


Best of both worlds
-------------------

Now, that's a lot of code, couldn't it be shorter? Hell yeah! Let's add some syntax sugar.
First, you have to register the extension to your `Configurator` as said in Installation. It handles registration of listeners.

```yml
services:
	foo:
		class: App\FooListener
		tags: [kdyby.subscriber]
```

When you tag the service with `kdyby.subscriber`, it's automatically registered to the EventManager.
Your listener is also automatically analysed whether it really contains the methods it should, because after all, there is interface only for the `getSubscribedEvents()` method.

Also, all the services your register, are automatically analysed whether they extend `Nette\Object`, because if they do, they could be containing some Nette events.
If your service contains public property that looks like Nette event, it gets replaced by instance of `Kdyby\Events\Event`.

The `Kdyby\Events\Event` acts like it's an regular array, so the `Nette\Object` doesn't even know it's invoking a global event.
That's right, the `Kdyby\Events\Event` automatically propagates the event dispatch to EventManager, which calls all the listeners.
And just like that, we have global events. But don't worry, everything you know about Nette events is still working.


Optimisation
------------

But wouldn't it be slow, when there were hundreds of listeners with their dependencies registered to the `EventManager`? No it wouldn't! It's all completely lazy.
All the listeners are analyzed on compile-time, then a map of services and events is created, which gets passed to EventManager
and they all are created just at the right time, only when they are needed.


Event namespacing
-----------------

All the generated events are (and should be) namespaced by the class name, they're attached to.
So for example the event `onFailure` in class `OrderProcess`
would have the full name `App\OrderProcess::onFailure`.

The rule is, that listener should implement a method, that gets called on the event dispatch.
In this case, it would have to implement method `onFailure()` which is the longest part of the event name, that can be used as method name.

If you don't like that, you can still use the dots and have events named for example `orderProcess.failure`, but the suggested convention is class name namespace.


Method aliasing
---------------

But what if you wanna listen on two events, that are both named `onSuccess`, but in different namespaces? No worries, you can just alias them and name the method as you like.

```php
namespace App;

use Nette;
use Kdyby;

class FooListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array(
			'App\OrderProcess::onSuccess' => 'orderSuccess',
			'App\StoreProcess::onSuccess' => 'storeSuccess'
		);
	}

	public function orderSuccess(OrderProcess $process)
	{
		// gets called when OrderProcess::onSuccess is invoked
	}

	public function storeSuccess(StoreProcess $process)
	{
		// gets called when StoreProcess::onSuccess is invoked
	}
}
```

And the compile-time validation for method presence still works!


Listener priorities
-------------------

Events are **executed** by priority in **descending order** from highest to lowest.
You should **never** rely on the order, in which are listeners called, but there are cases when you just can't dodge the bullet.


```php
namespace App;

use Nette;
use Kdyby;

class FatListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array(
			'App\OrderProcess::onSuccess' => array(
				array('orderSuccess', 10)
			),
		);
	}

	public function orderSuccess(OrderProcess $process)
	{
		echo __METHOD__, "\n";
	}

}


class SlimListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array(
			'App\OrderProcess::onSuccess' => array(
				array('orderSuccess', 15)
				array('slowOrderSuccess', 5)
			),
		);
	}

	public function orderSuccess(OrderProcess $process)
	{
		echo __METHOD__, "\n";
	}

	public function slowOrderSuccess(OrderProcess $process)
	{
		echo __METHOD__, "\n";
	}

}
```

Can you guess what will be the output, when the order process succeeded? It should output exactly this.

```
SlimListener::orderSuccess
FatListener::orderSuccess
SlimListener::slowOrderSuccess
```


Dispatch Order
--------------

When using Nette Events properties you can bind a normal callback and a listener from Doctrine Events to the same event. In this case the callbacks are invoked first by default. In some cases, for example when you use a redirect in the callback, you might want to reverse the order to call global listeners first.

You can change the default behaviour for all events in your config.neon.

```yml
events:
	globalDispatchFirst: on
```

You can also change the behaviour for one event only using annotation.

```php
class OrderProcess extends Nette\Object
{
	/**
	 * This event will always dispatch the global listeners first.
	 * @globalDispatchFirst
	 */
	public $onStartup = array();

	/**
	 * This event will always dispatch the callbacks first even if you changed the default behaviour in config.neon.
	 * @globalDispatchFirst false
	 */
	public $onSuccess = array();
}
```


Debugging
---------

Kdyby\Events comes with integrated panel for Nette\Tracy, which makes it easier to debug your application. The panel contains dispatch tree, list of dispatched events and lists of registered events and registered listeners.

However, if you don't want to use it, you can disable the whole panel in your config.neon.

```yml
events:
	debugger: on # off
```

Or you can disable it's categories.

```yml
events:
	debugger: # these are the default values
		dispatchTree: off
		dispatchLog: on
		events: on
		listeners: off
```
