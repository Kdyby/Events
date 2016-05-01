<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events\DI;

use Kdyby;
use Nette;
use Nette\PhpGenerator as Code;
use Nette\Utils\AssertionException;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventsExtension extends Nette\DI\CompilerExtension
{
	/** @deprecated */
	const EVENT_TAG = self::TAG_EVENT;
	/** @deprecated */
	const SUBSCRIBER_TAG = self::TAG_SUBSCRIBER;

	const TAG_EVENT = 'kdyby.event';
	const TAG_SUBSCRIBER = 'kdyby.subscriber';

	const PANEL_COUNT_MODE = 'count';

	/**
	 * @var array
	 */
	public $defaults = [
		'subscribers' => [],
		'validate' => TRUE,
		'autowire' => TRUE,
		'optimize' => TRUE,
		'debugger' => '%debugMode%',
		'exceptionHandler' => NULL,
		'globalDispatchFirst' => FALSE,
	];

	/**
	 * @var array
	 */
	private $loadedConfig;

	/**
	 * @var array
	 */
	private $listeners = [];

	/**
	 * @var array
	 */
	private $allowedManagerSetup = [];



	public function loadConfiguration()
	{
		$this->listeners = [];
		$this->allowedManagerSetup = [];

		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$userConfig = $this->getConfig();
		$isCli = in_array(php_sapi_name(), ['cli', 'phpdbg'], TRUE);
		if ($isCli || (!isset($userConfig['debugger']) && !$config['debugger'])) {
			$config['debugger'] = self::PANEL_COUNT_MODE;
		}

		$evm = $builder->addDefinition($this->prefix('manager'))
			->setClass('Kdyby\Events\EventManager');
		if ($config['debugger']) {
			$defaults = ['dispatchTree' => FALSE, 'dispatchLog' => TRUE, 'events' => TRUE, 'listeners' => FALSE];
			if (is_array($config['debugger'])) {
				$config['debugger'] = Nette\DI\Config\Helpers::merge($config['debugger'], $defaults);
			} else {
				$config['debugger'] = $config['debugger'] !== self::PANEL_COUNT_MODE;
			}

			$evm->addSetup('Kdyby\Events\Diagnostics\Panel::register(?, ?)->renderPanel = ?', ['@self', '@container', $config['debugger']]);
		}

		if ($config['exceptionHandler'] !== NULL) {
			$evm->addSetup('setExceptionHandler', $this->filterArgs($config['exceptionHandler']));
		}

		Nette\Utils\Validators::assertField($config, 'subscribers', 'array');
		foreach ($config['subscribers'] as $i => $subscriber) {
			$def = $builder->addDefinition($this->prefix('subscriber.' . $i));

			$def->setFactory(Nette\DI\Compiler::filterArguments([
				is_string($subscriber) ? new Nette\DI\Statement($subscriber) : $subscriber
			])[0]);

			list($subscriberClass) = (array) $builder->normalizeEntity($def->getEntity());
			if (class_exists($subscriberClass)) {
				$def->setClass($subscriberClass);
			}

			$def->setAutowired(FALSE);
			$def->addTag(self::SUBSCRIBER_TAG);
		}

		if (class_exists('Symfony\Component\EventDispatcher\Event')) {
			$builder->addDefinition($this->prefix('symfonyProxy'))
				->setClass('Symfony\Component\EventDispatcher\EventDispatcherInterface')
				->setFactory('Kdyby\Events\SymfonyDispatcher');
		}

		$this->loadedConfig = $config;
	}



	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->loadedConfig;

		$manager = $builder->getDefinition($this->prefix('manager'));
		foreach (array_keys($builder->findByTag(self::SUBSCRIBER_TAG)) as $serviceName) {
			$manager->addSetup('addEventSubscriber', ['@' . $serviceName]);
		}

		Nette\Utils\Validators::assertField($config, 'validate', 'bool');
		if ($config['validate']) {
			$this->validateSubscribers($builder, $manager);
		}

		Nette\Utils\Validators::assertField($config, 'autowire', 'bool');
		if ($config['autowire']) {
			Nette\Utils\Validators::assertField($config, 'globalDispatchFirst', 'bool');
			$this->autowireEvents($builder);
		}

		Nette\Utils\Validators::assertField($config, 'optimize', 'bool');
		if ($config['optimize']) {
			if (!$config['validate']) {
				throw new Kdyby\Events\InvalidStateException("Cannot optimize without validation.");
			}

			$this->optimizeListeners($builder);
		}
	}



	public function afterCompile(Code\ClassType $class)
	{
		$init = $class->getMethod('initialize');

		/** @hack This tries to add the event invokation right after the code, generated by NetteExtension. */
		$foundNetteInitStart = $foundNetteInitEnd = FALSE;
		$lines = explode(";\n", trim($init->getBody()));
		$init->setBody(NULL);
		while (($line = array_shift($lines)) || $lines) {
			if ($foundNetteInitStart && !$foundNetteInitEnd &&
					stripos($line, 'Nette\\') === FALSE && stripos($line, 'set_include_path') === FALSE && stripos($line, 'date_default_timezone_set') === FALSE
			) {
				$init->addBody(Code\Helpers::format(
					'$this->getService(?)->createEvent(?)->dispatch($this);',
					$this->prefix('manager'),
					['Nette\\DI\\Container', 'onInitialize']
				));

				$foundNetteInitEnd = TRUE;
			}

			if (!$foundNetteInitEnd && (
					stripos($line, 'Nette\\') !== FALSE || stripos($line, 'set_include_path') !== FALSE || stripos($line, 'date_default_timezone_set') !== FALSE
				)) {
				$foundNetteInitStart = TRUE;
			}

			$init->addBody($line . ';');
		}

		if (!$foundNetteInitEnd) {
			$init->addBody(Code\Helpers::format(
				'$this->getService(?)->createEvent(?)->dispatch($this);',
				$this->prefix('manager'),
				['Nette\\DI\\Container', 'onInitialize']
			));
		}
	}



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 * @param \Nette\DI\ServiceDefinition $manager
	 * @throws AssertionException
	 */
	private function validateSubscribers(Nette\DI\ContainerBuilder $builder, Nette\DI\ServiceDefinition $manager)
	{
		foreach ($manager->getSetup() as $stt) {
			if ($stt->getEntity() !== 'addEventSubscriber') {
				$this->allowedManagerSetup[] = $stt;
				continue;
			}

			try {
				$serviceName = $builder->getServiceName(reset($stt->arguments));
				$def = $builder->getDefinition($serviceName);

			} catch (\Exception $e) {
				throw new AssertionException(
					"Please, do not register listeners directly to service '" . $this->prefix('manager') . "'. " .
					"Use section '" . $this->name . ": subscribers: ', or tag the service as '" . self::SUBSCRIBER_TAG . "'.",
					0, $e
				);
			}

			if (!$def->getClass()) {
				throw new AssertionException(
					"Please, specify existing class for " . (is_numeric($serviceName) ? 'anonymous ' : '') . "service '$serviceName' explicitly, " .
					"and make sure, that the class exists and can be autoloaded."
				);

			} elseif (!class_exists($def->getClass())) {
				throw new AssertionException(
					"Class '" . $def->getClass() . "' of " . (is_numeric($serviceName) ? 'anonymous ' : '') . "service '$serviceName' cannot be found. " .
					"Please make sure, that the class exists and can be autoloaded."
				);
			}

			if (!in_array('Doctrine\Common\EventSubscriber' , class_implements($def->getClass()))) {
				// the minimum is Doctrine EventSubscriber, but recommend is Kdyby Subscriber
				throw new AssertionException("Subscriber '$serviceName' doesn't implement Kdyby\\Events\\Subscriber.");
			}

			$eventNames = [];
			$listenerInst = self::createInstanceWithoutConstructor($def->getClass());
			foreach ($listenerInst->getSubscribedEvents() as $eventName => $params) {
				if (is_numeric($eventName) && is_string($params)) { // [EventName, ...]
					list(, $method) = Kdyby\Events\Event::parseName($params);
					$eventNames[] = ltrim($params, '\\');
					if (!method_exists($listenerInst, $method)) {
						throw new AssertionException("Event listener " . $def->getClass() . "::{$method}() is not implemented.");
					}

				} elseif (is_string($eventName)) { // [EventName => ???, ...]
					$eventNames[] = ltrim($eventName, '\\');

					if (is_string($params)) { // [EventName => method, ...]
						if (!method_exists($listenerInst, $params)) {
							throw new AssertionException("Event listener " . $def->getClass() . "::{$params}() is not implemented.");
						}

					} elseif (is_string($params[0])) { // [EventName => [method, priority], ...]
						if (!method_exists($listenerInst, $params[0])) {
							throw new AssertionException("Event listener " . $def->getClass() . "::{$params[0]}() is not implemented.");
						}

					} else {
						foreach ($params as $listener) { // [EventName => [[method, priority], ...], ...]
							if (!method_exists($listenerInst, $listener[0])) {
								throw new AssertionException("Event listener " . $def->getClass() . "::{$listener[0]}() is not implemented.");
							}
						}
					}
				}
			}

			$this->listeners[$serviceName] = array_unique($eventNames);
		}
	}



	private function isAlias(Nette\DI\ServiceDefinition $definition)
	{
		return $definition->getFactory() && (
				$definition->getEntity() instanceof Nette\DI\ServiceDefinition
				|| (is_string($definition->getEntity()) && substr($definition->getEntity(), 0, 1) === '@')
			);
	}



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 */
	private function autowireEvents(Nette\DI\ContainerBuilder $builder)
	{
		foreach ($builder->getDefinitions() as $def) {
			/** @var Nette\DI\ServiceDefinition $def */
			if ($this->isAlias($def)) {
				continue; // alias
			}

			if (!class_exists($class = $builder->expand($def->getClass()))) {
				if (!$def->getFactory()) {
					continue;

				} elseif (is_array($class = $builder->expand($def->getEntity()))) {
					continue;

				} elseif (!class_exists($class)) {
					continue;
				}
			}
			if ($def->getImplementType() === 'get') {
				continue;
			}

			$this->bindEventProperties($def, Nette\Reflection\ClassType::from($class));
		}
	}



	protected function bindEventProperties(Nette\DI\ServiceDefinition $def, Nette\Reflection\ClassType $class)
	{
		foreach ($class->getProperties(Nette\Reflection\Property::IS_PUBLIC) as $property) {
			if (!preg_match('#^on[A-Z]#', $name = $property->getName())) {
				continue;
			}

			if ($property->hasAnnotation('persistent') || $property->hasAnnotation('inject')) { // definitely not an event
				continue;
			}

			$def->addSetup('$' . $name, [
				new Nette\DI\Statement($this->prefix('@manager') . '::createEvent', [
					[$class->getName(), $name],
					new Code\PhpLiteral('$service->' . $name),
					NULL,
					$property->hasAnnotation('globalDispatchFirst')
						? (bool) $property->getAnnotation('globalDispatchFirst')
						: $this->loadedConfig['globalDispatchFirst'],
				])
			]);
		}
	}



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 */
	private function optimizeListeners(Nette\DI\ContainerBuilder $builder)
	{
		$listeners = [];
		foreach ($this->listeners as $serviceName => $eventNames) {
			foreach ($eventNames as $eventName) {
				list($namespace, $event) = Kdyby\Events\Event::parseName($eventName);
				$listeners[$eventName][] = $serviceName;

				if (!$namespace || !class_exists($namespace)) {
					continue; // it might not even be a "classname" event namespace
				}

				// find all subclasses and register the listener to all the classes dispatching them
				foreach ($builder->getDefinitions() as $def) {
					if (!$class = $def->getClass()) {
						continue; // ignore unresolved classes
					}

					if (is_subclass_of($class, $namespace)) {
						$listeners["$class::$event"][] = $serviceName;
					}
				}
			}
		}

		foreach ($listeners as $id => $subscribers) {
			$listeners[$id] = array_unique($subscribers);
		}

		$builder->getDefinition($this->prefix('manager'))
			->setClass('Kdyby\Events\LazyEventManager', [$listeners])
			->setSetup($this->allowedManagerSetup);
	}



	/**
	 * @param string|\stdClass $statement
	 * @return Nette\DI\Statement[]
	 */
	private function filterArgs($statement)
	{
		return Nette\DI\Compiler::filterArguments([is_string($statement) ? new Nette\DI\Statement($statement) : $statement]);
	}



	/**
	 * @param \Nette\Configurator $configurator
	 */
	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('events', new EventsExtension());
		};
	}



	/**
	 * @param string $class
	 * @return \Doctrine\Common\EventSubscriber
	 */
	private static function createInstanceWithoutConstructor($class)
	{
		if (method_exists('ReflectionClass', 'newInstanceWithoutConstructor')) {
			$listenerInst = Nette\Reflection\ClassType::from($class)->newInstanceWithoutConstructor();

		} else {
			$listenerInst = unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
		}

		return $listenerInst;
	}

}
