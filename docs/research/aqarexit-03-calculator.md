# aqarexit 03: the exit calculator (`/calculator`)

Read from the page HTML, the message catalogue and the page's public JS (module `computeSellerResult`).

## Tabs

"I own a unit" (seller) and "I'm a buyer".

## Seller tab

**Inputs**

| Field | Rule |
|---|---|
| Total unit price in the contract | EGP, required |
| Total paid so far (down payment + installments) | EGP, required |
| Over how many years did you pay it? | integer 1–15, required |
| Developer (optional; label exists in the catalogue, not rendered on the page today) | "if selected, we use their contract data" |

**Policy constants (hard-coded in their JS)**

```
jurisdiction: EG
penaltyPctMin: 0.10            // of the TOTAL unit price, not of what was paid
penaltyPctMax: 0.15
refundMirrorsPaymentPeriod: true   // the refund is paid back over the same number of years
evidenceLevel: market_baseline     // vs "verified contract data for {developer}"
```

**Formula**

```
penaltyMin   = totalPrice * 0.10
penaltyMax   = totalPrice * 0.15
refundMax    = max(0, amountPaid - penaltyMin)
refundMin    = max(0, amountPaid - penaltyMax)
refundYears  = paymentYears
wipeout      = refundMin <= 0
lossRatioWorst = amountPaid > 0 ? (amountPaid - refundMin) / amountPaid : 0

transfer.recovery    = amountPaid          // 100% back, cash at completion
transfer.sellerFee   = 0
transfer.netRecovery = amountPaid

advantageMin = netRecovery - refundMax
advantageMax = netRecovery - refundMin

zone = wipeout ? "wipeout" : lossRatioWorst > 0.5 ? "deep_loss" : "moderate_loss"
```

**Outputs**

- A zone banner: "Danger zone: you could lose everything you paid" / "Heavy loss if you cancel" / "Significant loss
  if you cancel", with the loss amount and refund years.
- Cancellation scenario: expected penalty range (min–max), "calculated on the TOTAL price", amount returned, refund
  period ("about N years, mirroring the period you paid over").
- Transfer scenario: recovery = full documented payments, cash at completion; platform fee for the seller: zero;
  difference in your favour.
- Labels "Estimate" vs "Verified"; disclaimer: indicative, not a substitute for reviewing the contract.
- CTA: request the free exit report.

## Buyer tab

**Inputs:** unit price in the old contract; today's developer price for the same unit (approximate).

**Formula:** `fee = round(0.0125 * contractPrice)`; `gain = currentPrice - contractPrice - fee`;
`gainPct = gain / currentPrice`. Output: "your day-one advantage". CTA: register and see opportunities.

## What it leaves out (input for our calculator design)

- No time value of money: a refund paid over 3–15 years is shown at face value.
- No late-payment penalties, no arrears, no developer transfer/assignment fee (التنازل fees), no maintenance deposit,
  no club/utility fees.
- One penalty range for every developer (the per-developer override exists in code but is not used on the page).
- No VAT on the buyer fee shown, although their country config carries `vatRate: 0.14` for Egypt.
- Values are in code, not editable by staff.
