# aqarexit 04: opportunities listing and unit page

## Listing (`/opportunities`)

- **Header:** title, trust line ("every opportunity was reviewed before appearing"), two big counters: units on the
  platform (11,358 "published and under review") and estimated market value (EGP 162.2 bn).
- **Search:** one text box, "project, area or unit code (U-10057)", max 80 characters.
- **Filter panel** (opens client-side; filter state from the page props):
  `q, areas[], types[], maxCash, maxInstallment, maxPrice (budget), minBedrooms, readiness (ready / off-plan),
  flexible (open to negotiation), deliveryBy (year), contractBy (year), sort`.
  A quick chip "↓ open to negotiation".
- **Sort (11):** best for me 🎯, newest (default), featured ⭐, lowest cash, lowest installment, biggest gain, nearest
  delivery, transfer-ready, most requested, most saved, most visited.
- **"Best for me" preferences box:** areas (up to 3), unit type, cash bands (… "more than 5M"), monthly installment
  (optional).
- **Featured strip** at the top (paid placements; out of our scope as paid, but a staff-chosen "featured" flag is
  possible).
- **Results count** ("10,456 units available") and **pagination by "show 48 more"** (`?show=96`), one growing page.
- **Closed units** kept in a section "for their prices and projects only".
- The page also shows an empty-state text ("first verified opportunities coming soon") at the same time as 10,000
  units: a template bug.

### Card (each unit)

Photo · "Documents verified" badge · ⭐ featured · project name · developer · area · unit code (`U-22472`) ·
type · bedrooms · bathrooms · size m² · **cash required now** · "↓ open to negotiation" · **gain vs market**
(+ amount) · market price today (owner's figure) "after our 1.25% fee" · disclaimer "market price is the owner's,
not our valuation" · remaining to developer · installment + frequency · delivery year · "Details" link.

## Unit page (`/buy/opportunity/<uuid>`)

Top: back to opportunities · H1 project name · developer · area · type · unit code (copy button) · verified badge ·
featured · share (copy link, WhatsApp, Telegram, Facebook, e-mail) · add to favorites · photo gallery.

**Specs:** type, size m², floor, bedrooms, bathrooms, finishing, completion status, contract year, contract price
per m², garden/roof, amenities, free-text description (written by the seller, shown as is).

**Money block:**

| Line | Example |
|---|---|
| Cash required now ("exactly what the owner paid, zero overprice") | EGP 4,100,000 |
| Your gain vs current market price (after the 1.25% buyer fee) | + EGP 817,500 |
| Market price today (owner's estimate, their responsibility) | EGP 7,500,000 |
| Remaining to developer (you continue as installments) | EGP 2,500,000 |
| Installment + frequency | EGP 133,000 · quarterly |
| Delivery | 2029 |
| Buyer fee (1.25% of contract value, on completion) | EGP 82,500 |
| Total required from you now | EGP 4,182,500 (cash + fee, although the fee is due on completion) |

Optional blocks: "open to negotiation: this seller will take less than they paid", overdue installments ("arrears
must be cleared before the developer approves the transfer"), installments remaining (years), "N buyers asked this
week", "advertising permit no." and "licensed broker" lines (regulatory, appear to be for Saudi Arabia).

**Same-project compare:** "N more units in the same project: lowest cash {x}, this one {y}" → a compare table
(cash today, total today incl. fee, remaining, monthly-equivalent installment, gain, price per m², contract price,
market price, size, bedrooms, bathrooms, floor, delivery, finishing), with verdicts per priority (lowest cash,
lowest monthly, earliest delivery, biggest gain).

**Similar featured units** (paid ads) · **enquiry form** (see 02) · Product + BreadcrumbList JSON-LD.

### Gain formula (checked on three cards)

```
contractPrice = cashRequired + remaining
fee  = 0.0125 * contractPrice
gain = marketPrice - contractPrice - fee
```

## Data quality seen

- No cross-field validation: one card shows a quarterly installment of EGP 20,840,000 against EGP 2,518,000
  remaining.
- On the English site, unit type, finishing, completion, garden and the fee note stay in Arabic (values stored as
  Arabic text, not as codes).
- Market price is free input by the seller and drives the "gain" headline.
- Unit code (`U-#####`) is the only human-friendly identifier; URLs are UUIDs.

## Compounds

There is **no page per compound** on aqarexit: project names are free text on each unit (spelled in mixed Arabic and
English: "ذا فالي the valley", "Badya", "Palm hills"). Our compound pages need a managed compound list, which
aqarexit lacks.
