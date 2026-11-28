# Admin guide (staff dashboard)

For the Lokta team who review units, documents, requests and deals. It grows with the dashboard (PLAN phases 7 and
9); each rule here also has its reason, so it is not dropped by accident.

## Signing in

- After 20 wrong passwords within an hour on one staff e-mail (from any address), sign-in for that e-mail stops for
  15 minutes, even with the right password, and the staff member gets an e-mail. The three numbers are the same
  settings as for the public site (`rl_login_account`, `login_window_minutes`, `login_lock_minutes`). There is no
  way to lift it early: wait. If the e-mail arrives and it was not you, tell the administrator so the password is
  changed. Why: staff accounts can open national ID copies, so guessing a staff password must not go on without end.

## Documents uploaded by sellers

- **Open uploaded documents only in an up-to-date viewer, for example a current Chrome.** There is no antivirus on
  shared hosting. The server refuses active PDFs (scripts, attachments) and rebuilds every image, but a file crafted
  against a bug in an old PDF reader could still harm the computer that opens it (Michael, 2026-11-18).
- A document flagged **encrypted** opens without a password but restricts printing or copying. Check that it is
  readable and matches the unit; ask the seller for an unprotected copy if it does not.
- Every opening of a document is recorded in the activity log, national ID copies included.
- Photos: in review, check every photo for building numbers, signs or documents, and hide any that shows them
  (PLAN 2.5). Hiding removes the public copy at once; the action is logged.

## Calculator settings (admin only)

- Each value (cancellation, refund, late-payment penalty, transfer fee, maintenance deposit, discount rate) has its
  settings, the date it was checked, an internal note, its sources and a **Confirmed by the client** switch. Turn the
  switch on only when the client has confirmed the value; off marks it provisional, and the site and the exit report
  then call the figures an estimate. Why: the client is responsible for these figures (agreement clause 7).
- Sources and notes are internal. The public site shows each value in words and "checked <month year>", never a
  source, link or note (Michael, J10). Re-check every value after 6 months ("due for a re-check" badge); after 12 the
  site marks it "under review" until the date checked is updated.
- The values confirmed on 2026-11-25: cancellation 15% of the total price; refund in one payment; transfer fee a fixed
  EGP 50,000 (a seller's own figure in step 3, a percentage or an amount, replaces it for that unit); late-payment
  penalty 1% of the overdue installments, on both the cancellation and the transfer; the maintenance deposit is
  "usually 8% to 10% of the unit price, moves to the buyer with the transfer" (its range is the two deposit
  settings). A low and high end that are equal make one value; different ends show a range.
- **Developer policies:** add one only from the developer's own published terms (its website or published contract),
  with the link and dates. Press reports and customer accounts do not count. A policy is used only when **Approved by
  the client** is on (Michael, J3), and then only for that developer's units in the seller wizard and the seller's
  exit report. The public calculator always uses the market values and never names a developer (Michael, K6).
- **Display:** the 25% deep-loss line (confirmed by Michael) is a display threshold, not a market value; the
  present-value line is off
  (decided 2026-11-17) and uses the discount rate only when switched on.
- Every change is in the activity log.

## Reports and indicators (admin only)

- Only figures the client supplies, each with its date and an internal source. Nothing shows on the reports page
  until an indicator is published (Michael, J9). Our own listing statistics come later, and only for groups of at
  least 30 cases.

## Site texts

- Every text on the site is a site text, in Arabic and English. Its state: **written by us, the client reviews**,
  **awaiting legal review**, or **the client's text**. The overview counts them and lists every text awaiting legal
  review (privacy policy, terms, the seller notices and declarations, the ID paragraph, the fee consent, the two
  disclaimers and the FAQ answer on cancelling). Why: those must not go live before Eng. Enas reviews them (Michael,
  K7). The About page is the client's own text: change it only as the client asks.
- A text body is plain text. A blank line starts a new paragraph; a line starting "## " is a heading; lines starting
  "1. " or "- " make a list; "**words:**" at the start of a paragraph or list item is shown in bold. Nothing else is
  formatted, and no HTML is ever read.
- Changing the fee consent or a declaration starts a new version: requests and submits after the change carry it.

## Staff (admin only) and your own account

- **Invite** a new staff member from Staff: name, e-mail, role and the language of their e-mails. They receive a link
  to choose their own password; it works once, for 60 minutes. If it runs out, use **Send a password link**. Why: no
  one ever types or sees another person's password (Michael, G13 and G14).
- **Forgotten password:** an admin sends a password link from Staff. The old password keeps working until the link is
  used; the new password then signs that person out on every device.
- **Switch off** an account instead of deleting it: the activity log keeps the name. Switching off signs the person out
  at once and stops any password link. No one can switch off or demote themselves, and the last active admin cannot be
  switched off or demoted.
- **Roles:** admin does everything, including settings, texts, staff, commissions and the activity log; reviewer works
  on units, documents, requests and deals.
- **My account** (the menu under your name): change your own password with the current one. Five wrong tries pause the
  form for ten minutes. Your other sessions are signed out; this one stays.
- The first admin of a new server is still created on the server with `php artisan admin:create` (password at a hidden
  prompt).
