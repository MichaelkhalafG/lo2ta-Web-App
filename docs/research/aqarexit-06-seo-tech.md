# aqarexit 06: SEO, performance and technical observations

Raw per-page table: `research-raw/seo_table.tsv`.

## Stack seen

Next.js App Router (RSC payload in the HTML), next-intl style message catalogue sent to the browser (82–95 KB of
JSON per page), IBM Plex Sans Arabic + Inter, Tailwind, Supabase storage for photos, Cloudflare in front (Turnstile,
managed robots.txt), Google Analytics tag. Server-rendered first paint: cards, prices and text are in the HTML.

## SEO basics, page by page

| Check | Result |
|---|---|
| `<html lang dir>` | correct on both languages |
| One H1 | yes, on every page read |
| Title | present; **legal pages reuse the home title**; unit titles 112–150 characters (cut in results); some English titles very short ("How it works", 12) |
| Meta description | present on every page |
| Canonical | present on most; **missing on the home pages and both legal pages** |
| hreflang | ar + en on most; **missing on home and legal**; x-default only on unit pages |
| Structured data | unit pages: Product (offer price = cash required) + BreadcrumbList; **none on home and content pages** (no Organization, WebSite, FAQPage) |
| Sitemap | one 4.2 MB file, 10,478 URLs, no index split; static pages share a fake build-time `lastmod` |
| robots.txt | blocks its own `/buyers` page by prefix (`Disallow: /buy`) while listing it in the sitemap |
| URLs | units are UUIDs (`/buy/opportunity/6fe2b5a7-…`), no readable slug; no compound or area landing pages |
| Language switch | a `<button>`, not a link |
| Favorites | noindex (right) |

## Weight

HTML alone: 150–270 KB on content and unit pages, **806–848 KB** on the listing (48 cards plus the message
catalogue and RSC payload). The full catalogue is shipped on every page, including strings for the staff back office.

## UX and content issues worth avoiding

- English pages show Arabic enum values (type, finishing, completion, features).
- No cross-field validation on money (installment larger than the remaining balance).
- The listing shows an "opportunities are coming soon" empty state above 10,000 units.
- "Total required from you now" adds the fee that is due only on completion.
- "Show 48 more" grows one page instead of real paginated pages with their own URLs.
- The calculator ignores time value, late fees and transfer fees, and its constants are in code.
