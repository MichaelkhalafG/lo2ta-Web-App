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
- A **draft's** files (a unit the seller has not sent yet) answer "not found" to every staff role, admins included
  (PLAN 8.2, Q26). They open once the seller sends the unit.
- **National ID copies** (G2): both roles open them while the unit is under review (sent, or back with the seller) or
  in an open deal; otherwise only an admin. A refused opening is logged too.
- Photos: in review, check every photo for building numbers, signs or documents, and hide any that shows them
  (PLAN 2.5). Hiding removes the public copy at once; the action is logged.

## Unit review (both roles; part 7.2)

- **Unit review** lists every unit a seller has sent (drafts stay with the seller). Filter by state; "Withdrawal
  requests" shows the reserved units whose seller asked to withdraw. Only an admin approves or declines a withdrawal
  request, since approving it cancels the deal and its commission (PLAN 8.2, Q24); a reviewer sees the request as
  waiting for an admin.
- Open a unit: check the figures against the documents. **Approve** each document, or **Ask for a clearer copy** with a
  note the seller reads.
- **Map to the lists** when the seller typed an "other" developer, compound or area: pick the managed entries. What the
  seller typed stays private. If the entry is missing, an admin adds it first. A mapping that makes the unit match
  another active contract is refused and names that unit.
- **Correct figures** when the documents show a different value: write the reason; the seller sees each change and its
  reason in My account (G3). After publishing only the market estimate is corrected here.
- **Publish** is open once nothing is listed under "Before publishing": places mapped (the area has a code), every
  required document approved, none waiting for review or a clearer copy, one photo not hidden. Publishing forms the
  unit code, makes the photos public and e-mails the seller the exit report after review (G19).
- **Publish as verified** and turning "verified" on or off are for an admin only, with a reason (G1). Who verified and
  when are never shown on the site.
- **Request changes** sends the unit back with a reason, optional notes per field, and the documents marked for a
  clearer copy. **Reject** needs a reason and cannot be undone from the dashboard; the contract can then be listed again.
- **Hide** a photo that shows a building number, a sign or a document (reason required); **Show again** puts it back.
- **Duplicate attempts** lists every blocked attempt to send a contract another active unit holds, and every contract
  file already on another unit. An **admin** can **Override the block** on one blocked attempt, with a written reason
  (PLAN 8.2, Q23): that unit may then be sent although the matching unit holds the same contract. The override covers
  those two units only, so any other listing of the contract is still blocked; it is logged with who and when, both
  roles see it on the unit screen, and it is not lifted from the dashboard. The seller is never told which unit
  matched: tell them in the conversation they started (the block message asks them to contact us).

## After publishing (both roles; part 7.3)

- **Figure changes to approve:** a seller changed figures on a live unit; the listing keeps the earlier ones until you
  decide. **Approve** puts the new figures live together (the seller sees it in My account); **Reject** needs a
  reason, which the seller gets by e-mail. A change that no longer fits the live unit cannot be approved: reject it.
- **Check after edit:** the seller changed the description or the photos, and that was live at once. Read the change;
  **Put this description back** if the new one breaks the rules (reason required); hide a bad photo on the unit
  screen; then **Mark as checked**.
- **Documents added after publishing** wait there until you review them on the unit screen.

## Sellers, buyers, budgets, matching and requests (part 7.4)

- **Sellers and buyers** (both roles): everyone with an account, with their units, budget, requests and history.
- **Suspend** (admin only, reason required): the person is signed out everywhere and cannot sign in; their units leave
  the site with their state kept. **Lift the suspension** (reason required) puts everything back as it was (G4).
- **Buyer budgets**: what each buyer registered. **Matching** lists, for a unit, the buyers whose budget fits, or, for
  a buyer, the units that fit. Must fit: the cash (the amount to the owner, or the lowest amount they accept, plus the
  1%), the installment as a monthly figure, the type and the area (an area includes its districts). Compound,
  bedrooms, delivery and target price put the best first.
- **Unit requests**: assign a request, keep a team note (the buyer never sees it), mark it contacted, or close it with
  a reason. Closing sends nothing to the buyer.

## Deals and commissions (part 7.5)

- **Open a deal** from a unit request (Open a deal) or from Matching on a unit. Only one deal can be open on a unit;
  other buyers wait as requests. Both parties are e-mailed.
- **Move** a deal one stage at a time (an optional note), or **back** one stage (a note is required). **Cancel** it at
  any open stage with a reason: the unit returns to the market and both parties are told. Completed is final: the unit
  is sold and the other open requests on it close as "unit sold", each buyer told by e-mail.
- The unit is **reserved** from "seller accepted"; a step back before that, or a cancellation, puts it back on the site.
- **Commissions** (admin only): one record per deal, fixed when it opens (the amount the buyer agreed to on the
  request, or the rate of that day). Mark it invoiced, then collected, with a date and a reference each, or waive it
  with a reason. There is no invoice file and no part-payment.

## Inboxes, lists, site texts and exports (part 7.6)

- **Contact messages** and **Partnership requests** (both roles): each message also reached the team mailbox in full.
  Read it, keep a team note, and **mark it handled**. Handled messages are deleted automatically after 12 months (a
  setting); messages never marked handled are kept.
- **Areas, Compounds, Developers**: both roles see the lists; an admin adds and edits. Entries are hidden, never
  deleted. An area's code is fixed once a sent unit uses it. A compound's intro shows on its public page once both
  languages are written.
- **Site texts** (admin): every public text in Arabic and English with its state (placeholder, ours, awaiting legal
  review, the client's). Plain text: "## " starts a heading, "1. " or "- " a list item, "**words:**" a bold lead.
- **Export CSV** (admin, on Unit review, Deals, Commissions, Unit requests and Sellers and buyers): the file holds what
  the screen shows, with every data field, never document files. Each export is recorded in the activity log.

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
