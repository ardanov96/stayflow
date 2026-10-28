# PILOT INTERVIEW PACK — the 90 minutes that close the open questions

Task `P0-08` (pilot hotel recruitment) and `P0-03` (domain model
validation). The goal of the meeting is to **confirm or correct** the
model below, not to explain it.

Every question carries the position Stayflow has already taken. The pilot
answers "yes", "no" or "not like that", and the answer is written under the
question. A "no" is the most valuable outcome: it is the cheapest moment to
change the model, before it is in the database.

**Before the meeting:** send this pack and `docs/ERD.md` a day ahead, and
ask the pilot to read them. The meeting is then a review, not a
discovery.

**Who:** the general manager or the front-desk manager, plus whoever owns
the rates and the tax invoices. One person who decides the rates and the
taxes is enough for §1 and §2.

**How long:** 90 minutes. §1 rate plans (20 min), §2 restrictions (10
min), §3 taxes and service charge (20 min), §4 housekeeping (15 min),
§5 booking, deposit and cancellation (15 min), §6 the sign-off (10 min).

---

## 1. Rate plans

Our model: a property has several **rate plans** (the "BAR" and the other
public prices); each rate plan carries a price per room type per night, and
the prices of the other plans can be derived from a base one
(`docs/RATES.md`).

1. **How many prices do you sell today?** _Our answer: as many rate plans
   as you need per property, not per room type._
2. **Do you sell per room type, or per person?** _Our answer: per room
   type, per night, flat. Per-person pricing is not in the model yet
   (OQ-11)._
3. **Do you have a length-of-stay price (7 nights cheaper than 1)?**
   _Our answer: not in the MVP. Every night costs the same._
4. **Do you have a minimum or a maximum stay?** _Our answer: both exist as
   restrictions, per rate plan and per date._
5. **Do your room categories map one to one onto our room types?**
   _Our answer: yes — a room type is what the guest books, and the physical
   rooms hang under it._

## 2. Restrictions

Our model: a **restriction** is a per-date rule attached to a rate plan
and a room type: no arrival (CTA), no departure (CTD), no sale at all
(stop sell), minimum and maximum stay.

6. **Which dates do you close arrivals on, and which departures?** _Our
   answer: a date can carry both._
7. **Do you have days you do not sell at all?** _Our answer: yes, stop
   sell._
8. **Do you close the whole hotel for maintenance?** _Our answer: yes,
   as a block of units on a date range, not as a rate rule._

## 3. Taxes and service charge

Our model: a **tax rule** per property, applied to every charge when it is
posted; the invoice numbers per fiscal year
(`docs/MONEY.md`).

9. **Which taxes apply to a room night?** _Our answer: the Indonesian
   horeca taxes in force at launch, configured per property (OQ-02)._
10. **Which service charge, and on what base?** _Our answer: a percentage
    of the charge it applies to, so a service charge on food is not taxed
    like a room._
11. **Is the tax in the price the guest sees, or added at checkout?**
    _Our answer: added at checkout; the folio shows the lines._
12. **Who is the invoice for: the guest, or the company that booked?**
    _Our answer: the folio belongs to the reservation, and the invoice
    names the guest._

## 4. Housekeeping

Our model: every **room unit** has a status (clean, dirty, inspected, out
of order); a room out of order is not sellable.

13. **Who decides a room is sellable again?** _Our answer: housekeeping,
    after the inspection._
14. **Do you assign the room before the arrival, or on the day?**
    _Our answer: either; the assignment is on the reservation._
15. **How many physical rooms does each room type have?** _Our answer: the
    room type carries the number of units._
16. **Do you ever move a guest to another room type?** _Our answer: not in
    the MVP; it is a manual operation._

## 5. Booking, deposit and cancellation

17. **How do walk-ins and phone bookings work today?** _Our answer: staff
    creates the reservation in the PMS._
18. **Do you take a deposit, how much, and when?** _Our answer: not
    modelled yet (OQ-12)._
19. **What is your cancellation and no-show policy?** _Our answer: the
    reservation state machine has cancelled and no-show; the **policy**
    (when the money is returned) is not modelled yet (OQ-12)._

## 6. The model itself

20. **Read `docs/ERD.md` (the physical schema) and `docs/DOMAIN_MODEL.md`
    (the logical model). Is anything missing, or anything you would never
    use?** _Write it here:_
    > _…
21. **Is anything in the model that Stayflow should drop before it is
    built?** _Write it here:_
    > _…

## 7. What we need from the pilot after the meeting

| # | Question | Answer | Who answers | By when |
|---|---|---|---|---|
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |
| 4 | | | | |
| 5 | | | | |
| 6 | | | | |
| 7 | | | | |
| 8 | | | | |
| 9 | | | | |
| 10 | | | | |

The answers feed `docs/DECISIONS.md` §3 (OQ-11, OQ-12) and close task
`P0-03`.