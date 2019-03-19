<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Events\EventArgs.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Doctrine\Common\EventArgs;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class EventArgsTest extends \Tester\TestCase
{

	public function testImplementsDoctrineEventArgs(): void
	{
		$args = new EventArgsMock();
		Assert::true($args instanceof EventArgs);
	}

}

(new EventArgsTest())->run();
