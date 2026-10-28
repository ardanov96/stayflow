# VENDORS — the API contracts Stayflow depends on

Task `P0-04` (vendor selection ADRs). The ADRs are accepted
(`docs/DECISIONS.md` ADR-0004, ADR-0005, ADR-0011); what was missing is the
**contract**: what the API actually looks like, so the code can be written
against it and a contract test can fail when the vendor changes
something.

`AGENTS.md` §3 rule 10: *do not invent vendor API details*. Everything
below was read from the vendor's own documentation on the date in the
header of each section. What could **not** be confirmed is listed as
"to verify" — it is not guessed, and the code must not assume it.

**Verified on 2026-10-27.** The three contracts below are what the
adapters of `P3-04` (payments), `P4-02` (aggregator) and `P1-15`
(notifications) are written against.

---

## 1. Xendit — payments (ADR-0005)

Source: `https://docs.xendit.co/` (the machine-readable index is
`https://docs.xendit.co/llms.txt`; every page has a `.md` variant that
an agent can read).

### Authentication

- The secret API key is sent as **Basic authentication**: the key is the
  username, the password is empty, and the value is Base64 of
  `{key}:` (the trailing colon is required).
- HTTPS only; an HTTP request fails.
- The **test environment** does not touch the banking networks and costs
  nothing. This is the environment the sandbox credentials are for.

### Webhooks

- Xendit POSTs to the configured webhook URL when a payment status
  changes.
- The callback carries two required headers:
  - `X-Callback-Signature` — an **HMAC SHA256 signature**;
  - `X-Callback-Timestamp` — the Unix timestamp of the callback.
- The payload is a JSON envelope: `created`, `business_id`, `event`,
  `data` (with its own `event`, `business_id` and `api_version`), and
  `api_version`.
- `api_version` is `v1`.

**Consequence for Stayflow:** the signature has to be verified with the
webhook secret, and the timestamp checked, before the payload is trusted
(AGENTS.md §3.6: webhooks verify their signatures). The replay of the
same event must not charge twice — the webhook is the source of truth
for the payment state, and the adapter has to be idempotent.

### Payment methods available for Indonesia

- **Virtual accounts**: BCA, BNC, BNI, BRI, BSI, BSS, Mandiri, Muamalat,
  Permata, INA.
- **QRIS**.
- **E-wallets**: GoPay, DANA, OVO, LINKAJA, ShopeePay.
- **Retail outlets**: Alfamart, Indomaret.
- **Cards**, plus Indodana PayLater, Kredivo, JeniusPay, AstraPay,
  Akulaku, and Bank Transfer.

This matches OQ-05: the Indonesian residents see the local methods, the
foreign residents see the card only.

### To verify before the adapter is written

- The exact endpoint, the request fields and the response fields of the
  **payment session** (the page is not at a predictable URL; it has to be
  found in the index). The fields the adapter depends on: the amount, the
  currency, the customer, the items, the success and failure redirect
  URLs, the channel properties, and the response: the id, the status and
  the URL the guest is sent to.
- The complete list of the **payment session statuses** and the exact
  mapping onto the states of a reservation (`pending_payment` →
  `confirmed` / `expired`).
- The **refund** flow and the **disbursement** flow (ADR-0013 mentions
  the payouts; the amounts are in minor units, so the rounding has to be
  checked against the vendor).
- Whether the MIT-enabled channels require a per-channel configuration
  beyond the account.

### Credentials to ask for

| What | Why | Where it goes |
|---|---|---|
| A test-mode secret API key | The adapter, the contract tests, the sandbox | `XENDIT_SECRET_KEY` in the server secret store, never in the repository |
| The webhook secret of the test environment | Verifying `X-Callback-Signature` | `XENDIT_WEBHOOK_SECRET` |
| A test-mode business account, with the channels activated | VA, QRIS and the e-wallets of OQ-05 | Activated in the Xendit dashboard |
| The MIT onboarding status | `P1-11` needs to know which channels are MIT-enabled | `docs/DECISIONS.md` §3 |

---

## 2. Channex — channel manager (ADR-0004)

Source: `https://docs.channex.io/`.

### The contract is published

- Channex publishes an **OpenAPI specification**:
  `https://openapi.channex.io/public/openapi.json`. It is the
  authoritative contract and it can be played against the staging server.
- The API is organized in **collections**: Properties, Users, Groups, Room
  Types, Rate Plans, **Availability and Rates**, **Bookings**,
  **Webhooks**, Channel API, Channel Codes, IFrame, Taxes and Tax Sets,
  Availability Rules, Stripe Tokenization.

### Authentication

- An API key is created in the user profile of the extranet and sent in a
  header. Channex asks for **one key for all the properties** to start
  with.

### Environments and certification

- The **staging server** is `https://staging.channex.io/`: signing up
  gives access to the test extranet, where the rates and the rooms are
  created and the ARI is viewed.
- **Test accounts** exist for Booking.com and Airbnb, to test real
  bookings, their modifications and their cancellations. This is what
  `P4-05` (inbound reservations) and `P4-07` (reconciliation) are tested
  against.
- The **certification tests** are published; the certification is what
  `P4-10` is about. The certification request goes to
  `support@channex.io`.
- The **Open Channel API** is what an OTA connects to when it is not
  through the aggregator; the **Shopping API** is the search side.

### To verify before the adapter is written

- The exact shape of the **Availability and Rates** payload (the ARI
  push of `P4-03`): the fields, the date format, the inventory
  representation (the number of rooms, the out-of-order rooms) and how a
  restriction is expressed.
- The exact shape of the **Bookings** collection: the reservation
  identifier, the guest, the dates, the rate plan, the status, and how a
  modification and a cancellation are expressed.
- The **webhook** delivery: the events, the payload, the signature, and
  how a delivery is retried.
- The **property size limits** and the **retention periods** (what is
  removed and after how long), because they decide what Stayflow has to
  keep.

### Credentials to ask for

| What | Why | Where it goes |
|---|---|---|
| A staging account on `staging.channex.io` | The test extranet, the ARI, the test bookings | The account of the person who owns the relationship |
| An API key of the staging server | The adapter, the contract tests | `CHANNEX_API_KEY` in the server secret store |
| The Booking.com and Airbnb test accounts | The inbound reservations of `P4-05` | In the account, with the credentials in the secret store |
| The certification checklist and a certification date | `P4-10` | `docs/DECISIONS.md` §4 (the gate log) |

---

## 3. Postmark — transactional email (ADR-0011)

Source: `https://postmarkapp.com/developer/api/email-api` and
`https://postmarkapp.com/developer/webhooks/webhooks-overview`.

### Sending an email

- `POST /email`, with the headers `Accept: application/json`,
  `Content-Type: application/json` and `X-Postmark-Server-Token`.
- The body: `From`, `To`, `Cc`, `Bcc`, `Subject`, `HtmlBody`,
  `TextBody`, `ReplyTo`, `Headers`, `TrackOpens`, `TrackLinks`,
  `Metadata`, `Attachments`, `Tag`, `MessageStream` (the default is the
  `outbound` transactional stream) and the template fields.
- The response: `ErrorCode`, `Message`, `MessageID`, `SubmittedAt`,
  `To`.
  - `ErrorCode` `0` means accepted.
  - `ErrorCode` `406` means the recipient is inactive (a hard bounce, a
    spam complaint or a manual suppression). **The message was not
    sent**: the caller has to know the difference.
- The batch endpoint answers `200` even when the individual messages
  failed, so the caller has to read the `ErrorCode` of every message.

### Webhooks

- The event types are: **Delivery, Bounce, SpamComplaint, Open, Click,
  SubscriptionChange** and **Inbound**.
- The **retry policy**: a delivery is retried on a `5xx`, a `408`, a
  `429` and a network failure; it is **not** retried on any other `4xx`
  (the event is dropped). The backoff is 1, 5, 10, 10, 10 and 15 minutes.
  The `X-PM-Retries-Remaining` header says how many attempts are left.
- A persistently failing endpoint has that **event type paused** until
  it is verified again — one broken type does not stop the others.
- **Idempotency**: the same event can arrive more than once. The
  `MessageID` is unique, and the `X-PM-Webhook-Trace-Id` header stays
  the same across the retries of one event. One of them has to be stored
  and checked before the event is acted upon.

### To verify before the adapter is written

- The **signature header** of the webhook and the exact verification
  procedure (the page "Verifying your webhook" was not read on the date
  above). The signature has to be checked before the payload is trusted
  (AGENTS.md §3.6).
- The **fields of the Delivery and Bounce payloads** (`MessageID`,
  `Recipient`, `Details`, `Type`, `Tag`, `DeliveredAt`, `BouncedAt`).
- The **inbound** webhook, used when a guest writes back to the hotel.
- The **sender signature** and the **message stream** the pilot needs.

### Credentials to ask for

| What | Why | Where it goes |
|---|---|---|
| A Postmark account, and the server token of the transactional stream | The adapter, the contract tests | `POSTMARK_SERVER_TOKEN` in the server secret store |
| The webhook secret, and the URL of the webhook | Verifying the callbacks | `POSTMARK_WEBHOOK_SECRET` |
| A verified sender signature for the pilot domain | The emails are refused without it | In the Postmark dashboard |
| The template alias of each transactional email | `P1-15` sends from templates | In the code, per template |

---

## 4. What this changes for the code

- Every adapter is written against a **contract test** that runs against
  the sandbox, so a vendor change fails a test instead of a booking
  (the mitigation of the risk register, R8).
- Every webhook handler is **idempotent** and **verifies its
  signature**: Postmark gives a stable trace id, Xendit signs the
  callback. The Xendit `X-Callback-Timestamp` has to be checked against
  the clock, so a captured callback cannot be replayed later.
- The amounts are **integer minor units** on the Stayflow side, and the
  rounding against the vendor has to be pinned by a test before the
  first live payment (AGENTS.md §3.2).
- The channels that need a **per-channel configuration** (Xendit MIT,
  Channex channel codes) are configuration, never code.

## 5. What still needs a human

- The **sandbox credentials** above: the task `P0-04` stays `blocked`
  until they exist, and 20 tasks wait on them (see `docs/BACKLOG.md`).
- The **spending approval** for the accounts that are not free
  (`docs/DECISIONS.md` §2, item 7).