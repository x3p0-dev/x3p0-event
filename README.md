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
use X3P0\Event\EventDispatcher;

// 1. A dispatcher fires events — and lets you register the listeners for them.
// With no arguments it sets up its own listener registry, so there's nothing
// else to wire. (Pass your own provider when you need to; see below.)
$dispatcher = new EventDispatcher();

// 2. An event is just a class. Give it whatever data it needs.
final class PostViewed
{
	public function __construct(public readonly int $postId) {}
}

// 3. A listener is any callable that accepts the event.
$dispatcher->listen(PostViewed::class, function (PostViewed $event): void {
	error_log("Post {$event->postId} was viewed.");
});

// 4. Dispatch the event wherever it happens.
$dispatcher->dispatch(new PostViewed(42));
```

`dispatch()` returns the same event object it was given, so you can read
anything the listeners changed on it (see the next section).

The dispatcher's `listen()` / `subscribe()` methods are a convenience facade over
its provider; you can also register on the provider directly (see
[Registering through the dispatcher](#registering-through-the-dispatcher)).

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

$dispatcher->listen(PriceCalculated::class, function (PriceCalculated $event): void {
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
$dispatcher->listen(PostViewed::class, $runsSecond);         // priority 0 (default)
$dispatcher->listen(PostViewed::class, $runsFirst, -10);     // negative runs earlier
$dispatcher->listen(PostViewed::class, $runsLast, 20);       // higher runs later
```

---

## Stoppable events

Sometimes one listener should be able to stop the rest from running. Make the
event implement `StoppableEvent` and pull in the `Stoppable` trait for a
ready-made implementation:

```php
use X3P0\Event\Stoppable;
use X3P0\Event\StoppableEvent;

final class CommentSubmitted implements StoppableEvent
{
	use Stoppable;

	public function __construct(public readonly string $text) {}
}

$dispatcher->listen(CommentSubmitted::class, function (CommentSubmitted $event): void {
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
use X3P0\Event\Subscriber;

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

$dispatcher->subscribe(new AnalyticsSubscriber());
```

**Each method listed is itself a listener** — the subscriber just groups them.
Remember a listener is any callable, and `[$object, 'method']` is a callable, so
`subscribe()` is shorthand for registering each method by hand:

```php
$sub = new AnalyticsSubscriber();
$dispatcher->listen(PostViewed::class,       [$sub, 'onPostViewed']);   // priority 0 (default)
$dispatcher->listen(CommentSubmitted::class, [$sub, 'onComment'], 5);   // priority 5
```

Everything a subscriber registered can be removed in one call:
`$dispatcher->unsubscribe($subscriber)`.

---

## Removing listeners

To drop individual listeners, use `forget()`. Pass the event type and the exact
listener to remove just that one, or the event type alone to remove every
listener for it:

```php
$listener = function (PostViewed $event): void { /* … */ };

$dispatcher->listen(PostViewed::class, $listener);

$dispatcher->forget(PostViewed::class, $listener); // remove that one listener
$dispatcher->forget(PostViewed::class);            // remove all PostViewed listeners
```

Listeners are matched by identity, so an inline closure can only be forgotten by
passing back the same closure instance — keep a reference if you'll need to
remove it. (Subscribers are removed as a group with `unsubscribe()`, above.)

---

## Registering through the dispatcher

For convenience, the dispatcher doubles as a facade over its provider — you can
`listen()`, `subscribe()`, `unsubscribe()`, and `forget()` on it directly, so
code that holds the dispatcher needn't also hold a reference to the provider:

```php
$dispatcher = new EventDispatcher(); // or pass your own provider

$dispatcher->listen(PostViewed::class, function (PostViewed $event): void {
	// …
});

$dispatcher->subscribe(new AnalyticsSubscriber());

$dispatcher->dispatch(new PostViewed(42));
```

These delegate to the underlying provider, so they need a provider that accepts
registrations — one implementing `ListenerRegistry`. The default provider a
bare `new EventDispatcher()` creates is a `PriorityListenerRegistry`, which is
exactly that, so the facade works out of the box. It also works when you pass a
registry provider yourself (`new EventDispatcher(new PriorityListenerRegistry())`).

The facade throws a `LogicException` when the dispatcher's provider does *not*
accept registrations — a lone `HookListenerProvider`, or an
`AggregateListenerProvider` (which is read-only; see below). In the combined
set-up, register on the concrete `PriorityListenerRegistry` directly rather than
through the dispatcher.

The dispatcher and the provider stay separate types — this is only sugar. You can
always register on the provider directly, which is the only option when you hold
a provider but not the dispatcher.

This full surface — dispatch plus the registration facade — is the
`ListenerAwareDispatcher` interface (a `Dispatcher` that is also a
`ListenerRegistry`), so type-hint that when you want one object for both jobs.
Type-hint `Dispatcher` when you only need to fire events, or `ListenerRegistry`
when you want the listener store itself. The facade methods delegate to the
backing provider, so they carry its precondition: give the dispatcher a provider
that accepts registrations, or they throw.

---

## Where listeners come from: providers

The provider is the part that answers "which listeners apply to this event?"
There are three, and you can **combine** them. They all implement the
`ListenerProvider` interface and live under the `X3P0\Event\Provider` namespace
(the interface itself sits at the package root).

The name tells you which way each one goes: a **`…Registry`** is writable — you
register listeners on it — while a read-only **`…Provider`** only supplies
listeners it sources elsewhere. So of the three, only `PriorityListenerRegistry`
accepts registrations.

### `PriorityListenerRegistry` (the default)

An in-memory, writable registry. This is what you register listeners and
subscribers on, with priority ordering. A listener registered against a base
class or interface also fires for any event that extends or implements it.

### `AggregateListenerProvider` (combine providers)

Wraps several providers and draws listeners from all of them, in the order you
list them. It is **read-only** — it combines what its children *provide* but
accepts no registrations itself (mirroring PSR-14's own aggregation model). You
register on the concrete registry; the aggregate reads through to it.

```php
use X3P0\Event\Provider\AggregateListenerProvider;

$inMemory = new PriorityListenerRegistry();

$provider = new AggregateListenerProvider(
	$inMemory,     // PriorityListenerRegistry
	$fromHooks     // HookListenerProvider (below)
);

$dispatcher = new EventDispatcher($provider);

// Register on the concrete registry, not the aggregate or the dispatcher.
$inMemory->listen(PostViewed::class, $listener);
```

### `HookListenerProvider` (talk to WordPress hooks)

See the next section.

---

## Talking to WordPress hooks

This is the part WordPress developers will care about most. `HookListenerProvider`
lets **any** code react to your typed events using ordinary `add_action()` — even
code that has never heard of this library.

By default, it uses the event's class name as the hook tag, so there's nothing to
configure:

```php
use X3P0\Event\Provider\HookListenerProvider;

$fromHooks = new HookListenerProvider();
```

The tag is the fully-qualified class name, which is already unique and namespaced
for correctly-namespaced code. Combine it with your in-memory provider and
dispatch as usual:

```php
$provider   = new AggregateListenerProvider($inMemory, $fromHooks);
$dispatcher = new EventDispatcher($provider);

$dispatcher->dispatch(new PostViewed(42));
```

And anywhere else — another plugin, a theme's `functions.php` — a developer hooks
it with the class name (use `::class` so the namespaced string is exact):

```php
add_action(PostViewed::class, function (PostViewed $event): void {
	// React to the event. You can read or modify $event here too.
});
```

### Mapping events to custom tags

Pass a closure when you want to decide the tag yourself — to publish a **stable,
readable hook name** that survives renaming the class, or to **opt an event out**
of the bridge entirely by returning an empty string:

```php
use X3P0\Event\Provider\HookListenerProvider;

$fromHooks = new HookListenerProvider(
	fn (object $event): string => match ($event::class) {
		PostViewed::class       => 'acme/post_viewed',
		CommentSubmitted::class => 'acme/comment_submitted',
		InternalAudit::class    => '', // not exposed to add_action()
		default                 => $event::class,
	}
);
```

Now the public hook name is decoupled from the class, so a third party hooks the
stable tag and you're free to rename or move `PostViewed` later:

```php
add_action('acme/post_viewed', function (PostViewed $event): void {
	// …
});
```

Under the hood, when the event is dispatched the provider fires
`do_action($tag, $event)`, so WordPress runs every `add_action()` callback for
that tag, in WordPress's own priority order. If the tag is empty or nothing is
hooked, it costs nothing.

> **Note:** `HookListenerProvider` is the only piece of this library that knows
> about WordPress. Everything else is plain PHP.

---

## Putting it all together

No service container or framework is required — you wire it up by hand:

```php
use X3P0\Event\Provider\AggregateListenerProvider;
use X3P0\Event\Provider\HookListenerProvider;
use X3P0\Event\Provider\PriorityListenerRegistry;
use X3P0\Event\EventDispatcher;

// Build the providers. HookListenerProvider uses the event class name as the
// hook tag by default; pass a closure if you want to map custom tags.
$inMemory  = new PriorityListenerRegistry();
$fromHooks = new HookListenerProvider();

// Combine them and create the dispatcher.
$dispatcher = new EventDispatcher(
	new AggregateListenerProvider($inMemory, $fromHooks)
);

// Register listeners on the in-memory provider…
$inMemory->listen(PostViewed::class, fn (PostViewed $e) => /* … */ null);

// …and/or let others hook in with add_action(PostViewed::class, …).

// Then dispatch events from your code.
$dispatcher->dispatch(new PostViewed(42));
```

Keep one `$dispatcher` (and one provider set-up) for your whole plugin so every
part shares the same listeners.

---

## Class reference

| Class / interface           | Role                                                                                   |
|-----------------------------|----------------------------------------------------------------------------------------|
| `Dispatcher`                | Minimal, PSR-14-style contract: just `dispatch()`                                      |
| `ListenerAwareDispatcher`   | A `Dispatcher` that is also a `ListenerRegistry`                                       |
| `EventDispatcher`           | Dispatches events; also a `listen()` / `subscribe()` facade                            |
| `ListenerProvider`          | Contract for "which listeners apply to this event?"                                    |
| `ListenerRegistry`          | Contract for the write side: `listen()` / `subscribe()` / `unsubscribe()` / `forget()` |
| `PriorityListenerRegistry`  | In-memory registry; priority-ordered; `listen()` / `subscribe()`                       |
| `AggregateListenerProvider` | Combines several providers into one                                                    |
| `HookListenerProvider`      | Bridges events to WordPress `add_action()` hooks                                       |
| `StoppableEvent`            | Contract for an event whose propagation can be stopped                                 |
| `Stoppable`                 | Trait with a ready-made `StoppableEvent` implementation                                |
| `Subscriber`                | Contract for a class that registers many listeners at once                             |
