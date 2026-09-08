---
paths:
  - app/Console/Commands/SendPostTripReviewRequestsCommand.php
---

# Commands

## Skip review mail without a URL
Post-trip review mail is not sent unless Operator::getReviewUrl() is a non-empty URL, even on Growth/Agency. Do not mark review_request_sent_at when the URL is missing so a later save can still send.
