<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008, 2012 Filip Procházka (filip@prochazka.su)
 *
 * @license http://www.kdyby.org/license
 */

namespace Kdyby\Events;

use Kdyby;
use Nette\Application\Application;
use Nette\Application\IResponse;
use Nette\Application\Request;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
final class LifeCycleEvent extends Nette\Object
{

	/**
	 * Occurs before the application loads presenter
	 */
	const onStartup = 'onStartup';

	/**
	 * Occurs before the application shuts down
	 */
	const onShutdown = 'onShutdown';

	/**
	 * Occurs when a new request is ready for dispatch;
	 */
	const onRequest = 'onRequest';

	/**
	 * Occurs when a new response is received
	 */
	const onResponse = 'onResponse';

	/**
	 * Occurs when an unhandled exception occurs in the application
	 */
	const onError = 'onError';

}
