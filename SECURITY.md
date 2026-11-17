# Security policy

This platform stores training evidence used in ISO/IEC 27001 and NIS2 audits, so we take security reports seriously.

## Reporting a vulnerability

Please **do not open a public issue**. Use GitHub's private vulnerability reporting
(*Security → Report a vulnerability* on this repository). We aim to reply within 5 working days.

## Hardening checklist for operators

- Run it on your intranet or VPN only, behind HTTPS.
- Set `APP_DEBUG=false` and `APP_ENV=production`.
- Use Microsoft Entra ID sign-in and set `ENABLE_LOCAL_AUTH=false` once SSO works.
- Change the kiosk PIN from the pack's initial value (Admin → Settings).
- Back up the database and `storage/` regularly; run `php artisan audit:verify` to check the audit trail.
- Keep company packs (which may contain names and internal policies) out of public repositories.
