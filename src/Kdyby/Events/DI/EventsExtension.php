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
use Kdyby;
use Nette;
use Nette\PhpGenerator\PhpLiteral;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class EventsExtension extends Nette\Config\CompilerExtension
{
	const EVENT_TAG = 'kdyby.event';
	const SUBSCRIBER_TAG = 'kdyby.subscriber';

	/**
	 * @var array
	 */
	public $defaults = array(
		'subscribers' => array(),
		'validate' => TRUE,
		'autowire' => TRUE,
		'optimize' => TRUE,
	);

	/**
	 * @var array
	 */
	private $listeners = array();

	/**
	 * @var array
	 */
	private $allowedManagerSetup = array();



	public function loadConfiguration()
	{
		$this->listeners = array();
		$this->allowedManagerSetup = array();

		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$builder->addDefinition($this->prefix('manager'))
			->setClass('Kdyby\Events\EventManager')
			->setInject(FALSE);

		Nette\Utils\Validators::assertField($config, 'subscribers', 'array');
		foreach ($config['subscribers'] as $subscriber) {
			$def = $builder->addDefinition($this->prefix('subscriber.' . md5(Nette\Utils\Json::encode($subscriber))));
			list($def->factory) = Nette\Config\Compiler::filterArguments(array(
				is_string($subscriber) ? new Nette\DI\Statement($subscriber) : $subscriber
			));

			list($subscriberClass) = (array) $builder->normalizeEntity($def->factory->entity);
			if (class_exists($subscriberClass)) {
				$def->class = $subscriberClass;
			}

			$def->setAutowired(FALSE);
			$def->addTag(self::SUBSCRIBER_TAG);
		}
	}



	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$manager = $builder->getDefinition($this->prefix('manager'));
		foreach (array_keys($builder->findByTag(self::SUBSCRIBER_TAG)) as $serviceName) {
			$manager->addSetup('addEventSubscriber', array('@' . $serviceName));
		}

		Nette\Utils\Validators::assertField($config, 'validate', 'bool');
		if ($config['validate']) {
			$this->validateSubscribers($builder, $manager);
		}

		Nette\Utils\Validators::assertField($config, 'autowire', 'bool');
		if ($config['autowire']) {
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



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 * @param \Nette\DI\ServiceDefinition $manager
	 * @throws \Nette\Utils\AssertionException
	 */
	private function validateSubscribers(Nette\DI\ContainerBuilder $builder, Nette\DI\ServiceDefinition $manager)
	{
		foreach ($manager->setup as $stt) {
			if ($stt->entity !== 'addEventSubscriber') {
				$this->allowedManagerSetup[] = $stt;
				continue;
			}

			try {
				$serviceName = $builder->getServiceName(reset($stt->arguments));
				$def = $builder->getDefinition($serviceName);

			} catch (\Exception $e) {
				throw new Nette\Utils\AssertionException(
					"Please, do not register listeners directly to service '" . $this->prefix('manager') . "'. " .
					"Use section '" . $this->name . ": subscribers: ', or tag the service as '" . self::SUBSCRIBER_TAG . "'.",
					0, $e
				);
			}

			if (!$def->class || !class_exists($def->class)) {
				throw new Nette\Utils\AssertionException("Please, specify existing class for service '$serviceName' explicitly.");
			}

			if (!in_array('Doctrine\Common\EventSubscriber' , class_implements($def->class))) {
				// the minimum is Doctrine EventSubscriber, but recommend is Kdyby Subscriber
				throw new Nette\Utils\AssertionException("Subscriber '$serviceName' doesn't implement Kdyby\\Events\\Subscriber.");
			}

			$eventNames = array();
			$listenerInst = Nette\PhpGenerator\Helpers::createObject($def->class, array());
			/** @var EventSubscriber $listenerInst */
			foreach ($listenerInst->getSubscribedEvents() as $eventName) {
				list(, $method) = Kdyby\Events\Event::parseName($eventName);
				$eventNames[] = ltrim($eventName, '\\');
				if (!method_exists($listenerInst, $method)) {
					throw new Nette\Utils\AssertionException("Event listener " . $def->class . "::{$method}() is not implemented.");
				}
			}

			$this->listeners[$serviceName] = $eventNames;
		}
	}



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 */
	private function autowireEvents(Nette\DI\ContainerBuilder $builder)
	{
		foreach ($builder->getDefinitions() as $def) {
			/** @var Nette\DI\ServiceDefinition $def */
			if (!class_exists($class = $builder->expand($def->class))) {
				continue;
			}

			$properties = Nette\Reflection\ClassType::from($class)->getProperties(Nette\Reflection\Property::IS_PUBLIC);
			foreach ($properties as $property) {
				if (!preg_match('#^on[A-Z]#', $name = $property->getName())) {
					continue 1;
				}

				$def->addSetup('$' . $name, array(
					new Nette\DI\Statement($this->prefix('@manager') . '::createEvent', array(
						$property->getDeclaringClass()->getName() . '::' . $name,
						new PhpLiteral('$service->' . $name)
					))
				));
			}
		}
	}



	/**
	 * @param \Nette\DI\ContainerBuilder $builder
	 */
	private function optimizeListeners(Nette\DI\ContainerBuilder $builder)
	{
		$listeners = array();
		foreach ($this->listeners as $serviceName => $eventNames) {
			foreach ($eventNames as $eventName) {
				list($namespace, $event) = Kdyby\Events\Event::parseName($eventName);
				$listeners[$eventName][] = $serviceName;
				if ($namespace !== NULL) {
					$listeners[$event][] = $serviceName;
				}
			}
		}

		$builder->getDefinition($this->prefix('manager'))
			->setClass('Kdyby\Events\LazyEventManager')
			->setArguments(array($listeners))
			->setup = $this->allowedManagerSetup;
	}



	/**
	 * @param \Nette\Config\Configurator $configurator
	 */
	public static function register(Nette\Config\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\Config\Compiler $compiler) {
			$compiler->addExtension('events', new EventsExtension());
		};
	}

}
