---
paths:
  - 'docs/**'
---

# Docs

## Docs are grouped and kept current
docs/ has product/, commercial/, features/, engineering/ and audits/. The single entry point is docs/product/overview.md: it summarises the product and its Documentation map lists every doc (docs/README.md and the root README only point to it). A new or renamed doc gets a row in that map in the same change. A change to plans, money, hosting, integrations or a feature's behaviour updates its doc in the same change. One home per fact: prices and limits only in commercial/plans-and-pricing.md, money rules only in commercial/money-rules-and-settlement.md, hosting only in engineering/stack-and-hosting.md; other docs link. No test counts or "100% passing" in docs. Audits are dated snapshots: add a new dated file, move superseded ones to audits/archive/ with a banner. Bump "Last reviewed" when a doc is checked against code.
