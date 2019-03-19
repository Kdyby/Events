<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Events;

use Nette\Application\Application;

final class LifeCycleEvent
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * Occurs before the application loads presenter
	 */
	public const onStartup = Application::class . '::onStartup';

	/**
	 * Occurs before the application shuts down
	 */
	public const onShutdown = Application::class . '::onShutdown';

	/**
	 * Occurs when a new request is ready for dispatch;
	 */
	public const onRequest = Application::class . '::onRequest';

	/**
	 * Occurs when a presenter is created
	 */
	public const onPresenter = Application::class . '::onPresenter';

	/**
	 * Occurs when a new response is received
	 */
	public const onResponse = Application::class . '::onResponse';

	/**
	 * Occurs when an unhandled exception occurs in the application
	 */
	public const onError = Application::class . '::onError';

}
