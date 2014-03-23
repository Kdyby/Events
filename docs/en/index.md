Quickstart
==========

This extension is here to provide robust events system for Nette Framework.


Installation
-----------

The best way to install Kdyby/Events is using  [Composer](http://getcomposer.org/):

```sh
$ composer require kdyby/events:@dev
```

With dev Nette, you can enable the extension using your neon config.

```yml
extensions:
	events: Kdyby\Events\DI\EventsExtension
```

If you're using stable Nette, you have to register them in `app/bootstrap.php`

```php
Kdyby\Events\DI\EventsExtension::register($configurator);

return $configurator->createContainer();
```


Nette Events
------------

By extending `Nette\Object`, you're obtaining a very simple event system. Every property prefixed by "on", is an event.

```php
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

By passing callbacks to the event, you're making them listeners

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
The array should contain list of events, that when invoked, the EventManager would call this listener.


```php
class FooListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array('onStartup');
	}

	public function onStartup(Application $app)
	{
		// this will get called on each of application starts
	}
}
```

Now when I invoke the event `onStartup`, the listener method should get called

```php
use Kdyby\Events\EventManager;
use Kdyby\Events\EventArgsList;

class Application
{
	private $evm;

	public function __construct(EventManager $evm)
	{
		$this->evm = $evm;
	}

	public function run()
	{
		$this->evm->dispatchEvent('onStartup', new EventArgsList(array($this)));
	}
}

$evm = new EventManager();
$evm->addEventSubscriber(new FooListener());

$app = new Application($evm);
$app->run();
```

Now when you call the method `run`, the event gets dispatched and it will call the listener with given arguments.


Best of both worlds
-------------------

Now, that's a lot of code, couldn't it be shorter? Hell yeah! Let's add some syntax sugar.
First, you have to register the extension to your `Configurator` as said in Installation. It handles registration of listeners.

```yml
services:
	foo:
		class: FooListener
		tags: [kdyby.subscriber]
```

When you tag the service with `kdyby.subscriber`, it's automatically registered to the EventManager.
Your listener is also automatically analysed whether it really contains the methods it should, because after all, there is interface only for the `getSubscribedEvents` method.

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
So for example the event `onRequest` in class `Nette\Application\Application`
would have the full name `Nette\Application\Application::onRequest`.

The rule is, that listener should implement a method, that gets called on the event dispatch.
In this case, it would have to implement method `onRequest` which is the longest part of the event name, that can be used as method name.

If you don't like that, you can still use the dots and have events named for example `app.request`, but the suggested convention is class name namespace.


Method aliasing
---------------

But what if you wanna listen on two events, that are both named `onRequest`, but in different namespaces? No worries, you can just alias them and name the method as you like.

```php
class FooListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array(
			'Nette\Application\Application::onStartup' => 'appStartup',
			'NuclearReactor::onStartup' => 'reactorStartup'
		);
	}

	public function appStartup(Application $app)
	{
		// todo
	}

	public function reactorStartup(Reactor $reactor)
	{
		// todo
	}
}
```

And the compile-time validation for method presence still works!


Listener priorities
-------------------

You should **never** rely on the order, in which are listeners called, but there are cases when you just can't dodge the bullet.


```php
class FatListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array(
			'Nette\Application\Application::onStartup' => array(
				array('appStartup', 10)
			),
		);
	}

	public function appStartup(Application $app)
	{
		echo __METHOD__, "\n";
	}

}


class SlimListener extends Nette\Object implements Kdyby\Events\Subscriber
{
	public function getSubscribedEvents()
	{
		return array(
			'Nette\Application\Application::onStartup' => array(
				array('appStartup', 15)
				array('slowStartup', 5)
			),
		);
	}

	public function appStartup(Application $app)
	{
		echo __METHOD__, "\n";
	}

	public function slowStartup(Application $app)
	{
		echo __METHOD__, "\n";
	}

}
```

Can you guess what will be the output, when the application is started? It should output exactly this.

```
SlimListener::appStartup
FatListener::appStartup
SlimListener::slowStartup
```

