# X3P0: Event

A small, dependency-free **event system** for WordPress plugins and themes.

If you've used WordPress hooks (`do_action()` / `add_action()`), you already
understand the idea — this library is the same idea expressed with **objects**
instead of stringly-typed tags, and it can still talk to your existing hooks.

---

## Why would I use this instead of hooks?

WordPress hooks are great, but they have rough edges:

| WordPress hooks                                                               | This library                                                               |
|-------------------------------------------------------------------------------|----------------------------------------------------------------------------|
| `do_action('my_plugin_order_placed', $id, $total, $user)`                     | `$dispatcher->dispatch(new OrderPlaced($id, $total, $user))`               |
| Data is passed as **positional arguments** you must remember and keep in sync | Data lives on a **typed event object** with named properties               |
| Tag names are strings — typos fail silently, IDEs can't help                  | Events are classes — autocomplete, "find usages", and refactoring all work |
| A listener is `add_action('tag', 'callback')`                                 | A listener is any callable, registered against the event class             |

You don't have to choose, though. The library includes a **bridge** so the rest
of the WordPress world can still hook your events with plain `add_action()`
(see [Talking to WordPress hooks](#talking-to-wordpress-hooks)).

---

## The five ideas

If you know hooks, here's the whole vocabulary mapped to things you already know:

| Term                  | What it is                                      | Closest WordPress idea                  |
|-----------------------|-------------------------------------------------|-----------------------------------------|
| **Event**             | An object describing something that happened    | The moment you'd call `do_action()`     |
| **Listener**          | A callable that reacts to an event              | An `add_action()` callback              |
| **Dispatcher**        | Sends an event to its listeners                 | `do_action()` itself                    |
| **Listener provider** | Decides *which* listeners apply to an event     | (hidden inside WordPress's hook system) |
| **Subscriber**        | One class that registers many listeners at once | (no direct equivalent)                  |

That's it. An **event** is data. A **listener** is code that runs when that data
shows up. The **dispatcher** connects the two, asking a **provider** for the
right listeners.

---

## Quick start

```php
use X3P0\Breadcrumbs\Events\Provider\PriorityListenerProvider;
use X3P0\Breadcrumbs\Events\EventDispatcher;

// 1. Create a provider (holds your listeners) and a dispatcher (fires events).
$listeners  = new PriorityListenerProvider();
$dispatcher = new EventDispatcher($listeners);

// 2. An event is just a class. Give it whatever data it needs.
final class PostViewed
{
	public function __construct(public readonly int $postId) {}
}

// 3. A listener is any callable that accepts the event.
$listeners->listen(PostViewed::class, function (PostViewed $event): void {
	error_log("Post {$event->postId} was viewed.");
});

// 4. Dispatch the event wherever it happens.
$dispatcher->dispatch(new PostViewed(42));
```

`dispatch()` returns the same event object it was given, so you can read
anything the listeners changed on it (see the next section).

---

## Events can carry data back

Because an event is an object passed by reference, listeners can **change it**,
and the code that dispatched it can read the result. This is how you'd replace a
WordPress *filter* (`apply_filters()`):

```php
final class PriceCalculated
{
	public function __construct(public float $price) {}
}

$listeners->listen(PriceCalculated::class, function (PriceCalculated $event): void {
	$event->price *= 0.9; // apply a 10% discount
});

$event = $dispatcher->dispatch(new PriceCalculated(100.0));
echo $event->price; // 90.0
```

---

## Priorities

Listeners run in priority order. **A lower number runs first**, and the default
is `0`. Listeners with the same priority run in the order they were added. To run
*before* a default listener, use a negative number.

```php
$listeners->listen(PostViewed::class, $runsSecond);          // priority 0 (default)
$listeners->listen(PostViewed::class, $runsFirst, -10);      // negative runs earlier
$listeners->listen(PostViewed::class, $runsLast, 20);        // higher runs later
```

---

## Stoppable events

Sometimes one listener should be able to stop the rest from running. Make the
event implement `StoppableEvent` and pull in the `Stoppable` trait for a
ready-made implementation:

```php
use X3P0\Breadcrumbs\Events\Stoppable;
use X3P0\Breadcrumbs\Events\StoppableEvent;

final class CommentSubmitted implements StoppableEvent
{
	use Stoppable;

	public function __construct(public readonly string $text) {}
}

$listeners->listen(CommentSubmitted::class, function (CommentSubmitted $event): void {
	if (str_contains($event->text, 'spam')) {
		$event->stopPropagation(); // later listeners won't run
	}
});
```

The dispatcher checks `isPropagationStopped()` before calling each listener.

---

## Subscribers

A **subscriber** is a single class that registers several listeners at once —
handy for grouping related logic. It declares which events it handles and which
of its methods handles each:

```php
use X3P0\Breadcrumbs\Events\Subscriber;

final class AnalyticsSubscriber implements Subscriber
{
	public function getSubscribedEvents(): array
	{
		return [
			PostViewed::class       => 'onPostViewed',                       // priority 0 (default)
			CommentSubmitted::class => ['method' => 'onComment', 'priority' => 5],
		];
	}

	public function onPostViewed(PostViewed $event): void { /* … */ }
	public function onComment(CommentSubmitted $event): void { /* … */ }
}

$listeners->subscribe(new AnalyticsSubscriber());
```

**Each method listed is itself a listener** — the subscriber just groups them.
Remember a listener is any callable, and `[$object, 'method']` is a callable, so
`subscribe()` is shorthand for registering each method by hand:

```php
$sub = new AnalyticsSubscriber();
$listeners->listen(PostViewed::class,       [$sub, 'onPostViewed']);    // priority 0 (default)
$listeners->listen(CommentSubmitted::class, [$sub, 'onComment'], 5);    // priority 5
```

Everything a subscriber registered can be removed in one call:
`$listeners->unsubscribe($subscriber)`.

---

## Where listeners come from: providers

The provider is the part that answers "which listeners apply to this event?"
There are three, and you can **combine** them. They implement the
`ListenerProvider` interface and live under the
`X3P0\Breadcrumbs\Events\Provider` namespace (the interface itself sits at the
package root).

### `PriorityListenerProvider` (the default)

An in-memory registry. This is what you register listeners and subscribers on,
with priority ordering. A listener registered against a base class or interface
also fires for any event that extends or implements it.

### `AggregateListenerProvider` (combine providers)

Wraps several providers and draws listeners from all of them, in the order you
list them:

```php
use X3P0\Breadcrumbs\Events\Provider\AggregateListenerProvider;

$provider = new AggregateListenerProvider(
	$inMemory,     // PriorityListenerProvider
	$fromHooks     // HookListenerProvider (below)
);

$dispatcher = new EventDispatcher($provider);
```

### `HookListenerProvider` (talk to WordPress hooks)

See the next section.

---

## Talking to WordPress hooks

This is the part WordPress developers will care about most. `HookListenerProvider`
lets **any** code react to your typed events using ordinary `add_action()` — even
code that has never heard of this library.

You give it a closure that turns an event into a hook tag:

```php
use X3P0\Breadcrumbs\Events\Provider\HookListenerProvider;

$fromHooks = new HookListenerProvider(
	fn (object $event): string => 'acme/event/' . $event::class
);
```

Now combine it with your in-memory provider and dispatch as usual:

```php
$provider   = new AggregateListenerProvider($inMemory, $fromHooks);
$dispatcher = new EventDispatcher($provider);

$dispatcher->dispatch(new PostViewed(42));
```

And anywhere else — another plugin, a theme's `functions.php` — a developer can
hook it the familiar way:

```php
add_action('acme/event/' . PostViewed::class, function (PostViewed $event): void {
	// React to the event. You can read or modify $event here too.
});
```

Under the hood, when the event is dispatched the provider fires
`do_action('acme/event/...', $event)`, so WordPress runs every `add_action()`
callback for that tag, in WordPress's own priority order. If nothing is hooked,
it costs nothing.

> **Note:** `HookListenerProvider` is the only piece of this library that knows
> about WordPress. Everything else is plain PHP.

---

## Putting it all together

No service container or framework is required — you wire it up by hand:

```php
use X3P0\Breadcrumbs\Events\Provider\AggregateListenerProvider;
use X3P0\Breadcrumbs\Events\Provider\HookListenerProvider;
use X3P0\Breadcrumbs\Events\Provider\PriorityListenerProvider;
use X3P0\Breadcrumbs\Events\EventDispatcher;

// Build the providers.
$inMemory  = new PriorityListenerProvider();
$fromHooks = new HookListenerProvider(
	fn (object $event): string => 'acme/event/' . $event::class
);

// Combine them and create the dispatcher.
$dispatcher = new EventDispatcher(
	new AggregateListenerProvider($inMemory, $fromHooks)
);

// Register listeners on the in-memory provider…
$inMemory->listen(PostViewed::class, fn (PostViewed $e) => /* … */ null);

// …and/or let others hook in with add_action('acme/event/...').

// Then dispatch events from your code.
$dispatcher->dispatch(new PostViewed(42));
```

Keep one `$dispatcher` (and one provider set-up) for your whole plugin so every
part shares the same listeners.

---

## Class reference

| Class / interface           | Role                                                             |
|-----------------------------|------------------------------------------------------------------|
| `Dispatcher`                | Contract for an event dispatcher                                 |
| `EventDispatcher`           | Dispatches events to their listeners, in the current request     |
| `ListenerProvider`          | Contract for "which listeners apply to this event?"              |
| `PriorityListenerProvider`  | In-memory registry; priority-ordered; `listen()` / `subscribe()` |
| `AggregateListenerProvider` | Combines several providers into one                              |
| `HookListenerProvider`      | Bridges events to WordPress `add_action()` hooks                 |
| `StoppableEvent`            | Contract for an event whose propagation can be stopped           |
| `Stoppable`                 | Trait with a ready-made `StoppableEvent` implementation          |
| `Subscriber`                | Contract for a class that registers many listeners at once       |
