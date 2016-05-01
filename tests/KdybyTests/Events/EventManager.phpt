<?php

/**
 * Test: Kdyby\Events\EventManager.
 *
 * @testCase Kdyby\Events\EventManagerTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Events
 */

namespace KdybyTests\Events;

use Kdyby\Events\EventManager;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventManagerTest extends Tester\TestCase
{

	/** @var EventManager */
	private $manager;



	protected function setUp()
	{
		$this->manager = new EventManager();
	}



	public function testListenerHasRequiredMethod()
	{
		$listener = new EventListenerMock();
		$this->manager->addEventListener('onFoo', $listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::same(['onFoo' => [$listener]], $this->manager->getListeners());
	}



	public function testListenerIsMissingMethod()
	{
		Assert::exception(function () {
			$this->manager->addEventListener('onStartup', new EventListenerMock());
		}, 'Kdyby\Events\InvalidListenerException', 'Event listener "KdybyTests\Events\EventListenerMock" has no method "onStartup"');
	}



	public function testListenerIsCallable()
	{
		$listener = function () {
		};
		$this->manager->addEventListener('onFoo', $listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::same(['onFoo' => [$listener]], $this->manager->getListeners());
	}



	public function testListenerMagic()
	{
		$listener = new MagicEventListenerMock();
		$this->manager->addEventListener('onBaz', $listener);
		Assert::true($this->manager->hasListeners('onBaz'));
		Assert::same(['onBaz' => [$listener]], $this->manager->getListeners());
	}



	public function testRemovingListenerFromSpecificEvent()
	{
		$subscriber = new EventListenerMock();
		$listenerCallback = function () {};
		$callableSubscriber = new MagicEventListenerMock();

		$this->manager->addEventListener('onFoo', $subscriber);
		$this->manager->addEventListener('onBar', $subscriber);
		$this->manager->addEventListener('onBaz', $listenerCallback);
		$this->manager->addEventListener('onQux', $listenerCallback);
		$this->manager->addEventListener('onQuux', $callableSubscriber);
		$this->manager->addEventListener('onCorge', $callableSubscriber);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
		Assert::true($this->manager->hasListeners('onBaz'));
		Assert::true($this->manager->hasListeners('onQux'));
		Assert::true($this->manager->hasListeners('onQuux'));
		Assert::true($this->manager->hasListeners('onCorge'));

		$this->manager->removeEventListener('onFoo', $subscriber);
		$this->manager->removeEventListener('onBaz', $listenerCallback);
		$this->manager->removeEventListener('onQuux', $callableSubscriber);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
		Assert::false($this->manager->hasListeners('onBaz'));
		Assert::true($this->manager->hasListeners('onQux'));
		Assert::false($this->manager->hasListeners('onQuux'));
		Assert::true($this->manager->hasListeners('onCorge'));
	}



	public function testRemovingListenerCompletely()
	{
		$subscriber = new EventListenerMock();
		$listenerCallback = function () {};
		$callableSubscriber = new MagicEventListenerMock();

		$this->manager->addEventListener('onFoo', $subscriber);
		$this->manager->addEventListener('onBar', $subscriber);
		$this->manager->addEventListener('onBaz', $listenerCallback);
		$this->manager->addEventListener('onQux', $listenerCallback);
		$this->manager->addEventListener('onQuux', $callableSubscriber);
		$this->manager->addEventListener('onCorge', $callableSubscriber);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
		Assert::true($this->manager->hasListeners('onBaz'));
		Assert::true($this->manager->hasListeners('onQux'));
		Assert::true($this->manager->hasListeners('onQuux'));
		Assert::true($this->manager->hasListeners('onCorge'));

		$this->manager->removeEventListener($subscriber);
		$this->manager->removeEventListener($listenerCallback);
		$this->manager->removeEventListener($callableSubscriber);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::false($this->manager->hasListeners('onBar'));
		Assert::false($this->manager->hasListeners('onBaz'));
		Assert::false($this->manager->hasListeners('onQux'));
		Assert::false($this->manager->hasListeners('onQuux'));
		Assert::false($this->manager->hasListeners('onCorge'));
		Assert::same([], $this->manager->getListeners());
	}



	public function testRemovingSomeListeners()
	{
		$listener = new EventListenerMock();
		$this->manager->addEventListener('onFoo', $listener);
		$this->manager->addEventListener('onBar', $listener);
		$listener2 = new EventListenerMock2();
		$this->manager->addEventListener('onFoo', $listener2);
		$this->manager->addEventListener('onBar', $listener2);
		Assert::count(2, $this->manager->getListeners('onFoo'));
		Assert::count(2, $this->manager->getListeners('onBar'));

		$this->manager->removeEventListener($listener);
		$this->manager->removeEventListener('onFoo', $listener2);
		Assert::count(0, $this->manager->getListeners('onFoo'));
		Assert::count(1, $this->manager->getListeners('onBar'));
		Assert::same(['onBar' => [$listener2]], $this->manager->getListeners());
	}


	public function testListenerDontHaveRequiredMethodException()
	{
		$evm = $this->manager;
		$listener = new EventListenerMock();

		Assert::exception(function () use ($evm, $listener) {
			$evm->addEventListener('onNonexisting', $listener);
		}, 'Kdyby\Events\InvalidListenerException');

	}



	public function testListenerWithoutInterface()
	{
		Assert::false($this->manager->hasListeners('onClear'));
		$this->manager->addEventListener(['onClear'], $listener = new ListenerWithoutInterface());
		Assert::true($this->manager->hasListeners('onClear'));

		Assert::same([
			[$listener, 'onClear'],
		], $this->manager->getListeners('onClear'));

		Assert::same(['onClear' => [
			[$listener, 'onClear'],
		]], $this->manager->getListeners());
	}



	public function testDispatching()
	{
		$listener = new EventListenerMock();
		$this->manager->addEventSubscriber($listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		$eventArgs = new EventArgsMock();
		$this->manager->dispatchEvent('onFoo', $eventArgs);

		Assert::same([
			['KdybyTests\Events\EventListenerMock::onFoo', [$eventArgs]]
		], $listener->calls);
	}



	public function testDispatchingCallable()
	{
		$triggerCounter = 0;
		$callback = function () use (& $triggerCounter) {
			$triggerCounter++;
		};

		$this->manager->addEventListener('onFoo', $callback);
		$this->manager->addEventListener('onBar', $callback);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		Assert::same(0, $triggerCounter);

		$eventArgs = new EventArgsMock();
		$this->manager->dispatchEvent('onFoo', $eventArgs);

		Assert::same(1, $triggerCounter);

		$eventArgs = new EventArgsMock();
		$this->manager->dispatchEvent('onBar', $eventArgs);

		Assert::same(2, $triggerCounter);
	}



	public function testDispatchingMagic()
	{
		$listener = new MagicEventListenerMock();
		$this->manager->addEventSubscriber($listener);
		Assert::true($this->manager->hasListeners('onQuux'));
		Assert::true($this->manager->hasListeners('onCorge'));

		$eventArgs = new EventArgsMock();
		$this->manager->dispatchEvent('onQuux', $eventArgs);

		Assert::same([
			['KdybyTests\Events\MagicEventListenerMock::onQuux', [$eventArgs]]
		], $listener->calls);
	}



	/**
	 * @return array
	 */
	public function dataEventsDispatching_Namespaces()
	{
		return [
			['App::onFoo', ['App::onFoo']],
			['onFoo', ['onFoo']],
			['Other::onFoo', []],
		];
	}



	/**
	 * @dataProvider dataEventsDispatching_Namespaces
	 *
	 * @param string $trigger
	 * @param array $called
	 */
	public function testEventsDispatching_Namespaces($trigger, array $called)
	{
		$this->manager->addEventListener('onFoo', $plain = new EventListenerMock());
		$this->manager->addEventListener('App::onFoo', $ns = new NamespacedEventListenerMock());

		$this->manager->dispatchEvent($trigger, $args = new EventArgsMock());

		$expected = [];
		if (in_array('App::onFoo', $called)) {
			$expected[] = [__NAMESPACE__ . '\\NamespacedEventListenerMock::onFoo', [$args]];
		}
		if (in_array('onFoo', $called)) {
			$expected[] = [__NAMESPACE__ . '\\EventListenerMock::onFoo', [$args]];
		}

		Assert::same($expected, array_merge($ns->calls, $plain->calls));
	}



	public function testEventsDispatching_CustomNamespaces()
	{
		$this->manager->addEventSubscriber($listener = new CustomNamespacedEventListenerMock());

		$this->manager->dispatchEvent('updated', $first = new EventArgsMock());
		$this->manager->dispatchEvent('domain.users.updated', $second = new EventArgsMock());

		Assert::same([
			[__NAMESPACE__ . '\\CustomNamespacedEventListenerMock::updated', [$second]],
		], $listener->calls);
	}



	public function testEventsDispatching_CustomMethodAlias()
	{
		$this->manager->addEventSubscriber($listener = new MethodAliasListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same([
			[__NAMESPACE__ . '\\MethodAliasListenerMock::customMethod', [$args]],
		], $listener->calls);
	}



	public function testEventsDispatching_Priority()
	{
		$this->manager->addEventSubscriber($lower = new PriorityMethodAliasListenerMock());
		$this->manager->addEventSubscriber($higher = new HigherPriorityMethodAliasListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same([
			[__NAMESPACE__ . '\\HigherPriorityMethodAliasListenerMock::customMethod', [$args]],
			[__NAMESPACE__ . '\\PriorityMethodAliasListenerMock::customMethod', [$args]],
		], $args->calls);
	}



	public function testEventsDispatching_MultipleEventMethods()
	{
		$this->manager->addEventSubscriber($listener = new MultipleEventMethodsListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same([
			[__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::firstMethod', [$args]],
			[__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::secondMethod', [$args]],
		], $listener->calls);
	}



	public function testEventsDispatching_MultipleEventMethods_namespaced()
	{
		$this->manager->addEventSubscriber($listener = new MultipleEventMethodsListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same([
			[__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::firstMethod', [$args]],
			[__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::secondMethod', [$args]],
		], $listener->calls);
	}



	public function testEventsDispatching_ListenerWithoutInterface()
	{
		$this->manager->addEventListener(['onClear'], $listener = new ListenerWithoutInterface());

		$this->manager->dispatchEvent('onClear', $args = new EventArgsMock());

		Assert::same([
			[__NAMESPACE__ . '\\ListenerWithoutInterface::onClear', [$args]],
		], $listener->calls);
	}



	public function testEventDispatching_Inheritance_hasListeners()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		Assert::true($this->manager->hasListeners('KdybyTests\Events\ParentClass::onCreate'));
		Assert::true($this->manager->hasListeners('KdybyTests\Events\InheritedClass::onCreate'));
		Assert::true($this->manager->hasListeners('KdybyTests\Events\LeafClass::onCreate'));

		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		Assert::true($this->manager->hasListeners('KdybyTests\Events\ParentClass::onCreate'));
		Assert::true($this->manager->hasListeners('KdybyTests\Events\InheritedClass::onCreate'));
		Assert::true($this->manager->hasListeners('KdybyTests\Events\LeafClass::onCreate'));

		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());
		Assert::true($this->manager->hasListeners('KdybyTests\Events\ParentClass::onCreate'));
		Assert::true($this->manager->hasListeners('KdybyTests\Events\InheritedClass::onCreate'));
		Assert::true($this->manager->hasListeners('KdybyTests\Events\LeafClass::onCreate'));
	}



	public function testEventDispatching_Inheritance_getListeners()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());

		Assert::same([
			$parentClassOnly,
		], $this->manager->getListeners('KdybyTests\Events\ParentClass::onCreate'));

		Assert::same([
			$inheritClassOnly,
			$parentClassOnly,
		], $this->manager->getListeners('KdybyTests\Events\InheritedClass::onCreate'));

		Assert::same([
			$leafClassOnly,
			$inheritClassOnly,
			$parentClassOnly,
		], $this->manager->getListeners('KdybyTests\Events\LeafClass::onCreate'));
	}



	public function testEventDispatching_Inheritance_ListeningOnParentClass()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());

		$parentClass = new ParentClass();
		$parentClass->onCreate = $this->manager->createEvent('KdybyTests\Events\ParentClass::onCreate');
		$parentClass->create(1);

		Assert::same([[1]], $parentClassOnly->eventCalls);
		Assert::same([], $inheritClassOnly->eventCalls);
		Assert::same([], $leafClassOnly->eventCalls);
	}



	public function testEventDispatching_Inheritance_ListeningOnInheritedClass()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());

		$inheritedClass = new InheritedClass();
		$inheritedClass->onCreate = $this->manager->createEvent('KdybyTests\Events\InheritedClass::onCreate');
		$inheritedClass->create(1);

		Assert::same([[1]], $parentClassOnly->eventCalls);
		Assert::same([[1]], $inheritClassOnly->eventCalls);
		Assert::same([], $leafClassOnly->eventCalls);
	}



	public function testEventDispatching_Inheritance_ListeningOnLeafClass()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());

		$leafClass = new LeafClass();
		$leafClass->onCreate = $this->manager->createEvent('KdybyTests\Events\LeafClass::onCreate');
		$leafClass->create(1);

		Assert::same([[1]], $parentClassOnly->eventCalls);
		Assert::same([[1]], $inheritClassOnly->eventCalls);
		Assert::same([[1]], $leafClassOnly->eventCalls);
	}

}

\run(new EventManagerTest());
