<?php

namespace KdybyTests\Events;

use Kdyby;
use Kdyby\Events\Event;
use Nette;
use Tester;
use Tester\Assert;



require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/mocks.php';


$event = new Event('Foo::onSomething');
