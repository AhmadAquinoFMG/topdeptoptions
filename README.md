# Top Debt Options — vanilla PHP replica

A faithful, functional rebuild of the topdebtoptions.com debt-relief lead funnel using
plain PHP (no framework), HTML, CSS, and vanilla JavaScript.

## Features

- **7-step qualification form** with progress bar, animated step transitions, and
  client-side validation (debt amount → payment status → address → DOB/18+ check →
  name + credit authorization → email → phone with simulated verification code).
- **Value-prop sidebar** and Google/Yelp review badges, matching the original layout.
- **Server-side validation** mirroring the client checks, with CSRF protection.
- **Lead storage** to `storage/leads.jsonl` on successful submission.
- **Legal pages**: Privacy Policy, Our Partners, Terms of Use.
- Responsive, single-column on mobile.

## Run locally

```bash
php -S 127.0.0.1:8000
```

Then open http://127.0.0.1:8000

> Requires PHP 8.0+. Submitted leads are appended to `storage/leads.jsonl`.

## Structure

```
index.php            Homepage with the multi-step form
submit.php           Form handler: validates, stores lead, shows result
includes/
  config.php         Constants, escaping helper, CSRF helpers
  header.php         Shared <head> + site header
  footer.php         Footer nav + legal disclaimer
assets/css/style.css Theme, layout, form, sidebar styling
assets/js/app.js     Step navigation, validation, progress bar
privacy-policy.php   /  our-partners.php  /  terms-of-use.php
storage/leads.jsonl  Captured submissions (one JSON object per line)
```

## Notes

All copy and legal text is original placeholder content. Replace the disclaimer,
contact phone (`includes/header.php`), email (`includes/config.php`), and review
numbers with your real values before going live. To deliver leads to a CRM or partner
API instead of a flat file, replace the `file_put_contents` block in `submit.php`.
