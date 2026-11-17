# Plan: installment contract transfer platform (Egypt)

Status: **draft for Michael's review** (2026-11-17). Nothing below is decided unless it says "decided" or comes from
the agreement or the brief. No feature code is written until this plan is reviewed.

Sources: the agreement (13 November 2026), Michael's brief, `HANDOVER_FOR_NEW_PROJECT_2026-11-17.md`, and the
aqarexit study in `docs/research/`.

Names used here: **core** = the Laravel app (`core/`), **web** = the Next.js public site (`web/`), **dashboard** = the
admin screens inside core. The public site name is still open; this document says "the platform".

---

## 0. Already fixed (for reference, not for discussion)

From the agreement and the brief:

- Egypt only. Arabic first (RTL), English second, the full platform in both.
- Seller pays 0%. Buyer pays **1%** on completion (aqarexit: 1.25%). No "over": the buyer takes the contract at its
  original price.
- Unit types: residential, commercial, administrative, land.
- Visitors browse everything without an account; login is asked only on a real action (start selling, register a
  budget, save a favorite, request a unit).
- Forms, steps and fields follow aqarexit's flows, adapted to our 6 seller steps.
- Calculator values live in admin-editable settings, based on researched market averages.
- Missing client texts: we write temporary texts flagged as placeholders in the database; the dashboard lists them.
- The client's own claims stay as they wrote them.
- Out of scope: blog, page-text quality gate, IndexNow, AI chat, investors page or investor deals, buyer alerts,
  units map, online payment, WhatsApp, SMS, Pixel, Analytics.

Setup choices made in part 1 (departures from handover 2.1, with reasons):

| Handover | Now | Why |
|---|---|---|
| Inertia 2 + React 18 (dashboard) | Inertia 3 + React 19 | Current stable since March 2026; Inertia 3 requires React 19; one React version across both apps |
| Next 15 | Next 16.4 | Current stable |
| Tailwind 3.4 | Tailwind 4.3 | Current shadcn/ui targets v4; logical-property utilities built in |
| TypeScript strict | TypeScript 6.0 strict | TS 7 (native) is out, but typescript-eslint supports < 6.1 only |
| (Pest) | Pest 4.7 | Pest 5 needs PHP 8.4; local PHP is 8.3.15 |
| Several shops, twins manifest, `storefronts` table | One public site | One storefront: the multi-shop patterns do not apply |
| Mixed line endings (core LF, shops CRLF) | LF everywhere (`.ps1` CRLF) | New repository; one rule, enforced by `.gitattributes` |
| Next template default `cacheComponents: true` | off; ISR as in handover 2.2 | Proven path with next-intl; can be revisited |

The agreement names **MySQL**; local and planned is **MariaDB 11.8** (MySQL-compatible). Worth one line to the client.

---

## 1. Sitemap and page list

### 1.1 URL scheme: options (**Q1**)

| Option | Example AR / EN | Cost | Risk | Changes |
|---|---|---|---|---|
| **A. Arabic at root, English under `/en`, Latin slugs in both (recommended, built provisionally)** | `/opportunities` / `/en/opportunities` | none (already wired) | none known | same paths in both languages; easy sharing in WhatsApp (no percent-encoding) |
| B. Both prefixed (`/ar/…`, `/en/…`), root redirects to `/ar` | `/ar/opportunities` | small | a redirect on the most-linked URL | symmetric, slightly longer Arabic URLs |
| C. Arabic slugs for Arabic pages | `/الفرص` / `/en/opportunities` | slug pairs per page | percent-encoded links look broken when pasted | marginal relevance gain in Arabic results |

### 1.2 Public pages (web)

| Page | AR URL | EN URL | Index? | Notes |
|---|---|---|---|---|
| Home | `/` | `/en` | yes | |
| About | `/about` | `/en/about` | yes | aqarexit calls it "Our story" |
| How it works | `/how-it-works` | `/en/how-it-works` | yes | seller and buyer journeys, fees |
| FAQ | `/faq` | `/en/faq` | yes | FAQPage markup |
| Contact | `/contact` | `/en/contact` | yes | contact form |
| Privacy | `/privacy` | `/en/privacy` | yes | |
| Terms | `/terms` | `/en/terms` | yes | |
| Opportunities | `/opportunities` | `/en/opportunities` | yes (page 1..n) | filters and sort as query; filtered URLs `noindex, follow` and blocked in robots |
| Unit detail | `/units/{slug}` | `/en/units/{slug}` | yes while listed | slug e.g. `mivida-apartment-3-bedrooms-u10234` (see 5.2) |
| Compounds index | `/compounds` | `/en/compounds` | yes | links every compound page (crawl path) |
| Compound page | `/compounds/{slug}` | `/en/compounds/{slug}` | yes when it has units (threshold: **Q14**) | its units + intro text |
| For developers | `/developers` | `/en/developers` | yes | intro + partnership request form |
| Exit calculator | `/calculator` | `/en/calculator` | yes | |
| Reports & indicators | `/reports` | `/en/reports` | yes | admin-edited |
| Sell your unit (start) | `/sell` | `/en/sell` | yes | explains, then asks to log in / register |
| Seller wizard | `/account/units/{id}/edit/{step}` | `/en/…` | noindex | 6 steps, behind login |
| Register budget (buyer) | `/account/budget` | `/en/account/budget` | noindex | behind login; the public explainer is a section of How it works / home (**Q12**) |
| My account | `/account` | `/en/account` | noindex | my units and their status, my budget, my requests |
| Favorites | `/account/favorites` | `/en/account/favorites` | noindex | |
| Login / Register | `/login`, `/register` | `/en/login`, `/en/register` | noindex | |
| Forgot / reset password | `/forgot-password`, `/reset-password/{token}` | `/en/…` | noindex | |
| Verify e-mail | `/verify-email/{id}/{hash}` | `/en/…` | noindex | |
| Not found | any | any | noindex | |
| `sitemap.xml`, `robots.txt` | root | | | sitemap index per type and language |

### 1.3 Dashboard pages (core, Arabic RTL; language **Q5**)

Login · Overview (counts, what waits for action) · Units review queue · Unit detail (data, documents, exit report,
history) · Documents review · Sellers and buyers (users) · Buyer budgets · Matching · Deals (stage board and list) ·
Commissions · Compounds · Developers · Areas · Unit request inbox · Partnership requests · Contact messages · Reports
and indicators editor · Calculator settings · Site texts (with the placeholder list) · Admin users and roles ·
Activity log.

---

## 2. Flows

Each flow lists steps, fields, states and the e-mails it sends. Field rules borrow aqarexit's limits where sensible
(money > 0, money ≤ 1,000,000,000 EGP; installment ≤ 100,000,000; name ≥ 2 chars; password ≥ 8).

### 2.1 Accounts (shared)

- **Register:** full name, e-mail, password (≥ 8), mobile (Egyptian format, **Q11** whether required), consent
  to terms and privacy. Then a verification e-mail.
- **Login:** e-mail + password. "Forgot password" → reset link by e-mail.
- **Return to the action:** after login/register the visitor lands back on what they were doing (the unit they
  favorited, the sell start, the budget form), and that action completes (the favorite is saved).
- One account can be both seller and buyer.
- Whether an unverified e-mail may already submit: **Q13**.

E-mails: verify your e-mail · reset your password · (optional) welcome.

### 2.2 Seller flow (6 steps)

Entry: "Sell your unit" → `/sell` explainer → login/register → wizard. Each step saves a draft; the seller can stop
and resume from "My account". Steps follow aqarexit's fields (their 2 lead steps + 4 portal steps), re-cut into 6
because our account comes first.

**Proposed steps (Q7: approve or reorder):**

| Step | Title | Fields |
|---|---|---|
| 1 | Contract basics | Developer (pick from list or "other" + text), Compound (pick from list filtered by developer, or "other" + text), Unit type (residential / commercial / administrative / land) and sub-type (apartment, villa, office, shop, clinic, plot …), Total contract price (EGP), Total paid so far (EGP), Contract date (month/year), **zero-over acknowledgement** (required) |
| 2 | Unit details | Size (m²), Floor, Bedrooms, Bathrooms (not for land), Garden / roof (m², optional), Finishing (core & shell / semi / fully finished), Delivery status (ready / under construction) and expected delivery year, Unit number as in the contract (private, never shown), Contract number (private, for duplicate blocking) |
| 3 | Payment plan | Remaining to developer (EGP), Installment amount, Frequency (monthly / quarterly / semi-annual / annual), Last installment date (month/year), Overdue installments (count and amount, default 0), Maintenance deposit still due (optional), Today's developer price for a similar unit (optional) |
| 4 | Documents | Contract (required), Payment receipts (required, several files), Latest developer statement (optional), National ID (optional; **Q9**). PDF or image, size limit per file |
| 5 | Photos & description | Photos (at least 1, up to N), Description (free text, max length) |
| 6 | Review & submit | Read-only summary, the generated **exit report** preview, owner-not-broker declaration (required), terms consent, Submit |

Cross-field checks aqarexit lacks: paid < total; remaining ≈ total − paid (warn if off by more than a tolerance);
installment ≤ remaining; last installment date after today unless remaining = 0; delivery year ≥ contract year.

**Duplicate block:** on submit, the contract key (see 3.4) is checked against every unit that is not rejected,
withdrawn or sold. A match stops the submission with a clear message and shows the admin the attempt.

**Exit report PDF:** generated automatically at step 6 from the seller's figures and the current calculator settings
(content in 4.4). Timing and versions: **Q8**.

**Unit states**

```
draft → submitted → (changes_requested ↔ submitted) → published → reserved → sold
                  ↘ rejected            published → withdrawn (by seller or admin)
                                         reserved  → published (deal cancelled)
```

- `verified` is a separate flag set when the admin approves the contract and receipts (the "verified unit" badge).
  Publishing before verification: **Q10**.
- The seller sees the state, the documents' review status (received / approved / needs a clearer copy + reason) and
  the next step in "My account".

**E-mails to the seller:** submission received (with the exit report PDF attached or linked) · a document needs
replacing (reason) · unit published · unit rejected (reason) · a deal started on your unit · deal stage changed
(selected stages) · deal completed.

**E-mails to the admin team:** new unit submitted · documents re-uploaded · seller withdrew a unit.

### 2.3 Buyer flow

**Register a budget** (login required). Fields from aqarexit's buyer form:

| Group | Fields |
|---|---|
| Capacity | Cash available now (EGP, ≥ 0), Maximum comfortable installment (EGP, ≥ 0), Installment frequency |
| Preferences | Areas (multi, up to N), Compounds (optional, multi), Unit types (multi), Target contract price / budget (optional), Minimum bedrooms (optional), Delivery: ready / under construction / either |

The budget can be edited any time; matching is done by the admin (2.4). No automatic alerts (out of scope).

**Favorites:** a heart on cards and unit pages; login asked on first use, then the favorite is saved.
"My favorites" lists them, marking units no longer available.

**Request a unit** (login required): on the unit page, "Request this unit" with: a question for the team (optional,
max 500), readiness (ready now / within 1–3 months / just looking), **fee consent** showing the exact 1% amount, then
submit. Limits per buyer (aqarexit: 5 units per 30 days; **Q15**). Contact details typed into the question are
masked.

Request states: `new → contacted → matched (deal opened) | closed (reason)`.

**E-mails to the buyer:** budget saved (optional) · request received (reference) · a deal was opened with you · deal
stage changed (selected) · deal completed. **To the admin team:** new unit request · new budget registered.

### 2.4 Admin flow

| Area | What the admin does | Result |
|---|---|---|
| Units review | Open a submitted unit, check fields against documents, edit typos (logged), approve each document or ask for a replacement (reason), then publish or reject (reason) | state change + e-mail to seller |
| Verification | Mark "verified" once contract and receipts are approved | badge on card and page |
| Duplicates | See blocked attempts and possible near-duplicates; can override with a reason | logged |
| Compounds / developers / areas | Create, edit, merge "other" entries typed by sellers into the managed lists | compound pages fill up |
| Matching | For a unit: buyers whose budget fits (cash ≥ cash required, installment fits, area/type match), ranked; for a buyer: units that fit. Open a deal from a pair | deal created, unit `reserved` (**Q16** when) |
| Deals | Move a deal through its stages with a date and note; cancel with a reason | history, e-mails |
| Commissions | Each deal carries its commission record; mark invoiced / collected with date and reference | commission report |
| Requests and messages | Unit requests, partnership requests, contact messages: assign, note, close | |
| Content | Reports and indicators; site texts (placeholder list); calculator settings | public pages update |

**Deal stages (proposal, Q16):**

```
opened (buyer + unit linked) → buyer confirmed (viewing / call done) → seller accepted
→ documents and developer approval → signing and payment → completed
                                   any stage → cancelled (reason)
```

E-mails go to both parties at: opened, developer approval obtained, completed, cancelled.

**Commission record:** deal, base amount, rate (from settings, 1% today, frozen on the deal), amount, VAT if
applicable (**Q3**), status `expected → due (on completion) → invoiced → collected | waived (reason)`, dates,
reference, notes. The commission base (contract price or something else) and VAT are **Q3**.

**Roles (Q5):** handover pattern is admin + data-entry. Proposal: `admin` (everything, settings, users) and
`reviewer` (units, documents, requests, deals; no settings, no commissions). Every write is in the activity log.

---

## 3. Data model (options, not decided)

Per the handover, listings and lead forms are designed with Michael. Each block gives options and a recommendation.

### 3.1 Units: one table or case + listing

| Option | Shape | Cost | Risk | Changes |
|---|---|---|---|---|
| **A. One `units` table (recommended)** | the seller's submission and the public listing are the same row; public fields are whitelisted by one API resource | fewest tables, simplest queries | a private field could leak if a resource is written carelessly: covered by a test that fails on any non-whitelisted field | private columns (unit number, contract number, documents) never leave core |
| B. `cases` + `listings` (aqarexit's shape) | the private case and its public listing are separate rows | more tables and syncing | the two drift apart (aqarexit shows inconsistent figures) | allows several listings per case, which we do not need |

### 3.2 Compounds and developers

| Option | Shape | Cost | Risk |
|---|---|---|---|
| **A. Managed lists + "other" (recommended)** | `developers`, `compounds` (developer, area, names AR/EN, slug, intro text, published flag); the seller picks from the list or types "other", and the admin maps it on review | admin work per new name | a wrong mapping, mitigated by a merge tool and the log |
| B. Free text, normalised later | as aqarexit | none at first | compound pages impossible to build cleanly; duplicates ("Palm hills" vs "palm Hills") |

Compound pages (in scope) need A.

### 3.3 Areas

**Recommended:** a two-level list (governorate → area/city) seeded from a reviewed file, editable in the dashboard;
compounds belong to an area. Alternative: one flat list (simpler, weaker filters).

### 3.4 Duplicate contract key

| Option | Key | Catches | Misses |
|---|---|---|---|
| **A. Developer + normalised contract number, with developer + compound + normalised unit number as a second key (recommended)** | unique among active units | the same contract listed twice, even with other photos or prices | a contract number typed wrongly, caught by the second key |
| B. sha256 of the uploaded contract file | exact file | re-uploads of the same file | any re-scan or photo |
| C. Both A and B | | most cases | |

Recommend A for blocking, B as an extra admin warning.

### 3.5 Money and enums

- Money as integer EGP (no piastres) in `BIGINT UNSIGNED`; percentages as basis points (`100` = 1%).
- Every choice (type, finishing, frequency, state) stored as a **code**, translated per language: avoids aqarexit's
  Arabic values on the English site.
- Monthly-equivalent installment computed in one place (for matching, sorting, compare).

### 3.6 Proposed tables (with option A everywhere)

| Table | Key columns |
|---|---|
| `users` | name, email, password, phone, locale, email_verified_at |
| `admin_users`, `admin_roles` | separate from public users (handover 2.3: roles in their own table) |
| `developers` | name_ar, name_en, slug, published |
| `areas` | parent_id, name_ar, name_en, slug |
| `compounds` | developer_id, area_id, name_ar, name_en, slug, intro_ar, intro_en, published |
| `units` | code (`U-10001`), slug, seller_id, developer_id / developer_other, compound_id / compound_other, type, subtype, size_m2, floor, bedrooms, bathrooms, garden_m2, finishing, delivery_status, delivery_year, contract_month, total_price, paid_amount, remaining_amount, installment_amount, installment_frequency, last_installment_month, overdue_count, overdue_amount, maintenance_due, developer_price_today, description, contract_number (private), unit_number (private), contract_key (unique among active), state, verified_at, published_at, rejected_reason |
| `unit_photos` | unit_id, path, sort, width, height |
| `unit_documents` | unit_id, type, path, sha256, status, review_note, reviewed_by, reviewed_at |
| `exit_reports` | unit_id, version, inputs (JSON), settings snapshot (JSON), results (JSON), pdf_path, generated_at |
| `buyer_budgets` | user_id, cash_available, max_installment, frequency, target_price, min_bedrooms, delivery_pref |
| `buyer_budget_areas`, `buyer_budget_compounds`, `buyer_budget_types` | pivots |
| `favorites` | user_id, unit_id |
| `unit_requests` | unit_id, buyer_id, note, readiness, fee_consent_amount, fee_consent_at, state |
| `deals` | unit_id, buyer_id, seller_id, stage, opened_at, completed_at, cancelled_reason |
| `deal_stage_events` | deal_id, from, to, note, admin_id, at |
| `commissions` | deal_id, base_amount, rate_bp, amount, vat_bp, vat_amount, status, invoiced_at, collected_at, reference |
| `settings` | key, typed value, group (calculator, fees, contact facts), updated_by |
| `developer_policies` | developer_id, overrides of calculator settings (penalty range, refund rule, transfer fee) |
| `report_indicators`, `report_issues` | admin-edited figures with sources and dates; optional PDF issues |
| `site_texts` | key, ar, en, is_placeholder, source (client / placeholder), updated_by |
| `partnership_requests`, `contact_messages` | form data, state, notes |
| `blocked_duplicates` | attempted key, user, matched unit, at |
| `activity_log` | who, what, before/after |
| `mail_outbox` | handover 2.5: row first, then send; retried by the scheduler |

---

## 4. Calculator

### 4.1 Inputs

**Seller tab:** total contract price, total paid so far, contract date (or years paid), developer (optional, picks
up that developer's policy), overdue installments (optional).

**Buyer tab:** contract price (old), developer price today for a similar unit, transfer fee payer (from settings).

### 4.2 Formula (proposal; every constant is a setting)

```
S = settings, or the developer's policy when one exists

Cancellation
  penalty_min   = total_price * S.penalty_pct_min           # basis: total price (S.penalty_basis = total | paid)
  penalty_max   = total_price * S.penalty_pct_max
  late_fees     = overdue_amount * S.late_fee_pct            # optional
  refund_min    = max(0, paid - penalty_max - late_fees)
  refund_max    = max(0, paid - penalty_min - late_fees)
  refund_years  = S.refund_rule == mirror ? years_paid : S.refund_years_fixed
  refund_pv_min = present value of refund_min paid evenly over refund_years at S.discount_rate   # proposal 5.3
  zone          = wipeout | deep_loss (> S.deep_loss_ratio) | moderate_loss

Transfer through the platform
  recovery      = paid                                       # 0% seller fee
  transfer_fee  = contract_price * S.developer_transfer_fee_pct, shown with who pays it (S.transfer_fee_payer)
  seller_net    = recovery - (transfer_fee if seller pays)
  advantage     = seller_net - refund (nominal and present value)

Buyer
  fee           = round(contract_price * S.buyer_fee_pct)    # 1%
  fee_vat       = fee * S.vat_pct if applicable (Q3)
  gain          = developer_price_today - contract_price - fee - fee_vat - (transfer_fee if buyer pays)
```

### 4.3 Market values to research (each with a source and a date, stored in settings)

1. Cancellation (refund) deduction in Egyptian developer contracts: typical range and its basis (total price or
   amount paid); differences between large developers.
2. Refund schedule after cancellation: lump sum vs installments; usual period (mirroring the payment period or
   fixed).
3. Late-payment penalties: rate and basis (per month or per year, on the overdue amount), grace period, when
   cancellation is triggered (number of missed installments).
4. Developer assignment (transfer) fee: percentage or fixed amount, its basis, and who usually pays (buyer, seller,
   split).
5. Conditions on assignment: minimum paid share before a developer allows transfer, blackout periods, approval time.
6. Maintenance deposit at delivery (percentage of price) and whether it transfers.
7. A discount rate for the present-value line (for example the Central Bank of Egypt's rate) if proposal 5.3 is
   accepted.
8. VAT treatment of a brokerage commission (14%?), confirmed by the client (Eng. Enas).

Method: public sources only (developer contract terms published online, law firm and press articles, court
summaries), at least two independent sources per value, each recorded with URL and date; values marked "market
average" on the page. The client reviews the numbers before launch.

### 4.4 Exit report PDF content (proposal)

Unit summary · the seller's figures (marked "as declared" until verified) · cancellation scenario (range, refund
period, present value) · transfer scenario · difference · assumptions with their source and date · disclaimer (client
text) · reference number and date. Arabic and English versions; fonts embedded with a Unicode map so the text is
selectable (aqarexit's PDFs are not).

PDF engine (**Q17**): **mPDF (recommended)**: pure PHP, shapes Arabic and RTL, runs on shared hosting. Dompdf:
poor Arabic shaping. Headless Chrome (Browsershot): best rendering, needs Chrome on the server, unlikely on shared
hosting.

---

## 5. Where we improve on aqarexit (proposals)

Each item is a proposal for Michael to accept or drop.

### 5.1 UX

1. Enums as codes, so the English site is fully English (aqarexit shows Arabic values on `/en`).
2. Cross-field money checks (2.2); impossible figures cannot be saved.
3. Real pages for listings (`?page=2` with its own canonical) instead of "show 48 more".
4. Compound pages and a compounds index (aqarexit has none).
5. Calculator: settings per developer, present value of the refund, late fees and transfer fee shown.
6. The language switch is a real link to the same page in the other language.
7. "Total due now" shows only cash due now; the 1% appears as "due on completion" (aqarexit adds it to today's total).
8. Return-to-action after login (the favorite or request completes after sign-in).
9. Readable unit URLs (5.2) and the unit code shown and searchable.
10. A partnership form on the developers page (aqarexit has only a WhatsApp link).
11. The dark, luxury design direction from the brief, with accessible contrast (WCAG AA) checked in both languages.

### 5.2 SEO basics (within scope)

1. Canonical + hreflang (ar-EG, en, x-default) on **every** page (aqarexit misses them on home and legal pages).
2. Unique titles per page with a length budget; unit titles: compound + type + bedrooms + area + code.
3. Sitemap index split by type and language, real `lastmod` from data, listed units only; no 4 MB single file.
4. robots.txt without prefix traps (aqarexit's `/buy` rule blocks `/buyers`), filtered listing URLs disallowed.
5. Structured data: Organization + WebSite on home, BreadcrumbList everywhere, FAQPage on the FAQ,
   `RealEstateListing` with an `Offer` on unit pages (instead of a bare Product), ItemList on listings and compound
   pages.
6. Server-rendered first paint everywhere (handover 2.2): cards, prices and text in the HTML.
7. Unit slug `/{compound}-{type}-{bedrooms}-bedrooms-{code}`; old slugs redirect 301 when data changes.

### 5.3 Performance

1. Send the browser only the message namespaces a page uses (aqarexit ships 82–95 KB of catalogue on every page).
2. A page-weight budget per template, checked with the handover's crawler/Lighthouse scripts; the listing well under
   aqarexit's 806–848 KB HTML.
3. Images resized to fixed widths, WebP, lazy below the fold, fixed aspect ratio (no layout shift).
4. ISR on public pages; core answers from a versioned file cache (handover 2.2).

---

## 6. Texts needed from the client

Agreement clause 7: the client supplies every text in Arabic and English and is responsible for its content.
Until a text arrives, we draft a placeholder, flag it in `site_texts.is_placeholder`, and the dashboard lists it.

Legend: **P** = we draft a placeholder now; **C** = client text required before launch, with a placeholder only so the
page works; **F** = a fact we must not invent (left visibly empty until supplied).

| # | Text | AR | EN | Mark |
|---|---|---|---|---|
| 1 | Site name shown on the site and in e-mails (Lo2ta4U or لقطة لك) | | | **F** (Q2) |
| 2 | Home: hero, value proposition, seller and buyer sections, steps, CTAs | ✓ | ✓ | P → C |
| 3 | About page | ✓ | ✓ | C |
| 4 | How it works: seller journey, buyer journey, fees | ✓ | ✓ | P → C |
| 5 | FAQ questions and answers | ✓ | ✓ | P → C |
| 6 | Privacy policy | ✓ | ✓ | C (legal; Eng. Enas) |
| 7 | Terms and conditions | ✓ | ✓ | C (legal) |
| 8 | Seller declarations: zero-over acknowledgement, owner-not-broker | ✓ | ✓ | C (legal) |
| 9 | Buyer fee consent wording | ✓ | ✓ | C (legal) |
| 10 | Calculator and exit report disclaimers | ✓ | ✓ | C (legal) |
| 11 | Exit report PDF wording (headings, explanations) | ✓ | ✓ | P → C |
| 12 | Developers page intro and partnership points | ✓ | ✓ | P → C |
| 13 | Reports page intro and methodology | ✓ | ✓ | P → C |
| 14 | Initial indicators for the reports page (with sources) | ✓ | ✓ | F |
| 15 | Contact facts: phone, e-mail, address, working hours, social profiles | | | F |
| 16 | Company facts for markup and footer: legal name, founding year | | | F |
| 17 | Compound intros (per compound) | ✓ | ✓ | P (short, factual) → C |
| 18 | E-mail templates (about 20, listed in 2.x) | ✓ | ✓ | P → C |
| 19 | Form labels, hints, validation and error messages | ✓ | ✓ | P (we write; client reviews) |
| 20 | Unit state and deal stage labels shown to users | ✓ | ✓ | P |
| 21 | Meta titles and descriptions per page | ✓ | ✓ | P → C |
| 22 | Logo files (have: navy and transparent PNG), favicon, social image | | | have / need SVG |

---

## 7. Phases (checklists, no durations)

Durations come only from timed work, so none are given. The agreement's own clock (one and a half months from the
first payment and all client texts) is a contract matter for Michael.

**Phase 0: Setup (done in this session; awaiting the initial commit)**
- [x] Repository, `.gitignore`, `.gitattributes`, `.editorconfig`, README
- [x] Core: Laravel 13, MariaDB scratch + test databases, Inertia 3 + React 19 + TS + Tailwind 4 dashboard shell
- [x] Tooling: Pint, PHPStan level 10, Pest 4 (guarded TestCase), ESLint, `tsc`, RTL check script
- [x] Web: Next 16 + next-intl, Arabic at root and English under `/en`, `lang`/`dir`, canonical + hreflang helper
- [ ] Initial commit by Michael (script provided)

**Phase 1: Foundations**
- [ ] Decisions from section 8 recorded
- [ ] Migrations for the agreed model; seeders for areas, unit types, sample developers and compounds (scratch only)
- [ ] Admin auth, roles, activity log, mail outbox
- [ ] `site_texts` with placeholder flag; `settings` with typed values
- [ ] Public API v1 skeleton with server key (handover 2.2) and the field-whitelist test

**Phase 2: Visual identity and site shell** (agreement: palette, fonts, page design, UX; 3 free revision rounds)
- [ ] Palette, Arabic and Latin fonts, components; dark luxury direction
- [ ] Header, footer, language link, mobile navigation
- [ ] Static pages with placeholders: home, about, how it works, FAQ, contact, privacy, terms

**Phase 3: Accounts**
- [ ] Register, verify, login, reset, return-to-action
- [ ] Favorites
- [ ] Buyer budget form and "My account"

**Phase 4: Seller**
- [ ] 6-step wizard with drafts and cross-field checks
- [ ] Uploads (documents, photos) with limits and private storage for documents
- [ ] Duplicate contract block
- [ ] Exit report PDF (AR + EN)
- [ ] Unit status in "My account"; seller e-mails

**Phase 5: Listings**
- [ ] Opportunities page: filters, sort, pagination, server-rendered cards
- [ ] Unit page: specs, money block, verified badge, request form
- [ ] Compound pages and index; developers page with partnership form; contact form

**Phase 6: Tools**
- [ ] Calculator (both tabs) on settings and developer policies
- [ ] Market values researched, sourced and loaded as settings
- [ ] Reports and indicators page + dashboard editor

**Phase 7: Dashboard operations**
- [ ] Units review, document review, publish/reject, verification
- [ ] Matching screen, deals and stages, commissions
- [ ] Inboxes: unit requests, partnership requests, contact messages
- [ ] E-mails to both parties per stage

**Phase 8: English, SEO, quality**
- [ ] Every page and e-mail complete in English
- [ ] Sitemaps, robots, structured data, titles, canonical/hreflang checks (crawler, PASS/FAIL)
- [ ] Page weight, images, accessibility pass

**Phase 9: Delivery**
- [ ] Placeholder report from the dashboard: every text still waiting for the client
- [ ] Deploy scripts per handover 3 (numbered steps, STOP, backups, rehearsal on a scratch copy), live checks
- [ ] Hand-over notes for the client team

---

## 8. Open questions for Michael

Ordered by what blocks work first. Each has a recommendation where one makes sense.

| # | Question | Recommendation |
|---|---|---|
| Q1 | URL scheme (1.1) | A: Arabic at root, `/en` for English, Latin slugs |
| Q2 | Which name does the site show: "Lo2ta4U", "لقطة لك", or both (Arabic name on AR, Latin on EN)? | Michael's / client's call. Until then code says "lo2ta" and pages show a placeholder |
| Q3 | Commission: base of the 1% (total contract price, like aqarexit's 1.25%, or the cash paid?), VAT on top or included, who pays the developer's transfer fee | Total contract price (comparable with aqarexit); VAT and transfer-fee payer per Eng. Enas |
| Q4 | aqarexit's flows behind `/app` are robots-blocked. To see the real portal screens: (a) Michael registers a test account and screenshots them, (b) keep the reconstruction from code and catalogue (done), (c) skip | b now; a only if a screen is unclear later |
| Q5 | Dashboard: Arabic only, or both languages? Roles: admin + reviewer? | Arabic RTL only (staff are Arabic speakers), admin + reviewer |
| Q6 | Data model options 3.1–3.4 | A in each |
| Q7 | The 6 seller steps as cut in 2.2 | As proposed |
| Q8 | Exit report: generated at submit from declared figures, and regenerated as "verified" after document review? | Yes, two versions, both kept |
| Q9 | Required documents: contract + receipts (agreement). Also ask for national ID and developer statement (optional)? | Contract + receipts required; statement optional; ID not collected (privacy) unless the client needs it |
| Q10 | Publish before or only after document verification? | Publish only after the admin's review; badge only after contract + receipts approved |
| Q11 | Mobile number at registration: required, optional, or not asked? (WhatsApp and SMS are out of scope, but staff call people) | Required, Egyptian format, not verified |
| Q12 | Is there a public "for buyers" explainer page, or a section only? (Agreement page list has none) | A section on home and How it works, no extra page |
| Q13 | Must the e-mail be verified before submitting a unit or a request? | Allow drafts; require verification before submit |
| Q14 | Compound page indexed from how many published units? | From 1 unit, noindex at 0 (keeps the page alive between units) |
| Q15 | Limits on unit requests per buyer | 5 open requests per buyer |
| Q16 | Deal stages (2.4) and when the unit becomes "reserved" | As proposed; reserved when the seller accepts |
| Q17 | PDF engine | mPDF |
| Q18 | Captcha on forms: Cloudflare Turnstile (external script, free) or honeypot + rate limits only | Honeypot + rate limits first; Turnstile only if spam appears |
| Q19 | Uploaded documents storage on the host: private disk outside `public/`, served through a signed admin route | Yes |
| Q20 | Allow "open to negotiation" (seller accepts less than paid), as aqarexit does? | Client's call; not in the agreement, so off unless they ask |
| Q21 | Market price on cards ("gain vs market") is the seller's own figure on aqarexit. Show it, hide it, or show only a developer-price figure verified by the admin? | Show only when the admin has verified it |
| Q22 | Hosting stays open (Michael's call); mail: the two official mailboxes in the agreement as SMTP sender | Noted, no action now |
