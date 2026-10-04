# TravelEngine docs

TravelEngine is the booking product. **EMVI Technologies** is the company. Agent and tooling files (`AGENTS.md`, `CLAUDE.md`, `.ai/rules`) stay at the repo root.

Docs are grouped by who reads them. Start with **Product**. If a doc and the code disagree, the code wins: fix the doc in the same change.

## Product: what we build and for whom
| Doc | What |
| --- | --- |
| [product/overview.md](product/overview.md) | Mission, audience, problems solved, business model in one page |
| [product/scope-and-features.md](product/scope-and-features.md) | V1 product rules and every feature that ships (the main spec) |
| [product/roadmap-v2.md](product/roadmap-v2.md) | Not in the product yet. Nothing here ships until this file says so |

## Commercial: plans and money
| Doc | What |
| --- | --- |
| [commercial/plans-and-pricing.md](commercial/plans-and-pricing.md) | Plans, prices, limits, guest service fee, why the model works |
| [commercial/money-rules-and-settlement.md](commercial/money-rules-and-settlement.md) | Canonical money rules: ledger, escrow, payouts, refunds, disputes, fees |

## Features: how specific areas work
| Doc | What |
| --- | --- |
| [features/operator-portal-coupons-billing.md](features/operator-portal-coupons-billing.md) | Coupons and reports, login handoff, billing and invoices, guest CRM |
| [features/vendor-dispatch.md](features/vendor-dispatch.md) | Vendors (suppliers) and automatic booking dispatch |
| [features/custom-domains.md](features/custom-domains.md) | Slug storefronts and Agency custom domains on Laravel Cloud |
| [features/storefront-website.md](features/storefront-website.md) | Website extras: gallery, FAQ, contact and group enquiries, cookie notice, Google listing (with the UI brief for Antigravity) |

## Engineering: how it is built and run
| Doc | What |
| --- | --- |
| [engineering/stack-and-hosting.md](engineering/stack-and-hosting.md) | Stack, hosts, Laravel Cloud hosting, queues, scheduler, time zone, where code lives |
| [engineering/integrations.md](engineering/integrations.md) | Every third-party service, its client class, settings and failure behaviour |
| [engineering/production-launch.md](engineering/production-launch.md) | Fresh production checklist: environment variables, Cloud settings, first deploy, smoke test |

## Audits: point-in-time reviews (not specs)
| Doc | What |
| --- | --- |
| [audits/2026-10-storefront-and-landing-audit.md](audits/2026-10-storefront-and-landing-audit.md) | Security pass, guest storefront and landing page audit with fixes and the Antigravity UI list (Oct 2026) |
| [audits/2026-10-backend-audit.md](audits/2026-10-backend-audit.md) | Backend, DRY, security, admin and MVP audit with fix log (Oct 2026) |
| [audits/2026-09-storefront-pre-prod-audit.md](audits/2026-09-storefront-pre-prod-audit.md) | Guest storefront pre-production review (Sep 2026) |
| [audits/archive/](audits/archive/) | Superseded audits, kept for history only. Do not build from them |

## Keeping docs current
- **Same change, same PR.** A change to plans, money, hosting, integrations or a feature's behaviour updates its doc here.
- **One home per fact.** Prices and limits live in `commercial/plans-and-pricing.md`. Money rules live in `commercial/money-rules-and-settlement.md`. Hosting lives in `engineering/stack-and-hosting.md`. Other docs link to these instead of copying them.
- **No numbers that rot.** Do not write test counts, "100% passing" or line counts in docs.
- **Specs vs audits.** Specs describe how things work now. Audits are dated snapshots: add a new dated file rather than rewriting an old one, and move superseded audits to `audits/archive/` with a banner on top.
- Each doc starts with a `Last reviewed:` date. Update it when you check the doc against the code.
