# NOTIFICATIONS — the framework of the notifications

Documentation for task `P1-15` (notification framework).

## 1. What it is

A notification is a message to one or more recipients, rendered from a
template in a given locale, and delivered through a channel. The framework
is three things:

1. a **channel** (`NotificationChannel`) — mail today, WhatsApp tomorrow;
2. a **delivery log** (`NotificationDeliveryLog`) — what happened to every
   message, successful or not;
3. a **dispatcher** (`NotificationDispatcher`) — renders, delivers, records.

The caller does not know the channel:

```php
app(NotificationDispatcher::class)->dispatch(new Notification(
    organizationId: $organization->id,
    propertyId: $property->id,
    channel: 'mail',
    mailable: VerifyEmailMail::class,
    locale: 'id',
    arguments: ['verifyUrl' => $url, 'userName' => 'Wayan'],
    recipients: ['guest@example.test'],
));
```

## 2. The templates and the locales

A template is a **mailable**: a class with a view and the data of the
view. The notification carries the **locale** it has to be rendered in, so
the subject and the body come out in the language of the reader.

The locale of a notification is the locale of the **recipient**, not the
locale of the staff member who triggered it. The three locales of OQ-06
(`id`, `en`, `zh`) are the same three the i18n check of P0-05 covers, so
a template that renders in one of them renders in all of them.

The texts of a template live in the language files (`lang/*.json`), never
in the view.

## 3. The delivery log

One row per delivery attempt, in `notification_deliveries`:

| Column | What it is |
|---|---|
| `organization_id`, `property_id` | The tenant the notification belongs to |
| `channel` | `mail` today |
| `template` | The mailable that was sent |
| `locale` | The locale it was rendered in |
| `recipient` | Who it was sent to |
| `status` | `sent`, `failed`, `bounced` or `complained` |
| `provider_message_id` | The identifier the provider gave the message |
| `error` | Why the delivery failed |
| `delivered_at` | When it went out |

**A notification that failed silently is a notification that never
arrives**, and nobody notices until a guest complains. Every attempt is
recorded, and the failures are readable through
`NotificationDeliveryLog::failedFor($organizationId)`.

## 4. The bounce and the complaint

The provider tells us that a message bounced or was reported as spam:
`POST /webhooks/postmark/{token}`.

The endpoint is public, so it carries a **shared secret in the path**,
compared in constant time (`POSTMARK_WEBHOOK_TOKEN`). It carries no
middleware: the provider knows nothing about a session, and a CSRF token
would reject every callback.

A bounce marks the delivery as `bounced`, a spam complaint as
`complained`; both show up in the failures of the organization.

> **To verify before the endpoint is opened to the internet:** the exact
> signature scheme of the provider (the header and how the signature is
> computed) has to be read from its documentation and checked on top of
> the shared secret. `docs/VENDORS.md` §3 lists what was verified on
> 2026-10-27 and what was not.

## 5. What is not built yet

- **WhatsApp**: the contract is there, the channel is not. A WhatsApp
  channel is one class and one line in the dispatcher; nothing else
  changes.
- **The delivery log has no screen yet**: it is read through the contract.
  The screen belongs to the module that shows it (the PMS, the booking
  engine).
- **The webhook matches the delivery on the identifier of the provider
  and the recipient.** The identifier Laravel gives the message and the
  one the provider gives it are not the same; the check is therefore
  deliberately narrow, and a callback that matches nothing is recorded
  as unknown rather than applied to the wrong row.

## 6. Related documents

- `docs/VENDORS.md` — the contract of the provider (ADR-0011).
- `docs/INVENTORY.md` — the other thing that must never be lost.
- `docs/PRODUCTION_READINESS.md` — the readiness items.