<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Events\Diagnostics;

use Closure;
use Doctrine\Common\EventArgs;
use Kdyby\Events\Event;
use Kdyby\Events\EventManager;
use Nette\DI\Container as DIContainer;
use Nette\Utils\Arrays;
use Nette\Utils\Callback;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionProperty;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\Helpers as TracyHelpers;

class Panel implements \Tracy\IBarPanel
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \Nette\DI\Container
	 */
	private $sl;

	/**
	 * @var array
	 */
	private $events = [];

	/**
	 * @var array
	 */
	private $dispatchLog = [];

	/**
	 * @var array
	 */
	private $dispatchTree = [];

	/**
	 * @var array|NULL
	 */
	private $dispatchTreePointer;

	/**
	 * @var array
	 */
	private $listenerIds = [];

	/**
	 * @var array
	 */
	private $inlineCallbacks = [];

	/**
	 * @var array|NULL
	 */
	private $registeredClasses;

	/**
	 * @var bool|array<string, mixed>
	 */
	public $renderPanel = TRUE;

	public function __construct(DIContainer $sl)
	{
		$this->sl = $sl;
	}

	public function setEventManager(EventManager $evm): void
	{
		$evm->setPanel($this);
	}

	public function setServiceIds(array $listenerIds): void
	{
		if (!$this->renderPanel || (is_array($this->renderPanel) && !$this->renderPanel['listeners'])) {
			return;
		}
		$this->listenerIds = $listenerIds;
	}

	public function registerEvent(Event $event): void
	{
		$this->events[] = $event;
		$event->setPanel($this);
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param string|null $eventName
	 */
	public function eventDispatch($eventName, EventArgs $args = NULL): void
	{
		if (!$this->renderPanel) {
			return;
		}

		if (!is_array($this->renderPanel) || $this->renderPanel['dispatchLog']) {
			$this->dispatchLog[$eventName][] = $args;
		}

		if (!is_array($this->renderPanel) || $this->renderPanel['dispatchTree']) {
			// meta is array of (parent-ref, name, args, children)
			$meta = [&$this->dispatchTreePointer, $eventName, $args, []];
			if ($this->dispatchTreePointer === NULL) {
				$this->dispatchTree[] = &$meta;
			} else {
				$this->dispatchTreePointer[3][] = &$meta;
			}
			$this->dispatchTreePointer = &$meta;
		}
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param string|null $eventName
	 */
	public function eventDispatched($eventName, EventArgs $args = NULL): void
	{
		if (!$this->renderPanel || (is_array($this->renderPanel) && !$this->renderPanel['dispatchTree'])) {
			return;
		}
		$this->dispatchTreePointer = &$this->dispatchTreePointer[0];
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param string|null $eventName
	 * @param array $inlineCallbacks
	 */
	public function inlineCallbacks($eventName, $inlineCallbacks): void
	{
		if (!$this->renderPanel) {
			return;
		}
		$this->inlineCallbacks[$eventName] = (array) $inlineCallbacks;
	}

	/**
	 * Renders HTML code for custom tab.
	 */
	public function getTab(): ?string
	{
		if (empty($this->events)) {
			return NULL;
		}

		$iconFile = file_get_contents(__DIR__ . '/icon.png');
		if ($iconFile === FALSE) {
			throw new \RuntimeException('File icon.png not found');
		}

		return '<span title="Kdyby/Events">'
			. '<img width="16" height="16" src="data:image/png;base64,' . base64_encode($iconFile) . '" />'
			. '<span class="tracy-label">' . count(Arrays::flatten($this->dispatchLog)) . ' calls</span>'
			. '</span>';
	}

	/**
	 * Renders HTML code for custom panel.
	 */
	public function getPanel(): ?string
	{
		if (!$this->renderPanel) {
			return '';
		}

		if (empty($this->events)) {
			return NULL;
		}

		$visited = [];

		$h = 'htmlspecialchars';

		$s = '';
		$s .= $this->renderPanelDispatchLog($visited);
		$s .= $this->renderPanelEvents($visited);
		$s .= $this->renderPanelListeners($visited);

		if ($s) {
			$s .= '<tr class="blank"><td colspan=2>&nbsp;</td></tr>';
		}

		$s .= $this->renderPanelDispatchTree();

		$totalEvents = (string) count($this->listenerIds);
		$totalListeners = (string) count(array_unique(Arrays::flatten($this->listenerIds)));

		return '<style>' . $this->renderStyles() . '</style>' .
			'<h1>' . $h($totalEvents) . ' registered events, ' . $h($totalListeners) . ' registered listeners</h1>' .
			'<div class="nette-inner tracy-inner nette-KdybyEventsPanel"><table>' . $s . '</table></div>';
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param array $visited
	 */
	private function renderPanelDispatchLog(&$visited): string
	{
		if (!$this->renderPanel || (is_array($this->renderPanel) && !$this->renderPanel['dispatchLog'])) {
			return '';
		}

		$h = 'htmlspecialchars';
		$s = '';

		foreach ($this->dispatchLog as $eventName => $calls) {
			$s .= '<tr><th colspan=2 id="' . $this->formatEventId($eventName) . '">' . count($calls) . 'x ' . $h($eventName) . '</th></tr>';
			$visited[] = $eventName;

			$s .= $this->renderListeners($this->getInlineCallbacks($eventName));

			if (empty($this->listenerIds[$eventName])) {
				$s .= '<tr><td>&nbsp;</td><td>no system listeners</th></tr>';

			} else {
				$s .= $this->renderListeners($this->listenerIds[$eventName]);
			}

			$s .= $this->renderCalls($calls);
		}

		return $s;
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param array $visited
	 */
	private function renderPanelEvents(&$visited): string
	{
		if (!$this->renderPanel || (is_array($this->renderPanel) && !$this->renderPanel['events'])) {
			return '';
		}

		$h = 'htmlspecialchars';
		$s = '';
		foreach ($this->events as $event) {
			/** @var \Kdyby\Events\Event $event */
			if (in_array($event->getName(), $visited, TRUE)) {
				continue;
			}

			$calls = $this->getEventCalls($event->getName());
			$s .= '<tr class="blank"><td colspan=2>&nbsp;</td></tr>';
			$s .= '<tr><th colspan=2>' . count($calls) . 'x ' . $h($event->getName()) . '</th></tr>';
			$visited[] = $event->getName();

			$s .= $this->renderListeners($this->getInlineCallbacks($event->getName()));

			if (empty($this->listenerIds[$event->getName()])) {
				$s .= '<tr><td>&nbsp;</td><td>no system listeners</th></tr>';

			} else {
				$s .= $this->renderListeners($this->listenerIds[$event->getName()]);
			}

			$s .= $this->renderCalls($calls);
		}

		return $s;
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param array $visited
	 */
	private function renderPanelListeners(&$visited): string
	{
		if (!$this->renderPanel || (is_array($this->renderPanel) && !$this->renderPanel['listeners'])) {
			return '';
		}

		$h = 'htmlspecialchars';
		$s = '';
		foreach ($this->listenerIds as $eventName => $ids) {
			if (in_array($eventName, $visited, TRUE)) {
				continue;
			}

			$calls = $this->getEventCalls($eventName);
			$s .= '<tr class="blank"><td colspan=2>&nbsp;</td></tr>';
			$s .= '<tr><th colspan=2>' . count($calls) . 'x ' . $h($eventName) . '</th></tr>';

			$s .= $this->renderListeners($this->getInlineCallbacks($eventName));

			if (empty($ids)) {
				$s .= '<tr><td>&nbsp;</td><td>no system listeners</th></tr>';

			} else {
				$s .= $this->renderListeners($ids);
			}

			$s .= $this->renderCalls($calls);
		}

		return $s;
	}

	private function renderPanelDispatchTree(): string
	{
		if (!$this->renderPanel || (is_array($this->renderPanel) && !$this->renderPanel['dispatchTree'])) {
			return '';
		}

		$s = '<tr><th colspan=2>Summary event call graph</th></tr>';
		foreach ($this->dispatchTree as $item) {
			$s .= '<tr><td colspan=2>';
			$s .= $this->renderTreeItem($item);
			$s .= '</td></tr>';
		}

		return $s;
	}

	/**
	 * Renders an item in call graph.
	 */
	private function renderTreeItem(array $item): string
	{
		$h = 'htmlspecialchars';

		$s = '<ul><li>';
		$s .= '<a href="#' . $this->formatEventId($item[1]) . '">' . $h($item[1]) . '</a>';
		if ($item[2]) {
			$s .= ' (<a href="#' . $this->formatArgsId($item[2]) . '">' . get_class($item[2]) . '</a>)';
		}

		if ($item[3]) {
			foreach ($item[3] as $child) {
				$s .= $this->renderTreeItem($child);
			}
		}

		return $s . '</li></ul>';
	}

	private function getEventCalls(string $eventName): array
	{
		return !empty($this->dispatchLog[$eventName]) ? $this->dispatchLog[$eventName] : [];
	}

	private function getInlineCallbacks(string $eventName): array
	{
		return !empty($this->inlineCallbacks[$eventName]) ? $this->inlineCallbacks[$eventName] : [];
	}

	private function renderListeners(array $ids): string
	{
		static $addIcon;
		if (empty($addIcon)) {
			$icon = file_get_contents(__DIR__ . '/add.png');
			if ($icon === FALSE) {
				throw new \RuntimeException('Missing file add.png');
			}

			$addIcon = '<img width="18" height="18" src="data:image/png;base64,' . base64_encode($icon) . '" title="Listener" />';
		}

		$registeredClasses = (array) $this->getClassMap();

		$h = 'htmlspecialchars';

		$shortFilename = static function (ReflectionFunctionAbstract $refl): string {
			$title = '.../' . basename($refl->getFileName() ?: 'unknown.php') . ':' . (string) $refl->getStartLine();

			/** @var string|NULL $editor */
			$editor = TracyHelpers::editorUri($refl->getFileName() ?: 'unknown.php', $refl->getStartLine() ?: 0);
			if ($editor !== NULL) {
				return sprintf(' defined at <a href="%s">%s</a>', htmlspecialchars($editor), $title);
			}

			return ' defined at ' . $title;
		};

		$s = '';
		foreach ($ids as $id) {
			if (is_callable($id)) {
				$s .= '<tr><td width=18>' . $addIcon . '</td><td><pre class="nette-dump"><span class="nette-dump-object">' .
					Callback::toString($id) . ($id instanceof Closure ? $shortFilename(Callback::toReflection($id)) : '') .
					'</span></span></th></tr>';

				continue;
			}

			$class = array_search($id, $registeredClasses, TRUE);
			if (!$this->sl->isCreated($id) && $class !== FALSE) {
				$classRefl = new ReflectionClass($class);

				$s .= '<tr><td width=18>' . $addIcon . '</td><td><pre class="nette-dump"><span class="nette-dump-object">' .
					$h($classRefl->getName()) .
					'</span></span></th></tr>';

			} else {
				try {
					$s .= '<tr><td width=18>' . $addIcon . '</td><td>' . self::dumpToHtml($this->sl->getService($id)) . '</th></tr>';

				} catch (\Exception $e) {
					$s .= sprintf("<tr><td colspan=2>Service %s cannot be loaded because of exception<br><br>\n%s</td></th>", $id, (string) $e);
				}
			}
		}

		return $s;
	}

	/**
	 * @param string|object|array $structure
	 */
	private static function dumpToHtml($structure): string
	{
		return Dumper::toHtml($structure, [Dumper::COLLAPSE => TRUE, Dumper::DEPTH => 2]);
	}

	private function getClassMap(): ?array
	{
		if ($this->registeredClasses !== NULL) {
			return $this->registeredClasses;
		}

		$refl = new ReflectionProperty(DIContainer::class, 'meta');
		$refl->setAccessible(TRUE);
		$meta = $refl->getValue($this->sl);

		$this->registeredClasses = [];
		foreach ($meta[DIContainer::TYPES] as $type => $serviceIds) {
			if (isset($this->registeredClasses[$type])) {
				$this->registeredClasses[$type] = FALSE;
				continue;
			}

			$this->registeredClasses[$type] = $serviceIds;
		}

		return $this->registeredClasses;
	}

	private function renderCalls(array $calls): string
	{
		static $runIcon;
		if (empty($runIcon)) {
			$runIconFile = file_get_contents(__DIR__ . '/run.png');
			if ($runIconFile === FALSE) {
				throw new \RuntimeException('File run.png not found');
			}

			$runIcon = '<img width="18" height="18" src="data:image/png;base64,' . base64_encode($runIconFile) . '" title="Event dispatch" />';
		}

		$s = '';
		foreach ($calls as $args) {
			$s .= '<tr><td width=18>' . $runIcon . '</td>';
			$s .= '<td' . ($args ? ' id="' . $this->formatArgsId($args) . '">' . self::dumpToHtml($args) : '>dispatched without arguments');
			$s .= '</td></tr>';
		}

		return $s;
	}

	private function formatEventId(string $name): string
	{
		return 'kdyby-event-' . md5($name);
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param object $args
	 */
	private function formatArgsId($args): string
	{
		return 'kdyby-event-arg-' . md5(spl_object_hash($args));
	}

	protected function renderStyles(): string
	{
		return <<<CSS
			#nette-debug .nette-panel .nette-KdybyEventsPanel,
			#tracy-debug .tracy-panel .nette-KdybyEventsPanel { width: 670px !important;  }
			#nette-debug .nette-panel .nette-KdybyEventsPanel table,
			#tracy-debug .tracy-panel .nette-KdybyEventsPanel table { width: 655px !important; }
			#nette-debug .nette-panel .nette-KdybyEventsPanel table th,
			#tracy-debug .tracy-panel .nette-KdybyEventsPanel table th { font-size: 16px; }
			#nette-debug .nette-panel .nette-KdybyEventsPanel table tr td:first-child,
			#tracy-debug .tracy-panel .nette-KdybyEventsPanel table tr td:first-child { padding-bottom: 0; }
			#nette-debug .nette-panel .nette-KdybyEventsPanel table tr.blank td,
			#tracy-debug .tracy-panel .nette-KdybyEventsPanel table tr.blank td { background: white; height:25px; border-left:0; border-right:0; }
			#nette-debug .nette-panel .nette-KdybyEventsPanel table tr td ul,
			#tracy-debug .tracy-panel .nette-KdybyEventsPanel table tr td ul { background: url(data:image/gif;base64,R0lGODlhCQAJAIABAIODg////yH5BAEAAAEALAAAAAAJAAkAAAIPjI8GebDsHopSOVgb26EAADs=) 0 5px no-repeat; padding-left: 12px; list-style-type: none; }
CSS;
	}

	public static function register(EventManager $eventManager, DIContainer $sl): Panel
	{
		$panel = new static($sl);
		$panel->setEventManager($eventManager);
		Debugger::getBar()->addPanel($panel);

		return $panel;
	}

}
