---
paths:
  - app/Services/OperatorActivitySlackNotifier.php
---

# Services

## Slack operator alerts use Block Kit blocks
Operator activity Slack webhooks use standard Block Kit layout blocks (header for title, context for `Slug · {slug}`, section for bold operator name plus a monospace ``` box with padded `Label : Value` rows, context for timestamp, actions for URL buttons Open Admin / Open Storefront). Do not use an unsupported card block type. Event-useful fields only. Omit icon when APP_URL is a local Herd host. No emoji in titles. Keep fallback text for notifications list.

