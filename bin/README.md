# Funnel drop-off report → Slack

`funnel_report.php` pulls the enrollment funnel from **Umami Cloud** and posts a
per-step drop-off digest to one or more **Slack Incoming Webhooks**. Intended to
run from cron a few times a day.

## What it measures

Two funnels are queried and merged (Umami caps a funnel at 8 steps):

1. **Form** — the 7 on-form view steps + `event_submit_success`. This is where
   users abandon the form and is fully actionable UX drop-off.
2. **Conversion** — `event_submit_success` → `thank_you_view` → `call_click`.
   The step from submit → qualified is a *routing outcome* (Equifax < $10k is
   sent to the offerwall), not a UX drop-off, so it's reported separately.

The Slack message shows the per-step form table, the single biggest drop, and
headline numbers: entered, completed form, qualified, offerwall/declined, called.

## Requirements

- PHP CLI with the cURL extension (Cloudways has both).
- Beacon tracking must be working in `assets/js/analytics.js` (sends `text/plain`,
  not `application/json`) — otherwise `event_submit_success` and `call_click`
  never reach Umami and those lines read 0.

## Configuration

Set these in `.env.local` at the project root (gitignored) — or as real env vars:

| Variable | Required | Notes |
|---|---|---|
| `UMAMI_API_KEY` | yes | Umami Cloud API key (`x-umami-api-key`). |
| `SLACK_WEBHOOK_URLS` | yes | One or more webhook URLs, comma-separated. |
| `UMAMI_WEBSITE_ID` | no | Defaults to the Top Debt Options website id. |

```
UMAMI_API_KEY=api_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SLACK_WEBHOOK_URLS=https://hooks.slack.com/services/A/B/C,https://hooks.slack.com/services/D/E/F
```

## Run manually

```bash
php bin/funnel_report.php
```

Exit codes: `0` success · `1` config error · `2` Umami error · `3` Slack post failed.
Progress/errors go to stdout/stderr (captured by the cron redirect below).

## Cron (Cloudways → Application → Cron Job Management)

The report window is computed in `America/New_York` internally, so it's always
"today so far in ET" regardless of the server clock. Cloudways servers run UTC.

**Option A — UTC schedule (simplest).** These UTC hours are 9am/1pm/5pm ET during
EDT. After DST ends (early Nov) change `13,17,21` → `14,18,22`, or use Option B.

```
0 13,17,21 * * * cd /home/master/applications/<APP_DIR>/public_html && php bin/funnel_report.php >> storage/funnel_report.log 2>&1
```

**Option B — pin to ET (no seasonal edit).** If your cron supports `CRON_TZ`:

```
CRON_TZ=America/New_York
0 9,13,17 * * * cd /home/master/applications/<APP_DIR>/public_html && php bin/funnel_report.php >> storage/funnel_report.log 2>&1
```

Replace `<APP_DIR>` with your Cloudways application folder (`pwd` in the app's SSH).

## Notes

- Test traffic (`?test=fmg_true`) is **not** excluded yet — the funnel `filters`
  object is empty. Add a custom-property filter there once confirmed against the
  Umami funnel API if test volume becomes significant.
- The script is CLI-only guarded, so a web request to it returns 403.
