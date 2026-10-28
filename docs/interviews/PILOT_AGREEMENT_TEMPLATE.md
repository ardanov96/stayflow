# PILOT AGREEMENT TEMPLATE — task P0-08

A template, not a contract. The commercial terms are a human decision
(`docs/DECISIONS.md` §2, item 1) and this document must be reviewed by
whoever signs it. It exists so the conversation starts from a written
position instead of from nothing.

Placeholders are written in `{{ }}`.

---

## Design partner agreement — Stayflow

**Between:** {{legal name of the hotel}} ("the Hotel")
**And:** {{legal name of the software owner}} ("Stayflow")

**Effective date:** {{date}}

### 1. Purpose

The Hotel is a design partner of Stayflow, a property management and
booking engine for hotels and villas. Stayflow builds and operates the
software; the Hotel operates the hotel. The purpose of this agreement is
to let both sides build and test the software on real operations, and to
fix the model before it becomes a product.

### 2. What the Hotel commits

- To name one **contact person** for the product questions, and one for
the day-to-day operation.
- To take part in the interviews of `docs/interviews/PILOT_INTERVIEW_PACK.md`
(about 90 minutes, once) and to answer the questions that follow.
- To take part in the test windows of `docs/interviews/PILOT_TEST_CALENDAR.md`.
- To report what does not work, in the order it gets in the way.
- To let Stayflow use anonymized observations of the operation to improve
the software. **No guest data leaves the Hotel** without a written
instruction; see §5.

### 3. What Stayflow commits

- To keep the Hotel's guest data in the Hotel's own system, and to delete
it on request.
- To give the Hotel the read and write access it needs, and to say plainly
which parts are not ready yet.
- To fix, or to say clearly that it will not be fixed, every problem the
Hotel reports during the test windows.
- To tell the Hotel before anything changes that touches the prices, the
taxes or the reservation rules.

### 4. The software

- Access: {{URL of the Hotel's Stayflow instance}}
- The Hotel is the first user of the features that are not finished; a
feature that is marked "alpha" in the release notes may change or be
withdrawn.
- Nothing in the software is a commitment to a service level until the
pilot is over; the target is {{99.5% availability per OQ-09}}.

### 5. Data

- The data the Hotel puts into Stayflow stays in the Hotel's instance.
- Stayflow accesses the data only to support the Hotel, and only with the
contact person of the Hotel.
- **No guest data is used for anything else**, no anonymization for
analytics, no training, and no sharing with another hotel.
- On the end of the pilot, the Hotel can ask for its data back (export) or
for it to be deleted. The procedure is in `docs/runbooks/` (P6-05).

### 6. Commercial terms

- {{price or "no charge during the pilot"}}
- {{payment terms}}
- {{what happens after the pilot: a decision point, a date}}

### 7. Confidentiality

- Both sides keep what they learn from the other confidential, for
  {{duration}}.
- The Hotel may name itself as a design partner, with Stayflow's written
  agreement, once the pilot is over.

### 8. Exiting

- Either side may end the pilot at any time, with {{notice period}}.
- On the end of the pilot: the data is exported or deleted as the Hotel
  chooses, and the access is closed.

### 9. Signatures

| | |
|---|---|
| {{name}}, {{role}}, for the Hotel | {{name}}, {{role}}, for Stayflow |
| {{date}} | {{date}} |