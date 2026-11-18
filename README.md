# edu-cate-ees

**Open-source security awareness, policy acknowledgment and training-evidence platform for ISO/IEC 27001 and NIS2.**

[Ελληνικά](README.el.md) · [Company pack guide](docs/PACK_GUIDE.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md)

Employees go through three short steps: acknowledge the company's policies, read bite-sized training cards, and pass a short knowledge check. When they pass, they download a certificate. Every step is timestamped, tied to an IP address and written to an append-only audit log, so you have the evidence auditors ask for (ISO/IEC 27001 clauses 7.2 and 7.3 and control A.6.3; NIS2 Article 20(2) and Article 21).

Everything company-specific (name, logo, colours, policies, modules, questions, groups, people) lives in a **company pack**: a folder of simple YAML files. The engine stays generic, so any organisation can run it.

## Features

- **Three-step employee journey.** Policies (expandable key rules, link to the full document, signed declaration) → slide-card modules → randomised quiz with instant feedback, explanations and unlimited retries → PDF certificate.
- **Supervised kiosk mode** for production and warehouse staff without email. A supervisor unlocks a shared tablet with a PIN. Each worker finds their name and enters their own employee code, then reads the rules, answers the questions and signs off. The tablet stays locked to the kiosk until the PIN is entered again.
- **Admin and HR dashboard.** KPIs, progress per training group with org-chart colours, a filterable roster and reminders.
- **Excel roster import** with a dry-run preview. It reads the department colour from each cell's fill and maps tracks from a "training type" column. Re-importing never loses progress.
- **Audit exports.** A three-sheet compliance workbook, a printable A4 attendance sheet with a signature column, and the full audit log.
- **Live sessions.** Record classroom or Teams sessions and who attended (digital, kiosk or paper sheet).
- **Training cycles (campaigns).** Start a new cycle each year; history is kept. A new policy version triggers re-acknowledgment of that policy only.
- **In-browser content editor** for policies, modules and questions, with export back to a pack.
- **Microsoft Entra ID (Azure AD) single sign-on**, plus optional email and password sign-in.
- **Greek and English** interface; packs can provide content in both.
- **Intranet-ready.** No CDNs, with all fonts, icons and scripts bundled. Runs on SQLite or MySQL 8.
- **Tamper-evident audit log.** Each entry is hash-chained to the previous one, and database triggers block updates and deletes.

## Quick start (local, about 2 minutes)

Requirements: PHP 8.3+ with `intl`, `zip`, `xml`, `xmlreader`, `gd`, `mbstring`, `pdo_sqlite` (or `pdo_mysql`); Composer; Node 20+. On Ubuntu: `sudo apt install php8.3-{intl,zip,xml,gd,mbstring,sqlite3,mysql}`. **Admin → Settings → System check** lists anything missing.

```bash
git clone https://github.com/SilenceWF/edu-cate-ees.git && cd edu-cate-ees
```

```bash
composer install && npm install && npm run build
```

```bash
cp .env.example .env && php artisan key:generate && touch database/database.sqlite && php artisan migrate
```

```bash
php artisan pack:install packs/demo
```

```bash
php artisan serve
```

Open http://localhost:8000 and sign in with one of the demo accounts (password `password`):

| Role | Email |
| --- | --- |
| Administrator | `admin@helios-foods.example` |
| HR / auditor | `hr@helios-foods.example` |
| Supervisor (can open the kiosk, PIN `2468`) | `supervisor@helios-foods.example` |
| Employee | `employee@helios-foods.example` |

The demo company and every person in it are fictional.

## Run with Docker (app + MySQL + Nginx)

```bash
cp .env.example .env
```

Edit `.env`: set `APP_URL`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, and set `APP_KEY` to the output of `docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));"`. Then:

```bash
docker compose up -d --build
```

```bash
docker compose exec app php artisan pack:install packs/demo
```

The app is now on port `8080` (change `HTTP_PORT` in `.env`). Put your own pack in `packs/` (mounted read-only into the container) or upload it as a `.zip` in **Admin → Settings**.

## Set it up for your company

1. Copy `packs/_skeleton` to `packs/<your-company>` and fill it in. [docs/PACK_GUIDE.md](docs/PACK_GUIDE.md) explains every file.
2. Check it: `php artisan pack:install packs/<your-company> --validate-only`.
3. Install it: `php artisan pack:install packs/<your-company>`. On a fresh install you can also upload the zipped folder on the first-run setup screen.
4. Import your people from Excel (**Admin → Excel import**) or list them in the pack's `people.csv`.

Keep your pack in a **private** repository: `packs/*` is git-ignored here, apart from the skeleton and the demo.

## Microsoft Entra ID sign-in

1. In the Entra admin centre, go to **App registrations → New registration**.
2. Add a redirect URI of type *Web*: `https://<your-host>/auth/microsoft/callback`.
3. Under **Certificates & secrets**, create a client secret.
4. Under **API permissions**, keep `User.Read` (delegated).
5. Put the values in `.env`:

```dotenv
MICROSOFT_CLIENT_ID=<Application (client) ID>
MICROSOFT_CLIENT_SECRET=<secret value>
MICROSOFT_TENANT_ID=<Directory (tenant) ID>
```

People sign in only if their email is on the roster. Once SSO works, set `ENABLE_LOCAL_AUTH=false`.

## Where is my data? Backups

| What | Where |
| --- | --- |
| All records (people, acknowledgments, quiz attempts, sessions, audit log, settings) | The database in `.env`. With `DB_CONNECTION=sqlite` that is the single file `database/database.sqlite`; with MySQL it is the configured database. **Admin → Settings → System check** shows which one is in use. |
| Uploaded files (logo, policy PDFs) | `storage/app/pack/` |
| Application log (errors) | `storage/logs/laravel.log` |

Back up the database (or the `.sqlite` file) **and** `storage/app/` together. Copy the SQLite file while the site is idle, or use `sqlite3 database/database.sqlite ".backup backup.sqlite"`.

Make changes through the admin screens or `php artisan tinker` rather than by editing the database directly. Opening the SQLite file read-only with a tool such as *DB Browser for SQLite* is fine. Keep in mind:

- `audit_logs` cannot be changed or deleted, by design (database triggers block it), and `php artisan audit:verify` detects tampering.
- Acknowledgments, completions and quiz attempts are audit evidence. Editing them by hand undermines that evidence; correct mistakes with a new record instead.

## Useful commands

| Command | What it does |
| --- | --- |
| `php artisan pack:install <folder or zip>` | Install or update a company pack (idempotent) |
| `php artisan pack:install <path> --validate-only` | Check a pack and list every problem |
| `php artisan pack:export` | Write the current content (as edited in the UI) to a pack zip |
| `php artisan user:password <email>` | Set a local-login password |
| `php artisan audit:verify` | Verify the audit log hash chain |
| `php artisan test` | Run the test suite |

## Tech

Laravel 13, PHP 8.3, Blade with Alpine.js, Tailwind CSS 4 via Vite, Dompdf, PhpSpreadsheet and Laravel Socialite.

## License

[Apache License 2.0](LICENSE). See [NOTICE](NOTICE) for third-party components.
