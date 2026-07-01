<?php

/**
 * Listener-aware dispatcher contract.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-event
 */

declare(strict_types=1);

namespace X3P0\Event;

/**
 * Contract for the dispatcher's full public surface: a `Dispatcher` that is also
 * a `ListenerRegistry`, so a single object both fires events and accepts the
 * listeners and subscribers that handle them.
 *
 * The registration methods are a facade — they delegate to the provider the
 * dispatcher was built with — so they carry the `ListenerRegistry` precondition:
 * the backing provider must accept registrations, or they throw. Depend on this
 * contract when you want one object for both jobs; depend on `Dispatcher` to only
 * fire events, or `ListenerRegistry` to only register them.
 */
interface ListenerAwareDispatcher extends Dispatcher, ListenerRegistry
{
}
