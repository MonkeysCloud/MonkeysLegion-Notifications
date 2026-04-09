# 🎧 Using the NotificationListener

The `NotificationListener` is the bridge between your application's events and the notification system. It works in two parallel ways: **Dynamic Mapping** and **Automatic Attribute Scanning**.

---

## 1. How it works (The Lifecycle)

When any object event is dispatched in your application (e.g., `OrderProcessed`, `UserUpdated`), the `NotificationListener` performs these checks:

1. **Direct Mapping:** Does the listener have a manual instruction to send `NotificationX` when `EventY` occurs?
2. **Attribute Scanning:** Does the event contain an `entity`? If so, are there any `#[Notify]` attributes on it?

---

## 2. Dynamic Mapping

You can manually map events to notifications using the `listen()` method. This is useful for notifications that are specific to an action but aren't tied directly to a single object property.

### Example

```php
// In a Service Provider or Bootstrap file:
$listener->listen(UserRegistered::class, [
    WelcomeNotification::class,
    AdminAlert::class
]);
```

---

## 3. Automatic Attribute Scanning (The Magic)

The `AttributeProcessor` allows you to trigger notifications by simply decorating your classes, properties, or methods.

### 3.1 Class-Level (The "Global" Trigger)

Triggered every time the entity is processed by the listener.

- **Param passed to Notification:** The entire `$entity`.

```php
#[Notify(NewOrderAdminAlert::class)]
class Order implements NotifiableInterface {
    use Notifiable;
}
```

### 3.2 Property-Level (The "State" Trigger)

Triggered based on the value of a specific property.

- **Param passed to Notification:** The `$property->getValue()`.

```php
class User implements NotifiableInterface {
    use Notifiable;

    #[Notify(StatusChangedNotification::class)]
    public string $status = 'active';
}
```

### 3.3 Method-Level (The "Calculated" Trigger)

Triggered based on the return value of a public or private getter.

- **Param passed to Notification:** The result of `$method->invoke()`.

```php
class Wallet implements NotifiableInterface {
    use Notifiable;

    #[Notify(LowBalanceWarning::class)]
    private function hasLowBalance(): bool {
        return $this->balance < 10.00;
    }
}
```

---

## 4. Full Flow Example

Here is a complete scenario showing how your code stays clean while notifications happen behind the scenes.

### Step 1: Define the Event

```php
class OrderUpdated {
    // The Listener expects this "entity" property
    public function __construct(public Order $entity) {}
}
```

### Step 2: Decorate the Entity

```php
class Order implements NotifiableInterface {
    use Notifiable;

    #[Notify(OrderShippedNotification::class)]
    public string $status;
}
```

### Step 3: Dispatch the Event

```php
use Psr\EventDispatcher\EventDispatcherInterface;

$order = $repository->find(1);
$order->status = 'shipped';
$repository->save($order);

// This is the ONLY line your controller/service needs
// Any PSR-14 Dispatcher will work
$dispatcher->dispatch(new OrderUpdated($order));
```

### Step 4: The Result

1. `NotificationListener->handle($event)` is called.
2. It detects `$event->entity`.
3. `AttributeProcessor->process($order)` is called.
4. It finds the `#[Notify]` on `$status`.
5. It instantiates `new OrderShippedNotification('shipped')`.
6. The `NotificationManager` sends it!

---

## 5. Integration Checklist

1. **Register the Listener:**
    > **Tip:** If using `MonkeysLegion-Skeleton`, register the `NotificationListener` in `\App\Providers\AppProvider::register()`. The framework will handle dependency injection.
2. **Entity Interface:** The object inside the event **must** implement `NotifiableInterface`.
3. **Property Visibility:** The `$entity` property on your **Event** MUST be public. Properties/Methods inside the **Entity** can be private.
4. **Attribute Matching:** The class passed to `#[Notify]` must exist.

---
Made with ❤️ by MonkeysLegion
