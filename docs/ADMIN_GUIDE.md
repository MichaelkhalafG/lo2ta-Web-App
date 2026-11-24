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
