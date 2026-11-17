# aqarexit 01: sitemap, navigation, page list

## URL scheme

- Arabic at the root, English under `/en` (same as our provisional choice). `hreflang` ar / en in the sitemap;
  x-default only on unit pages.
- One `sitemap.xml`, 4.2 MB, 10,478 URLs: 16 static entries + 10,462 unit pages (`/buy/opportunity/<uuid>`).
  The 14 static pages share one build-time `lastmod` (not a real change date).

## Pages

| Page | AR URL | EN URL | In our scope? |
|---|---|---|---|
| Home | `/` | `/en` | Yes |
| Sell your unit (seller lead form, step 1 of 6) | `/sellers` | `/en/sellers` | Yes (seller flow) |
| Buyers lead form | `/buyers` (robots-blocked, not read) | `/en/buyers` | Yes (buyer budget) |
| Exit report request (2-step lead form) | `/exit-report` | `/en/exit-report` | Partly: our exit report is a generated PDF |
| Opportunities listing | `/opportunities` | `/en/opportunities` | Yes |
| Unit detail | `/buy/opportunity/<uuid>` | `/en/buy/opportunity/<uuid>` | Yes |
| Exit calculator | `/calculator` | `/en/calculator` | Yes |
| How it works | `/how-it-works` | `/en/how-it-works` | Yes |
| Our story (about) | `/story` | `/en/story` | Yes, as "About" |
| Reports & index | `/reports` (+ 2 PDFs) | `/en/reports` | Yes (admin-edited indicators) |
| For developers | `/developers` | `/en/developers` | Yes (+ partnership form, they have none) |
| FAQ | `/faq` | `/en/faq` | Yes |
| Investor relations | `/investors` | `/en/investors` | **No** (out of scope) |
| Privacy | `/legal/privacy` | `/en/legal/privacy` | Yes |
| Terms | `/legal/terms` | `/en/legal/terms` | Yes |
| Contact | `/contact` | `/en/contact` | Yes |
| Favorites | `/favorites` (noindex) | `/en/favorites` | Yes (we require login) |
| Portal, login, register | `/app/...` (robots-blocked) | | Yes (accounts), not read |

## Navigation

- **Header:** logo; Sell your unit; Opportunities; Our story; How we work; Contact; For investors; language switch
  button; two CTAs: "I want to buy" (`/buyers`), "I want to sell" (`/sellers`).
- **Mobile bottom tabs:** Home, Opportunities, Sell your unit, My account.
- **Footer:** tagline; WhatsApp number; e-mail. Columns: Platform (Favorites, Exit calculator, For sellers, For
  buyers); Company (Story, Reports, How we work, Developers, Investor relations, FAQ, Contact); Legal (Privacy,
  Terms). A country picker: Egypt, Saudi Arabia (soon), UAE (soon). A disclaimer: figures are indicative, not legal
  advice. "Powered by" line naming the operating company.
- **Floating:** an AI chat ("Ask Aqar Exit, replies in seconds"): out of scope for us.
- The language switch is a `<button>`, not a link: crawlers cannot follow it (ours must be a real link).
