<?php

/**
 * Listener contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Marks a class as a listener that can be registered by its class name rather
 * than as a ready-made callable — e.g. `$registry->listen(Event::class,
 * MyListener::class)`. The registry resolves the class to an instance the first
 * time its event fires (lazily) and calls it, so the object is only built if the
 * event actually occurs.
 *
 * This is a marker only: it declares no method so that implementations keep a
 * naturally typed `__invoke()` (a method declared here would force the generic
 * `__invoke(object $event)` signature). A listener class must therefore be
 * invokable — define `public function __invoke(TheEvent $event): void`.
 *
 * Instances are built with `new $class()` by default, so a bare listener class
 * needs no constructor arguments. To resolve listeners with dependencies, give
 * the registry a resolver (such as a PSR-11 container) via its constructor.
 */
interface Listener
{
}
