<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events\DI;

use Doctrine\Common\EventSubscriber;
use Kdyby\Events\Diagnostics\Panel;
use Kdyby\Events\Event;
use Kdyby\Events\EventManager;
use Kdyby\Events\LazyEventManager;
use Kdyby\Events\Subscriber;
use Kdyby\Events\SymfonyDispatcher;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\Config\Helpers;
use Nette\DI\Container as DIContainer;
use Nette\DI\ContainerBuilder as DIContainerBuilder;
use Nette\DI\Definitions\AccessorDefinition;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ImportedDefinition;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Helpers as DIHelpers;
use Nette\PhpGenerator\ClassType as ClassTypeGenerator;
use Nette\PhpGenerator\Helpers as GeneratorHelpers;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Utils\Validators;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\Event as SymfonyEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventsExtension extends \Nette\DI\CompilerExtension
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
		/** @var array $config */
		$config = Helpers::merge($this->getConfig(), $this->defaults);

		/** @var array $userConfig */
		$userConfig = $this->getConfig();
		if (!array_key_exists('debugger', $userConfig)) {
			if (in_array(php_sapi_name(), ['cli', 'phpdbg'], TRUE)) {
				$config['debugger'] = FALSE; // disable by default in CLI

			} elseif (!$config['debugger']) {
				$config['debugger'] = self::PANEL_COUNT_MODE;
			}
		}

		$evm = $builder->addDefinition($this->prefix('manager'))
			->setType(EventManager::class);
		if ($config['debugger']) {
			$defaults = ['dispatchTree' => FALSE, 'dispatchLog' => TRUE, 'events' => TRUE, 'listeners' => FALSE];
			if (is_array($config['debugger'])) {
				$config['debugger'] = Helpers::merge($config['debugger'], $defaults);
			} else {
				$config['debugger'] = $config['debugger'] !== self::PANEL_COUNT_MODE;
			}

			$evm->addSetup('?::register(?, ?)->renderPanel = ?', [new PhpLiteral(Panel::class), '@self', '@container', $config['debugger']]);
		}

		if ($config['exceptionHandler'] !== NULL) {
			$evm->addSetup('setExceptionHandler', $this->filterArgs($config['exceptionHandler']));
		}

		Validators::assertField($config, 'subscribers', 'array');
		foreach ($config['subscribers'] as $i => $subscriber) {
			$def = $builder->addDefinition($this->prefix('subscriber.' . $i));

			$def->setFactory(DIHelpers::filterArguments([
				is_string($subscriber) ? new Statement($subscriber) : $subscriber,
			])[0]);

			/** @var string $subscriberClass */
			$subscriberClass = $def->getEntity();
			if (class_exists($subscriberClass)) {
				$def->setType($subscriberClass);
			}

			$def->setAutowired(FALSE);
			$def->addTag(self::TAG_SUBSCRIBER);
		}

		if (class_exists(SymfonyEvent::class)) {
			$builder->addDefinition($this->prefix('symfonyProxy'))
				->setClass(EventDispatcherInterface::class)
				->setFactory(SymfonyDispatcher::class);
		}

		$this->loadedConfig = $config;
	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->loadedConfig;

		/** @var \Nette\DI\Definitions\ServiceDefinition $manager */
		$manager = $builder->getDefinition($this->prefix('manager'));
		foreach (array_keys($builder->findByTag(self::TAG_SUBSCRIBER)) as $serviceName) {
			$manager->addSetup('addEventSubscriber', ['@' . $serviceName]);
		}

		Validators::assertField($config, 'validate', 'bool');
		if ($config['validate']) {
			$this->validateSubscribers($builder, $manager);
		}

		Validators::assertField($config, 'autowire', 'bool');
		if ($config['autowire']) {
			Validators::assertField($config, 'globalDispatchFirst', 'bool');
			$this->autowireEvents($builder);
		}

		Validators::assertField($config, 'optimize', 'bool');
		if ($config['optimize']) {
			if (!$config['validate']) {
				throw new \Kdyby\Events\InvalidStateException('Cannot optimize without validation.');
			}

			$this->optimizeListeners($builder);
		}
	}

	public function afterCompile(ClassTypeGenerator $class)
	{
		$init = $class->getMethod('initialize');

		/** @hack This tries to add the event invokation right after the code, generated by NetteExtension. */
		$foundNetteInitStart = $foundNetteInitEnd = FALSE;
		$lines = explode(";\n", trim($init->getBody() ?: ''));
		$init->setBody('');
		while (($line = array_shift($lines)) !== NULL) {
			if ($foundNetteInitStart && !$foundNetteInitEnd &&
				stripos($line, 'Nette\\') === FALSE && stripos($line, 'set_include_path') === FALSE && stripos($line, 'date_default_timezone_set') === FALSE
			) {
				$init->addBody(GeneratorHelpers::format(
					'$this->getService(?)->createEvent(?)->dispatch($this);',
					$this->prefix('manager'),
					[DIContainer::class, 'onInitialize']
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
			$init->addBody(GeneratorHelpers::format(
				'$this->getService(?)->createEvent(?)->dispatch($this);',
				$this->prefix('manager'),
				[DIContainer::class, 'onInitialize']
			));
		}
	}

	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 * @param \Nette\DI\ServiceDefinition $manager
	 * @throws \Nette\Utils\AssertionException
	 */
	private function validateSubscribers(DIContainerBuilder $builder, Definition $manager)
	{
		foreach ($manager->getSetup() as $stt) {
			if ($stt->getEntity() !== 'addEventSubscriber') {
				$this->allowedManagerSetup[] = $stt;
				continue;
			}

			try {
				$argument = reset($stt->arguments);
				if ($argument instanceof Reference) {
					$serviceName = $argument->getValue();
					$def = $builder->getDefinition($serviceName);
				} elseif ($argument instanceof Statement) {
					$entity = $argument->getEntity();

					if ($entity instanceof Definition) {
						$serviceName = $entity->getName();
					} elseif ($entity instanceof Reference) {
						$serviceName = $entity->getValue();
					} elseif (is_array($entity)) {
						$serviceName = $entity[0];
					} else {
						$serviceName = (string) $entity;
					}
					$def = $builder->getDefinition($serviceName);
				} else {
					$serviceName = ltrim($argument, '@');
					$def = $builder->getDefinition($serviceName);
				}

			} catch (\Exception $e) {
				throw new \Nette\Utils\AssertionException(
					sprintf(
						'Please, do not register listeners directly to service %s. Use section "%s: subscribers: ", or tag the service as "%s".',
						$this->prefix('@manager'),
						$this->name,
						self::TAG_SUBSCRIBER
					),
					0,
					$e
				);
			}

			$defClass = $def->getType();

			if (!$defClass) {
				throw new \Nette\Utils\AssertionException(
					sprintf(
						'Please, specify existing class for %sservice @%s explicitly, and make sure, that the class exists and can be autoloaded.',
						is_numeric($serviceName) ? 'anonymous ' : '',
						$serviceName
					)
				);

			}

			if (!class_exists($defClass)) {
				throw new \Nette\Utils\AssertionException(
					sprintf(
						'Class %s of %sservice @%s cannot be found. Please make sure, that the class exists and can be autoloaded.',
						$defClass,
						is_numeric($serviceName) ? 'anonymous ' : '',
						$serviceName
					)
				);
			}

			if (!in_array(EventSubscriber::class, class_implements($defClass), TRUE)) {
				// the minimum is Doctrine EventSubscriber, but recommend is Kdyby Subscriber
				throw new \Nette\Utils\AssertionException(sprintf('Subscriber @%s doesn\'t implement %s.', $serviceName, Subscriber::class));
			}

			$eventNames = [];
			$listenerInst = self::createEventSubscriberInstanceWithoutConstructor($defClass);
			foreach ($listenerInst->getSubscribedEvents() as $eventName => $params) {
				if (is_numeric($eventName) && is_string($params)) { // [EventName, ...]
					[, $method] = Event::parseName($params);
					$eventNames[] = ltrim($params, '\\');
					if (!method_exists($listenerInst, $method)) {
						throw new \Nette\Utils\AssertionException(sprintf('Event listener %s::%s() is not implemented.', $defClass, $method));
					}

				} elseif (is_string($eventName)) { // [EventName => ???, ...]
					$eventNames[] = ltrim($eventName, '\\');

					if (is_string($params)) { // [EventName => method, ...]
						if (!method_exists($listenerInst, $params)) {
							throw new \Nette\Utils\AssertionException(sprintf('Event listener %s::%s() is not implemented.', $defClass, $params));
						}

					} elseif (is_string($params[0])) { // [EventName => [method, priority], ...]
						if (!method_exists($listenerInst, $params[0])) {
							throw new \Nette\Utils\AssertionException(sprintf('Event listener %s::%s() is not implemented.', $defClass, $params[0]));
						}

					} else {
						foreach ($params as $listener) { // [EventName => [[method, priority], ...], ...]
							if (!method_exists($listenerInst, $listener[0])) {
								throw new \Nette\Utils\AssertionException(sprintf('Event listener %s::%s() is not implemented.', $defClass, $listener[0]));
							}
						}
					}
				}
			}

			$this->listeners[$serviceName] = array_unique($eventNames);
		}
	}

	private function isAlias(Definition $definition)
	{
		if ($definition instanceof ServiceDefinition) {
			return $definition->getFactory()->getEntity() !== NULL && (
					$definition->getFactory()->getEntity() instanceof Reference
					|| (is_string($definition->getEntity()) && substr($definition->getEntity(), 0, 1) === '@')
				);
		}

		return FALSE;
	}

	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 */
	private function autowireEvents(DIContainerBuilder $builder)
	{
		foreach ($builder->getDefinitions() as $def) {
			if ($def instanceof ImportedDefinition) {
				continue;
			}

			if ($this->isAlias($def)) {
				continue; // alias
			}

			$class = $def->getType();
			if ($class === NULL || !class_exists($class)) {
				if ($def instanceof AccessorDefinition) {
					continue;
				}

				if ($def instanceof FactoryDefinition && $def->getResultDefinition()->getFactory()->getEntity() === NULL) {
					continue;
				}

				if ($def instanceof FactoryDefinition) {
					$entity = $def->getResultDefinition()->getEntity();
					$class = $entity instanceof Definition ? $entity->getType() : $entity;
				} elseif ($def instanceof ServiceDefinition) {
					/** @var string $class */
					$class = $def->getEntity();
				} else {
					continue;
				}

				if (is_array($class)) {
					continue;
				}

				if ($class === NULL || !class_exists($class)) {
					continue;
				}
			}
			if ($def instanceof AccessorDefinition || $def instanceof ImportedDefinition) {
				continue;
			}

			$this->bindEventProperties($def, new \ReflectionClass($class));
		}
	}

	protected function bindEventProperties(Definition $def, \ReflectionClass $class)
	{
		/** @var \Nette\DI\Definitions\ServiceDefinition $def */
		$def = $def instanceof FactoryDefinition ? $def->getResultDefinition() : $def;

		foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$name = $property->getName();
			if (!preg_match('#^on[A-Z]#', $name)) {
				continue;
			}

			if (self::propertyHasAnnotation($property, 'persistent') || self::propertyHasAnnotation($property, 'inject')) { // definitely not an event
				continue;
			}

			$dispatchAnnotation = self::propertyHasAnnotation($property, 'globalDispatchFirst');
			$def->addSetup('$' . $name, [
				new Statement($this->prefix('@manager') . '::createEvent', [
					[$class->getName(), $name],
					new PhpLiteral('$service->' . $name),
					NULL,
					$dispatchAnnotation ?? $this->loadedConfig['globalDispatchFirst'],
				]),
			]);
		}
	}

	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 */
	private function optimizeListeners(DIContainerBuilder $builder)
	{
		$listeners = [];
		foreach ($this->listeners as $serviceName => $eventNames) {
			foreach ($eventNames as $eventName) {
				[$namespace, $event] = Event::parseName($eventName);
				$listeners[$eventName][] = $serviceName;

				if (!$namespace || !class_exists($namespace)) {
					continue; // it might not even be a "classname" event namespace
				}

				// find all subclasses and register the listener to all the classes dispatching them
				foreach ($builder->getDefinitions() as $def) {
					$class = $def->getClass();
					if (!$class) {
						continue; // ignore unresolved classes
					}

					if (is_subclass_of($class, $namespace)) {
						$listeners[sprintf('%s::%s', $class, $event)][] = $serviceName;
					}
				}
			}
		}

		foreach ($listeners as $id => $subscribers) {
			$listeners[$id] = array_unique($subscribers);
		}

		/** @var \Nette\DI\Definitions\ServiceDefinition $manager */
		$manager = $builder->getDefinition($this->prefix('manager'));
		$manager->setFactory(LazyEventManager::class, [$listeners])
			->setSetup($this->allowedManagerSetup);
	}

	/**
	 * @param string|\stdClass $statement
	 * @return \Nette\DI\Statement[]
	 */
	private function filterArgs($statement)
	{
		return DIHelpers::filterArguments([is_string($statement) ? new Statement($statement) : $statement]);
	}

	/**
	 * @param \Nette\Configurator $configurator
	 */
	public static function register(Configurator $configurator)
	{
		$configurator->onCompile[] = static function ($config, Compiler $compiler) {
			$compiler->addExtension('events', new EventsExtension());
		};
	}

	/**
	 * @param string|NULL $class
	 * @return \Doctrine\Common\EventSubscriber
	 */
	private static function createEventSubscriberInstanceWithoutConstructor($class)
	{
		if ($class === NULL) {
			throw new \InvalidArgumentException('Given class cannot be NULL');
		}

		$instance = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
		if (!$instance instanceof EventSubscriber) {
			throw new \Kdyby\Events\UnexpectedValueException(sprintf('The class %s does not implement %s', $class, EventSubscriber::class));
		}

		return $instance;
	}

	private static function propertyHasAnnotation(ReflectionProperty $property, string $annotation): ?bool
	{
		$comment = $property->getDocComment();

		$exists = \strpos($comment, "@$annotation") !== FALSE;

		return $exists ? !\stripos($comment, "@$annotation false") : NULL;
	}
}
