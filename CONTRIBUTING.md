# Contributing

Thanks for helping! Issues and pull requests are welcome.

## Ground rules

- **Nothing company-specific in the engine.** Names, colours, policies, questions and people belong in a *company pack* (see [docs/PACK_GUIDE.md](docs/PACK_GUIDE.md)). If you need a new kind of setting, add it to the pack format.
- **Every user-facing string goes through `lang/`.** Add it to both `lang/el` and `lang/en`.
- **No external CDNs.** The app must work on an isolated network; bundle assets with Vite.
- **Evidence is append-only.** Never update or delete acknowledgments, completions, attempts or audit logs in normal flows.

## Development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan pack:install packs/demo
npm run build && php artisan serve
```

Before opening a pull request:

```bash
vendor/bin/pint
php artisan test
```
