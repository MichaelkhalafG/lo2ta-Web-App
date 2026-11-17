# aqarexit 02: flows, forms, states, e-mails

Sources: the served HTML of `/sellers`, `/exit-report`, `/calculator`, `/contact` and unit pages; their message
catalogue (`research-raw/messages_flat.tsv`); the public JS of `/sellers`. Nothing was submitted.

## Common pattern: "lead form creates the account"

One lead-form component serves three kinds (`seller`, `exit_report`, `buyer`). The last step asks for contact details
and a password, and submitting **creates the account and signs the person in** ("your account is created
automatically when you submit"). If the e-mail or phone already has an account, the request is still saved and the
person is asked for the existing password (or offered a recovery link to the registered e-mail).

Every form has: a Cloudflare Turnstile check ("Checking you're human…"), a hidden honeypot field `company`,
rate-limit and "page is stale after a deploy, refresh, your answers are kept" errors, answers kept in browser storage
across reloads, and a WhatsApp fallback link.

### Contact step (shared, last step of every lead form)

| Field | Rule |
|---|---|
| Name | required, at least 2 characters |
| Mobile | country code select (EG default, full world list) + local number, stored as E.164; Egyptian `01XXXXXXXXX` recognised |
| E-mail | required; detailed shape errors (spaces, Arabic letters, missing @, dots, domain) and a "did you mean" typo hint |
| Password | required, at least 8 characters, show/hide |
| Consent line | "By sending, we create your platform account… you agree to the privacy policy and to us contacting you" |

## Seller flow (their "6 steps" = 2 public + 4 in the portal)

`SELLER_JOURNEY_STEPS = 2 + 4` in their code.

**Public lead form on `/sellers` (steps 1–2):**

| Step | Fields |
|---|---|
| 1 Unit details (`stepContract`) | Developer name (text), Project/compound (text), Total unit price in contract (EGP, required, > 0, ≤ 1,000,000,000), Total paid so far (EGP, required, > 0, ≤ 1e9), **zero-over acknowledgement** checkbox (required: "the amount I receive is exactly what I paid"), Developer's price today for the same unit (optional; used to show buyers their saving) |
| 2 Contact | shared contact step + **owner-not-broker declaration** (required for sellers; brokers' units are rejected or deleted) |

Then a thank-you page: reference number, "your account is ready", "one step left: complete your unit details".

**Portal case wizard (steps 3–6), behind `/app`, not opened; titles from code:**

| Step | Title | Fields (inferred from unit page specs and catalogue) |
|---|---|---|
| 3 | Unit details | type, size m², floor, bedrooms, bathrooms, garden/roof, finishing, completion status (ready / under construction), contract year, delivery year, amenities, free-text description |
| 4 | Price & installments | remaining balance to developer, installment amount, frequency (monthly / quarterly / semi-annual / annual), installments remaining, overdue installments, market price today (owner's estimate), "open to negotiation" flag, private ("silent exit") listing option |
| 5 | Photos | unit photos (at least one to publish) |
| 6 | Review & send | summary, submit for review |

A no-password "magic link" page (`tokenPage`) also lets a seller complete missing unit details; expired links can be
re-sent to WhatsApp and e-mail.

**Seller documents** (portal): types contract, payment receipts, latest developer statement, national ID. Each file
has a status: uploaded (under review) → approved, or needs replacement (with a reason). Documents are optional for
listing, required before completing the sale.

**Seller case stages shown in the portal (8):** Request started → Unit details & photos → Reviewing & publishing →
Live on the marketplace → Finding the right buyer → You have an offer → Contract transfer (verifying documents) →
Completed. Also a "closed" state (request closed, not on the market).

**Offers (seller side):** list of offers with amount; accept (with a fee-consent line: seller pays nothing) or decline.
"Accepting is preliminary; nothing is final until you sign the official transfer documents; all communication with
the buyer goes through the platform team."

**Multiple units:** a seller can add another unit; each unit has its own file, buyers and progress.

## Exit report request (`/exit-report`)

Two steps: Unit details (developer, project, total price, paid) → Contact. Promised output: a written, human-reviewed
report on the actual contract: expected cancellation penalty, refund schedule, expected transfer value, a
recommendation. "Free for now". (Ours is different by contract: an exit report **PDF generated automatically**.)

## Buyer flow

**Lead form on `/buyers`** (page itself robots-blocked; fields from the shared component and catalogue):

| Step | Fields |
|---|---|
| Your capacity (`stepCapacity`) | Cash available now (EGP, required, ≥ 0, ≤ 1e9), Maximum comfortable installment (EGP, required, ≥ 0 — "type 0 if you won't pay in installments", ≤ 100,000,000), Installment frequency (monthly / quarterly / semi-annual / annual) |
| Your preferences (`stepPreferences`) | Areas or compounds (multi, from a fixed region list or free text, up to 8), Target contract price / budget (optional), Unit types (multi), Minimum bedrooms (optional), Handover: ready / under construction |
| Contact | shared contact step; "buyers only, no brokers" line |

**Buyer portal:** budget profile (edit), matching opportunities ("in your areas", "others you may like"), filter by
fit (area, cash, installment, budget, type, bedrooms, delivery), saved units, "my offers" with statuses: under
review → presented to seller → accepted / declined / withdrawn / expired. Alert preferences (new matching units, at
most 3 a day; promoted units): **buyer alerts are out of our scope**.

**Buyer steps as marketed:** budget → see matches → submit offer → confirm reservation → receive contract.

## Unit enquiry ("Grab this opportunity", on every unit page, no login)

| Field | Rule |
|---|---|
| Name | required |
| WhatsApp number | country code + local number, required |
| Question for the seller | required, max 500 characters; "contact details written are removed automatically" |
| Readiness (radio, required) | ready for a call today and a viewing within 48 h / ready in 1–3 months / browsing, need info |
| Fee consent (required) | "I agree to my buyer fee: 1.25% of the contract value ({amount}), paid only on successful completion" |
| Honeypot `company`, Turnstile | |

Caps: a few requests per phone per day; at most 5 units per buyer in 30 days. Success shows a reference number.

## Contact form (`/contact`, no account)

Name (required) · "How should we reply? (at least one)": mobile and/or e-mail · Subject select: general, I want to
sell, I want to buy, complaint, partnership, press · Message (required, 10–4000 characters) · honeypot · Turnstile.
Nudges: "if you're actually selling, a sell request is faster". Working hours: Saturday–Thursday, 10:00–21:00 Cairo.

## Accounts

Login: e-mail + password. Register: full name, mobile, e-mail, password (≥ 8), consent; confirmation e-mail
("open the link, then sign in"). One account per phone number; "forgot password"; recovery link by e-mail for an
existing phone. **Favorites** work without an account (stored on the device) and sync after sign-in. (Our decision:
favorites ask for login.)

## Notifications and e-mails (customer-facing; channel per kind not visible)

| Kind | Text (EN) | In our scope? |
|---|---|---|
| case.documents_pending | We need your documents | Yes |
| case.under_review | We're reviewing your file | Yes |
| case.assessment_ready / assessment.published | Your assessment is ready | Yes (exit report) |
| case.listed / unit.published | Your unit is live for buyers | Yes |
| doc.needs_replacement | We need a clearer copy of {document} (+ reason) | Yes |
| unit.request_received | A buyer asked about your unit | Yes |
| offer.received / offer.accepted | New offer on your unit / your offer was accepted | Yes (if we keep offers) |
| case.transfer_in_progress | The transfer has started | Yes |
| case.completed | Done, the transfer is complete | Yes |
| meeting.confirmed / meeting.reminder | Your meeting is set / is today | Optional |
| unit.match_found, unit.suggested, unit.promoted_match | Buyer alerts | **No** (buyer alerts out of scope) |
| unit.promotion_* (paid promotion, Fawry) | Paid featuring | **No** (online payment out of scope) |

Staff notifications (about 60 kinds: lead distribution to agents, "yellow cards", agencies, team chat, ad permits,
WhatsApp session) show a large back office far beyond our scope.
