<?php

/**
 * Test: Kdyby\Events\EventManager.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\EventManager;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class EventManagerTest extends \Tester\TestCase
{

	/** @var \Kdyby\Events\EventManager */
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
		}, \Kdyby\Events\InvalidListenerException::class, 'Event listener "KdybyTests\Events\EventListenerMock" has no method "onStartup"');
	}

	public function testListenerIsCallable()
	{
		$listener = self::getEmptyClosure();
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
		$listenerCallback = self::getEmptyClosure();
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
		$listenerCallback = self::getEmptyClosure();
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

	public function testRemovingListenersIssue90()
	{
		$listener1 = new EventListenerMock();
		$listener2 = new EventListenerMock2();
		$this->manager->addEventListener('onFoo', $listener1);
		$this->manager->addEventListener('onFoo', $listener2);
		Assert::count(2, $this->manager->getListeners('onFoo'));

		$this->manager->removeEventListener('onFoo', $listener2);
		Assert::count(1, $this->manager->getListeners('onFoo'));
		Assert::same(['onFoo' => [$listener1]], $this->manager->getListeners());
	}

	public function testListenerDontHaveRequiredMethodException()
	{
		$evm = $this->manager;
		$listener = new EventListenerMock();

		Assert::exception(function () use ($evm, $listener) {
			$evm->addEventListener('onNonexisting', $listener);
		}, \Kdyby\Events\InvalidListenerException::class);
	}

	public function testListenerWithoutInterface()
	{
		Assert::false($this->manager->hasListeners('onClear'));
		$listener = new ListenerWithoutInterface();
		$this->manager->addEventListener(['onClear'], $listener);
		Assert::true($this->manager->hasListeners('onClear'));

		Assert::same([
			[$listener, 'onClear'],
		], $this->manager->getListeners('onClear'));

		Assert::same([
			'onClear' => [
				[$listener, 'onClear'],
			],
		], $this->manager->getListeners());
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
			[EventListenerMock::class . '::onFoo', [$eventArgs]],
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
			[MagicEventListenerMock::class . '::onQuux', [$eventArgs]],
		], $listener->calls);
	}

	/**
	 * @return array
	 */
	public function dataEventsDispatchingNamespaces()
	{
		return [
			['App::onFoo', ['App::onFoo']],
			['onFoo', ['onFoo']],
			['Other::onFoo', []],
		];
	}

	/**
	 * @dataProvider dataEventsDispatchingNamespaces
	 *
	 * @param string $trigger
	 * @param array $called
	 */
	public function testEventsDispatchingNamespaces($trigger, array $called)
	{
		$plain = new EventListenerMock();
		$this->manager->addEventListener('onFoo', $plain);
		$ns = new NamespacedEventListenerMock();
		$this->manager->addEventListener('App::onFoo', $ns);

		$args = new EventArgsMock();
		$this->manager->dispatchEvent($trigger, $args);

		$expected = [];
		if (in_array('App::onFoo', $called, TRUE)) {
			$expected[] = [NamespacedEventListenerMock::class . '::onFoo', [$args]];
		}
		if (in_array('onFoo', $called, TRUE)) {
			$expected[] = [EventListenerMock::class . '::onFoo', [$args]];
		}

		Assert::same($expected, array_merge($ns->calls, $plain->calls));
	}

	public function testEventsDispatchingCustomNamespaces()
	{
		$listener = new CustomNamespacedEventListenerMock();
		$this->manager->addEventSubscriber($listener);

		$first = new EventArgsMock();
		$this->manager->dispatchEvent('updated', $first);
		$second = new EventArgsMock();
		$this->manager->dispatchEvent('domain.users.updated', $second);

		Assert::same([
			[CustomNamespacedEventListenerMock::class . '::updated', [$second]],
		], $listener->calls);
	}

	public function testEventsDispatchingCustomMethodAlias()
	{
		$listener = new MethodAliasListenerMock();
		$this->manager->addEventSubscriber($listener);

		$args = new EventArgsMock();
		$this->manager->dispatchEvent('Article::onDiscard', $args);

		Assert::same([
			[MethodAliasListenerMock::class . '::customMethod', [$args]],
		], $listener->calls);
	}

	public function testEventsDispatchingPriority()
	{
		$lower = new PriorityMethodAliasListenerMock();
		$this->manager->addEventSubscriber($lower);
		$higher = new HigherPriorityMethodAliasListenerMock();
		$this->manager->addEventSubscriber($higher);

		$args = new EventArgsMock();
		$this->manager->dispatchEvent('Article::onDiscard', $args);

		Assert::same([
			[HigherPriorityMethodAliasListenerMock::class . '::customMethod', [$args]],
			[PriorityMethodAliasListenerMock::class . '::customMethod', [$args]],
		], $args->calls);
	}

	public function testEventsDispatchingMultipleEventMethods()
	{
		$listener = new MultipleEventMethodsListenerMock();
		$this->manager->addEventSubscriber($listener);

		$args = new EventArgsMock();
		$this->manager->dispatchEvent('Article::onDiscard', $args);

		Assert::same([
			[MultipleEventMethodsListenerMock::class . '::firstMethod', [$args]],
			[MultipleEventMethodsListenerMock::class . '::secondMethod', [$args]],
		], $listener->calls);
	}

	public function testEventsDispatchingMultipleEventMethodsNamespaced()
	{
		$listener = new MultipleEventMethodsListenerMock();
		$this->manager->addEventSubscriber($listener);

		$args = new EventArgsMock();
		$this->manager->dispatchEvent('Article::onDiscard', $args);

		Assert::same([
			[MultipleEventMethodsListenerMock::class . '::firstMethod', [$args]],
			[MultipleEventMethodsListenerMock::class . '::secondMethod', [$args]],
		], $listener->calls);
	}

	public function testEventsDispatchingListenerWithoutInterface()
	{
		$listener = new ListenerWithoutInterface();
		$this->manager->addEventListener(['onClear'], $listener);

		$args = new EventArgsMock();
		$this->manager->dispatchEvent('onClear', $args);

		Assert::same([
			[ListenerWithoutInterface::class . '::onClear', [$args]],
		], $listener->calls);
	}

	public function testEventDispatchingInheritanceHasListeners()
	{
		$parentClassOnly = new ParentClassOnlyListener();
		$this->manager->addEventSubscriber($parentClassOnly);
		Assert::true($this->manager->hasListeners(ParentClass::class . '::onCreate'));
		Assert::true($this->manager->hasListeners(InheritedClass::class . '::onCreate'));
		Assert::true($this->manager->hasListeners(LeafClass::class . '::onCreate'));

		$inheritClassOnly = new InheritClassOnlyListener();
		$this->manager->addEventSubscriber($inheritClassOnly);
		Assert::true($this->manager->hasListeners(ParentClass::class . '::onCreate'));
		Assert::true($this->manager->hasListeners(InheritedClass::class . '::onCreate'));
		Assert::true($this->manager->hasListeners(LeafClass::class . '::onCreate'));

		$leafClassOnly = new LeafClassOnlyListener();
		$this->manager->addEventSubscriber($leafClassOnly);
		Assert::true($this->manager->hasListeners(ParentClass::class . '::onCreate'));
		Assert::true($this->manager->hasListeners(InheritedClass::class . '::onCreate'));
		Assert::true($this->manager->hasListeners(LeafClass::class . '::onCreate'));
	}

	public function testEventDispatchingInheritanceGetListeners()
	{
		$parentClassOnly = new ParentClassOnlyListener();
		$this->manager->addEventSubscriber($parentClassOnly);
		$inheritClassOnly = new InheritClassOnlyListener();
		$this->manager->addEventSubscriber($inheritClassOnly);
		$leafClassOnly = new LeafClassOnlyListener();
		$this->manager->addEventSubscriber($leafClassOnly);

		Assert::same([
			$parentClassOnly,
		], $this->manager->getListeners(ParentClass::class . '::onCreate'));

		Assert::same([
			$inheritClassOnly,
			$parentClassOnly,
		], $this->manager->getListeners(InheritedClass::class . '::onCreate'));

		Assert::same([
			$leafClassOnly,
			$inheritClassOnly,
			$parentClassOnly,
		], $this->manager->getListeners(LeafClass::class . '::onCreate'));
	}

	public function testEventDispatchingInheritanceListeningOnParentClass()
	{
		$parentClassOnly = new ParentClassOnlyListener();
		$this->manager->addEventSubscriber($parentClassOnly);
		$inheritClassOnly = new InheritClassOnlyListener();
		$this->manager->addEventSubscriber($inheritClassOnly);
		$leafClassOnly = new LeafClassOnlyListener();
		$this->manager->addEventSubscriber($leafClassOnly);

		$parentClass = new ParentClass();
		$parentClass->onCreate = $this->manager->createEvent(ParentClass::class . '::onCreate');
		$parentClass->create(1);

		Assert::same([[1]], $parentClassOnly->eventCalls);
		Assert::same([], $inheritClassOnly->eventCalls);
		Assert::same([], $leafClassOnly->eventCalls);
	}

	public function testEventDispatchingInheritanceListeningOnInheritedClass()
	{
		$parentClassOnly = new ParentClassOnlyListener();
		$this->manager->addEventSubscriber($parentClassOnly);
		$inheritClassOnly = new InheritClassOnlyListener();
		$this->manager->addEventSubscriber($inheritClassOnly);
		$leafClassOnly = new LeafClassOnlyListener();
		$this->manager->addEventSubscriber($leafClassOnly);

		$inheritedClass = new InheritedClass();
		$inheritedClass->onCreate = $this->manager->createEvent(InheritedClass::class . '::onCreate');
		$inheritedClass->create(1);

		Assert::same([[1]], $parentClassOnly->eventCalls);
		Assert::same([[1]], $inheritClassOnly->eventCalls);
		Assert::same([], $leafClassOnly->eventCalls);
	}

	public function testEventDispatchingInheritanceListeningOnLeafClass()
	{
		$parentClassOnly = new ParentClassOnlyListener();
		$this->manager->addEventSubscriber($parentClassOnly);
		$inheritClassOnly = new InheritClassOnlyListener();
		$this->manager->addEventSubscriber($inheritClassOnly);
		$leafClassOnly = new LeafClassOnlyListener();
		$this->manager->addEventSubscriber($leafClassOnly);

		$leafClass = new LeafClass();
		$leafClass->onCreate = $this->manager->createEvent(LeafClass::class . '::onCreate');
		$leafClass->create(1);

		Assert::same([[1]], $parentClassOnly->eventCalls);
		Assert::same([[1]], $inheritClassOnly->eventCalls);
		Assert::same([[1]], $leafClassOnly->eventCalls);
	}

	/**
	 * @return \Closure
	 */
	private static function getEmptyClosure()
	{
		return function () {
		};
	}

}

(new EventManagerTest())->run();
