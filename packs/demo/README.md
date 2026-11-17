# Helios Foods demo pack

This is the public demo company pack for the security-awareness training platform
(ISO/IEC 27001 / NIS2). It contains a complete, bilingual (Greek / English) example:
tracks, groups, departments, nine policies, four training modules, two quiz banks and
a sample roster.

**Everything here is fictional.** "Helios Foods S.A.", its sites (Head Office, North
Plant, South DC) and every person in `people.csv` are invented for demonstration only.
Any resemblance to real companies or people is coincidental. The `.example` email
domain cannot receive mail.

## Install

```
php artisan pack:install packs/demo --validate-only   # check only
php artisan pack:install packs/demo                   # load it
```

## Demo logins

All four accounts use the password `password`.

| Role       | Email                              |
|------------|------------------------------------|
| Admin      | admin@helios-foods.example         |
| HR auditor | hr@helios-foods.example            |
| Supervisor | supervisor@helios-foods.example    |
| Employee   | employee@helios-foods.example      |

Kiosk PIN: `2468`. Shop-floor workers have no email; in kiosk mode they are
identified by their employee code (10001 to 10012).

Do not use these credentials or this PIN outside a demo environment.
