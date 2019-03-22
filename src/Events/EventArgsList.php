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

class EventArgsList extends \Kdyby\Events\EventArgs
{

	/**
	 * @var array
	 */
	private $args;

	/**
	 * @param array $args
	 */
	public function __construct(array $args)
	{
		$this->args = $args;
	}

	public function getArgs(): array
	{
		return $this->args;
	}

}
