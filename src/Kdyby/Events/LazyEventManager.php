<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * @license http://www.kdyby.org/license
 */

namespace Kdyby\Events;

use Doctrine;
use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LazyEventManager extends EventManager
{

	/**
	 * @var array
	 */
	private $listenerIds;

	/**
	 * @var \Nette\DI\Container
	 */
	private $container;



	/**
	 * @param array $listenerIds
	 * @param \Nette\DI\Container $container
	 */
	public function __construct(array $listenerIds, Nette\DI\Container $container)
	{
		$this->listenerIds = $listenerIds;
		$this->container = $container;
	}



	/**
	 * @param string $eventName
	 * @return \Doctrine\Common\EventSubscriber[]
	 */
	public function getListeners($eventName = NULL)
	{
		if (!empty($this->listenerIds[$eventName])) {
			$this->initializeListener($eventName);
		}

		if ($eventName === NULL) {
			foreach (array_keys($this->listenerIds) as $type) {
				$this->initializeListener($type);
			}
		}

		return parent::getListeners($eventName);
	}



	private function initializeListener($eventName)
	{
		foreach ($this->listenerIds[$eventName] as $serviceName) {
			$subscriber = $this->container->getService($serviceName);
			/** @var Doctrine\Common\EventSubscriber $subscriber */
			$this->addEventListener($eventName, $subscriber);
		}
		unset($this->listenerIds[$eventName]);
	}

}
