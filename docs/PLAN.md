# Plan: installment contract transfer platform (Egypt)

Status: **reviewed by Michael on 2026-11-17.** Items marked "decided 2026-11-17" are fixed; items marked "proposal"
wait for review. No feature code is written until the proposals in 2.2 are approved.

Sources: the agreement (13 November 2026), Michael's brief, `HANDOVER_FOR_NEW_PROJECT_2026-11-17.md`, and the
aqarexit study in `docs/research/`.

Names used here: **core** = the Laravel app (`core/`), **web** = the Next.js public site (`web/`), **dashboard** = the
admin screens inside core. Site name (decided 2026-11-17): **لقطة** in Arabic, **Lokta** in English, domain
loktaeg.com. Internal identifiers keep "lo2ta" (repository, packages, databases).

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

### 1.1 URL scheme (**Q1**: option A, decided 2026-11-17)

| Option | Example AR / EN | Cost | Risk | Changes |
|---|---|---|---|---|
| **A. Arabic at root, English under `/en`, Latin slugs in both (decided 2026-11-17, already wired)** | `/opportunities` / `/en/opportunities` | none (already wired) | none known | same paths in both languages; easy sharing in WhatsApp (no percent-encoding) |
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
| Unit detail | `/units/{slug}` | `/en/units/{slug}` | yes while listed | slug e.g. `mivida-apartment-3-bedrooms-lk-nc-0124` (see 5.2, unit code in 2.2) |
| Compounds index | `/compounds` | `/en/compounds` | yes | links every compound page (crawl path) |
| Compound page | `/compounds/{slug}` | `/en/compounds/{slug}` | yes when it has units (threshold: **Q14**) | its units + intro text |
| For developers | `/developers` | `/en/developers` | yes | intro + partnership request form |
| Exit calculator | `/calculator` | `/en/calculator` | yes | |
| Reports & indicators | `/reports` | `/en/reports` | yes | admin-edited |
| For buyers | `/buyers` | `/en/buyers` | yes | public explainer like aqarexit's `/buyers` (**Q12**, decided 2026-11-17; not in the agreement's page list, see 2.2.0) |
| Sell your unit (start) | `/sell` | `/en/sell` | yes | explains the process, 0% seller fee and the no-over rule, then asks to log in / register |
| Seller wizard | `/account/units/{id}/edit/{step}` | `/en/…` | noindex | 6 steps, behind login |
| Register budget (buyer) | `/account/budget` | `/en/account/budget` | noindex | behind login; the public explainer is `/buyers` |
| My account | `/account` | `/en/account` | noindex | my units and their status, my budget, my requests |
| Favorites | `/account/favorites` | `/en/account/favorites` | noindex | |
| Login / Register | `/login`, `/register` | `/en/login`, `/en/register` | noindex | |
| Forgot / reset password | `/forgot-password`, `/reset-password/{token}` | `/en/…` | noindex | |
| Verify e-mail | `/verify-email/{id}/{hash}` | `/en/…` | noindex | |
| Not found | any | any | noindex | |
| `sitemap.xml`, `robots.txt` | root | | | sitemap index per type and language |

### 1.3 Dashboard pages (core; Arabic RTL and English LTR, decided 2026-11-17, Q5)

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

- **Register:** full name, e-mail, password (≥ 8), mobile (required, Egyptian format, not verified: decided
  2026-11-17, Q11), consent to terms and privacy. Then a verification e-mail. All verification and notifications are
  by e-mail only.
- **Login:** e-mail + password. "Forgot password" → reset link by e-mail.
- **Return to the action:** after login/register the visitor lands back on what they were doing (the unit they
  favorited, the sell start, the budget form), and that action completes (the favorite is saved).
- One account can be both seller and buyer.
- An unverified e-mail may save drafts; submitting a unit or a request needs a verified e-mail (decided 2026-11-17,
  Q13).

E-mails: verify your e-mail · reset your password · (optional) welcome.

### 2.2 Seller flow (decided 2026-11-17)

Michael's seller spec of 2026-11-17 replaces the earlier draft of this section and his earlier partial messages where
they differ. Items marked **proposal** (sub-types, features, finishing, editing after publishing, ID retention, the
unit code details, the points in 2.2.9) wait for his review. Wording in quotes is placeholder text for the client to
confirm (section 6).

#### 2.2.1 Entry

- "Sell your unit" (`/sell`) explains the process, the 0% seller fee and the no-over rule, then asks to log in or
  register. Return-to-action: after login the seller lands on step 1.
- A fixed notice on every step: "We deal with the unit's owner only. Requests from brokers or intermediaries are
  rejected, and any unit found to come from one is removed."
- On opening step 1 of a unit for the first time, a blocking modal: "No over price, ever. If your unit is published
  and you ask for an over price, it is removed." The seller must confirm ("I understand, I list without over") to
  continue. The confirmation is stored with a timestamp and the text version (per unit, **proposal**).
- Every step autosaves as a draft ("saved ✓"); the seller can leave and resume from My account. Required fields are
  marked *.

#### 2.2.2 The six steps

| Step | Title AR / EN | Fields |
|---|---|---|
| 1 | العقد / Contract | Developer * (managed list, or "other" + text) · Project / compound * (list filtered by developer, or "other" + text) · Area * (searchable two-level list, or "my area is not in the list" + text) · Main type * and sub-type * (2.2.3) · Contract number * (private) · Unit number as in the contract * (private) · Contract year * (dropdown, recent years first). The duplicate check runs at the end of this step and again on submit |
| 2 | الوحدة / Unit | Size m² * · garden m² · roof m² · Floor * · Bedrooms *, bathrooms * (residential only) · Finishing * (2.2.4) · Delivery status * (ready / under construction + expected delivery year) · Façade orientation (optional) · Features (multi, 2.2.5) · Short description * (min and max length: **proposal** 60 and 1,200 characters; aqarexit's minimum is not in their public material). Every field that does not apply to the chosen sub-type is hidden (matrix in 2.2.3) |
| 3 | الفلوس والأقساط / Money and installments | Total contract price * · Total paid to date * · Today's market price for a similar unit (seller's estimate) * · Installment amount *, frequency * (monthly / quarterly / semi-annual / annual) · Next installment due date * (unless installments are finished) · Years of installments left (optional; 0 if finished; shown next to the installment) · Annual extra payment yes/no (+ amount) · Ready to give up part of what I paid to sell faster yes/no (+ the lowest amount accepted) · Overdue installments yes/no (+ count and amount; hint: most developers refuse a transfer before arrears are paid) · Maintenance deposit still due (optional) |
| 4 | المستندات / Documents | Contract copy * (PDF or images, several files) · Installment schedule * · National ID copy * (front and back) · Payment receipts (optional by default; "required" is a setting) · Latest developer statement (optional) |
| 5 | الصور / Photos | 1 to 10 photos *, drag to reorder, the first is the cover · Floor plan image (optional, shown as a plain image) · Warning: no photos showing personal data, the unit number or documents |
| 6 | المراجعة والإرسال / Review and submit | Read-only summary of steps 1 to 5, each with an "edit" link · Exit report preview (declared figures, current calculator settings) · Declarations, each required: I am the owner, not a broker or intermediary; the data matches my contract and documents; I accept the terms and privacy policy · Submit |

Shown on step 3: "you recover what you paid, with no over"; "the buyer continues the remaining installments as they
are"; if the seller accepts less, the amount they will receive instead.

**Computed and checked on step 3** (limits per field as in 2: money > 0 and ≤ 1,000,000,000; installment ≤
100,000,000):

- remaining = total − paid.
- paid < total (see 2.2.9 point 1 for fully paid contracts).
- installment ≤ remaining.
- installment × installments left (years left × payments per year) roughly matches remaining: a **warning**, not a
  block, with a tolerance setting (proposal: 10%; annual extra payments counted in).
- next due date in the future (see 2.2.9 point 2 for overdue units).
- delivery year ≥ contract year.
- lowest accepted amount < paid (and > 0).

**Documents** are stored on a private disk, served only through admin-only signed routes, and never appear in any
public resource. **Photos** are resized to fixed widths, converted to WebP, and stripped of all EXIF data (location
included).

**After submit:** a confirmation screen and e-mail: received, reference number, the exit report PDF, and "we review
within 48 hours and reply by e-mail". The exit report is generated at submit from the declared figures and again as
a "verified" version after review; both are kept (Q8, decided 2026-11-17).

#### 2.2.3 Sub-types and which fields apply (proposal)

Garden and roof are optional everywhere they appear. Land hides finishing, floor, bedrooms, bathrooms, garden and
roof; its delivery status reads "utilities delivered / under development" (proposal).

| Code | AR | EN | Main type | Floor | Bed / bath | Garden | Roof |
|---|---|---|---|---|---|---|---|
| `apartment` | شقة | Apartment | residential | ✓ | ✓ | ✓ | ✓ |
| `garden_apartment` | شقة أرضي بحديقة | Ground-floor apartment with garden | residential | – (ground) | ✓ | ✓ | – |
| `duplex` | دوبلكس | Duplex | residential | ✓ (lower floor) | ✓ | ✓ | ✓ |
| `penthouse` | بنتهاوس | Penthouse | residential | ✓ | ✓ | – | ✓ |
| `studio` | استوديو | Studio | residential | ✓ | bedrooms 0, bath ✓ | ✓ | – |
| `serviced_apartment` | شقة فندقية | Serviced (hotel) apartment | residential | ✓ | ✓ | – | – |
| `townhouse` | تاون هاوس | Townhouse | residential | – | ✓ | ✓ | ✓ |
| `twin_house` | توين هاوس | Twin house | residential | – | ✓ | ✓ | ✓ |
| `quattro_villa` | كواترو فيلا | Quattro villa | residential | – | ✓ | ✓ | ✓ |
| `i_villa` | آي فيلا | i-Villa | residential | ✓ (it is stacked: garden or roof level) | ✓ | ✓ | ✓ |
| `standalone_villa` | فيلا مستقلة | Standalone villa | residential | – | ✓ | ✓ | ✓ |
| `chalet` | شاليه | Chalet | residential | ✓ | ✓ | ✓ | ✓ |
| `cabin` | كابينة | Cabin | residential | – | ✓ | ✓ | – |
| `shop` | محل تجاري | Shop | commercial | ✓ | – | – | – |
| `pharmacy` | صيدلية | Pharmacy | commercial | ✓ | – | – | – |
| `restaurant_cafe` | مطعم أو كافيه | Restaurant or café | commercial | ✓ | – | ✓ (outdoor area) | – |
| `kiosk` | كشك | Kiosk | commercial | ✓ | – | – | – |
| `showroom` | معرض (شوروم) | Showroom | commercial | ✓ | – | – | – |
| `warehouse` | مخزن | Warehouse | commercial | ✓ | – | – | – |
| `office` | مكتب إداري | Office | administrative | ✓ | – | – | – |
| `administrative_unit` | وحدة إدارية | Administrative unit | administrative | ✓ | – | – | – |
| `clinic` | عيادة | Clinic | administrative | ✓ | – | – | – |
| `residential_land` | أرض سكنية | Residential land plot | land | – | – | – | – |
| `commercial_land` | أرض تجارية | Commercial land plot | land | – | – | – | – |
| `administrative_land` | أرض إدارية | Administrative land plot | land | – | – | – | – |
| `industrial_land` | أرض صناعية | Industrial land plot | land | – | – | – | – |

Open points in this list: `office` and `administrative_unit` overlap (proposal: keep one, "مكتب / وحدة إدارية");
`clinic` is mapped to administrative (medical buildings are licensed as administrative in most compounds; commercial
is the alternative); `i_villa` keeps the floor field although it is a villa type.

Floor values: basement, ground, mezzanine, 1 to 40. Size m² is the plot area for land.

#### 2.2.4 Finishing (proposal; hidden for land)

| Code | AR | EN |
|---|---|---|
| `core_shell` | على الطوب الأحمر | Core & shell |
| `semi_finished` | نصف تشطيب | Semi-finished |
| `fully_finished` | تشطيب كامل | Fully finished |
| `finished_kitchen_acs` | تشطيب كامل بالمطبخ والتكييفات | Fully finished with kitchen and ACs |
| `furnished` | مفروش | Furnished |

Façade orientation (optional): any of north, east, south, west (one to four sides), shown combined with the north
arrow: شمالي (بحري) / North, شرقي / East, جنوبي (قبلي) / South, غربي / West; e.g. "شمالي شرقي / North-east".

#### 2.2.5 Features (proposal; multi-select, shown only where they apply)

| Group | Code | AR | EN | Applies to |
|---|---|---|---|---|
| Unit | `private_garden` | حديقة خاصة | Private garden | residential |
| Unit | `roof` | روف | Roof | residential |
| Unit | `private_pool` | حمام سباحة خاص | Private pool | residential |
| Unit | `balcony` | بلكونة / تراس | Balcony / terrace | residential, administrative |
| Unit | `nanny_room` | غرفة مربية | Nanny's room | residential |
| Unit | `laundry_room` | غرفة غسيل | Laundry room | residential |
| Unit | `storage_room` | غرفة تخزين | Storage room | all but land |
| Unit | `kitchen_installed` | مطبخ مركّب | Kitchen installed | residential |
| Unit | `acs_installed` | تكييفات مركّبة | ACs installed | all but land |
| Unit | `central_ac` | تكييف مركزي | Central A/C | all but land |
| Unit | `smart_home` | نظام منزل ذكي | Smart home system | residential |
| Unit | `natural_gas` | غاز طبيعي | Natural gas | residential |
| Unit | `corner_unit` | وحدة ناصية | Corner unit | all |
| Unit | `private_garage` | جراج خاص | Private garage | residential |
| Unit | `parking_spot` | مكان ركن مخصص | Dedicated parking spot | all but land |
| Building | `elevator` | أسانسير | Elevator | all but land |
| Building | `backup_generator` | مولد كهرباء احتياطي | Backup generator | all but land |
| Building | `main_road_frontage` | واجهة على طريق رئيسي | Main road frontage | commercial, administrative, land |
| Compound | `security_24h` | أمن وحراسة 24 ساعة | 24-hour security | all |
| Compound | `cctv` | كاميرات مراقبة | CCTV | all |
| Compound | `shared_pool` | حمام سباحة مشترك | Shared pool | residential |
| Compound | `clubhouse` | كلوب هاوس | Clubhouse | residential |
| Compound | `gym` | جيم | Gym | residential, administrative |
| Compound | `kids_area` | منطقة ألعاب أطفال | Kids' play area | residential |
| Compound | `green_areas` | مساحات خضراء ولاندسكيب | Green areas and landscaping | all |
| Compound | `commercial_area` | منطقة تجارية داخل المشروع | Commercial area inside the project | all |
| Compound | `mosque` | مسجد | Mosque | all |
| Compound | `beach_access` | شاطئ | Beach access | residential |
| Compound | `near_services` | قريب من الخدمات | Near services | all |
| Compound | `utilities_delivered` | مرافق موصّلة | Utilities connected | land |
| View | `landscape_view` | إطلالة على اللاندسكيب | Landscape view | all but land |
| View | `pool_view` | إطلالة على حمام السباحة | Pool view | residential |
| View | `lake_view` | إطلالة على بحيرة / لاجون | Lake / lagoon view | residential |
| View | `sea_view` | إطلالة على البحر | Sea view | residential |
| View | `golf_view` | إطلالة على ملعب جولف | Golf view | residential |

Michael's "pool" is split into private and shared. Features live in a table so the admin can add or hide one
(3.7).

#### 2.2.6 After submit (admin)

Review outcome, one of:

- **Request changes:** the seller gets an e-mail with the reason per field or document; the unit returns to the seller
  as "changes requested"; resubmitting notifies the admin.
- **Reject** (reason, e-mailed).
- **Publish:** listed without the badge.
- **Publish as verified:** listed with the "verified" badge.

**Verified** is the team's optional judgement (documents fully consistent, or they inspected the unit). The admin can
add or remove it later; both are logged with a reason. Who verified and when is never shown publicly. The
opportunities page has a "verified only" filter; otherwise unverified units are ranked normally. (This settles the
badge question from the earlier review: no options needed.)

The admin can edit the market price estimate and set the "لقطة" deal tag (English label: **proposal** "Lokta deal");
both are logged.

**Default sort** on opportunities: units whose seller accepts less first (as aqarexit), then newest. The unit page
shows the lowest accepted amount when the seller opted in.

**Unit states:**

```
draft → submitted → changes_requested → submitted → … → published → reserved → sold
                  ↘ rejected            published / changes_requested → withdrawn
                                        reserved → published (deal cancelled)
```

`verified` and the "لقطة" tag are flags on a published unit, not states.

#### 2.2.7 After publishing (seller)

- My account shows each unit's state, its documents' review status and the next step.
- Withdraw at any time (reason optional); the admin is notified. While a deal is open on the unit, the button sends a
  withdrawal request that the admin handles (**proposal**, 2.2.9 point 5).
- E-mails to the seller: received · changes requested · rejected · published · verified · a deal started on your unit
  · deal completed · withdrawn. To the admin team: new unit submitted · resubmitted after changes · documents
  re-uploaded · seller withdrew a unit.

**Editing a published unit (proposal):**

| Tier | Fields | What happens |
|---|---|---|
| Free | description, photos (add, remove, reorder, cover), floor plan, features, façade | live at once, logged; changed photos and descriptions go to an admin "check after edit" list, and the admin can revert |
| Review | every money field (paid to date, next due date, years left, installment amount and frequency, annual extra, overdue, maintenance, market estimate, accepts less and the lowest amount), size, garden, roof, floor, bedrooms, bathrooms, finishing, delivery status and year | saved as a pending change; the admin approves or rejects it; the unit stays live with the old values meanwhile (option A below) |
| Locked | developer, compound, area, main type and sub-type, contract number, unit number, contract year, total contract price | the seller cannot change them; the admin can (logged), or the seller withdraws and submits again |

New documents (for example new receipts) can be uploaded at any time; they are reviewed but change nothing on the
listing.

How "back to review" behaves:

| Option | Cost | Risk |
|---|---|---|
| **A. Unit stays live with the old values until the admin approves the change (recommended)** | one pending-changes table | buyers briefly see older figures (paid to date grows every month, so this happens often) |
| B. Unit is unpublished until the admin approves | none extra | every monthly installment update takes the unit off the market |

#### 2.2.8 Unit code and ID retention (proposal)

**Unit code** `LK-{area code}-{serial}`, e.g. `LK-NC-0124`; area codes live in the areas table. Proposal: one global
serial (4 digits, grows past 9999), assigned at first submit; the e-mails before publishing use the serial as the
reference (`#0124`); the full code is formed at publish, when the area is certain (a seller's "other" area is mapped
by the admin first), and it never changes after that, even if the area is corrected.

**National ID retention:**

| Option | Rule | Cost | Risk |
|---|---|---|---|
| **A. 90 days (recommended)** | ID files deleted 90 days after the unit is sold, withdrawn or rejected; drafts never submitted lose their ID files after 180 days without activity; the record stays (type, sha256, reviewed by and when, deleted at); both periods are settings | a daily scheduled job | none known; 90 days covers a late dispute about a completed deal |
| B. 30 days | same, shorter | same | a dispute after 30 days has no ID copy |
| C. Kept until the client's lawyer sets a period | none now | ID copies pile up with no end date |

Contract, schedule, receipts and statement are kept until the client sets a period (they back the commission record).
The privacy policy (client text, section 6) must state the rule chosen.

#### 2.2.9 Points to settle in the spec (proposal for each)

1. **Fully paid contracts.** "paid < total" blocks a contract that is fully paid but not yet delivered, while "years
   left: 0 if finished" allows it. Proposal: paid ≤ total; when paid = total, the installment fields are hidden and
   "installments finished" is shown.
2. **Overdue and the next due date.** With overdue installments the next due date can be in the past. Proposal: the
   date must be in the future unless "overdue" is yes.
3. **Delivery payment.** Many Egyptian contracts have a lump sum due at delivery (دفعة الاستلام), which breaks the
   installment × installments-left warning. Proposal: an optional "payment due at delivery" field on step 3, counted
   in the check and shown to buyers.
4. **Duplicate check on step 1.** The message must not reveal which unit matched; checks are rate-limited per user and
   every hit is logged for the admin.
5. **Withdrawing during an open deal** (2.2.7): a request to the admin rather than an immediate withdrawal.
6. **Contact details in the description.** Proposal: phone numbers, e-mails and links are refused in the description
   (owner-only rule, no going around the platform).
7. **Photos of units under construction.** The seller may have none of the unit itself; proposal: allow project
   photos or renders, with a hint.
8. **Exit report before the market research.** The step 6 preview needs calculator settings, which phase 6 researches.
   Proposal: load the settings with clearly marked provisional values in phase 1 and research them before launch.

#### 2.2.10 Not building

Construction progress %, inspection dates, per-unit reviewer names or dates, developer-approval status on the unit
page, a public revision log, architect-annotated plans, inspection report uploads, SMS or WhatsApp.

#### 2.2.11 Against the agreement (note for Michael)

- The agreement's seller line names "رفع العقد وإيصالات السداد" (contract and receipt uploads). Receipts stay
  uploadable, optional by default with a "required" setting, so the line is met.
- Clause 2 lists the site pages and says "nothing else"; a `/buyers` page (Q12) is not on that list, and clause 9
  calls any unlisted page extra work. Michael's call whether to absorb it.
- Nothing in the spec is on clause 9's excluded list (AI assistant, investors page, buyer alerts, units map, logo,
  social designs, ads and tracking, online payment, WhatsApp, SMS).

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
submit. Limit: 5 open requests per buyer (Q15, decided 2026-11-17). Contact details typed into the question are
masked.

Request states: `new → contacted → matched (deal opened) | closed (reason)`.

**E-mails to the buyer:** budget saved (optional) · request received (reference) · a deal was opened with you · deal
stage changed (selected) · deal completed. **To the admin team:** new unit request · new budget registered.

### 2.4 Admin flow

| Area | What the admin does | Result |
|---|---|---|
| Units review | Open a submitted unit, check fields against documents, edit typos (logged), approve each document or ask for a replacement, then one outcome: request changes, reject, publish, publish as verified (2.2.6) | state change + e-mail to seller |
| Verification | Add or remove "verified" at any time, with a reason (2.2.6) | badge on card and page; logged |
| Market price and "لقطة" tag | Edit the seller's market estimate; set or clear the deal tag | logged |
| Pending changes | Approve or reject edits made after publishing (2.2.7) | live values updated |
| Duplicates | See blocked attempts (contract key blocks) and warnings (same contract file sha256); can override with a reason | logged |
| Compounds / developers / areas | Create, edit, merge "other" entries typed by sellers into the managed lists | compound pages fill up |
| Matching | For a unit: buyers whose budget fits (cash ≥ cash required, installment fits, area/type match), ranked; for a buyer: units that fit. Open a deal from a pair | deal created, unit `reserved` when the seller accepts (Q16) |
| Deals | Move a deal through its stages with a date and note; cancel with a reason | history, e-mails |
| Commissions | Each deal carries its commission record; mark invoiced / collected with date and reference | commission report |
| Requests and messages | Unit requests, partnership requests, contact messages: assign, note, close | |
| Content | Reports and indicators; site texts (placeholder list); calculator settings | public pages update |

**Deal stages (Q16, as recommended, decided 2026-11-17):**

```
opened (buyer + unit linked) → buyer confirmed (viewing / call done) → seller accepted
→ documents and developer approval → signing and payment → completed
                                   any stage → cancelled (reason)
```

E-mails go to both parties at: opened, developer approval obtained, completed, cancelled.

**Commission record:** deal, base amount = **total contract price** (Q3, decided 2026-11-17, as aqarexit), rate
(from settings, 1% today, frozen on the deal), amount, VAT if applicable (asked of the client), status
`expected → due (on completion) → invoiced → collected | waived (reason)`, dates, reference, notes. Who pays the
developer's transfer fee is asked of the client too.

**Roles (Q5, decided 2026-11-17):** `admin` (everything, settings, users) and `reviewer` (units, documents, requests,
deals; no settings, no commissions). Every write is in the activity log. The dashboard is in Arabic and English.

---

## 3. Data model (Q6: option A in 3.1, 3.2 and 3.3; in 3.4 A blocks and B warns; decided 2026-11-17)

Per the handover, listings and lead forms are designed with Michael. The options are kept for the record.

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

Decided 2026-11-17: A blocks, B (file sha256) only warns the admin.

### 3.5 Money and enums

- Money as integer EGP (no piastres) in `BIGINT UNSIGNED`; percentages as basis points (`100` = 1%).
- Every choice (type, finishing, frequency, state) stored as a **code**, translated per language: avoids aqarexit's
  Arabic values on the English site.
- Monthly-equivalent installment computed in one place (for matching, sorting, compare).

### 3.6 Tables for the rest of the platform (option A everywhere)

| Table | Key columns |
|---|---|
| `users` | name, email, password, phone, locale, email_verified_at |
| `admin_users`, `admin_roles` | separate from public users (handover 2.3: roles in their own table) |
| `developers` | name_ar, name_en, slug, published |
| `areas` | parent_id, name_ar, name_en, slug |
| `compounds` | developer_id, area_id, name_ar, name_en, slug, intro_ar, intro_en, published |
| `units`, `unit_photos`, `unit_documents`, and the other seller-flow tables | superseded by the seller spec: see 3.7 |
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
| `activity_log` | who, what, before/after |
| `mail_outbox` | handover 2.5: row first, then send; retried by the scheduler |

### 3.7 Migrations for the seller flow (proposal)

In run order. Nothing is deployed yet, so the skeleton's own `users` migration is edited rather than altered.
Money is `BIGINT UNSIGNED` whole EGP; every choice is a short code string; private columns never appear in a
public API resource (one whitelist test).

| # | Migration | Columns and indexes |
|---|---|---|
| 1 | `users` (edit skeleton) | + `phone` (string 20), `locale` (char 2, default `ar`) |
| 2 | `admin_users`, `admin_roles` | name, email unique, password, role (`admin` / `reviewer`), locale, is_active, last_login_at |
| 3 | `settings` | key unique, value JSON, type, group, updated_by → admin_users. Seller-flow keys: `receipts_required`, `installment_check_tolerance_pct`, `id_retention_days`, `draft_id_retention_days`, `description_min`, `description_max`, `photo_max_count`, calculator keys (4.2) |
| 4 | `areas` | parent_id (null = governorate), code (string 4, unique, nullable on governorates), name_ar, name_en, slug unique, sort, is_active |
| 5 | `developers` | name_ar, name_en, slug unique, is_active |
| 6 | `compounds` | developer_id, area_id, name_ar, name_en, slug unique, is_active; index (developer_id) |
| 7 | `features` | code unique, group, name_ar, name_en, applies_to (JSON list of main types), sort, is_active (seeded from 2.2.5) |
| 8 | `units` | **owner and state:** seller_id → users, state (8 codes, 2.2.6), step_reached, serial unique nullable (at first submit), code unique nullable (at publish), slug unique nullable · **step 1:** developer_id / developer_other, compound_id / compound_other, area_id / area_other, main_type, sub_type, contract_number, unit_number (both private), contract_year · **duplicate keys:** contract_key, unit_key (normalised, indexed) and `active_contract_key`, `active_unit_key` (unique, filled only while submitted, changes_requested, published or reserved; MariaDB has no partial indexes, and NULLs do not collide) · **step 2:** size_m2, garden_m2, roof_m2 (decimal 8,2), floor (small int; basement −1, ground 0, mezzanine as a code), bedrooms, bathrooms, finishing, delivery_status, delivery_year, facade (4-bit set), description · **step 3:** total_price, paid_amount, remaining_amount (stored generated column total − paid, for filters and sort), market_price_estimate, installment_amount, installment_frequency, next_due_date, years_left, annual_extra_amount (null = no), accepts_less, lowest_accepted_amount, has_overdue, overdue_count, overdue_amount, maintenance_due, delivery_payment (if 2.2.9 point 3 is accepted) · **flags:** verified_at, verified_by, deal_tag (bool) · **dates:** submitted_at, published_at, withdrawn_at, withdraw_reason, rejected_at · indexes for the default sort (accepts_less, published_at) and the listing filters |
| 9 | `unit_feature` | unit_id, feature_id; primary key on both |
| 10 | `unit_photos` | unit_id, kind (`photo` / `floor_plan`), path base, sort, width, height, bytes |
| 11 | `unit_documents` | unit_id, type (`contract`, `schedule`, `national_id_front`, `national_id_back`, `receipt`, `statement`), path (private disk), original_name, mime, bytes, sha256 (indexed, for the 3.4 B warning), status (`uploaded` / `approved` / `needs_replacement`), review_note, reviewed_by, reviewed_at, purge_after, purged_at |
| 12 | `unit_declarations` | unit_id, user_id, kind (`no_over`, `owner_not_broker`, `data_matches`, `terms_privacy`), text_version, accepted_at, ip |
| 13 | `unit_reviews` | unit_id, admin_id, outcome (`changes_requested`, `rejected`, `published`, `published_verified`), reason, notes per field or document (JSON), created_at |
| 14 | `unit_pending_changes` | unit_id, changes (JSON: field → old, new), state (`pending` / `approved` / `rejected`), submitted_at, reviewed_by, reviewed_at, note (2.2.7) |
| 15 | `duplicate_attempts` | user_id, unit_id (the attempt), matched_unit_id, key_kind (`contract` / `unit` / `file_sha256`), stage (`step1` / `submit` / `upload`), blocked (bool), created_at |
| 16 | `exit_reports` | unit_id, kind (`declared` / `verified`), locale, inputs JSON, settings snapshot JSON, results JSON, pdf_path, generated_at |
| 17 | `activity_log` | actor_type, actor_id, subject_type, subject_id, action, before JSON, after JSON, reason, created_at (verified on and off, market price edits, deal tag, admin edits) |
| 18 | `mail_outbox` | to, locale, template, payload JSON, state, attempts, last_error, sent_at (handover 2.5) |

Deals, commissions, budgets, favorites and requests come in later migrations (phases 3 and 7). The skeleton's
`cache`, `jobs` and `sessions` tables stay as they are.

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
  refund_pv_min = present value of refund_min paid evenly over refund_years at S.discount_rate   # shown only when S.show_present_value (default OFF, decided 2026-11-17)
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
7. A discount rate for the present-value line (for example the Central Bank of Egypt's rate); the line is a
   setting, OFF by default (decided 2026-11-17).
8. VAT treatment of a brokerage commission (14%?), confirmed by the client (Eng. Enas).

Method: public sources only (developer contract terms published online, law firm and press articles, court
summaries), at least two independent sources per value, each recorded with URL and date; values marked "market
average" on the page. The client reviews the numbers before launch.

### 4.4 Exit report PDF content (proposal)

Unit summary · the seller's figures (marked "as declared" until verified) · cancellation scenario (range, refund
period, present value) · transfer scenario · difference · assumptions with their source and date · disclaimer (client
text) · reference number and date. Arabic and English versions; fonts embedded with a Unicode map so the text is
selectable (aqarexit's PDFs are not).

PDF engine (**Q17**): **mPDF (decided 2026-11-17)**: pure PHP, shapes Arabic and RTL, runs on shared hosting. Dompdf:
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
5. Calculator: settings per developer, late fees and transfer fee shown; present value of the refund as a setting,
   OFF by default (decided 2026-11-17).
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
7. Unit slug `/{compound}-{type}-{bedrooms}-bedrooms-{code}` with the unit code from 2.2.8 (`lk-nc-0124`); old slugs
   redirect 301 when data changes.

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
| 1 | Site name: لقطة / Lokta (decided 2026-11-17, Q2) | ✓ | ✓ | done |
| 2 | Home: hero, value proposition, seller and buyer sections, steps, CTAs | ✓ | ✓ | P → C |
| 3 | About page | ✓ | ✓ | C |
| 4 | How it works: seller journey, buyer journey, fees | ✓ | ✓ | P → C |
| 5 | FAQ questions and answers | ✓ | ✓ | P → C |
| 6 | Privacy policy | ✓ | ✓ | C (legal; Eng. Enas) |
| 7 | Terms and conditions | ✓ | ✓ | C (legal) |
| 8 | Seller notices and declarations: owner-only notice, no-over modal, owner-not-broker, data matches documents (2.2.1, 2.2.2) | ✓ | ✓ | P → C (legal) |
| 8a | Privacy policy paragraph on national ID copies and their retention (2.2.8) | ✓ | ✓ | C (legal) |
| 8b | Seller e-mails (8, 2.2.7) and the "48 hours" promise | ✓ | ✓ | P → C |
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

**Phase 0: Setup (done)**
- [x] Repository, `.gitignore`, `.gitattributes`, `.editorconfig`, README
- [x] Core: Laravel 13, MariaDB scratch + test databases, Inertia 3 + React 19 + TS + Tailwind 4 dashboard shell
- [x] Tooling: Pint, PHPStan level 10, Pest 4 (guarded TestCase), ESLint, `tsc`, RTL check script
- [x] Web: Next 16 + next-intl, Arabic at root and English under `/en`, `lang`/`dir`, canonical + hreflang helper
- [x] Initial commit by Michael (e843f41, 2026-11-17 15:42 Cairo)

**Phase 1: Foundations**
- [x] Decisions from section 8 recorded (2026-11-17)
- [ ] Proposals in 2.2 (sub-types, features, finishing, editing after publish, ID retention, unit code, 2.2.9)
      reviewed by Michael
- [ ] Migrations for the agreed model (3.7 first); seeders for areas with codes, features, sample developers and
      compounds (scratch only)
- [ ] Admin auth, roles, activity log, mail outbox
- [ ] `site_texts` with placeholder flag; `settings` with typed values, calculator values marked provisional
      (2.2.9 point 8)
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
- [ ] Unit status in "My account"; seller e-mails; editing after publish and withdrawal
- [ ] ID retention job (2.2.8)

**Phase 5: Listings**
- [ ] Opportunities page: filters, sort, pagination, server-rendered cards
- [ ] Unit page: specs, money block, verified badge, request form
- [ ] Compound pages and index; developers page with partnership form; contact form

**Phase 6: Tools**
- [ ] Calculator (both tabs) on settings and developer policies
- [ ] Market values researched, sourced and loaded as settings
- [ ] Reports and indicators page + dashboard editor

**Phase 7: Dashboard operations**
- [ ] Units review and document review; outcomes: request changes, reject, publish, publish as verified
- [ ] Verified on and off, market price edits, "لقطة" tag, pending changes after publish (all logged)
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

## 8. Questions and decisions

Michael's review of 2026-11-17. "As recommended" means the recommendation in the earlier draft of this table.

| # | Question | Decision |
|---|---|---|
| Q1 | URL scheme (1.1) | A: Arabic at root, English under `/en`, Latin slugs (decided 2026-11-17) |
| Q2 | Site name | Arabic "لقطة", English "Lokta", domain loktaeg.com (decided 2026-11-17) |
| Q3 | Commission base, VAT, transfer fee | Base: total contract price at 1%, as aqarexit (decided 2026-11-17). VAT and who pays the developer's transfer fee: **asked of the client**, open. What aqarexit shows: section 8.1 |
| Q4 | aqarexit's portal screens | Michael walked the seller form to the review screen (nothing submitted); his findings are in 2.2 (decided 2026-11-17) |
| Q5 | Dashboard language and roles | Arabic and English; roles admin + reviewer (decided 2026-11-17) |
| Q6 | Data model 3.1–3.4 | A in 3.1, 3.2, 3.3; in 3.4 A blocks and B (file sha256) only warns the admin (decided 2026-11-17) |
| Q7 | The 6 seller steps | Michael's spec, 2.2.2 (decided 2026-11-17) |
| Q8 | Exit report versions | At submit from the declared figures, and a "verified" version after review; both kept (decided 2026-11-17) |
| Q9 | Seller documents | Contract, installment schedule and national ID (front and back) required; receipts optional with a "required" setting; developer statement optional (decided 2026-11-17 by the seller spec). What aqarexit asks: section 8.1 |
| Q10 | Publish before or after documents | Documents required before a unit is published (aqarexit publishes first) (decided 2026-11-17). The seller spec makes receipts optional by default: see 2.2.11 |
| Q11 | Mobile at registration | Required, Egyptian format, not verified; all verification and notifications by e-mail only (decided 2026-11-17) |
| Q12 | Public "for buyers" page | Yes, `/buyers`, like aqarexit's (decided 2026-11-17); outside the agreement's page list, see 2.2.11 |
| Q13 | Verified e-mail before submitting | As recommended: drafts allowed, submit needs a verified e-mail (decided 2026-11-17) |
| Q14 | Compound page indexing | As recommended: indexed from 1 published unit, noindex at 0 (decided 2026-11-17) |
| Q15 | Unit requests per buyer | As recommended: 5 open requests (decided 2026-11-17) |
| Q16 | Deal stages, when "reserved" | As recommended: stages in 2.4; reserved when the seller accepts (decided 2026-11-17) |
| Q17 | PDF engine | As recommended: mPDF (decided 2026-11-17) |
| Q18 | Spam protection | Honeypot + rate limits; Cloudflare Turnstile (free) only if spam appears (decided 2026-11-17) |
| Q19 | Document storage | As recommended: private disk outside `public/`, signed admin-only routes (decided 2026-11-17) |
| Q20 | Seller accepts less | Yes, like aqarexit: yes/no + the lowest amount accepted; those units first in the default sort; shown on the unit page (decided 2026-11-17) |
| Q21 | Market price today | Seller's estimate, required; the admin can edit it (logged); shown on cards and unit pages. The "لقطة" deal tag is set by the admin (decided 2026-11-17) |
| Q22 | Hosting and mail | Michael's call; no action now |
| Calc | Calculator | As proposed in 4.2; the present-value line is a setting, OFF by default (decided 2026-11-17) |
| Badge | What "verified" means | The team's optional judgement, set at publish or later, logged, never showing who or when (decided 2026-11-17, 2.2.6) |

Still open: VAT and the transfer-fee payer (client), and the proposals in 2.2 (sub-types, features, finishing,
editing after publish, ID retention, unit code details, 2.2.9).

### 8.1 What aqarexit says on VAT, the transfer fee and seller documents

Source: their message catalogue (1,476 keys, AR + EN, read 2026-11-17) in `research-raw/messages_flat.tsv`, and their
public JavaScript in `research-raw/js/`.

**VAT.** No key in either language mentions VAT, tax or ضريبة. Their fee texts say "1.25% of the contract value …
no hidden charges" (`faq.items.4.a`, `howItWorks.fees.description`, `legal.termsSections.2.p`,
`portalBuyer.detail.feeConsent`), and the compare table's line is "Total you pay today incl. fee"
(`opportunities.compare.rows.totalNow`) with no VAT line. Their code does carry a rate: a market config in five
public chunks (e.g. `_next_static_chunks_0ba8db14v9yk_.js`) holds
`EG: { fees: { buyerRate: .0125, sellerRate: 0, consentVersion: "buyer-1.25-v1-2026-08-02" }, vatRate: .14 }` (also
SA .15, AE .05). No public code reads `vatRate`. So: a 14% rate is configured but neither shown nor added anywhere
public; whether their invoices add it is not visible (the portal is closed to crawlers).

**Developer transfer fee.** No key mentions it or who pays it. The nearest texts: outcomes "depend on your contract,
developer approval, and the market" (`howItWorks.fees.noGuarantee`); "we prepare your complete assignment file …
from the balance statement to the signing appointment" (`faq.items.6.a`); "no hidden charges" (`faq.items.4.a`).
Their calculator leaves it out (`docs/research/aqarexit-03-calculator.md`).

**Seller documents.** Four types (`portal.seller.docs.types.*`): عقد الوحدة / unit contract, إيصالات السداد /
payment receipts, آخر كشف حساب من المطور / latest developer statement, بطاقة الرقم القومي / national ID. No
installment schedule type. Only contract and receipts are ever called required, and only "before completing the
sale, not before listing" (`portal.seller.docsOptional`, `portal.seller.actions.documents`,
`notifications.kind.case.documents_pending.body`). Each file is reviewed: received → reviewed ✓ or needs a clearer
copy (`portal.seller.docs.status.*`, `notifications.kind.doc.needs_replacement.title`). Their public pages say the
opposite: "we never publish a unit before reviewing its contract, payment receipts, balance, and assignment terms"
(`opportunities.emptyBody`, also `opportunities.subtitle`, `faq.items.3.a`).

**Description length.** Not in their public material: the only minimum in the catalogue is 10 characters on the
contact form (`contact.errors.messageShort`). The portal code that validates the description is behind `/app`.
