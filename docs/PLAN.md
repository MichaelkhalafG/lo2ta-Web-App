# Plan: installment contract transfer platform (Egypt)

Status: **reviewed by Michael on 2026-11-17.** Items marked "decided 2026-11-17" or "approved 2026-11-17" are fixed; the
proposals in 2.2 and 3.7 were approved on 2026-11-17 with the notes recorded where they apply. Phase 1 has started.

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
they differ. The items first written as proposals (sub-types, features, finishing, editing after publishing, ID
retention, the unit code details, the points in 2.2.9) were approved 2026-11-17, with Michael's notes recorded in place. Wording in quotes is placeholder text for the client to
confirm (section 6).

#### 2.2.1 Entry

- "Sell your unit" (`/sell`) explains the process, the 0% seller fee and the no-over rule, then asks to log in or
  register. Return-to-action: after login the seller lands on step 1.
- A fixed notice on every step: "We deal with the unit's owner only. Requests from brokers or intermediaries are
  rejected, and any unit found to come from one is removed."
- On opening step 1 of a unit for the first time, a blocking modal: "No over price, ever. If your unit is published
  and you ask for an over price, it is removed." The seller must confirm ("I understand, I list without over") to
  continue. The confirmation is stored with a timestamp and the text version (per unit, approved 2026-11-17).
- Every step autosaves as a draft ("saved ✓"); the seller can leave and resume from My account. Required fields are
  marked *.

#### 2.2.2 The six steps

| Step | Title AR / EN | Fields |
|---|---|---|
| 1 | العقد / Contract | Developer * (managed list, or "other" + text) · Project / compound * (list filtered by developer, or "other" + text) · Area * (searchable two-level list, or "my area is not in the list" + text) · Main type * and sub-type * (2.2.3) · Contract number * (private) · Unit number as in the contract * (private) · Contract year * (dropdown, recent years first). The duplicate check runs at the end of this step and again on submit |
| 2 | الوحدة / Unit | Size m² * · garden m² · roof m² · Floor * · Bedrooms *, bathrooms * (residential only) · Finishing * (2.2.4) · Delivery status * (ready / under construction + expected delivery year) · Façade orientation (optional) · Features (multi, 2.2.5) · Short description * (60 to 1,200 characters, approved 2026-11-17; both are settings). Every field that does not apply to the chosen sub-type is hidden (matrix in 2.2.3) |
| 3 | الفلوس والأقساط / Money and installments | Total contract price * · Total paid to date * · Today's market price for a similar unit (seller's estimate) * · Installment amount *, frequency * (monthly / quarterly / semi-annual / annual) · Next installment due date * (unless installments are finished) · Years of installments left (optional; 0 if finished; shown next to the installment) · Annual extra payment yes/no (+ amount) · Ready to give up part of what I paid to sell faster yes/no (+ the lowest amount accepted) · Overdue installments yes/no (+ count and amount, and how they are settled: `self` = I pay them before the transfer, or `deduct` = deducted from the amount the buyer pays me, which the buyer pays to the developer instead; hint: most developers refuse a transfer before arrears are paid; 2.2.12) · Maintenance deposit still due (optional) |
| 4 | المستندات / Documents | Contract copy * (PDF or images, several files) · Installment schedule * · National ID copy * (front and back) · Payment receipts (optional by default; "required" is a setting) · Latest developer statement (optional) |
| 5 | الصور / Photos | 1 to 10 photos *, drag to reorder, the first is the cover · Floor plan image (optional, shown as a plain image) · Warning: no photos showing personal data, the unit number or documents |
| 6 | المراجعة والإرسال / Review and submit | Read-only summary of steps 1 to 5, each with an "edit" link · Exit report preview (declared figures, current calculator settings) · Declarations, each required: I am the owner, not a broker or intermediary; the data matches my contract and documents; I accept the terms and privacy policy · Submit |

Shown on step 3: "you recover what you paid, with no over"; "the buyer continues the remaining installments as they
are"; if the seller accepts less, the amount they will receive instead.

**Estimated net amount** (step 3 and the review step; decided 2026-11-17 after the client's answer that the seller
pays the developer's transfer fee): net = paid − the developer's transfer fee − any amount given up (paid − lowest
accepted). The fee line reads "the developer's transfer fee (paid by you to the developer, not to Lokta)"; the "0%
Lokta commission" wording stays, because the fee belongs to the developer.

**Computed and checked on step 3** (limits per field as in 2: money > 0 and ≤ 1,000,000,000; installment ≤
100,000,000):

- remaining = total − paid.
- paid < total (see 2.2.9 point 1 for fully paid contracts).
- installment ≤ remaining.
- installment × installments left (years left × payments per year) roughly matches remaining: a **warning**, not a
  block, with a tolerance setting (10%, approved 2026-11-17; annual extra payments and the delivery payment counted in).
- next due date in the future (see 2.2.9 point 2 for overdue units).
- delivery year ≥ contract year.
- lowest accepted amount < paid (and > 0).

**Documents** are stored on a private disk, served only to the unit's seller and to staff (2.2.15, which replaces
"admin-only signed routes"), and never appear in any public resource. **Photos** are resized to fixed widths, converted to WebP, and stripped of all EXIF data (location
included).

**After submit:** a confirmation screen and e-mail: received, reference number, the exit report PDF, and "we review
within 48 hours and reply by e-mail". The exit report is generated at submit from the declared figures and again as
a "verified" version after review; both are kept (Q8, decided 2026-11-17).

#### 2.2.3 Sub-types and which fields apply (approved 2026-11-17)

Garden and roof are optional everywhere they appear. Land hides finishing, floor, bedrooms, bathrooms, garden and
roof; its delivery status reads "utilities delivered / under development".

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
| `office` | مكتب / وحدة إدارية | Office / administrative unit | administrative | ✓ | – | – | – |
| `clinic` | عيادة | Clinic | administrative | ✓ | – | – | – |
| `residential_land` | أرض سكنية | Residential land plot | land | – | – | – | – |
| `commercial_land` | أرض تجارية | Commercial land plot | land | – | – | – | – |
| `administrative_land` | أرض إدارية | Administrative land plot | land | – | – | – | – |
| `industrial_land` | أرض صناعية | Industrial land plot | land | – | – | – | – |

Settled with the approval: office and administrative unit are one sub-type (`office`, "مكتب / وحدة إدارية");
`clinic` is administrative; `i_villa` keeps the floor field although it is a villa type. 25 sub-types in all.

Floor values: basement, ground, mezzanine, 1 to 40. Size m² is the plot area for land.

#### 2.2.4 Finishing (approved 2026-11-17; hidden for land)

| Code | AR | EN |
|---|---|---|
| `core_shell` | على الطوب الأحمر | Core & shell |
| `semi_finished` | نصف تشطيب | Semi-finished |
| `fully_finished` | تشطيب كامل | Fully finished |
| `finished_kitchen_acs` | تشطيب كامل بالمطبخ والتكييفات | Fully finished with kitchen and ACs |
| `furnished` | مفروش | Furnished |

Façade orientation (optional): any of north, east, south, west (one to four sides), shown combined with the north
arrow: شمالي (بحري) / North, شرقي / East, جنوبي (قبلي) / South, غربي / West; e.g. "شمالي شرقي / North-east".

#### 2.2.5 Features (approved 2026-11-17; multi-select, shown only where they apply)

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

The admin can edit the market price estimate and set the "لقطة" deal tag (English label "Lokta deal", approved 2026-11-17);
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
  withdrawal request that the admin handles (approved 2026-11-17, 2.2.9 point 5).
- E-mails to the seller: received · changes requested · rejected · published · verified · a deal started on your unit
  · deal completed · withdrawn. To the admin team: new unit submitted · resubmitted after changes · documents
  re-uploaded · seller withdrew a unit.

**Editing a published unit (approved 2026-11-17: tiers as below, option A):**

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
| **A. Unit stays live with the old values until the admin approves the change (approved 2026-11-17)** | one pending-changes table | buyers briefly see older figures (paid to date grows every month, so this happens often) |
| B. Unit is unpublished until the admin approves | none extra | every monthly installment update takes the unit off the market |

#### 2.2.8 Unit code and ID retention (approved 2026-11-17)

**Unit code** `LK-{area code}-{serial}`, e.g. `LK-NC-0124`; area codes live in the areas table. One global
serial (4 digits, grows past 9999), assigned at first submit; the e-mails before publishing use the serial as the
reference (`#0124`); the full code is formed at publish, when the area is certain (a seller's "other" area is mapped
by the admin first), and it never changes after that, even if the area is corrected.

**National ID retention:**

| Option | Rule | Cost | Risk |
|---|---|---|---|
| **A. 90 days (approved 2026-11-17)** | ID files deleted 90 days after the unit is sold, withdrawn or rejected; drafts never submitted lose their ID files after 180 days without activity; the record stays (type, sha256, reviewed by and when, deleted at); both periods are settings | a daily scheduled job | none known; 90 days covers a late dispute about a completed deal |
| B. 30 days | same, shorter | same | a dispute after 30 days has no ID copy |
| C. Kept until the client's lawyer sets a period | none now | ID copies pile up with no end date |

Contract, schedule, receipts and statement are kept until the client sets a period (they back the commission record).
The privacy policy (client text, section 6, row 8a) must state this rule.

#### 2.2.9 Points settled in the spec (each approved 2026-11-17)

1. **Fully paid contracts.** "paid < total" blocks a contract that is fully paid but not yet delivered, while "years
   left: 0 if finished" allows it. Rule: paid ≤ total; when paid = total, the installment fields are hidden and
   "installments finished" is shown.
2. **Overdue and the next due date.** With overdue installments the next due date can be in the past. Rule: the
   date must be in the future unless "overdue" is yes.
3. **Delivery payment.** Many Egyptian contracts have a lump sum due at delivery (دفعة الاستلام), which breaks the
   installment × installments-left warning. Rule: an optional "payment due at delivery" field on step 3, counted
   in the check and shown to buyers.
4. **Duplicate check on step 1.** The message must not reveal which unit matched; checks are rate-limited per user and
   every hit is logged for the admin.
5. **Withdrawing during an open deal** (2.2.7): a request to the admin rather than an immediate withdrawal.
6. **Contact details in the description.** Rule: phone numbers, e-mails and links are refused in the description
   (owner-only rule, no going around the platform).
7. **Photos of units under construction.** The seller may have none of the unit itself: project photos or
   renders are allowed, and each such photo carries a visible label "صورة من المشروع / Project image", set by the
   seller at upload and editable by the admin (Michael's note on the approval).
8. **Exit report before the market research.** The step 6 preview needs calculator settings, which phase 6 researches.
   Rule: the settings are loaded with clearly marked provisional values in phase 1 and researched before launch.

#### 2.2.14 Uploads, declarations and the exit report wording (decided by Michael 2026-11-18)

- **Photos (step 5):** JPG, PNG or WebP, up to 10 MB each (`upload_max_mb`), at least 800 px on the long side
  (`photo_min_px`). The server stores a WebP of at most 2000 px and strips all EXIF. HEIC photos (the iPhone default)
  are converted to JPG in the seller's browser before upload; the library loads on step 5 only.
- **Documents (step 4):** PDF, JPG or PNG up to 10 MB per file (2.2.13 point 8). Files per type per unit, as settings:
  contract 15 (`doc_max_contract`), schedule 5 (`doc_max_schedule`), receipts 30 (`doc_max_receipt`), developer
  statement 3 (`doc_max_statement`); national ID front 1 and back 1, fixed.
- **Declarations:** owner not broker, data matches the documents, terms and privacy; accepted on the first submit and
  again on every resubmit (after "changes requested", or after editing a published unit's figures). Each acceptance is
  stored with the text version, time and IP; a wording change applies to new submits only. The no-over confirmation
  stays per unit on step 1 (2.2.1).
- **Exit report wording:** every heading and explanation is a site text flagged placeholder, holding a neutral label
  and no legal or advisory sentence. While any placeholder text or provisional calculator value is used, each page
  carries a visible band "Draft: texts and rates not final". The client's texts replace them in the dashboard (PLAN 6
  rows 10 and 11).

#### 2.2.15 Upload and wizard security (Michael's rules and answers, 2026-11-18)

Michael's rules: type from the content only; photos re-encoded and the original never kept; documents outside the web
root, served only to the owner or staff, random names; nothing executable, blocked at server level too; size, count
and pixel limits before processing; PDFs checked; rate limits per user and per IP; ownership on every endpoint; tests
for each. This replaces "served only through admin-only signed routes" in 2.2.2: the owner may download their own
documents. As built (core, `app/Support/Uploads`, `app/Support/Seller`):

- **Type:** magic bytes and libmagic must agree (JPEG, PNG, WebP for photos; PDF, JPEG, PNG for documents); the name,
  extension and client Content-Type are never read. A PDF must start with `%PDF-` at byte 0.
- **Images (photos and image documents, Michael's answer):** the header is read first (no decode); over 8000 px a side
  or 25 MP (`image_max_side_px`, `image_max_mp`) or over the memory left is refused; then decoded and written as a new
  file: photos as WebP of at most 2000 px, image documents in their own format at full size. EXIF and any trailing
  bytes do not survive. The browser shrinks photos to 2560 px and scans to 4000 px before upload (web side).
- **PDFs (Michael's answers):** refused if they hold JavaScript, embedded files, file attachments, launch actions, XFA,
  rich media or embedded go-to actions, anywhere: the plain file, compressed object streams, encrypted object streams
  (decrypted in memory with the empty password: RC4-40/128, AES-128, AES-256 R5 and R6), or objects hidden in another
  stream's bytes. Refused too: a password needed to open, anything not readable with certainty, decompression over
  16 MB per object stream or 32 MB per file. Accepted with the reviewer flag `encrypted`: files that open without a
  password. The flag is never shown to the seller.
- **Storage:** everything on the private disk under 40 random hex characters; the uploaded name is kept for display
  only. A photo gets a public copy (new random name) only when its unit is published and it is not hidden; hiding
  deletes the copy. Laravel's own private-file serving is switched off.
- **Serving:** owner routes and staff routes (`view-documents`: both staff roles, every document opening logged) stream
  with `nosniff`, a sandbox CSP, `no-store` and a generated file name; PDFs always as downloads.
- **Server level:** `php artisan uploads:harden` writes the `.htaccess` files (docs/DEPLOY_NOTES.md section 1).
- **Limits (Michael's answer, settings):** uploads 100 per hour per user and 200 per IP; submit 5 per user and 10 per
  IP; draft saves 120 per user; duplicate checks 20 per user. File 10 MB; counts as 2.2.14.
- **Ownership:** every seller route looks the row up by its public ID (ULID) and the owner in one query; anything else
  answers 404. Tested for each route with another seller's token, a guessed ID and a sequential number.
- **Account limits (Michael 2026-11-18):** stored uploads at most 300 MB per account, all units together
  (`upload_max_account_mb`); at most 10 open drafts per account (approved).
- **HEIC in the browser (Michael 2026-11-18):** heic-to 1.6.6 (LGPL-3.0), a separate chunk loaded only when a HEIC
  photo is picked; its licence notice and source link go in the site credits (web/THIRD_PARTY_NOTICES.md until the
  credits page exists).
- **Staff rule (Michael 2026-11-18):** documents are opened only in an up-to-date viewer; there is no antivirus on
  shared hosting (docs/ADMIN_GUIDE.md).
- **Smaller choices made while building (reversible, each a line or two):** submit also requires the no-over confirmation on record for the unit; documents can be
  uploaded while a unit is submitted, published or reserved (new receipts, 2.2.7) and then notify the team, but are
  deleted only while the steps are editable.

#### 2.2.12 Overdue installments: settlement (decided 2026-11-17)

When overdue = yes, the seller chooses `overdue_settlement`:

- `self`: the seller pays the arrears before the transfer. The amount they receive is unchanged; the review step
  and exit report say "the transfer happens after you settle the arrears (X EGP)".
- `deduct`: the arrears are deducted from the amount the buyer pays the seller; the buyer pays that part to the
  developer. **The buyer's total does not change.**

Where it shows:

- Calculator and exit report: with `deduct`, the seller's net = received − transfer fee − arrears; with `self`, the
  net is unchanged and the note above is shown.
- Unit page (public): with `deduct`, under the amount for the owner: "of which X goes to the developer to settle
  arrears" (as on the board's unit detail).
- Dashboard: shown in unit review and on the deal screens.
- Data: `units.overdue_settlement` (`self` | `deduct`, null when there are no arrears), added by a new migration in
  phase 4; the public unit resource gains it (and the whitelist test with it).

#### 2.2.13 Final board (docs/design) against this section (decided 2026-11-17)

The board in `docs/design/Lokta Blueprint Identity.dc.html` (section 07) is final. Michael's answers on where it
differed from 2.2:

1. **Description moves to step 5**, "الصور والوصف / Photos & description", as on the board.
2. **Transfer fee typed by the seller:** an optional % on step 3. If entered it applies to that unit; if not, the
   developer policy; then the global setting. The admin sees it in review and can edit it (logged). New unit field
   `transfer_fee_bp` (nullable); not public (it is the seller's cost, never in the buyer's figures).
3. **Lowest accepted amount < paid** (strictly less), as in 2.2.2.
4. **Contract date and delivery date as month + year:** new unit fields `contract_month` and `delivery_month` (1–12)
   beside the years.
5. **No separate request reference:** the confirmation and every e-mail use our unit code: `#0124` before publishing,
   `LK-xx-0124` after. Our serial has no link to the real unit number.
6. **Default sort** as in 2.2.6: sellers who accept less first, then newest.
7. **Card labels at 11px minimum** (the board's 10px labels go up to 11px).
8. **Documents:** PDF, JPG, PNG up to 10 MB per file; "Next" on step 4 stays blocked until every required file has
   finished uploading.

The home copy "we show the unit to serious investors" stays (copy only, not an investors section).

Phase 4 migration (with 2.2.12): `transfer_fee_bp`, `contract_month`, `delivery_month`, `overdue_settlement` on
`units`; `is_hidden` + `hidden_reason` on `unit_photos` (2.5).

#### 2.2.10 Not building

Construction progress %, inspection dates, per-unit reviewer names or dates, developer-approval status on the unit
page, a public revision log, architect-annotated plans, inspection report uploads, SMS or WhatsApp.

#### 2.2.11 Against the agreement (note for Michael)

- The agreement's seller line names "رفع العقد وإيصالات السداد" (contract and receipt uploads). Receipts stay
  uploadable, optional by default with a "required" setting, so the line is met.
- Clause 2 lists the site pages and says "nothing else"; a `/buyers` page (Q12) is not on that list, and clause 9
  calls any unlisted page extra work. **Michael absorbs it at no extra charge (decided 2026-11-17).**
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
(from settings, 1% today, frozen on the deal), amount, VAT (setting `vat_bp` = 0: the client confirmed no VAT on the
1%, decided 2026-11-17), status `expected → due (on completion) → invoiced → collected | waived (reason)`, dates,
reference, notes. The developer's transfer fee is paid by the seller to the developer and is not part of the
commission record.

**Roles (Q5, decided 2026-11-17):** `admin` (everything, settings, users) and `reviewer` (units, documents, requests,
deals; no settings, no commissions). Every write is in the activity log. The dashboard is in Arabic and English.


### 2.5 Privacy of the unit's identity, and the public fields per page (decided 2026-11-17; table approved 2026-11-18)

A buyer must never be able to find the unit or its owner and go around Lokta.

**Never public**, on any page, card, API answer, sitemap, structured data, image or e-mail to a buyer:

- the real unit number, contract number, building or block number, plot number;
- the seller's name, phone, e-mail, or any document (or its file name or path);
- an exact address or a map location;
- photo location data: EXIF is stripped on upload; in review the admin checks photos for visible building numbers,
  signs or documents and can hide a photo (logged, with a reason);
- contact details in the description: refused on the seller form, and the same check runs on the admin's edits;
- the seller's free text for "other" developer, compound or area (the admin maps it to the managed list before
  publishing);
- the seller's own transfer fee (`transfer_fee_bp`), review notes, who verified and when.

**Public:** governorate, area, district, compound, developer, type, size, floor, finishing, delivery, orientation,
features, the money figures, and our unit code.

**Enforcement:** `Unit::PRIVATE` lists the private columns; the API answers only through `UnitResource` (hand-listed
fields); `tests/Feature/Api/PrivateFieldsTest.php` fills every private column, the seller's name, phone and e-mail and
a document path with sentinel values and fails if any appears in any keyed API answer. Phase 5 adds the same scan
over the built public pages, the sitemaps and the structured data (crawler check), and phase 7 over buyer e-mails.

**Public fields, page by page** (approved by Michael 2026-11-18, with the exact floor shown on the unit page; the admin
checks photos in review for building numbers, signs or documents and can hide a photo, logged):

| Where | Fields shown |
|---|---|
| Unit card (opportunities, compound page, home "لقطات الأسبوع") | cover photo (not hidden; "Project image" label if set); unit code; main type and sub-type; size m²; orientation; title built from sub-type, bedrooms and district (e.g. "شقة 3 غرف في التجمع الخامس"); district, area; delivery month.year; contract price (gold); similar unit today (market estimate); paid to owner and remaining (dimension line, %); installment count × amount and frequency; verified badge; "لقطة" tag; "owner accepts from X" when the seller accepts less |
| Unit page | everything on the card, plus: governorate; compound and developer (linked); garden m², roof m², floor, bedrooms, bathrooms, finishing, delivery status; features; description; all photos (not hidden) and the floor plan; contract date month.year; money: contract price, paid, remaining, market estimate, installment amount, frequency, next due date, years left, the computed schedule, annual extra payment, delivery payment, maintenance due, overdue count and amount with "of which X goes to the developer to settle arrears" when the settlement is `deduct`; lowest accepted amount when opted in; the 1% fee as an amount; published date. No map: the location block names governorate, area, district, compound only |
| Opportunities filters | main type; area and district; "you pay the owner up to"; installment up to; verified only; sort |
| Compound page | compound name, developer, governorate / area / district, intro text, its unit cards |
| Developer page (partnership) | page text and the partnership form only (no unit data) |
| Sitemaps | URLs of static pages, published units (slug ends with the unit code), compounds; `lastmod` |
| Structured data | `RealEstateListing` with name (the card title), URL, images (not hidden), offer (amount to the owner, EGP), `address` with `addressRegion` (governorate) and `addressLocality` (area, district) only; never a street, building or coordinates |
| Social preview (Open Graph) | card title, unit code, cover photo |
| E-mails to a buyer | unit code, card title, compound, district / area, money figures; never seller details |
| Public API (`UnitResource`) | the unit-page fields above, as codes plus labels; phase 4 adds district, `contract_month`, `delivery_month`, `overdue_settlement` and the whitelist test with them |

### 2.6 Listings, requests and public forms (phase 5, 2026-11-22)

**Michael's answers (2026-11-22):**
- "You pay the owner up to X" compares X with the lowest amount the owner accepts: what he paid, or his lowest
  accepted amount when he opted in.
- Form limits, as settings: contact 5 per hour per IP, partnership 3 per IP, unit requests 10 per user and 20 per IP;
  messages at most 2,000 characters (`form_message_max`); the request question stays at 500. Honeypot on every form.
- Contact form: name, e-mail, mobile (optional), message. Partnership form: company, contact person, job title
  (optional), e-mail, phone, number of projects (optional), message. No automatic e-mail to the sender; a thank-you
  on screen.
- Listings: numbered pages of 24 with their own URLs, plus "show more" that adds the next page in place.

**Choices made while building (for review; each reversible in a few lines):**

| # | Choice | Why | Reversing |
|---|---|---|---|
| D21 | A unit request needs a confirmed e-mail | RUN D15 notes Q13 covers units and requests; the team answers by e-mail | 1 check |
| D22 | Sorts: suggested (2.2.6: accepts less first, then newest), least to the owner, lowest installment, newest | the board shows a sort menu without its list | 1 list |
| D23 | Listing answers are cached 60 seconds on the web | a published unit shows within a minute; every visitor shares one answer per filter set | 1 constant |
| D24 | One open request per buyer per unit; the 1% amount the buyer saw must equal today's, else the request is refused; the consent text is versioned like the declarations | a consent never attaches to a stale figure | 2 checks |
| D25 | Contact and partnership messages go to the team mailbox in full until the phase 7 inbox exists; a unit request notice carries only the reference, unit code and readiness | the team has no other way to read them yet; buyer details stay out of mail | template |
| D26 | A filled honeypot gets the same "thank you" and nothing is stored or sent | a bot learns nothing | 1 line |
| D27 | Partnership phone: an Egyptian mobile, or a landline or number abroad (7 to 15 digits); contact phone optional (Egyptian mobile) | companies often give a landline | 1 function |
| D28 | Home "لقطات الأسبوع": units with the لقطة tag; when none is tagged, the newest three under the existing "recent" text | the section is never empty while units exist | 1 query |
| D29 | The area filter takes a governorate, an area or a district, each with the levels under it | the three-level list of 3.3 | 1 function |
| D30 | A compound intro is not shown while flagged placeholder; compound pages are noindex at 0 published units (Q14) | no placeholder text in front of visitors on a page meant to rank | 1 condition |
| D31 | The unit page's heart does not look up whether the unit is already saved (saving twice is harmless) | the page stays shared and cached | a session read |
| D32 | The request form shows on published units only; a reserved unit shows "a deal is open" | a reserved unit is in a deal (Q16) | 1 condition |
| D33 | `UnitCode` forms the code and slug (2.2.8, 5.2) for phase 7 to use at publish; SampleSeeder publishes 12 invented units, scratch and test only | the pages can be seen and tested before phase 7 | none needed |
| D34 | Filter amounts are fixed steps (to owner 0.5 to 5 million; installment 25,000 to 250,000) | a select works without JavaScript | 2 lists |
| D35 | The card shows district then area, and the title "type N rooms in place" (2.5) | the board's card | lang lines |
| D36 | The structured data offer is the amount the owner asks (paid), as 2.5 says "amount to the owner" | 2.5 | 1 field |

**Choices made while fixing the audit of 2026-11-22 (Michael's decisions 1 to 8 of 2026-11-23; for review):**

| # | Choice | Why | Reversing |
|---|---|---|---|
| D37 | The visitor's address is the entry the host's proxy appends to X-Forwarded-For (right-most; `TRUSTED_PROXY_HOPS`, default 1); a short or malformed list counts as the Next server's own address | the first entry is whatever the visitor sends (H1); failing to the shared address is stricter, never looser | 1 helper |
| D38 | Sign-in lock (decision 1A): 20 failures within 60 minutes from any address lock the e-mail for 15 minutes, even for the right password; unknown e-mails lock the same way without a mail; a password reset lifts the lock; all three values are settings | the lock must not reveal which accounts exist, and the owner always has a way back in | 3 settings |
| D39 | PDFs (decision 2A): an open action may only show a page (destination, go-to or named page); remote go-to, form submission, data import, media, sound and additional actions are refused anywhere; a link must be http, https or mailto; an open action we cannot follow is refused as unsupported | contracts never need these; "we cannot read it with certainty" was already the rule | 1 list |
| D40 | Typed developer and compound names are matched to the lists after dropping company words (Developments, Group, شركة, للتطوير العقاري ...); two entries with the same name are never guessed between | decision 3A; a wrong match would block an honest seller | 1 word list |
| D41 | A pending change that an edit puts back to the live values is marked `withdrawn` (a fourth state next to pending, approved, rejected) | the team should not review a change that no longer exists; the history stays | 1 state |
| D42 | A next due date that passed after publishing does not block a live edit; a newly entered date must still lie ahead | M6; time passing is not the seller's mistake | 1 parameter |
| D43 | Contact details: text is NFKC-normalised, invisible characters dropped, a letter O inside a digit run read as 0, a mobile without its 0 caught unless written as a grouped amount; social handles caught; numbers spelled in words stay with the review | M7 without refusing prices such as 1,000,000,000 | 1 class |
| D44 | Request references are "R-" plus six random characters of the public id | L3: a running number shows the site's volume | 1 line |
| D45 | Account writes (profile, favorites, budget): 120 per user per hour (`rl_account_write_user`) | L4: far above a person, low for a script | 1 setting |
| D46 | Core answers no cross-origin request at all | L7: only the Next server calls core | config/cors.php |
| D47 | Security headers on both apps: frame protection (X-Frame-Options and CSP frame-ancestors), nosniff, strict-origin-when-cross-origin, a Permissions-Policy turning off camera, microphone, location, payment and USB, COOP same-origin, HSTS one year in production without subdomains | decision 7A; subdomains stay out of HSTS because the host's mail and panel subdomains are not ours | 2 lists |
| D48 | Money is set in Alexandria with tabular figures; IBM Plex Mono stays for codes, dates in tables and dimension-line annotations; the contract price on the unit page is 42 px gold (32 px under 640 px) | tokens: mono for annotations only; decision 5/6 "price typography" | classes |
| D49 | Stamp "LOKTA" text at 11 px (token minimum) instead of the board's 10 px; the menu arrow is a drawn chevron, not a 10 px glyph | decision 5: what reads best at real size | 2 classes |
| D50 | The loading line is only on /opportunities: a loading boundary higher up made unknown pages answer 200 instead of 404 | a real 404 status matters for search engines and for monitoring | 1 file |
| D51 | The phone request bar shows "you pay the owner now", the amount, "accepts from" when set, and opens the request form in place | board 06.4; the amount the buyer acts on is the paid amount, not the contract price | 1 block |

**Choices made while building the three approved layouts (Michael 2026-11-24; for review):**

| # | Choice | Why | Reversing |
|---|---|---|---|
| D52 | The seller form hides the site header and footer with CSS (`body:has([data-wizard])`) instead of a second route layout; the wizard bar carries its own skip link | no page moves between folders; the site chrome still renders for every other page | 1 CSS rule |
| D53 | Filters apply on change on wide screens; on phones the fields behind "تصفية" wait for "اعرض النتائج" (type and sort apply at once); without JavaScript a button submits | a phone picker should not reload the page at each tap | 1 handler |
| D54 | The phone filter panel opens with a CSS-only toggle (a checkbox without a name), so it works without JavaScript and never flashes | no layout jump on load | 1 input |
| D55 | The week's لقطة is the first deal-tagged unit, shown wide on page 1 of the plain list and left out of the grid below it | the same unit twice on one screen reads as a mistake | 1 filter |
| D56 | The filter address carries only what is set (no empty fields, no default sort); fields are re-enabled when the page comes back from the history | clean, shareable addresses | 1 rule |
| D57 | Gallery viewer: a native modal dialog (Escape closes, focus returns to the picture), arrows follow the reading direction, the floor plan last; without JavaScript each picture links to its file | the platform does focus and Escape right; nothing extra to maintain | 1 component |

**Second security pass fixes (Michael 2026-11-24, decision A; for review):**

| # | Choice | Why | Reversing |
|---|---|---|---|
| D58 | Staff sign-in gets the public account-wide lock with the same three settings but its own counters; an active staff member is e-mailed (template admin.login_locked, link to the dashboard sign-in page); unknown and switched-off accounts lock without a mail; the lock ends only when its time runs out (staff have no password reset) | staff can open national ID copies; one e-mail on both sides must not let a public lock block a staff member or the reverse | 1 guard scope |
| D59 | Every call to core builds its address in one helper (`coreUrl`) that turns any language value other than "en" into Arabic | server actions take the language from the browser; nothing can be added to core's query through it | 1 helper |

**Phase 6, tools (plan approved 2026-11-24 with J1-J12; values answered by the client 2026-11-25; for review):**

| # | Choice | Why | Reversing |
|---|---|---|---|
| D60 | The numbers stay in `settings`; `market_values` holds per value the date checked, an internal note and the client's confirmation; `market_value_sources` the sources (internal, J10); `config/market_values.php` groups the settings of each value. Confirming a value clears the provisional flag of its settings; switching it off sets it again | one place for each number (typed, validated, logged); sources never travel with a setting the public API reads | 1 migration |
| D61 | Client 2026-11-25 (relayed by Michael), loaded as confirmed: cancellation 15% of the total price, one value (min = max); refund in one payment (new rule `lump`); transfer fee a fixed EGP 50,000 (`transfer_fee_mode` = fixed); late-payment penalty 1% of each late installment, charged once, line on; maintenance deposit moves to the buyer (a yes/no setting shown as a note). The 5% transfer fee loaded earlier the same day is replaced; the 1-5% and 8-10% research ranges were dropped from settings and stay in the register | the client's answers replace the research starting points | settings |
| D62 | The late-payment penalty applies on both exits (Michael 2026-11-25): it lowers the refund on a cancellation, and on a transfer it is settled with the arrears (deducted from the proceeds, or included in what the seller settles first). 1% of each late installment equals 1% of the overdue amount, so the formula keeps one share of `overdue_amount` | the penalty is owed to the developer either way | 2 lines in ExitCalculator |
| D63 | A seller's own transfer fee in step 3 is a share or an amount, never both (new private column `units.transfer_fee_fixed`; "not sure" keeps the default); edits after publishing wait for review like the share (Michael 2026-11-25) | most developers charge a fixed amount | 1 migration, step 3 |
| D64 | The 50% deep-loss line (a display threshold, J6) and the discount rate while the present-value line is off never mark figures provisional; the exit report band reads "texts and rates not final" only while a rate the figures use is provisional, "texts not final" while only placeholder texts remain (Michael 2026-11-25) | every value the client answered is confirmed; placeholder texts keep the band | 1 list, 1 site text |
| D65 | A developer policy applies only when the client approved it and the developer is active (J3); only then does the public calculator list that developer. A policy's own share or amount carries its method, whatever the market default | J3; a policy giving 2% must not be ignored because the market default is a fixed amount | scope |
| D66 | Public calculator: two tabs as links (`?tab=buyer`), forms post to server actions, so both work without JavaScript; core computes, stores nothing (J12) and limits 60 calculations per address and hour (`rl_calculator_ip`, Michael 2026-11-25). Overdue installments on the seller tab are paid from the proceeds (the public calculator has no settlement choice). The years-paid field shows only while the refund rule follows the payment period | J12; the wizard keeps the choice | 1 route |
| D67 | Public values list (calculator and reports): each value in words with "checked <month year>", "provisional" or "under review" (12 months, J8); never a source, link, note or unapproved developer name (J10, J3). The reports page shows the client's indicators only once one is published (J9); their source stays internal | J8-J10 | 1 component |

**Decisions after Phase 6 (Michael 2026-11-25, after the Phase 6 commit a530e12; recorded only, nothing built yet;
to build in the next session):**

- K1, areas: the client approves the list in 3.3 as it is. One change: wherever governorates are offered as a
  choice (seller wizard, the listing filters, the dashboard), they are shown as a single vertical list, one under
  the other. Michael 2026-11-26: the wizard and filter dropdowns already list them one under the other and stay;
  the buyer budget form goes to one column; the dashboard's area screens (Phase 7) follow the same rule.
- K2, legal notes from the research: the client raised no objection, so they are treated as approved: a full
  refund when the developer is at fault, and no automatic cancellation once 75% of the price is paid. Texts may
  now rely on them (the legal texts still go to legal review, K7).
- K3, deep-loss warning: the threshold changes from 50% to 25% (`deep_loss_ratio_bp` 2500), confirmed by Michael,
  dated 2026-11-25; it stays editable in the dashboard (J6). Replaces the 50% line of D64.
- K4, maintenance deposit: shown as confirmed with the wording "usually 8% to 10% of the unit price, moves to the
  buyer with the transfer". Replaces the note-only wording of D61; the 8-10% range is now public.
- K5, transfer conditions (V5): one line in the calculator and in the wizard's transfer-fee area (step 3) telling
  the seller that some developers set conditions before approving a transfer, and to check with their developer.
- K6, developers: no developer names on the public site. Developer policies stay internal. Replaces the public
  naming in J3 and D65 (an approved developer listed in the public calculator). Michael 2026-11-26, answer B: the
  public calculator uses the market values only; an approved developer policy applies in the seller wizard (when
  the seller picks their developer, the figures follow that policy, and no page shows the developer's name next to
  them) and in the dashboard. Scope (Michael 2026-11-26): policies only; unit cards, unit and compound pages, the
  filters and structured data keep the developer name as decided in 2.5.
- K7, texts: every placeholder text is written by us, in Arabic and English, in the next session; the client
  reviews later. The legal ones (disclaimer, terms, privacy, fee consent: PLAN 6 rows 6, 7, 8a, 9, 10) are marked
  "awaiting legal review" for Eng. Enas before launch. Michael 2026-11-26: row 8 (seller notices and declarations)
  is marked "awaiting legal review" too. Scope (Michael 2026-11-26): the site texts (the dashboard's placeholder
  list); e-mail and interface messages are only corrected where they still say "placeholder" or promise what PLAN
  does not support. Compound intros only for compounds in the data. The About page uses what exists for now (PLAN
  facts and the founders' paragraph on the home page); the client's founders' bios and platform story, sent at the
  start, come from Michael next and replace it. Michael pasted them the same day; the About page is built from them.
- About page and exit report band (Michael 2026-11-26, on the K1-K7 report):
  - The About page keeps the client's wording exactly as they wrote it; none of our suggested rewrites (the
    sentences that read as promises are the client's text and the client's call). Only spelling, punctuation and
    spacing typos are fixed; "تطلبتهم" reads "متطلباتهم". The page is marked as the client's own text (`client`),
    not "awaiting legal review".
  - Eng. Enas's story is in the feminine throughout ("كنت مؤمنة", "أنا مؤمنة", "كنت عايزة"); nothing else changes.
    Her role caption on the About page is "الشريكة المحامية". On the home page too (Michael 2026-11-26): her
    portrait caption reads "الشريكة المحامية" and the review table line "بإشراف الشريكة المحامية"; the English
    ("Lawyer partner", "Led by our lawyer partner") carries no gender and stays.
  - Everywhere a text refers to Eng. Enas it is in the feminine (Michael 2026-11-26): the home review paragraph reads
    "ومحامية تحوّلت لمستثمرة عقارية" and the menu hint under About "المعماري والمحامية اللي ورا لقطة."; nothing
    else changes. The design board in docs/design (a reference file, not on the site) is left as it was.
  - The English of the About page follows the Arabic and stays marked as our draft for the client to review.
  - Exit report band: answer A. "Draft: texts not final" stays on the PDF until Eng. Enas has reviewed the exit report
    disclaimer (D69).

**K1-K7 built (2026-11-26; choices for review):**

| # | Choice | Why | Reversing |
|---|---|---|---|
| D68 | Site text states in `site_texts.source`: `placeholder`, `ours`, `legal_review`, `client`; `is_placeholder` is true for placeholders only. The seeder writes our rows as `ours`, the rows marked legal (PLAN 6 rows 6, 7, 8, 8a, 9, 10, FAQ pair 10) as `legal_review`, the About page (the client's own text, Michael 2026-11-26) as `client` | the dashboard and the PDF band need to tell "written, client to review" from "waiting for the lawyer" | 1 enum, seeder |
| D69 | The exit report band counts a text awaiting legal review as not final: today only the exit report disclaimer (row 10) keeps "Draft: texts not final" on. Michael 2026-11-26, answer A: the band stays until Eng. Enas has reviewed the disclaimer | his decision | 1 method (`ExitReportPdf::textsNotFinal`) |
| D70 | A site text body may carry structure as plain text: "## " a heading, lines starting "1. " or "- " a list, "**lead:**" a bold lead; rendered as React elements, never as HTML (`web/src/lib/blocks.ts`) | the legal pages and the About page need headings and lists, and the client edits plain text in the dashboard | 1 function |
| D71 | The deposit range is two settings (`maintenance_deposit_min_bp` 800, `maintenance_deposit_max_bp` 1000) in the maintenance value, a pair the editor checks like the cancellation range | the client may change the range; K4's wording reads from it | 2 settings |
| D72 | The dashboard overview shows the site texts by state and lists every text awaiting legal review (key and where it is used); the site texts editor stays a later screen | "mark the legal texts in the dashboard" without building the editor now | 1 card |
| D73 | The public calculator ignores a developer field and answers without `policy` (K6); the market-values answer has no `developers` list. Developer policies still apply to a seller's own unit in the wizard and the exit report, which stay private to the seller | K6 answer B | 1 route |

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

### 3.3 Areas (decided 2026-11-17: three levels)

Governorate > area > district (e.g. Cairo > New Cairo > Fifth Settlement), one `areas` table with `parent_id`
(already in place). Compounds belong to the most specific level known; a unit stores the most specific level the
seller picked.

**Which level carries the unit code (proposal, recommended): the area.** A district takes its area's code (a unit in
Fifth Settlement is `LK-NC-…`). Codes stay short and few (42 today), the board's example `LK-NC-0124` keeps working,
and a code never says more about the location than the page already does. Alternative: a code per district (more
codes to invent and keep unique, and longer unit codes).

The revised list (`core/database/data/areas.php`; 10 governorates, 42 areas, 19 districts), approved by the client
as it is (2026-11-25, K1: governorates offered as a choice are shown as one vertical list):

| Governorate | Area (code) | Districts |
|---|---|---|
| القاهرة / Cairo | القاهرة الجديدة / New Cairo (**NC**) | التجمع الخامس / Fifth Settlement · التجمع الأول / First Settlement · التجمع الثالث / Third Settlement · الرحاب / El Rehab · القطامية / Katameya · النرجس / El Narges · الياسمين / El Yasmeen · اللوتس / El Lotus · الأندلس / El Andalus · بيت الوطن / Beit El Watan · المربع الذهبي / Golden Square |
| القاهرة / Cairo | العاصمة الإدارية الجديدة / New Administrative Capital (**AC**) | الحي السكني الثالث (R3) / Residential District 3 (R3) · الحي السكني الخامس (R5) / Residential District 5 (R5) · الحي السكني السابع (R7) / Residential District 7 (R7) · الحي السكني الثامن (R8) / Residential District 8 (R8) · داون تاون / Downtown · حي المال والأعمال / Central Business District |
| القاهرة / Cairo | مدينة المستقبل / Mostakbal City (**MC**) | – |
| القاهرة / Cairo | مدينتي / Madinaty (**MD**) | – |
| القاهرة / Cairo | الشروق / El Shorouk (**SH**) | – |
| القاهرة / Cairo | مدينة بدر / Badr City (**BD**) | – |
| القاهرة / Cairo | هليوبوليس الجديدة / New Heliopolis (**NH**) | – |
| القاهرة / Cairo | مصر الجديدة / Heliopolis (**HL**) | – |
| القاهرة / Cairo | مدينة نصر / Nasr City (**NS**) | – |
| القاهرة / Cairo | المعادي / Maadi (**MA**) | دجلة المعادي / Degla Maadi · زهراء المعادي / Zahraa El Maadi |
| القاهرة / Cairo | المقطم / Mokattam (**MK**) | – |
| الجيزة / Giza | الشيخ زايد / Sheikh Zayed (**SZ**) | – |
| الجيزة / Giza | زايد الجديدة / New Zayed (**NZ**) | – |
| الجيزة / Giza | السادس من أكتوبر / 6th of October (**OC**) | – |
| الجيزة / Giza | حدائق أكتوبر / October Gardens (**OG**) | – |
| الجيزة / Giza | القرية الذكية / Smart Village (**SV**) | – |
| القليوبية / Qalyubia | العبور / El Obour (**OB**) | – |
| الشرقية / Sharqia | العاشر من رمضان / 10th of Ramadan (**TR**) | – |
| الإسكندرية / Alexandria | سموحة / Smouha (**SM**) | – |
| الإسكندرية / Alexandria | سيدي جابر / Sidi Gaber (**SG**) | – |
| الإسكندرية / Alexandria | سان ستيفانو / San Stefano (**ST**) | – |
| الإسكندرية / Alexandria | المنتزه / Montaza (**MZ**) | – |
| الإسكندرية / Alexandria | العجمي / Agami (**AG**) | – |
| الإسكندرية / Alexandria | كينج مريوط / King Mariout (**KM**) | – |
| الإسكندرية / Alexandria | برج العرب الجديدة / New Borg El Arab (**BA**) | – |
| الدقهلية / Dakahlia | المنصورة الجديدة / New Mansoura (**NM**) | – |
| مطروح (الساحل الشمالي) / Matrouh (North Coast) | الساحل الشمالي / North Coast (**SL**) | – |
| مطروح (الساحل الشمالي) / Matrouh (North Coast) | العلمين الجديدة / New Alamein (**NA**) | – |
| مطروح (الساحل الشمالي) / Matrouh (North Coast) | سيدي عبد الرحمن / Sidi Abdel Rahman (**SA**) | – |
| مطروح (الساحل الشمالي) / Matrouh (North Coast) | الضبعة / El Dabaa (**DB**) | – |
| مطروح (الساحل الشمالي) / Matrouh (North Coast) | رأس الحكمة / Ras El Hekma (**RH**) | – |
| مطروح (الساحل الشمالي) / Matrouh (North Coast) | مرسى مطروح / Marsa Matrouh (**MM**) | – |
| السويس / Suez | العين السخنة / Ain Sokhna (**SK**) | – |
| السويس / Suez | الجلالة / Galala (**GL**) | – |
| البحر الأحمر / Red Sea | الغردقة / Hurghada (**HG**) | – |
| البحر الأحمر / Red Sea | الجونة / El Gouna (**GN**) | – |
| البحر الأحمر / Red Sea | سهل حشيش / Sahl Hasheesh (**HS**) | – |
| البحر الأحمر / Red Sea | مكادي / Makadi Bay (**MB**) | – |
| البحر الأحمر / Red Sea | سوما باي / Soma Bay (**SB**) | – |
| البحر الأحمر / Red Sea | مرسى علم / Marsa Alam (**MR**) | – |
| جنوب سيناء / South Sinai | شرم الشيخ / Sharm El Sheikh (**SS**) | – |
| جنوب سيناء / South Sinai | دهب / Dahab (**DH**) | – |

Changes from the first draft: El Obour moved to Qalyubia and 10th of Ramadan sits in Sharqia (their real
governorates); Borg El Arab reads New Borg El Arab; added Badr City, Heliopolis, Maadi, Mokattam, Smart Village,
Sidi Gaber, San Stefano, Montaza, Agami, King Mariout, New Mansoura, El Dabaa, Marsa Matrouh, Makadi Bay, Marsa
Alam, Dahab and the districts of New Cairo, the New Capital and Maadi.

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

### 3.7 Migrations for the seller flow (approved 2026-11-17)

In run order. Nothing is deployed yet, so the skeleton's own `users` migration is edited rather than altered.
Money is `BIGINT UNSIGNED` whole EGP; every choice is a short code string; private columns never appear in a
public API resource (one whitelist test).

| # | Migration | Columns and indexes |
|---|---|---|
| 1 | `users` (edit skeleton) | + `phone` (string 20), `locale` (char 2, default `ar`) |
| 2 | `admin_users`, `admin_roles` | name, email unique, password, role (`admin` / `reviewer`), locale, is_active, last_login_at |
| 3 | `settings` | key unique, value JSON, type, group, updated_by → admin_users. Seller-flow keys: `receipts_required`, `installment_check_tolerance_pct`, `id_retention_days`, `draft_id_retention_days`, `description_min`, `description_max`, `photo_max_count`, `buyer_fee_bp` (100), `vat_bp` (0), `transfer_fee_payer` (seller), `transfer_fee_mode`, `transfer_fee_bp` / `transfer_fee_fixed`, calculator keys (4.2) |
| 4 | `areas` | parent_id (null = governorate), code (string 4, unique, nullable on governorates), name_ar, name_en, slug unique, sort, is_active |
| 5 | `developers` | name_ar, name_en, slug unique, is_active |
| 6 | `compounds` | developer_id, area_id, name_ar, name_en, slug unique, is_active; index (developer_id) |
| 7 | `features` | code unique, group, name_ar, name_en, applies_to (JSON list of main types), sort, is_active (seeded from 2.2.5) |
| 8 | `units` | **owner and state:** seller_id → users, state (8 codes, 2.2.6), step_reached, serial unique nullable (at first submit), code unique nullable (at publish), slug unique nullable · **step 1:** developer_id / developer_other, compound_id / compound_other, area_id / area_other, main_type, sub_type, contract_number, unit_number (both private), contract_year · **duplicate keys:** contract_key, unit_key (normalised, indexed) and `active_contract_key`, `active_unit_key` (unique, filled only while submitted, changes_requested, published or reserved; MariaDB has no partial indexes, and NULLs do not collide) · **step 2:** size_m2, garden_m2, roof_m2 (decimal 8,2), floor (a short code: basement, ground, mezzanine, 1 to 40; built as a code rather than a number so mezzanine needs no special value, 2026-11-17), bedrooms, bathrooms, finishing, delivery_status, delivery_year, facade (4-bit set), description · **step 3:** total_price, paid_amount, remaining_amount (stored generated column total − paid, for filters and sort), market_price_estimate, installment_amount, installment_frequency, next_due_date, years_left, annual_extra_amount (null = no), accepts_less, lowest_accepted_amount, has_overdue, overdue_count, overdue_amount, overdue_settlement (`self` | `deduct`, 2.2.12; new migration in phase 4), maintenance_due, delivery_payment (2.2.9 point 3) · **flags:** verified_at, verified_by, deal_tag (bool) · **dates:** submitted_at, published_at, withdrawn_at, withdraw_reason, rejected_at · indexes for the default sort (accepts_less, published_at) and the listing filters |
| 9 | `unit_feature` | unit_id, feature_id; primary key on both |
| 10 | `unit_photos` | unit_id, kind (`photo` / `floor_plan`), is_project_image (the "صورة من المشروع / Project image" label), path base, sort, width, height, bytes |
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

**Buyer tab:** contract price (old), developer price today for a similar unit. No transfer fee in the buyer's figures
(decided 2026-11-17).

### 4.2 Formula (as proposed, decided 2026-11-17; transfer fee and VAT updated the same day; every constant is a setting)

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
  recovery      = paid                                       # 0% Lokta commission for the seller
  transfer_fee  = S.transfer_fee_mode == pct ? contract_price * S.transfer_fee_bp : S.transfer_fee_fixed
                  # paid by S.transfer_fee_payer (default seller, decided 2026-11-17) to the developer, not to Lokta;
                  # a developer policy can override mode, rate or amount
  given_up      = accepts_less ? paid - lowest_accepted : 0
  arrears       = has_overdue and overdue_settlement == deduct ? overdue_amount : 0   # 2.2.12; buyer total unchanged
  seller_net    = recovery - (transfer_fee if payer is seller) - given_up - arrears
  advantage     = seller_net - refund (nominal, and present value when S.show_present_value)

Buyer
  fee           = round(contract_price * S.buyer_fee_pct)    # 1%
  fee_vat       = fee * S.vat_bp                            # vat_bp = 0 (client confirmed, decided 2026-11-17)
  gain          = developer_price_today - contract_price - fee - fee_vat   # no transfer fee on the buyer side
```

### 4.3 Market values to research (each with a source and a date, stored in settings)

1. Cancellation (refund) deduction in Egyptian developer contracts: typical range and its basis (total price or
   amount paid); differences between large developers.
2. Refund schedule after cancellation: lump sum vs installments; usual period (mirroring the payment period or
   fixed).
3. Late-payment penalties: rate and basis (per month or per year, on the overdue amount), grace period, when
   cancellation is triggered (number of missed installments).
4. Developer assignment (transfer) fee: percentage or fixed amount and its basis (the payer is decided: the seller).
5. Conditions on assignment: minimum paid share before a developer allows transfer, blackout periods, approval time.
6. Maintenance deposit at delivery (percentage of price) and whether it transfers.
7. A discount rate for the present-value line (for example the Central Bank of Egypt's rate); the line is a
   setting, OFF by default (decided 2026-11-17).
8. ~~VAT on the commission~~: settled, the client confirmed no VAT on the 1% (`vat_bp` = 0).

Method: public sources only (developer contract terms published online, law firm and press articles, court
summaries), at least two independent sources per value, each recorded with URL and date; values marked "market
average" on the page. The client reviews the numbers before launch.

**Phase 6 plan approved (Michael 2026-11-24), with these answers:**

- J1: a value is a range supported by at least two independent sources, never a single average. A developer's own
  published terms count alone for that developer only.
- J2: the client decides whether the cancellation deduction is taken from the total price or from the amount paid;
  the findings table asks it, with what each developer does.
- J3: no developer is named publicly unless the policy comes from its own published terms and the client approves
  it; until then the market range only.
- J4: the default refund period is a dashboard setting the client changes; it starts from what most sources say.
- J5: late fees are an optional line in the calculator.
- J6: the 50% deep-loss line stays a labelled display threshold, editable in the dashboard.
- J7: if the present-value line is ever switched on, the Central Bank of Egypt's overnight deposit rate, dated.
- J8: values are re-checked every 6 months; after 12 months a value is marked "under review".
- J9: the reports page shows only the client's indicators at launch; our own listing statistics come later, and
  only for groups of at least 30 cases.
- J10: sources (names, links, dates) are NOT shown on the public site. They stay internal: in the dashboard next to
  each value and in the findings table for the client. On the public site each value shows only its range and
  "checked <month year>"; the reports page shows the values without source links.
- J11: the buyer types the developer's price today; no researched prices per area at launch.
- J12: the calculator stores nothing and needs no sign-in.
- Order: research first; the findings table goes to Michael before anything is loaded into settings.
- Client's answers (2026-11-25, relayed by Michael): cancellation 15% of the total unit price, one value (J2: total
  price); refund in one payment (J4); default transfer fee a fixed EGP 50,000 (a seller's own figure in step 3
  replaces it); late-payment penalty 1% of each late installment, charged once, whatever the installment period (J5:
  the line is on); the maintenance deposit moves to the buyer with the transfer (an information note). Loaded as
  confirmed, dated 2026-11-25 (D61-D64). Not answered: the transfer-conditions note (V5, left out) and the legal notes.
- Later the same day (Michael, K2-K6 in section 2): legal notes approved; deep-loss line 25%; maintenance deposit
  "usually 8% to 10%, moves to the buyer" as confirmed; one transfer-conditions line (V5); no developer named
  publicly.

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

Michael 2026-11-25 (K7): we write every placeholder text in Arabic and English in the next session, and the client
reviews later. The legal ones (rows 6, 7, 8, 8a, 9, 10; row 8 added by Michael 2026-11-26) are marked "awaiting
legal review" for Eng. Enas before launch.

Written 2026-11-26 (K7): every site text is now ours or the client's; none is a placeholder. Each text carries its
state in `site_texts.source`: `ours` (written by us, the client reviews), `legal_review` (awaiting legal review),
`client`. The dashboard overview counts the texts by state and lists those awaiting legal review (D72).

Legend: **P** = we draft a placeholder now; **C** = client text required before launch, with a placeholder only so the
page works; **F** = a fact we must not invent (left visibly empty until supplied); **ours** = written by us
2026-11-26, the client reviews; **legal review** = written by us (or the client's text), awaiting legal review.

| # | Text | AR | EN | Mark |
|---|---|---|---|---|
| 1 | Site name: لقطة / Lokta (decided 2026-11-17, Q2) | ✓ | ✓ | done |
| 2 | Home: hero, value proposition, seller and buyer sections, steps, CTAs | ✓ | ✓ | ours (board copy; the review paragraph is the client's) |
| 3 | About page | ✓ | ✓ | client (founders' stories and platform story as written, typos fixed only; Eng. Enas's story in the feminine; EN our draft translation for the client, Michael 2026-11-26) |
| 4 | How it works: seller journey, buyer journey, fees | ✓ | ✓ | ours |
| 5 | FAQ questions and answers | ✓ | ✓ | ours (10 pairs); pair 10 uses the K2 legal notes: legal review |
| 6 | Privacy policy | ✓ | ✓ | legal review (Eng. Enas) |
| 7 | Terms and conditions | ✓ | ✓ | legal review (Eng. Enas) |
| 8 | Seller notices and declarations: owner-only notice, no-over modal, owner-not-broker, data matches documents (2.2.1, 2.2.2) | ✓ | ✓ | legal review (Eng. Enas) |
| 8a | Privacy policy paragraph on national ID copies and their retention: 90 days after sold, withdrawn or rejected; 180 days for abandoned drafts (2.2.8, option A) | ✓ | ✓ | legal review (Eng. Enas) |
| 8c | Transfer fee note: "the developer's transfer fee (paid by you to the developer, not to Lokta)" on step 3, the review step, the calculator and the exit report | ✓ | ✓ | ours |
| 8b | Seller e-mails (8, 2.2.7) and the "48 hours" promise | ✓ | ✓ | P → C |
| 9 | Buyer fee consent wording | ✓ | ✓ | legal review (Eng. Enas) |
| 10 | Calculator and exit report disclaimers | ✓ | ✓ | legal review (Eng. Enas) |
| 11 | Exit report PDF wording (headings, explanations) | ✓ | ✓ | ours |
| 12 | Developers page intro and partnership points | ✓ | ✓ | ours |
| 13 | Reports page intro and methodology | ✓ | ✓ | ours |
| 14 | Initial indicators for the reports page (with sources) | ✓ | ✓ | F |
| 15 | Contact facts: phone, e-mail, address, working hours, social profiles | | | F |
| 16 | Company facts for markup and footer: legal name, founding year | | | F |
| 17 | Compound intros (per compound) | ✓ | ✓ | none written: the data has only the invented sample compounds (K7: only compounds in the data); the index intro is ours |
| 18 | E-mail templates (about 20, listed in 2.x). Written so far as placeholders (`core/lang/{ar,en}/mail.php`): confirm your e-mail, reset your password, new budget (to the team) | ✓ | ✓ | P → C |
| 19 | Form labels, hints, validation and error messages (account pages: `web/messages/*.json` `account`; core validation and account messages: `core/lang/{ar,en}/validation.php`, `accounts.php`) | ✓ | ✓ | P (we write; client reviews) |
| 20 | Unit state and deal stage labels shown to users | ✓ | ✓ | P |
| 21 | Meta titles and descriptions per page | ✓ | ✓ | P → C |
| 22 | Logo files (have: navy and transparent PNG), favicon, social image | | | have / need SVG |
| 23 | Seller wizard texts: the web pages (`web/messages/*.json` `sell`, `account.units`), step checks, duplicate notice, upload refusals; e-mails "received", "withdrawn" and the team notices (`core/lang/{ar,en}/seller.php`, `uploads.php`, `mail.php` `unit_received`, `unit_team`, `unit_notice`). The exit report labels are site texts (group `exit_report`, rows 10 and 11) | ✓ | ✓ | P → C |
| 24 | Listing and request texts: card and unit page labels, filters, the request form, the forms, My requests (`web/messages/*.json` `listing`, `forms`, `account.requests`; `core/lang/{ar,en}/listing.php`, `requests.php`, `mail.php` `request_received`, `team_facts`); the fee consent and request note are site texts (`buyer.*`, row 9) | ✓ | ✓ | P → C |

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
- [x] Proposals in 2.2 and 3.7 approved by Michael (2026-11-17)
- [x] Migrations for the agreed model (3.7, all 18 tables; rollback and re-run proven on scratch)
- [x] Seeders: ReferenceSeeder (roles, 24 settings, 7 governorates and 25 areas with codes, 35 features, 15
      placeholder texts; add-only, never overwrites a dashboard edit) and SampleSeeder (invented developers and
      compounds; refuses any database not ending in _scratch or _test)
- [ ] Areas list (`core/database/data/areas.php`) reviewed by Michael: the seed is a first draft
- [x] Admin auth (guard `admin`, `admin_users`), roles admin and reviewer with gates, sign-in throttle, switched-off
      accounts signed out, dashboard in Arabic and English; `php artisan admin:create` (password at a hidden prompt)
- [x] Activity log: dashboard-authored models log create, update and delete by themselves (hidden fields never
      logged); sign-in, failed sign-in and sign-out logged; a test per model
- [x] Mail outbox: row first, send in the request, `outbox:send` every minute with back-off, dead after 6 tries;
      `php artisan mail:test {email}`
- [x] `site_texts` with placeholder flag; `settings` typed and validated, in a versioned file cache, calculator
      and transfer-fee values marked provisional (2.2.9 point 8)
- [x] Public API v1 skeleton behind the server key (fails closed), language by `?locale=`; settings, site texts,
      areas, developers, compounds, features, units list and unit by code; the field-whitelist test (exact keys
      plus a sentinel scan of every keyed GET route)

**Phase 2: Visual identity and site shell** (agreement: palette, fonts, page design, UX; 3 free revision rounds)
- [x] Palette and type from the design tokens: public theme `web/src/styles/lokta-theme.css` (rem type, mobile
      tokens, `text-hero` 86px override), dashboard theme `core/resources/css/app.css` + `lokta-tokens.css` (shadcn
      variable names, Lokta values, radius pinned to 2px, dark only); `scripts/check-tokens.mjs` keeps both in step
- [x] Fonts self-hosted (Alexandria Arabic/Latin, IBM Plex Mono; OFL) in both apps, no Google request
- [x] Header (grouped menu, dropdowns, language link, sign-in), phone menu, footer
- [x] Home from board 06.1; text pages about, how it works, FAQ, buyers, contact, privacy, terms (site texts,
      placeholders flagged); placeholder pages for the screens of later phases
- [x] Staff dashboard shell from the Watchizer copy (section 9): sidebar by role, header, language switch, tables,
      forms, dialogs, error page, activity log screen (admin), translation coverage test
- [x] Areas in three levels; public fields per page (2.5); privacy test with the seller's name

**Phase 3: Accounts**
- [x] Register (Egyptian mobile, consent, honeypot), e-mail confirmation (signed link, 24 h), sign-in, sign-out,
      forgot and reset (60 min, signs out everywhere), resend; e-mail only, through the outbox (RUN D10, D11, D17, D18)
- [x] Return-to-action: `next` and `intent` (favorite, budget, sell) carried through sign-in and registration
- [x] Favorites (public units only; departed units marked) and the heart button (RUN D19)
- [x] Buyer budget form (capacity, types, areas and districts, compounds, delivery) and the team e-mail (RUN D12,
      D14, D15); My account with details, confirmation status and profile edit (RUN D13)
- [x] Tests: 24 account tests; the private-fields scan now covers the account routes; end-to-end run in a browser

**Phase 4: Seller** (started 2026-11-18)
- [x] Migration from 2.2.13 and 2.2.12: transfer fee per unit, contract and delivery months, overdue settlement,
      photo hiding; team mailbox setting; upload limit settings (2.2.14) (Michael 2026-11-18)
- [x] 6-step wizard, server side (core API): drafts, autosave, cross-field checks, declarations, submit (2026-11-18)
- [x] 6-step wizard, web pages: /sell with the no-over modal, /sell/{id}/1..6 with autosave, the live exit report and
      the browser-side shrink and HEIC conversion (heic-to 1.6.6, LGPL-3.0, Michael 2026-11-18), /sell/{id}/done
      (board 07.1 frame built 2026-11-24 after Michael's OK: own header, step rail with the 0% box, phone dock)
- [x] Uploads (documents, photos) with limits and private storage for documents; security as 2.2.15 (2026-11-18)
- [x] Duplicate contract block (step 1 and submit, logged, rate limited) (2026-11-18)
- [x] Exit report PDF (AR + EN): calculator engine (4.2) in core, mPDF with Alexandria, generated at submit,
      attached to the seller's e-mail, owner download; "verified" version on publish comes with phase 7
- [x] Unit status in "My account" (/account/units and the unit page); seller e-mails (received, withdrawn; the review
      outcomes come with phase 7); editing after publish (tiers 2.2.7) and withdrawal (request during a deal);
      audit 2026-11-22 M5 and M6 fixed 2026-11-23 (pending values checked together, a passed due date no longer blocks)
- [x] ID retention job (2.2.8): `ids:purge`, daily 04:00 Cairo, with --dry-run

**Phase 5: Listings** (2026-11-22, decisions in 2.6)
- [x] Opportunities page: filters, sort, numbered pages plus show more, server-rendered cards (board 06.2; the
      filter bar, featured card and phone filter built 2026-11-24 after Michael's OK)
- [x] Unit page: specs, payment plan with the computed schedule, money panel, verified badge, request form with the
      fee consent, structured data, old slugs redirect (board 06.3: marks, price type and phone bar added 2026-11-23;
      the gallery with its viewer built 2026-11-24 after Michael's OK)
- [x] Compound pages and index; developers page with partnership form; contact form; My requests; home picks
- [x] Privacy: card keys pinned; the sentinel scan runs over the API and, in the browser run, over the built pages
      and their structured data (2.5)

**Phase 6: Tools**
- [x] Calculator (both tabs) on settings and developer policies (2026-11-25; D60-D67)
- [x] Market values researched, sourced and loaded as settings (research 2026-11-24, register
      `docs/research/market-values-2026-11.md`; client's answers loaded as confirmed 2026-11-25, D61)
- [x] Reports and indicators page + dashboard editor (2026-11-25; indicators wait for the client, PLAN 6 row 14)
- [ ] Open for the client: the transfer-conditions note (V5) stays out; the legal notes (75% rule, full refund on a
      court-ordered cancellation) are unconfirmed and no text relies on them; the 50% line and the discount rate are
      not confirmed; calculator and reports texts are placeholders (PLAN 6 rows 10, 13); no developer publishes its
      own terms yet (J3)
- [x] Decisions K1-K7 (Michael 2026-11-25 and 26, section 2) built 2026-11-26 (D68-D73): the budget form's
      governorates in one column, the 25% line, the deposit wording, the transfer-conditions line, no developer
      named or offered in the public calculator, every site text written (legal ones awaiting legal review), the
      About page from the client's own text (marked `client`)
- [ ] Open: the discount rate; contact and company facts (PLAN 6 rows 15, 16); legal review of the texts marked
      for it (the exit report band stays until then, D69)

**Phase 7: Dashboard operations**
- [ ] Units review and document review; outcomes: request changes, reject, publish, publish as verified
- [ ] Verified on and off, market price edits, "لقطة" tag, pending changes after publish (all logged)
- [ ] Matching screen, deals and stages, commissions
- [ ] Inboxes: unit requests, partnership requests, contact messages
- [ ] E-mails to both parties per stage
- [ ] Staff management, including changing a staff password (open item: today `admin:create` only creates accounts,
      so a staff password cannot be changed; Michael 2026-11-24 decision B: wait for this phase)

**Phase 8: English, SEO, quality**
- [ ] Every page and e-mail complete in English
- [ ] Sitemaps, robots, structured data, titles, canonical/hreflang checks (crawler, PASS/FAIL)
- [ ] Page weight, images, accessibility pass

**Phase 9: Delivery**
- [ ] Placeholder report from the dashboard: every text still waiting for the client
- [ ] Deploy scripts per handover 3 (numbered steps, STOP, backups, rehearsal on a scratch copy), live checks
- [ ] Host `.htaccess` API allow-list names every `/api/v1` route (handover 2.4), kept in the repository with a test
      that compares it with routes/api.php; `CORE_API_KEY` set in core `.env` and web variables (same value)
- [ ] Hand-over notes for the client team

---

## 8. Questions and decisions

Michael's review of 2026-11-17. "As recommended" means the recommendation in the earlier draft of this table.

| # | Question | Decision |
|---|---|---|
| Q1 | URL scheme (1.1) | A: Arabic at root, English under `/en`, Latin slugs (decided 2026-11-17) |
| Q2 | Site name | Arabic "لقطة", English "Lokta", domain loktaeg.com (decided 2026-11-17) |
| Q3 | Commission base, VAT, transfer fee | Base: total contract price at 1%, as aqarexit. No VAT on the 1% (`vat_bp` setting = 0). The seller pays the developer's transfer fee (`transfer_fee_payer` = seller; rate or amount a setting, a developer policy can override); shown to the seller as the developer's fee, never in the buyer's figures (all decided 2026-11-17, the last two from the client). What aqarexit shows: section 8.1 |
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

Still open: none of the above. All proposals in 2.2 and 3.7 were approved on 2026-11-17 (sub-types, features, finishing,
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

---

## 9. Files copied from the Watchizer dashboard

Rules (Michael, 2026-11-17): Watchizer (`D:\coding\watchizer website\new watchizer`) is read-only; files are copied,
never linked; only UI and generic parts; nothing of Watchizer may remain after copying (a search for its names,
routes, env names, colors and logos must return zero hits, with any kept term listed here). Every copied file is
listed below with its source, its path here and what changed.

| Source (Watchizer `core/`) | Path here (`core/`) | Changes |
|---|---|---|
| `resources/js/layouts/ManageLayout.tsx` | `resources/js/Layouts/DashboardLayout.tsx` | rewritten on the same structure: site name from shared props, no developer-credit footer, no theme toggle, rail state key renamed |
| `resources/js/components/manage/Sidebar.tsx` | `resources/js/Components/shell/Sidebar.tsx` | rewritten on the same structure: Lokta wordmark instead of the logo, our icon registry, active item = surface-2 with a 2px text-1 rule, "later" items for screens of later phases |
| `resources/js/components/manage/Header.tsx` | `resources/js/Components/shell/Header.tsx` | rewritten on the same structure: language switch posts to /dashboard/locale, user menu with role badge and sign-out only (no profile, no theme toggle) |
| `resources/js/components/manage/ConfirmAction.tsx` | `resources/js/Components/shell/ConfirmAction.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/table/DataTable.tsx` | `resources/js/Components/table/DataTable.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme; state resets moved from effects to render; new setFilters (several filters in one visit); export link computed per render |
| `resources/js/components/table/QuickSearch.tsx` | `resources/js/Components/table/QuickSearch.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/table/DateRangeFilter.tsx` | `resources/js/Components/table/DateRangeFilter.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/table/ExportLink.tsx` | `resources/js/Components/table/ExportLink.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme; reads our shared abilities |
| `resources/js/components/form/Field.tsx` | `resources/js/Components/form/Field.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/form/TextField.tsx` | `resources/js/Components/form/TextField.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/form/PasswordField.tsx` | `resources/js/Components/form/PasswordField.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme; physical right/pr classes made logical (end/pe) |
| `resources/js/components/form/SwitchField.tsx` | `resources/js/Components/form/SwitchField.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme; unused variable removed |
| `resources/js/components/form/Combobox.tsx` | `resources/js/Components/form/Combobox.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme; list id from useId; highlighted option reset in handlers, not in an effect |
| `resources/js/components/form/ErrorSummary.tsx` | `resources/js/Components/form/ErrorSummary.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/form/FormActions.tsx` | `resources/js/Components/form/FormActions.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/form/FormTabs.tsx` | `resources/js/Components/form/FormTabs.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme; the product-specific save note removed |
| `resources/js/components/form/TranslatedField.tsx` | `resources/js/Components/form/TranslatedField.tsx` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/form/useDirtyGuard.ts` | `resources/js/Components/form/useDirtyGuard.ts` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme |
| `resources/js/components/ui/{alert,badge,bidi,button,card,checkbox,dialog,dropdown-menu,input,label,popover,skeleton,switch,table}.tsx (14 files)` | `resources/js/Components/ui/ (same names)` | all comments removed; imports moved to `@/Components`; Tailwind 4 names; dark-only (every `dark:` variant dropped); Watchizer's blue `brand` accent replaced by Lokta values (checked = text-1, selected = surface-2); radius from the pinned 2px theme; Alert and Badge tones mapped to Lokta success / warning / error tints, Badge gains a verified variant; Switch square (toggles have no radius) |
| `resources/js/lib/utils.ts` | `resources/js/lib/utils.ts` | comments removed; unchanged code (cn helper) |
| `resources/js/pages/Auth/Login.tsx` | `resources/js/Pages/Auth/Login.tsx` | rewritten on the same split layout: Lokta wordmark panel, our login route and strings, language link |
| `resources/js/pages/Manage/Error.tsx` | `resources/js/Pages/Error.tsx` | rewritten: one Error page for 403/404/419/500/503 in both languages, rendered by bootstrap/app.php for dashboard routes |
| `resources/js/pages/Manage/NotFound/Index.tsx` | (none) | copied, then removed: the Error page covers 404 |
| `resources/js/pages/Manage/Activity/Index.tsx` | `resources/js/Pages/Activity/Index.tsx` | rewritten over our activity_log (actor kind, action, subject, field changes, reason, date range); only the screen's layout pattern kept |
| `app/Support/Table/TableQuery.php` | `app/Support/Table/TableQuery.php` | docblock prose removed (type tags kept); Watchizer's role class replaced by our gate `export-data`; its text helper by AppSupportDashboardText |
| `app/Support/Table/TableExport.php` | `app/Support/Table/TableExport.php` | docblock prose removed; text helper replaced; Watchizer's Coerce helper replaced by a local text() function |

Not copied: everything about products, orders, stock, storefronts, campaigns, the theme toggle, the developer-credit footer, the logo, the markdown and image fields, `lib/labels`, `lib/title`, Watchizer's translations, navigation class, roles and tests.

**Cleanup search (2026-11-17, every tracked and new file except `docs/research`, `docs/design` and the handover):** terms watchizer, brand fashion, product(s), storefront, store(s), shop(s), watch(es), sku, wa_code, order(s), cart, stock, inventory, `/manage`, `VITE_APP_NAME`, `#465fff`, `232 100%`, `#18181b`, zinc, tailadmin. **Zero hits in code.** Kept on purpose (false positives): this register, `docs/RUN_2026-11-17.md` and `docs/PROGRESS_2026-11-17.md` (they name Watchizer as the source); PLAN 0 (the handover's multi-shop pattern we did not take) and PLAN 5.2 ("a bare Product" schema type); the English words "order" ("in order", "default order"); Laravel's cache `store`, `AuthController::store()` and the `no-store` header; our sub-type `shop` (a unit type) and the home text "shops"; Vite's `watch` option.
