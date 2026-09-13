---
paths:
  - app/Services/OperatorActivitySlackNotifier.php
---

# Services

## Slack operator alerts use card blocks
Operator activity Slack webhooks use a single card block that wraps everything: title, subtitle `Slug · {slug}`, body = bold operator name plus a monospace ``` box with padded `Label : Value` rows (split Owner/Email on registration; amounts as `Rp 1.234.567`), subtext timestamp, URL buttons Open Admin / Open Storefront. Event-useful fields only. Omit icon when APP_URL is a local Herd host. No emoji in titles. Keep fallback text for notifications list.
