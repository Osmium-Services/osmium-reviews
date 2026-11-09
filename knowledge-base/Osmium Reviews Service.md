# Osmium Reviews service

Product reviews moved out of core into the service `osmium-reviews` (repo `Osmium-Services/osmium-reviews`, extracted
2026-10-10, v0.6.3<!--v:osmium-reviews-->, category shop). Core lost the reviews admin, the submit handler and its two result pages, the
`productreviews` table and the review join on the product page. See [[Core and Services]].

## What it does

- **Submit endpoint** `POST /api/reviews`: fields `name`, `email`, `pid`, `rating`, `message`, plus the form `token` and the
  `zip` honeypot. The visitor is always sent back to the product page (or the shop) with a one-time `reviewNotice`.
  Spam protection: honeypot, one-use token, a minimum time since the token was issued, and a limit of three reviews per
  IP per hour, plus whatever bot protection is installed, asked through the `form.verify` hook so the service never names
  one ([[Turnstile Service]]). Limits: name 50, email 255, message 2,000 characters.
- **Refusals say nothing about why.** A bot check, the hourly limit and a missing product all give the same message,
  "There was a technical problem sending your review. Please try again later", so a bot learns nothing about the rules.
  Problems a real visitor can fix (name, email, rating, length) keep their own messages.
- **Moderation** under Shop > Reviews (top-level menu item): list with a bin (soft delete, restore, permanent delete,
  purge), an edit page, and Settings > Reviews for the email addresses told about a new review (nobody is emailed until
  one is saved). The list uses the shared [[Admin Framework]] `ListView`.
- **Product page data** through the `product.page.data` hook: `reviews`, `reviewCount`, `ratingAverage` and `reviewNotice`
  (sent or failed, shown once). The reviewer's email is **never** given to the theme.
- **Dashboard card** "Pending reviews" through `dashboard.widgets`.
- **New-review email** to the addresses in Settings > Reviews, in the site's email styling (logo header, green accents,
  stars, the review in full). It deliberately carries **no link to the admin**; the team signs in to approve. The site
  name and logo are passed in (`ReviewsHooks::brand()`), so the service stays site-agnostic.
- **Sample email** "New review notification (sample data)" in Settings > Company > Email, through `email.samples`, under an
  "Osmium Reviews" heading.
- Table `product_reviews`; settings file `app/config/services/osmium-reviews.json.php`.

## Rules set by Gavin

- **Publishing is human only.** A review arrives unpublished and is published only from its edit page; there is no Publish in
  the list menu, so nobody approves a bot review unread.
- **Verified purchase is a badge, nothing more.** The email matches a paid or dispatched, non-test order for that product.
  It never auto-publishes, because a verified buyer can still write a wrong or abusive review. Kit-part-only purchases do
  not count as verified (known limit).
- Reviews are stored as plain text and escaped on output.

## Theme contract

Point the review form at `/api/reviews`; read the four keys from `$product` and show `reviewNotice` once. Put the
`form.widget` output inside the form (pestgo2 collects it into `$template['reviewFormProtection']` in `product-full.php`).
pestgo2 shows `reviewNotice` in a centred modal, the `notice-modal.phtml` block, which also switches to the Reviews tab: the
inline text sits in an inactive tab (`display: none`) and was never seen after the redirect. With the service
absent the keys are missing, so the theme must tolerate that. Details on [[Core and Services]].

## Decisions about emailing the visitor (2026-10-15)

- **No confirmation email on submit.** The form accepts any address, so it would let anyone make the site email a stranger,
  and bounces and spam complaints would hurt the deliverability of the order and password-reset email that share the sender.
  The "Thank you" modal already tells the visitor it is awaiting approval.
- **No email-verification step.** Review engagement is already low, and extra friction would probably kill the feature.
- **Approve-time email ("your review is now live") considered and parked.** Safe, because only human-approved reviews
  would send, but not needed yet.

## Nice to have (not built)

- **Ask for a review after dispatch.** One email a week or two after an order is dispatched, to buyers only, linking to the
  product page. Low engagement is a "nobody is asked" problem more than a form problem, and sending only to real order
  addresses carries no abuse risk. Would want a switch in Settings > Reviews, a styled template like the new-review email
  (`ReviewMail`), a sample in the Email test tool (`email.samples` hook), and a once-per-order guard.

## Known issues

`SuperGlobal::delete()` worked on a copy until 2026-10-10 and removed nothing, so core's form handlers never cleared the
session token; the service uses `unset($_SESSION[...])` and core is fixed (`4b3d48b`).

## Not tested

A real theme rendering the keys, and a verified-purchase review from a live order.
