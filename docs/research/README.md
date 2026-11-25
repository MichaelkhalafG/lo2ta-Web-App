# aqarexit.com study: method and index

Read on 2026-11-17, 13:55 to 14:04 Cairo. Public pages only.

## Method (handover 5.4)

- GET only, one request every 1.5 s, a generic desktop Chrome user agent, no login, no form submitted, no button
  that sends data pressed.
- `robots.txt` honoured in code: a URL is fetched only if it is allowed both as written and with the `/en` prefix
  removed. Disallowed for `*`: `/ops`, `/app`, `/buy` (except `/buy/opportunity/`), `/api` (except `/api/og/`),
  `/u`, `/stop`, any `_rsc=` URL.
- 71 requests in total: robots.txt, sitemap.xml, 38 HTML pages (19 Arabic + 19 English), 2 report PDFs, 7 unit pages,
  28 JavaScript chunks served under `/_next/static/` (allowed path) for the pages read. All answered 200.
- Skipped by robots: `/buyers` and `/en/buyers`. `Disallow: /buy` also matches `/buyers` (robots rules are prefix
  matches), so aqarexit blocks its own buyers page from crawlers while listing it in its sitemap.
- robots.txt also carries Cloudflare content signals: `search=yes, ai-train=no, use=reference`. Our use is reference
  reading for a competitor study; nothing is used for training, and none of their text is to be reused on our site.

## Where things are

| What | Where | In the repository? |
|---|---|---|
| These notes | `docs/research/*.md` | Yes |
| Raw HTML, PDFs, JS chunks, the crawler (`crawl.mjs`), request log (`index.tsv`) | `research-raw/` (repo root, gitignored) | No |
| Their full message catalogue, flattened (1,476 keys, AR + EN) | `research-raw/messages_flat.tsv` | No |
| SEO table per page | `research-raw/seo_table.tsv` | No |

The message catalogue was decoded from the Next.js RSC payload that every public page embeds; no extra request was
needed for it. It lists the labels of flows behind login (`/app`), which robots.txt does not let us open.

## Notes

1. [Sitemap, navigation, page list](aqarexit-01-sitemap.md)
2. [Flows and forms: seller, exit report, buyer, enquiry, contact, accounts, portal states, e-mails](aqarexit-02-flows-forms.md)
3. [Calculator: inputs, formula, outputs](aqarexit-03-calculator.md)
4. [Opportunities listing and unit page](aqarexit-04-listing-unit.md)
5. [Content pages: home, how it works, FAQ, developers, reports, about, legal, contact](aqarexit-05-content-pages.md)
6. [SEO, performance and technical observations](aqarexit-06-seo-tech.md)

## Limits

- Anything under `/app` (the seller and buyer portals) was not opened. Its fields and states come from the message
  catalogue and the public JS, not from seeing the screens.
- The seller's six steps were reconstructed from code: 2 public lead steps + 4 portal wizard steps
  (`SELLER_JOURNEY_STEPS = 2 + CASE_WIZARD_STEP_TITLES.length`). The portal steps' exact fields are inferred from
  the unit page's fields and the catalogue.
- Figures on their site (11,358 units, EGP 162.2 bn) are their claims, unverified.

## Market values for the calculator (2026-11-24)

`market-values-2026-11.md`: the sources (publisher, date, link, the figures in their own words) and the findings for
each calculator value (PLAN 4.3, Phase 6 step 1). Internal only (J10): the public site shows ranges and the month
checked, never the sources. Read with the same pace and robots rules as above; pages that refuse automated reading
were not read and are not counted.
