<?php

/**
 * Stoppable event contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Contract for an event whose propagation can be stopped. When the dispatcher
 * sees that propagation has been stopped, it calls no further listeners for that
 * event. The companion `Stoppable` trait provides a ready-made implementation.
 */
interface StoppableEvent
{
	/**
	 * Determines whether the previous listener halted propagation, in which
	 * case the dispatcher must not call any further listeners.
	 */
	public function isPropagationStopped(): bool;
}
