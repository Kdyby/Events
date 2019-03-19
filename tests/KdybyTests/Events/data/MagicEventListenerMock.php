<?php

declare(strict_types = 1);

namespace KdybyTests\Events;

class MagicEventListenerMock implements \Kdyby\Events\CallableSubscriber
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var array
	 */
	public $calls = [];

	public function getSubscribedEvents(): array
	{
		return [
			'onQuux',
			'onCorge',
		];
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param string $name
	 * @param array $arguments
	 */
	public function __call($name, $arguments): void
	{
		$args = $arguments[0];
		$args->calls[] = [self::class . '::' . $name, $arguments];
		$this->calls[] = [self::class . '::' . $name, $arguments];
	}

}
