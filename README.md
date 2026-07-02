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
use X3P0\Event\Listener\Registry\PriorityRegistry;
use X3P0\Event\EventDispatcher;

// 1. A registry holds your listeners; a dispatcher fires events at them.
$listeners  = new PriorityRegistry();
$dispatcher = new EventDispatcher($listeners);

// 2. An event is just a class. Give it whatever data it needs.
final class PostViewed
{
	public function __construct(public readonly int $postId) {}
}

// 3. A listener is any callable that accepts the event. Register it on the registry.
$listeners->listen(PostViewed::class, function (PostViewed $event): void {
	error_log("Post {$event->postId} was viewed.");
});

// 4. Dispatch the event, through the dispatcher, wherever it happens.
$dispatcher->dispatch(new PostViewed(42));
```

`dispatch()` returns the same event object it was given, so you can read
anything the listeners changed on it (see the next section).

The two objects have distinct jobs: you **register** listeners on the registry
(`$listeners`) and **fire** events through the dispatcher (`$dispatcher`). Keep
one of each for your whole plugin so every part shares the same listeners.

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
$listeners->listen(PostViewed::class, $runsSecond);         // priority 0 (default)
$listeners->listen(PostViewed::class, $runsFirst, -10);     // negative runs earlier
$listeners->listen(PostViewed::class, $runsLast, 20);       // higher runs later
```

For the common cases you can use the `ListenerPriority` enum instead of a bare
number — the cases name the *order*, not a magnitude, so they read the same way
the rule does ("lower runs first"):

```php
use X3P0\Event\ListenerPriority;

$listeners->listen(PostViewed::class, $early, ListenerPriority::First);   // before all
$listeners->listen(PostViewed::class, $usual, ListenerPriority::Normal);  // 0 (the default)
$listeners->listen(PostViewed::class, $late,  ListenerPriority::Last);    // after all
```

`First` and `Last` are the integer extremes, so they run before and after every
other listener respectively — true bookends. Pass a plain integer for any
ordering in between; you can mix the two freely. The same values work as a
subscriber's `priority` and with `listenOnce()`.

---

## One-time listeners

A listener registered with `listenOnce()` fires for the first matching event and
then removes itself — handy for one-shot work that should react to an event but
never again:

```php
$listeners->listenOnce(BootCompleted::class, function (BootCompleted $event): void {
	// runs on the first BootCompleted, then unregisters itself
});
```

It takes the same priority argument as `listen()` and is otherwise identical. The
listener is removed *before* it runs, so it fires at most once even if it — or
something it calls — dispatches the same event again.

---

## Inferring the event from the listener

A typed listener already names the event it handles — it's right there in the
parameter type. `listenTo()` reads it from there, so you don't repeat the class
you just type-hinted:

```php
// listen() — the event class is named twice:
$listeners->listen(PostViewed::class, function (PostViewed $event): void { /* … */ });

// listenTo() — named once, on the parameter:
$listeners->listenTo(function (PostViewed $event): void { /* … */ });
```

It's the same registration either way — same storage, same priority ordering,
same matching — so a `listenTo()` listener typed against a base class or
interface still fires for every subtype, exactly as with `listen()`. The
priority argument works the same too, as an integer or a `ListenerPriority`:

```php
$listeners->listenTo($handler, ListenerPriority::Last);
```

Any callable works, because the type is read from whichever parameter comes
first — a closure, an `[$object, 'method']` pair, or an invokable object:

```php
$listeners->listenTo([$analytics, 'onPostViewed']);
```

There's a once-only counterpart, `listenOnceTo()`, that combines this with
`listenOnce()`: the event is inferred *and* the listener removes itself after it
first runs.

```php
$listeners->listenOnceTo(function (BootCompleted $event): void { /* … */ });
```

`listenTo()` needs a type to read, so reach for plain `listen()` when there
isn't one: a `Listener` class name (resolved lazily, so there's no signature to
inspect yet), a named event's string key, or a listener whose first parameter is
untyped. A first parameter that is untyped, a builtin such as `string`, or a
union type throws `InvalidListener` — it names no single event to register
against, and guessing would be worse than asking you to say it.

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

$listeners->listen(CommentSubmitted::class, function (CommentSubmitted $event): void {
	if (str_contains($event->text, 'spam')) {
		$event->stopPropagation(); // later listeners won't run
	}
});
```

The dispatcher checks `isPropagationStopped()` before calling each listener.

---

## Named events

An event is matched by its class, but it can *also* expose a string name so
listeners may register against a friendly identifier as well as (or instead of)
the class. Implement `NamedEvent`, and back it with a `NAME` constant using the `Named` trait:

```php
use X3P0\Event\Named;
use X3P0\Event\NamedEvent;

final class OrderPlaced implements NamedEvent
{
	use Named;

	public const NAME = 'order.placed';

	public function __construct(public readonly int $orderId) {}
}
```

Now the event matches listeners registered under either key:

```php
$listeners->listen(OrderPlaced::class, $byClass);   // by class, as always
$listeners->listen(OrderPlaced::NAME,  $byName);    // by name ('order.placed')

$dispatcher->dispatch(new OrderPlaced(42));          // both listeners run
```

You still dispatch an **object** — the name is an *additional* routing key the
event opts into, not a replacement for the typed event, so listeners still get
the real object and its typed data. And because the name lives on the class as a
constant, registering with `OrderPlaced::NAME` keeps autocomplete, "find usages,"
and refactoring working — unlike a bare string. The name key composes with
everything else: priorities, `listenOnce()`, `forget()`, and subscribers (use
`OrderPlaced::NAME` as a `getSubscribedEvents()` key).

---

## Listeners as classes

A listener can be any callable, and an object with an `__invoke()` method is a
callable — so a listener can be a class:

```php
final class NotifyWarehouse
{
	public function __invoke(OrderPlaced $event): void { /* … */ }
}

$listeners->listen(OrderPlaced::class, new NotifyWarehouse());
```

If you'd rather register it by **class name** and have it built only when the
event fires, implement the `Listener` marker interface and pass the class name:

```php
use X3P0\Event\Listener;

final class NotifyWarehouse implements Listener
{
	public function __invoke(OrderPlaced $event): void { /* … */ }
}

$listeners->listen(OrderPlaced::class, NotifyWarehouse::class);   // resolved lazily
```

`Listener` is a marker (it declares no method) so your `__invoke()` keeps its
real, typed parameter. The class is instantiated the first time its event fires
and reused after that. By default it's built with `new $class()`, so a plain
listener class needs no constructor arguments; to resolve listeners that have
dependencies, give the registry a resolver — for example a container:

```php
use X3P0\Event\Listener\Registry\PriorityRegistry;

$registry   = new PriorityRegistry(
	fn (string $class): object => $container->get($class)
);
$dispatcher = new EventDispatcher($registry);
```

This also works with `listenOnce()`. (A class-name listener is matched by
identity like any other, so remove it with `forget(OrderPlaced::class)` rather
than by passing the class name back.)

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

$listeners->subscribe(new AnalyticsSubscriber());
```

**Each method listed is itself a listener** — the subscriber just groups them.
Remember a listener is any callable, and `[$object, 'method']` is a callable, so
`subscribe()` is shorthand for registering each method by hand:

```php
$sub = new AnalyticsSubscriber();
$listeners->listen(PostViewed::class,       [$sub, 'onPostViewed']);   // priority 0 (default)
$listeners->listen(CommentSubmitted::class, [$sub, 'onComment'], 5);   // priority 5
```

Everything a subscriber registered can be removed in one call:
`$listeners->unsubscribe($subscriber)`.

To register a subscriber whose listeners each fire **at most once**, use
`subscribeOnce()`. Every declared handler removes itself after it runs, and they
are independent — one firing doesn't disarm the others:

```php
$listeners->subscribeOnce(new AnalyticsSubscriber());
```

You can still remove the whole set early with `unsubscribe()` before any of them
have fired.

---

## Removing listeners

To drop individual listeners, use `forget()`. Pass the event type and the exact
listener to remove just that one, or the event type alone to remove every
listener for it:

```php
$listener = function (PostViewed $event): void { /* … */ };

$listeners->listen(PostViewed::class, $listener);

$listeners->forget(PostViewed::class, $listener); // remove that one listener
$listeners->forget(PostViewed::class);            // remove all PostViewed listeners
```

Listeners are matched by identity, so an inline closure can only be forgotten by
passing back the same closure instance — keep a reference if you'll need to
remove it. (Subscribers are removed as a group with `unsubscribe()`, above.)

---

## Checking for listeners

`hasListeners()` reports whether anything is registered for an event type —
useful for skipping work, like building an expensive event, when nothing is
listening:

```php
if ($listeners->hasListeners(ReportGenerated::class)) {
	$dispatcher->dispatch(new ReportGenerated($this->buildExpensiveReport()));
}
```

It respects the same matching as dispatch, so a listener registered against a
base class or interface counts for its subtypes. Pass a named event's name to
check listeners registered under that name. (It reflects listeners on the
registry, not the WordPress hook bridge — for that, use `has_action()`.)

---

## Where listeners come from: providers

The provider is the part that answers "which listeners apply to this event?"
There are three, and you can **combine** them. They all implement the
`ListenerProvider` interface (which lives at `X3P0\Event\Listener`), and the
concrete classes sit under `X3P0\Event\Listener\Provider` (read-only providers)
and `X3P0\Event\Listener\Registry` (writable registries).

The name tells you which way each one goes: a **`…Registry`** is writable — you
register listeners on it — while a read-only **`…Provider`** only supplies
listeners it sources elsewhere. So of the three, only `PriorityRegistry`
accepts registrations.

### `PriorityRegistry` (the default)

An in-memory, writable registry. This is what you register listeners and
subscribers on, with priority ordering. A listener registered against a base
class or interface also fires for any event that extends or implements it.

### `AggregateProvider` (combine providers)

Wraps several providers and draws listeners from all of them, in the order you
list them. It is **read-only** — it combines what its children *provide* but
accepts no registrations itself (mirroring PSR-14's own aggregation model). You
register on the concrete registry; the aggregate reads through to it.

```php
use X3P0\Event\Listener\Provider\AggregateProvider;

$inMemory = new PriorityRegistry();

$provider = new AggregateProvider(
	$inMemory,     // PriorityRegistry
	$fromHooks     // HookBridgeProvider (below)
);

$dispatcher = new EventDispatcher($provider);

// Register on the concrete registry, not the aggregate or the dispatcher.
$inMemory->listen(PostViewed::class, $listener);
```

### `HookBridgeProvider` (talk to WordPress hooks)

See the next section.

---

## Talking to WordPress hooks

This is the part WordPress developers will care about most. `HookBridgeProvider`
lets **any** code react to your typed events using ordinary `add_action()` — even
code that has never heard of this library.

By default, it uses the event's class name as the hook tag, so there's nothing to
configure:

```php
use X3P0\Event\Listener\Provider\HookBridgeProvider;

$fromHooks = new HookBridgeProvider();
```

The tag is the fully-qualified class name, which is already unique and namespaced
for correctly-namespaced code. Combine it with your in-memory provider and
dispatch as usual:

```php
$provider   = new AggregateProvider($inMemory, $fromHooks);
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
use X3P0\Event\Listener\Provider\HookBridgeProvider;

$fromHooks = new HookBridgeProvider(
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

> **Note:** `HookBridgeProvider` is the only piece of this library that knows
> about WordPress. Everything else is plain PHP.

---

## Putting it all together

No service container or framework is required — you wire it up by hand:

```php
use X3P0\Event\Listener\Provider\AggregateProvider;
use X3P0\Event\Listener\Provider\HookBridgeProvider;
use X3P0\Event\Listener\Registry\PriorityRegistry;
use X3P0\Event\EventDispatcher;

// Build the providers. HookBridgeProvider uses the event class name as the
// hook tag by default; pass a closure if you want to map custom tags.
$inMemory  = new PriorityRegistry();
$fromHooks = new HookBridgeProvider();

// Combine them and create the dispatcher.
$dispatcher = new EventDispatcher(
	new AggregateProvider($inMemory, $fromHooks)
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
| `Dispatcher`                | PSR-14-style contract: just `dispatch()`                                               |
| `EventDispatcher`           | Dispatches events to their listeners, in the current request                           |
| `ListenerProvider`          | Contract for "which listeners apply to this event?"                                    |
| `Listener`                  | Marker for a listener class registerable by name and resolved lazily                   |
| `ListenerPriority`          | Enum of named priorities (`First` / `Normal` / `Last`) for `listen()`                  |
| `ListenerRegistry`          | Contract for the write side: `listen()` / `listenTo()` / `listenOnce()` / `listenOnceTo()` / `subscribe()` / `subscribeOnce()` / `unsubscribe()` / `forget()` / `hasListeners()` |
| `PriorityRegistry`  | In-memory registry; priority-ordered; `listen()` / `subscribe()`                       |
| `RegistersListeners`        | Trait carrying the registry implementation, for building registry variants             |
| `AggregateProvider` | Combines several providers into one                                                    |
| `HookBridgeProvider`      | Bridges events to WordPress `add_action()` hooks                                       |
| `StoppableEvent`            | Contract for an event whose propagation can be stopped                                 |
| `Stoppable`                 | Trait with a ready-made `StoppableEvent` implementation                                |
| `NamedEvent`                | Contract for an event that also matches by a string name                               |
| `Named`                     | Trait implementing `NamedEvent` from a `NAME` class constant                           |
| `Subscriber`                | Contract for a class that registers many listeners at once                             |
| `EventException`            | Marker interface implemented by every exception the library throws                     |
| `InvalidListener`           | Thrown when a listener is neither a callable nor a `Listener` class name (extends `InvalidArgumentException`) |
| `NotInvokable`              | Thrown when a class-name `Listener` resolves to a non-invokable object (extends `LogicException`) |
