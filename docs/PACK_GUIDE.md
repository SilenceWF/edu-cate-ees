# Company pack guide

A **company pack** is a folder that tells the platform everything about one organisation: branding, policies, training content, quiz questions, groups and (optionally) people. The engine contains nothing company-specific.

> Ελληνικά: δείτε την ενότητα [«Σύντομα στα ελληνικά»](#σύντομα-στα-ελληνικά) στο τέλος.

## 1. Start from the skeleton

```bash
cp -r packs/_skeleton packs/my-company
```

Every file in `packs/_skeleton` is commented. `packs/demo` is a complete worked example (a fictional food manufacturer, in Greek and English).

## 2. Folder layout

```
packs/my-company/
├── pack.yaml            # company details, languages, branding, settings   (required)
├── tracks.yaml          # learning paths                                    (required)
├── groups.yaml          # training groups                                   (required)
├── departments.yaml     # departments and org-chart colours                 (optional)
├── policies/*.yaml      # one file per policy                               (required)
├── modules/*.yaml       # one file per training module                      (required)
├── quizzes/<track>.yaml # question bank per track                           (required)
├── people.csv           # roster                                            (optional)
├── branding/logo.svg    # logo, svg or png                                  (optional)
└── documents/*.pdf      # full policy documents                             (optional)
```

## 3. Concepts

- **Track**: what a person must do, meaning which modules, how many quiz questions, whether policies are required, and whether it can be done in kiosk mode. Typical tracks are `office_extensive`, `shopfloor_basic` and `it_specialized`. A track can reuse another track's question bank with `quiz_from`.
- **Group**: how you organise people for reporting and live sessions (e.g. "Group 7 - Warehouse"). Each group has a default track.
- **Department**: org-chart unit with a colour, shown as a badge.
- **Campaign**: one training cycle. The pack creates the first; admins start later ones.

## 4. Text in one or several languages

Any text people read can be a plain string (in `default_locale`) or a map per language:

```yaml
title: Clean Desk Policy
# or
title:
  el: Πολιτική Καθαρού Γραφείου
  en: Clean Desk Policy
```

## 5. File reference

### `pack.yaml`

| Key | Meaning |
| --- | --- |
| `slug` | Short id, lowercase, no spaces |
| `company.name` / `legal_name` | Header name / name on certificates and attendance sheets |
| `company.frameworks` | Reference line printed on PDFs |
| `company.admin_role_label` | What your admin role is called (e.g. "Information Security Officer") |
| `company.trainer` | Default trainer for live sessions |
| `company.signatory.name` / `.title` | Signature block on the certificate |
| `company.contact_email` | Shown to people who need help |
| `locales`, `default_locale`, `timezone` | Interface languages; timezone for displaying times (stored in UTC) |
| `branding.logo` | Path inside the pack |
| `branding.favicon` | Browser-tab icon (ico, png or svg), optional; the logo is used if absent |
| `branding.colors.primary`, `primary_dark`, `header`, `on_primary` | 6-digit hex colours |
| `quiz.pass_threshold` | Pass mark in percent (initial value; editable in Settings) |
| `kiosk.pin` | Initial kiosk PIN, quoted (`"2468"`). Change it in Settings |
| `declaration` | Statement signed when accepting policies |
| `campaign.name`, `campaign.starts_on` | First training cycle |
| `locations` | Suggested locations for live sessions |
| `import.default_track`, `import.track_rules` | How the Excel importer picks a track from column D |

### `tracks.yaml`

```yaml
- key: shopfloor_basic
  name: { el: Παραγωγή & Αποθήκη, en: Production & Warehouse }
  modules: [shopfloor-rules, shopfloor-emergency]
  quiz_questions: 4
  policies: false
  kiosk: true
```

### `policies/NN-name.yaml`

```yaml
code: POL-07
title: Clean Desk & Clear Screen Policy
version: "1"              # raise it to ask everyone to acknowledge again
effective_date: 2026-01-01
document: documents/pol-07.pdf   # or an https:// link (e.g. OneDrive). Optional
mandatory_all: true       # or false + tracks: [office_extensive]
key_rules:
  - Lock your screen (Windows + L) whenever you leave your desk.
  - Collect printouts immediately; use secure print.
  - Shred confidential paper.
```

### `modules/NN-name.yaml`

```yaml
key: cyber-everyday
title: Cybersecurity in everyday work
minutes: 10
slides:
  - title: Phishing & BEC
    icon: mail-warning          # any icon name from https://lucide.dev/icons
    summary: Most attacks start with an email.
    bullets:
      - Check the sender's domain.
      - Confirm bank-detail changes by phone.
    callout: When in doubt, report it.
```

### `quizzes/<track-key>.yaml`

```yaml
- question: You receive an email asking you to change a supplier's IBAN. What do you do?
  options:
    A: Pay immediately.
    B: Reply to the same email.
    C: Confirm by phone on a number you already know.
    D: Forward it to colleagues.
  correct: C
  explanation: Always verify through a different channel.
```

Write more questions than `quiz_questions`; each attempt draws a random set.

### `people.csv`

```
group,department,name,note,email,employee_code,role,password
group-6,IT - Head Office,Jane Doe,,jane@example.com,,admin,
group-7,Warehouse - DC,John Smith,Basic,,10001,employee,
```

`group` uses keys from `groups.yaml`. `role` is one of `employee`, `supervisor`, `hr_auditor`, `admin`. `password` only matters for local sign-in. People are matched by email (then by name), so re-installing updates them instead of duplicating them. Most companies leave this file out and use the Excel import instead.

## 6. Validate, install, update

```bash
php artisan pack:install packs/my-company --validate-only
```

```bash
php artisan pack:install packs/my-company
```

Installing again later updates everything in place. Training evidence (acknowledgments, completions, quiz attempts, audit log) is never deleted. You can also zip the folder and upload it in **Admin → Settings → Company pack**. Content edited in the browser can be exported back with **Export current content as a pack** or `php artisan pack:export`.

## 7. Keep it private

A pack often contains internal policies and staff names. `packs/*` is git-ignored in this repository (except `_skeleton` and `demo`). Store your pack in a private repository or on your file server.

---

## Σύντομα στα ελληνικά

1. Αντιγράψτε το `packs/_skeleton` σε `packs/<εταιρεία>`. Κάθε αρχείο έχει σχόλια που εξηγούν τι συμπληρώνεται.
2. Συμπληρώστε το `pack.yaml` (στοιχεία εταιρείας, χρώματα, λογότυπο, δήλωση αποδοχής, PIN Kiosk), τα προγράμματα (`tracks.yaml`), τις ομάδες (`groups.yaml`), μία πολιτική ανά αρχείο στο `policies/`, μία ενότητα ανά αρχείο στο `modules/` και τις ερωτήσεις ανά πρόγραμμα στο `quizzes/`.
3. Έλεγχος: `php artisan pack:install packs/<εταιρεία> --validate-only`. Εμφανίζονται όλα τα λάθη μαζί, με αρχείο και γραμμή.
4. Εγκατάσταση: `php artisan pack:install packs/<εταιρεία>` ή ανέβασμα του φακέλου σε .zip από **Διαχείριση → Ρυθμίσεις**.
5. Το προσωπικό εισάγεται από **Διαχείριση → Εισαγωγή Excel**.

Κρατήστε το πακέτο σε **ιδιωτικό** αποθετήριο: περιέχει εσωτερικές πολιτικές και ονόματα.
