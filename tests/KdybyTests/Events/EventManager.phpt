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
use Nette;
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
		Assert::same(array('onFoo' => array($listener)), $this->manager->getListeners());
	}



	public function testListenerIsCallable()
	{
		$listener = function () {};
		$this->manager->addEventListener('onFoo', $listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::same(array('onFoo' => array($listener)), $this->manager->getListeners());
	}



	public function testRemovingListenerFromSpecificEvent()
	{
		$listenerClass = new EventListenerMock();
		$listenerCallback = function () {};

		$this->manager->addEventListener('onFoo', $listenerClass);
		$this->manager->addEventListener('onBar', $listenerClass);
		$this->manager->addEventListener('onBaz', $listenerCallback);
		$this->manager->addEventListener('onQux', $listenerCallback);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
		Assert::true($this->manager->hasListeners('onBaz'));
		Assert::true($this->manager->hasListeners('onQux'));

		$this->manager->removeEventListener('onFoo', $listenerClass);
		$this->manager->removeEventListener('onBaz', $listenerCallback);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
		Assert::false($this->manager->hasListeners('onBaz'));
		Assert::true($this->manager->hasListeners('onQux'));
	}



	public function testRemovingListenerCompletely()
	{
		$listenerClass = new EventListenerMock();
		$listenerCallback = function () {};

		$this->manager->addEventListener('onFoo', $listenerClass);
		$this->manager->addEventListener('onBar', $listenerClass);
		$this->manager->addEventListener('onBaz', $listenerCallback);
		$this->manager->addEventListener('onQux', $listenerCallback);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
		Assert::true($this->manager->hasListeners('onBaz'));
		Assert::true($this->manager->hasListeners('onQux'));

		$this->manager->removeEventListener($listenerClass);
		$this->manager->removeEventListener($listenerCallback);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::false($this->manager->hasListeners('onBar'));
		Assert::false($this->manager->hasListeners('onBaz'));
		Assert::false($this->manager->hasListeners('onQux'));
		Assert::same([], $this->manager->getListeners());
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
		$this->manager->addEventListener(array('onClear'), $listener = new ListenerWithoutInterface());
		Assert::true($this->manager->hasListeners('onClear'));

		Assert::same(array(
			array($listener, 'onClear'),
		), $this->manager->getListeners('onClear'));

		Assert::same(array('onClear' => array(
			array($listener, 'onClear'),
		)), $this->manager->getListeners());
	}



	public function testDispatching()
	{
		$listener = new EventListenerMock();
		$this->manager->addEventSubscriber($listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		$eventArgs = new EventArgsMock();
		$this->manager->dispatchEvent('onFoo', $eventArgs);

		Assert::same(array(
			array('KdybyTests\Events\EventListenerMock::onFoo', array($eventArgs))
		), $listener->calls);
	}



	public function testDispatchingCallable()
	{
		$triggerCounter = 0;
		$listener = function () use (& $triggerCounter) {
			$triggerCounter++;
		};

		$this->manager->addEventListener('onFoo', $listener);
		$this->manager->addEventListener('onBar', $listener);
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



	/**
	 * @return array
	 */
	public function dataEventsDispatching_Namespaces()
	{
		return array(
			array('App::onFoo', array('App::onFoo')),
			array('onFoo', array('onFoo')),
			array('Other::onFoo', array()),
		);
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

		$expected = array();
		if (in_array('App::onFoo', $called)) {
			$expected[] = array(__NAMESPACE__ . '\\NamespacedEventListenerMock::onFoo', array($args));
		}
		if (in_array('onFoo', $called)) {
			$expected[] = array(__NAMESPACE__ . '\\EventListenerMock::onFoo', array($args));
		}

		Assert::same($expected, array_merge($ns->calls, $plain->calls));
	}



	public function testEventsDispatching_CustomNamespaces()
	{
		$this->manager->addEventSubscriber($listener = new CustomNamespacedEventListenerMock());

		$this->manager->dispatchEvent('updated', $first = new EventArgsMock());
		$this->manager->dispatchEvent('domain.users.updated', $second = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\CustomNamespacedEventListenerMock::updated', array($second)),
		), $listener->calls);
	}



	public function testEventsDispatching_CustomMethodAlias()
	{
		$this->manager->addEventSubscriber($listener = new MethodAliasListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\MethodAliasListenerMock::customMethod', array($args)),
		), $listener->calls);
	}



	public function testEventsDispatching_Priority()
	{
		$this->manager->addEventSubscriber($lower = new PriorityMethodAliasListenerMock());
		$this->manager->addEventSubscriber($higher = new HigherPriorityMethodAliasListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\HigherPriorityMethodAliasListenerMock::customMethod', array($args)),
			array(__NAMESPACE__ . '\\PriorityMethodAliasListenerMock::customMethod', array($args)),
		), $args->calls);
	}



	public function testEventsDispatching_MultipleEventMethods()
	{
		$this->manager->addEventSubscriber($listener = new MultipleEventMethodsListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::firstMethod', array($args)),
			array(__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::secondMethod', array($args)),
		), $listener->calls);
	}



	public function testEventsDispatching_MultipleEventMethods_namespaced()
	{
		$this->manager->addEventSubscriber($listener = new MultipleEventMethodsListenerMock());

		$this->manager->dispatchEvent('Article::onDiscard', $args = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::firstMethod', array($args)),
			array(__NAMESPACE__ . '\\MultipleEventMethodsListenerMock::secondMethod', array($args)),
		), $listener->calls);
	}



	public function testEventsDispatching_ListenerWithoutInterface()
	{
		$this->manager->addEventListener(array('onClear'), $listener = new ListenerWithoutInterface());

		$this->manager->dispatchEvent('onClear', $args = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\ListenerWithoutInterface::onClear', array($args)),
		), $listener->calls);
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

		Assert::same(array(
			$parentClassOnly,
		), $this->manager->getListeners('KdybyTests\Events\ParentClass::onCreate'));

		Assert::same(array(
			$inheritClassOnly,
			$parentClassOnly,
		), $this->manager->getListeners('KdybyTests\Events\InheritedClass::onCreate'));

		Assert::same(array(
			$leafClassOnly,
			$inheritClassOnly,
			$parentClassOnly,
		), $this->manager->getListeners('KdybyTests\Events\LeafClass::onCreate'));
	}



	public function testEventDispatching_Inheritance_ListeningOnParentClass()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());

		$parentClass = new ParentClass();
		$parentClass->onCreate = $this->manager->createEvent('KdybyTests\Events\ParentClass::onCreate');
		$parentClass->create(1);

		Assert::same(array(array(1)), $parentClassOnly->eventCalls);
		Assert::same(array(), $inheritClassOnly->eventCalls);
		Assert::same(array(), $leafClassOnly->eventCalls);
	}



	public function testEventDispatching_Inheritance_ListeningOnInheritedClass()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());

		$inheritedClass = new InheritedClass();
		$inheritedClass->onCreate = $this->manager->createEvent('KdybyTests\Events\InheritedClass::onCreate');
		$inheritedClass->create(1);

		Assert::same(array(array(1)), $parentClassOnly->eventCalls);
		Assert::same(array(array(1)), $inheritClassOnly->eventCalls);
		Assert::same(array(), $leafClassOnly->eventCalls);
	}



	public function testEventDispatching_Inheritance_ListeningOnLeafClass()
	{
		$this->manager->addEventSubscriber($parentClassOnly = new ParentClassOnlyListener());
		$this->manager->addEventSubscriber($inheritClassOnly = new InheritClassOnlyListener());
		$this->manager->addEventSubscriber($leafClassOnly = new LeafClassOnlyListener());

		$leafClass = new LeafClass();
		$leafClass->onCreate = $this->manager->createEvent('KdybyTests\Events\LeafClass::onCreate');
		$leafClass->create(1);

		Assert::same(array(array(1)), $parentClassOnly->eventCalls);
		Assert::same(array(array(1)), $inheritClassOnly->eventCalls);
		Assert::same(array(array(1)), $leafClassOnly->eventCalls);
	}

}

\run(new EventManagerTest());
