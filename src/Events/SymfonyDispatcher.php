<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Events;

use Symfony\Component\EventDispatcher\Event as SymfonyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SymfonyDispatcher implements \Symfony\Component\EventDispatcher\EventDispatcherInterface
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \Kdyby\Events\EventManager
	 */
	private $evm;

	public function __construct(EventManager $eventManager)
	{
		$this->evm = $eventManager;
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param string|null $eventName
	 * @param \Symfony\Component\EventDispatcher\Event $event
	 */
	public function dispatch($eventName, SymfonyEvent $event = NULL): void
	{
		$this->evm->dispatchEvent($eventName, new EventArgsList([$event]));
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function addListener($eventName, $listener, $priority = 0): void
	{
		throw new \Kdyby\Events\NotSupportedException();
	}

	public function addSubscriber(EventSubscriberInterface $subscriber): void
	{
		throw new \Kdyby\Events\NotSupportedException();
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function removeListener($eventName, $listener): void
	{
		throw new \Kdyby\Events\NotSupportedException();
	}

	public function removeSubscriber(EventSubscriberInterface $subscriber): void
	{
		throw new \Kdyby\Events\NotSupportedException();
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function getListenerPriority($eventName, $listener): void
	{
		throw new \Kdyby\Events\NotSupportedException();
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function getListeners($eventName = NULL): array
	{
		return $this->evm->getListeners($eventName);
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function hasListeners($eventName = NULL): bool
	{
		return $this->evm->hasListeners($eventName);
	}

}
