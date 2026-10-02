---
paths:
  - 'app/**/*.php'
---

# App

## No emoji in mail subjects or guest messages
Mail subjects, WhatsApp templates, Google Calendar event text, and dashboard announcement titles must not use emoji.

## One client class per third-party service
Every outside API has exactly one client in app/Services/Integrations (DokuClient, SlackClient, GooglePlacesClient, LaravelCloudClient). Only clients call Http:: for that service; business services (DokuPaymentService, OperatorActivitySlackNotifier, GooglePlacesService, CustomDomainService) call the client. Add a new client rather than calling a vendor API from a service, component or job.

## Operator website addresses go through CustomDomainService
Connect, check and disconnect Agency custom domains only with CustomDomainService. The provider is bound from config('domains.provider'): laravel_cloud (production default, Cloud API) or local (development and tests; refuses production). Do not write operator_domains rows for custom domains directly. domains:check is the scheduled re-check.

## Media writes never send an ACL to object storage
MediaStore only sets visibility on local disks. Cloudflare R2 has no ACLs and serves public files from the bucket's public domain (R2_URL). Keep the r2 disk without a visibility key and with throw on.

## No env() outside config
Production on Laravel Cloud caches config, so env() returns nothing outside config/ files (seeders and bootstrap included). Read settings through config(). Trusted proxies come from config('app.trusted_proxies') (TRUSTED_PROXIES, * on Cloud) and are applied in AppServiceProvider.

## Secrets live in Laravel Cloud, read through config
Keys, passwords and tokens are environment variables set in Laravel Cloud (or a developer's own git-ignored .env). Read them only through config/*.php with no default value; never hardcode them in config or code, and never copy them into the database (platform_settings). .env.example keeps a GENERAL block and an empty SECRETS block. SecretsHygieneTest enforces this.
