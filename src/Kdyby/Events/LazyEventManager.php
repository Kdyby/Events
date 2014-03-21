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
 * Is aware of DI Container and accepts map of listener service ids which then loads when needed.
 *
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

    private $initializing = array();



	/**
	 * @param array $listenerIds
	 * @param \Nette\DI\Container $container
	 */
	public function __construct(array $listenerIds, Nette\DI\Container $container)
	{
		$this->listenerIds = $listenerIds;
		$this->container = $container;
	}



	public function setPanel(Diagnostics\Panel $panel)
	{
		parent::setPanel($panel);
		$panel->setServiceIds($this->listenerIds);
	}



	/**
	 * @param string $eventName
	 * @param bool $asCallbacks
	 * @return \Doctrine\Common\EventSubscriber[]
	 */
	public function getListeners($eventName = NULL, $asCallbacks = FALSE)
	{
		if (!empty($this->listenerIds[$eventName])) {
			$this->initializeListener($eventName);
		}

		if ($eventName === NULL) {
			while (($type = key($this->listenerIds)) !== NULL) {
				$this->initializeListener($type);
			}
		}

		return parent::getListeners($eventName, $asCallbacks);
	}



	/**
	 * @param array|string $unsubscribe
	 * @param Doctrine\Common\EventSubscriber|array $subscriber
	 */
	public function removeEventListener($unsubscribe, $subscriber = NULL)
	{
		foreach ((array) $unsubscribe as $eventName) {
			if (array_key_exists($eventName, $this->listenerIds)) {
				$this->initializeListener($eventName);
			}
		}

		parent::removeEventListener($unsubscribe, $subscriber);
	}



	/**
	 * @param string $eventName
	 */
	private function initializeListener($eventName)
	{
		foreach ($this->listenerIds[$eventName] as $serviceName) {
            if(isset($this->initializing[$serviceName])) {
                continue;
            }
			$subscriber = $this->container->getService($serviceName);
            unset($this->initializing[$serviceName]);
			/** @var Doctrine\Common\EventSubscriber $subscriber */

			$this->addEventSubscriber($subscriber);
		}

		unset($this->listenerIds[$eventName]);
	}

}
