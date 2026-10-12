# Support

Cross-module shared code. Keep this folder **small** — anything that looks like business
behaviour belongs in a module's `Domain/` instead.

Allowed contents:

| Folder | Purpose |
|---|---|
| `ValueObjects/` | Pure-PHP value objects reused across modules — `Money` (brick/money wrapper), `DateRange`, `LocalDate`, `Address`. |
| `Helpers/` | Stateless utility functions (date math, currency formatting, id generation). |
| `Exceptions/` | Base exception classes shared by every module (`DomainException`, `InvariantViolation`). |

Anything in here is automatically fair game for every module to depend on. If you find
yourself adding business logic here, stop and move it to a module instead.