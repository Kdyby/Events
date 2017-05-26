<?php

/**
 * Test: Kdyby\Events\Extension.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\DI\EventsExtension;
use Kdyby\Events\Event;
use Kdyby\Events\EventManager;
use Kdyby\Events\IExceptionHandler;
use Nette\Application\Application;
use Nette\Configurator;
use Nette\Security\User;
use ReflectionProperty;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class ExtensionTest extends \Tester\TestCase
{

	/**
	 * @param string $configFile
	 * @return \Nette\DI\Container
	 */
	public function createContainer($configFile)
	{
		$config = new Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5($configFile)]]);
		EventsExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		return $config->createContainer();
	}

	public function testRegisterListeners()
	{
		$container = $this->createContainer('subscribers');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);
		Assert::equal(2, count($manager->getListeners()));
	}

	public function testRegisterListenersWithSameArguments()
	{
		$container = $this->createContainer('subscribersWithSameArgument');
		$manager = $container->getService('events.manager');

		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);
		Assert::same(['onFoo'], array_keys($manager->getListeners()));
		Assert::count(2, $manager->getListeners('onFoo'));
	}

	public function testValidateDirect()
	{
		$me = $this;

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.direct');
		}, \Nette\Utils\AssertionException::class, 'Please, do not register listeners directly to service @events.manager. %a%');
	}

	public function testValidateMissing()
	{
		$me = $this;

		try {
			$me->createContainer('validate.missing');
			Assert::fail('Expected exception');

		} catch (\Nette\Utils\AssertionException $e) {
			Assert::match(
				'Please, specify existing class for service \'events.subscriber.%a%\' explicitly, and make sure, that the class exists and can be autoloaded.',
				$e->getMessage()
			);

		} catch (\Nette\DI\ServiceCreationException $e) {
			Assert::match("Class NonExistingClass_%a% used in service 'events.subscriber.%a%' not found%a?%.", $e->getMessage());

		} catch (\Exception $e) {
			Assert::fail($e->getMessage());
		}
	}

	public function testValidateFake()
	{
		$me = $this;

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.fake');
		}, \Nette\Utils\AssertionException::class, 'Subscriber @events.subscriber.%a% doesn\'t implement Kdyby\Events\Subscriber.');
	}

	public function testValidateInvalid()
	{
		$me = $this;

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.invalid');
		}, \Nette\Utils\AssertionException::class, 'Event listener KdybyTests\Events\FirstInvalidListenerMock::onFoo() is not implemented.');
	}

	public function testValidateInvalid2()
	{
		$me = $this;

		Assert::exception(function () use ($me) {
			$me->createContainer('validate.invalid2');
		}, \Nette\Utils\AssertionException::class, 'Event listener KdybyTests\Events\SecondInvalidListenerMock::onBar() is not implemented.');
	}

	public function testAutowire()
	{
		$container = $this->createContainer('autowire');

		$app = $container->getService('application');
		/** @var \Nette\Application\Application $app */
		Assert::true($app->onStartup instanceof Event);
		Assert::same(Application::class . '::onStartup', $app->onStartup->getName());

		Assert::true($app->onRequest instanceof Event);
		Assert::same(Application::class . '::onRequest', $app->onRequest->getName());

		Assert::true($app->onResponse instanceof Event);
		Assert::same(Application::class . '::onResponse', $app->onResponse->getName());

		Assert::true($app->onError instanceof Event);
		Assert::same(Application::class . '::onError', $app->onError->getName());

		Assert::true($app->onShutdown instanceof Event);
		Assert::same(Application::class . '::onShutdown', $app->onShutdown->getName());

		// not all properties are affected
		Assert::true(is_bool($app->catchExceptions));
		Assert::true(!is_object($app->errorPresenter));

		$user = $container->getService('user');
		/** @var \Nette\Security\User $user */
		Assert::true($user->onLoggedIn instanceof Event);
		Assert::same(User::class . '::onLoggedIn', $user->onLoggedIn->getName());

		Assert::true($user->onLoggedOut instanceof Event);
		Assert::same(User::class . '::onLoggedOut', $user->onLoggedOut->getName());
	}

	public function testInherited()
	{
		$container = $this->createContainer('inherited');

		/** @var \KdybyTests\Events\LeafClass $leafObject */
		$leafObject = $container->getService('leaf');

		Assert::true($leafObject->onCreate instanceof Event);
		Assert::same(LeafClass::class . '::onCreate', $leafObject->onCreate->getName());

		$leafObject->create();

		/** @var \KdybyTests\Events\InheritSubscriber $subscriber */
		$subscriber = $container->getService('subscriber');

		/** @var \KdybyTests\Events\SecondInheritSubscriber $subscriber */
		$subscriber2 = $container->getService('subscriber2');

		Assert::same([
			LeafClass::class . '::onCreate' => 2,
			// not subscribed for middle class
		], $subscriber->eventCalls);

		Assert::same([
			LeafClass::class . '::onCreate' => 1,
			// not subscribed for middle class
		], $subscriber2->eventCalls);
	}

	public function testOptimize()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$bazArgs = new EventArgsMock();
		$manager->dispatchEvent('onFoo', $bazArgs);
		Assert::false($container->isCreated('foo'));
		Assert::true($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('onFoo')));

		$bazArgsSecond = new EventArgsMock();
		$manager->dispatchEvent('App::onFoo', $bazArgsSecond);
		Assert::same(1, count($manager->getListeners('App::onFoo')));

		$baz = $container->getService('baz');
		/** @var \KdybyTests\Events\NamespacedEventListenerMock $baz */
		$bar = $container->getService('bar');
		/** @var \KdybyTests\Events\EventListenerMock $bar */

		Assert::same([
			[EventListenerMock::class . '::onFoo', [$bazArgs]],
		], $bar->calls);

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$bazArgsSecond]],
		], $baz->calls);
	}

	public function testOptimizeDispatchNamespaceFirst()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$bazArgs = new EventArgsMock();
		$manager->dispatchEvent('App::onFoo', $bazArgs);
		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::true($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('App::onFoo')));

		$baz = $container->getService('baz');
		/** @var \KdybyTests\Events\NamespacedEventListenerMock $baz */

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$bazArgs]],
		], $baz->calls);
	}

	public function testOptimizeStandalone()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$foo = new FooMock();
		$bazArgs = new StartupEventArgs($foo, 123);
		$manager->dispatchEvent('onStartup', $bazArgs);
		Assert::true($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('onStartup')));

		$baz = $container->getService('foo');
		/** @var \KdybyTests\Events\NamespacedEventListenerMock $baz */

		Assert::same([
			[LoremListener::class . '::onStartup', [$bazArgs]],
		], $baz->calls);
	}

	public function testExceptionHandler()
	{
		$container = $this->createContainer('exceptionHandler');
		$manager = $container->getService('events.manager');

		// getter not needed, so hack it via reflection
		$rp = new ReflectionProperty(EventManager::class, 'exceptionHandler');
		$rp->setAccessible(TRUE);
		$handler = $rp->getValue($manager);

		Assert::true($handler instanceof IExceptionHandler);
	}

	public function testAutowireAlias()
	{
		$container = $this->createContainer('alias');
		Assert::same($container->getService('alias'), $container->getService('application'));
	}

	public function testFactoryAndAccessor()
	{
		$container = $this->createContainer('factory.accessor');

		$foo = $container->getService('foo');
		Assert::type(Event::class, $foo->onBar);

		$fooAccessor = $container->getService('fooAccessor');
		$foo2 = $fooAccessor->get();
		Assert::same($foo, $foo2);

		$fooFactory = $container->getService('fooFactory');
		$foo3 = $fooFactory->create();
		Assert::type(Event::class, $foo3->onBar);
		Assert::notSame($foo, $foo3);
	}

	public function testGlobalDispatchFirst()
	{
		$container = $this->createContainer('globalDispatchFirst');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */

		$mock = $container->getService('dispatchOrderMock');
		Assert::true($mock->onGlobalDispatchFirst->globalDispatchFirst);
		Assert::false($mock->onGlobalDispatchLast->globalDispatchFirst);
		Assert::true($mock->onGlobalDispatchDefault->globalDispatchFirst);
	}

	public function testGlobalDispatchLast()
	{
		$container = $this->createContainer('globalDispatchLast');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */

		$mock = $container->getService('dispatchOrderMock');
		Assert::true($mock->onGlobalDispatchFirst->globalDispatchFirst);
		Assert::false($mock->onGlobalDispatchLast->globalDispatchFirst);
		Assert::false($mock->onGlobalDispatchDefault->globalDispatchFirst);
	}

}

(new ExtensionTest())->run();
