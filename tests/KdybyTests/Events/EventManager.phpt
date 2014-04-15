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



	public function testRemovingListenerFromSpecificEvent()
	{
		$listener = new EventListenerMock();
		$this->manager->addEventListener('onFoo', $listener);
		$this->manager->addEventListener('onBar', $listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		$this->manager->removeEventListener('onFoo', $listener);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
	}



	public function testRemovingListenerCompletely()
	{
		$listener = new EventListenerMock();
		$this->manager->addEventListener('onFoo', $listener);
		$this->manager->addEventListener('onBar', $listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		$this->manager->removeEventListener($listener);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::false($this->manager->hasListeners('onBar'));
		Assert::same(array(), $this->manager->getListeners());
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



	/**
	 * @return array
	 */
	public function dataEventsDispatching_Namespaces()
	{
		return array(
			array('App::onFoo', array('App::onFoo')),
			array('onFoo', array('App::onFoo', 'onFoo')),
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
			array(__NAMESPACE__ . '\\CustomNamespacedEventListenerMock::updated', array($first)),
			array(__NAMESPACE__ . '\\CustomNamespacedEventListenerMock::updated', array($second)),
		), $listener->calls);
	}



	public function testEventsDispatching_CustomMethodAlias()
	{
		$this->manager->addEventSubscriber($listener = new MethodAliasListenerMock());

		$this->manager->dispatchEvent('onDiscard', $args = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\MethodAliasListenerMock::customMethod', array($args)),
		), $listener->calls);
	}



	public function testEventsDispatching_Priority()
	{
		$this->manager->addEventSubscriber($lower = new PriorityMethodAliasListenerMock());
		$this->manager->addEventSubscriber($higher = new HigherPriorityMethodAliasListenerMock());

		$this->manager->dispatchEvent('onDiscard', $args = new EventArgsMock());

		Assert::same(array(
			array(__NAMESPACE__ . '\\HigherPriorityMethodAliasListenerMock::customMethod', array($args)),
			array(__NAMESPACE__ . '\\PriorityMethodAliasListenerMock::customMethod', array($args)),
		), $args->calls);
	}



	public function testEventsDispatching_MultipleEventMethods()
	{
		$this->manager->addEventSubscriber($listener = new MultipleEventMethodsListenerMock());

		$this->manager->dispatchEvent('onDiscard', $args = new EventArgsMock());

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

}

\run(new EventManagerTest());
