# PILOT TEST CALENDAR — task P0-08

The windows of `docs/ROADMAP.md` §9, with what has to be true before each
one starts. A window that starts before its entry is ready turns into a
support problem instead of a test.

| Week | Window | What has to be ready | Who |
|---|---|---|---|
| 1–2 | The hotel is identified and the terms are agreed (`P0-08`) | The agreement of §1 of `PILOT_AGREEMENT_TEMPLATE.md` is signed; the contact person is named | PO |
| 5–6 | The **interview** (90 minutes) | `docs/ERD.md` and `docs/DOMAIN_MODEL.md` are written; the pack is sent a day ahead | PO + contact person |
| 9–12 | The Hotel tests the **PMS alpha** (M2) | The PMS alpha is usable on real data: reservations, room assignment, folio, check-in and check-out, night audit; the import of the Hotel's data works (`P2-16`) | BE + contact person |
| 13–16 | The **booking engine** is installed on the Hotel's website (M3) | The booking flow works end to end with the real gateway in sandbox; the widget is embedded on the Hotel's site; the confirmation emails reach a real inbox | BE + contact person |
| 19–20 | The **OTA connection plan** is agreed with the Hotel (M4) | The aggregator is in sandbox; the drift and the reconciliation report are readable; the plan says which channels, in which order | TL + contact person |
| 23–26 | **Data import, staff training, go-live** (M6) | The runbooks are written; the import is rehearsed on a copy; the staff is trained; the cutover plan and the rollback are agreed | PO + OPS |

## What the Hotel is asked to do in each window

| Window | The Hotel gives | The Hotel gets |
|---|---|---|
| Interview | 90 minutes, and the answers to §7 of the pack | The model corrected before it is built |
| PMS alpha | A week of real bookings and real check-ins | A PMS that fits the way the Hotel works |
| Booking engine | A test page on the Hotel's website, and the traffic it gets | Guests booking without a phone call |
| OTA plan | The list of channels the Hotel wants, and who owns the accounts | A plan that can be executed |
| Go-live | The data, the staff, and a go/no-go | The pilot running on Stayflow |

## The go / no-go of the last window

The Hotel says yes to the cutover when:

- the staff have used the PMS for a week without asking for help on the
  daily tasks;
- the booking engine has taken a real booking that was paid and confirmed;
- the import of the data is verified against the Hotel's own numbers;
- the rollback plan is written and understood by the Hotel's contact
  person.

If any of these is not true, the cutover is postponed, not the data
migrated in the hope that it will be fine.