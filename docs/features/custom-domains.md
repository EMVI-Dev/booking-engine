# Storefront addresses and custom domains

_Last reviewed: 2026-10-02_

Every operator gets `{slug}.travelengine.id`. Agency operators (`custom_domain` feature) can also connect their own address, e.g. `tours.yourbrand.com` or `yourbrand.com`.

## Slugs
- **Hosting:** served by the `*.travelengine.id` wildcard on Laravel Cloud, so nothing is added per operator.
- **Registration rules:** the slug is chosen at registration, made URL-safe and kept unique. Reserved platform names (`Operator::RESERVED_SLUGS`, e.g. `admin`, `www`, `api`, `mail`, `demo`) are refused. When a slug is auto-generated, it skips them.
- **Desk:** the operator's desk and login live on the slug host. Login, registration and email-verification links all land there.

## Custom domains: how it works
All logic lives in `App\Services\CustomDomainService`. Brand settings call it, and nothing else writes custom-domain rows.

1. **Connect** (Brand settings → save):
   - The address is normalised: no scheme, path or port, and lower case.
   - It is refused if it is invalid, an IP address, a platform host, used by another operator, or the plan lacks `custom_domain`.
   - The provider registers it and returns the DNS records to show.
2. **Records to add:** `customDomainRecords()` on the brand page lists them.
   - **Laravel Cloud:**
     - A bare domain (`yourbrand.com`, `yourbrand.co.id`) gets an `A @ → <Cloud origin IP>`.
     - A subdomain gets a `CNAME tours → <Cloud hostname>`.
     - Cloud may also ask for certificate (`ssl`) records, which are listed too.
   - **Local (development):** an informational CNAME (subdomain) or ALIAS (bare domain) to the platform domain.
3. **Check:** the "Check" button on the brand page, plus `domains:check` every 5 minutes.
   - **Laravel Cloud:** asks Cloud to verify.
     - `Active` only when the hostname, SSL and origin are all verified. `ssl_issued_at` is then set.
     - `Verifying` while some parts are verified.
     - `Failed` when Cloud reports a failure.
   - **Local:** a check marks the address live straight away (no DNS exists locally).
4. **Routing:**
   - A `Verifying` or `Active` custom domain already resolves to the operator's shop.
   - Storefront links (`Operator::getStorefrontUrl()`) switch to the custom domain only once it is `Active`.
5. **Change or clear:** the old address is removed at the provider first, then deleted here. If the provider is unreachable, the row stays and the operator is asked to retry, so no address is left counting toward the Cloud allowance.
6. **Downgrade:** a plan without `custom_domain` puts the address back to `Pending`, and the shop falls back to the slug.

## Providers
`App\Contracts\CustomDomainProvider` is bound from `config('domains.provider')` (`CUSTOM_DOMAIN_PROVIDER`). Empty means `laravel_cloud` in production and `local` everywhere else:

| Provider | Use | Class |
| :--- | :--- | :--- |
| `laravel_cloud` | Production | `App\Services\CustomDomains\LaravelCloudDomainProvider` (Cloud API: real-time verification, Cloudflare strategy `none`) |
| `local` | Development and tests only; refuses to run in production | `App\Services\CustomDomains\LocalDomainProvider` |

`operator_domains` stores `provider`, `provider_ref` (the Cloud domain id), `dns_records` (normalised records plus Cloud's raw answer) and `last_checked_at`.

## Cost
Each custom domain counts toward the Laravel Cloud plan's custom-domain allowance:

| Cloud plan | Domains included |
| :--- | :--- |
| Starter | 10 |
| Growth | 50 |
| Business | 250 |

Extra domains cost $0.25 per month each. Check Cloud's pricing page for current Cloud prices.

Cloudflare for SaaS was considered. It was set aside for V1 for three reasons:
- Proxying bare domains needs Cloudflare Enterprise.
- Changing the Host header to reach Laravel Cloud needs Cloudflare Enterprise.
- A Worker workaround adds moving parts.

Revisit it past a few hundred custom domains, or when leaving Laravel Cloud.

## UI
Brand settings render exactly the records from `customDomainRecords()` (type, name, value, copy button) and the status badge from the domain row. There are no fixed server addresses in the UI.
