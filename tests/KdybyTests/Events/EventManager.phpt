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

	/** @var EventListenerMock */
	private $listener;



	public function setUp()
	{
		$this->manager = new EventManager();
		$this->listener = new EventListenerMock();
	}



	public function testListenerHasRequiredMethod()
	{
		$this->manager->addEventListener('onFoo', $this->listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::same(array($this->listener), $this->manager->getListeners());
	}



	public function testRemovingListenerFromSpecificEvent()
	{
		$this->manager->addEventListener('onFoo', $this->listener);
		$this->manager->addEventListener('onBar', $this->listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		$this->manager->removeEventListener('onFoo', $this->listener);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));
	}



	public function testRemovingListenerCompletely()
	{
		$this->manager->addEventListener('onFoo', $this->listener);
		$this->manager->addEventListener('onBar', $this->listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		$this->manager->removeEventListener($this->listener);
		Assert::false($this->manager->hasListeners('onFoo'));
		Assert::false($this->manager->hasListeners('onBar'));
		Assert::same(array(), $this->manager->getListeners());
	}



	public function testListenerDontHaveRequiredMethodException()
	{
		$evm = $this->manager;
		$listener = $this->listener;

		Assert::exception(function () use ($evm, $listener) {
			$evm->addEventListener('onNonexisting', $listener);
		}, 'Kdyby\Events\InvalidListenerException');

	}



	public function testDispatching()
	{
		$this->manager->addEventSubscriber($this->listener);
		Assert::true($this->manager->hasListeners('onFoo'));
		Assert::true($this->manager->hasListeners('onBar'));

		$eventArgs = new EventArgsMock();
		$this->manager->dispatchEvent('onFoo', $eventArgs);

		Assert::same(array(
			array('KdybyTests\Events\EventListenerMock::onFoo', array($eventArgs))
		), $this->listener->calls);
	}



	/**
	 * @return array
	 */
	public function dataEventsDispatching_Namespaces()
	{
		return array(
			array('App::event', array('App::event')),
			array('event', array('App::event', 'event')),
			array('Other::event', array()),
		);
	}



	/**
	 * @dataProvider dataEventsDispatching_Namespaces
	 *
	 * @param string $trigger
	 * @param array $expected
	 */
	public function testEventsDispatching_Namespaces($trigger, array $expected)
	{
		Assert::fail("not implemented");
	}

}

\run(new EventManagerTest());
