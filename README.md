# Tainacan Journal Manager

A WordPress plugin that transforms a Tainacan-powered installation into a **complete electronic scientific journal management platform**, with editorial workflow inspired by OJS (Open Journal Systems) but adapted to the Tainacan ecosystem.

> **Status**: 0.1.0 — MVP foundation. Core architecture, CPTs, multi-role permissions, editorial workflow engine, and base shortcodes are in place. Several features marked as STUB will be expanded in next phases.
>
> **PHP**: 8.0+ • **WordPress**: 6.0+ • **License**: GPL-2.0-or-later

---

## Table of Contents

1. [Vision](#vision)
2. [Key features](#key-features)
3. [Architecture](#architecture)
4. [Editorial workflow](#editorial-workflow)
5. [Roles and permissions](#roles-and-permissions)
6. [Tainacan integration](#tainacan-integration)
7. [Shortcodes](#shortcodes)
8. [Hooks (actions and filters)](#hooks-actions-and-filters)
9. [Database options](#database-options)
10. [Installation](#installation)
11. [Configuration](#configuration)
12. [Usage by role](#usage-by-role)
13. [Development roadmap](#development-roadmap)
14. [Implementation status](#implementation-status)
15. [Security and privacy](#security-and-privacy)
16. [Internationalization](#internationalization)
17. [License](#license)

---

## Vision

Turn a WordPress + Tainacan installation into a professional electronic journal management platform that supports:

- Journal configuration and policies
- Editorial sections (article, review, dossier, etc.)
- Author submissions with multi-step wizard
- Editorial triage
- Peer review (single-blind, double-blind, open or editorial)
- Editorial decision (always human, never auto-scored)
- Copyediting and production
- Publishing in public Tainacan collections
- Volume / issue / dossier or continuous flow organization
- Qualified scientific metadata (Dublin Core, OJS-compatible)
- Editorial indicators dashboard
- Email notifications
- Future interoperability: DOI, ORCID, OAI-PMH, Crossref, DOAJ, JATS XML

The plugin adds an **editorial layer** on top of Tainacan, allowing it to function not only as a digital repository but also as a scientific publishing platform.

---

## Key features

### Implemented (v0.1.0 — MVP foundation)

- Plugin scaffold with PSR-4 autoload, activation/deactivation hooks
- Custom post types: `tjm_journal`, `tjm_submission`, `tjm_review`, `tjm_issue`
- Taxonomies: editorial sections, keywords, languages
- Multi-role system: a single user can be **editor of journal A, author of journal B, reviewer of journal C**
- Workflow engine with explicit status transitions and history tracking
- Decision manager (records editorial decisions, fires workflow transitions)
- Review service: invitations, accept/decline, deadlines, submission
- Reviewer assignment: least-loaded suggestion (counts pending only — lesson learned from prior project)
- Daily cron for review reminders and overdue detection
- Tainacan collection provisioning (one collection per journal, idempotent)
- Article item creation stub (full publishing flow in Phase 2)
- Mailer with HTML templates and per-template subject lines
- Token manager for one-click email links (review accept/decline)
- AuthGuard (URL-level access control)
- Shortcodes: login, author portal, reviewer dashboard, editorial dashboard, public journal, public indicators
- Settings admin page (emails, deadlines, tokens)
- Responsive frontend CSS with status badges and cards
- Stub classes for ORCID, DOI, Crossref, OAI-PMH, DOAJ (with structure for future expansion)

### Planned (next phases)

- Multi-step submission wizard with file upload, ORCID validation, coauthor management, copyright/originality declarations
- Configurable review forms per journal
- Blind/double-blind/open review modes (currently in workflow but UI not enforced)
- Copyediting and galley management UIs
- Public article page with full metadata, PDF download, citation export
- Chart.js indicators (acceptance rate, processing times, top reviewers, geographic distribution)
- DOI minting via Crossref
- ORCID OAuth sign-in
- OAI-PMH 2.0 endpoint
- DOAJ XML/JSON export
- JATS XML production
- 17+ additional email templates
- Spanish translation files

---

## Architecture

```
tainacan-journal-manager/
  tainacan-journal-manager.php  ← Main plugin file
  composer.json
  uninstall.php
  README.md

  src/
    Config.php                  ← Constants (CPTs, statuses, options)
    Plugin.php                  ← Singleton orchestrator
    Activator.php               ← On activation: register CPTs, install caps, flush rewrites
    Deactivator.php             ← Clean up cron, flush rewrites
    Autoloader.php              ← PSR-4 autoloader (Composer fallback)

    PostTypes/                  ← CPT registrations
      Journal.php               ← tjm_journal
      Submission.php            ← tjm_submission (private)
      Review.php                ← tjm_review (confidential)
      Issue.php                 ← tjm_issue
      Taxonomies.php            ← Sections, keywords, languages

    Frontend/                   ← Public-facing
      AuthGuard.php             ← URL-level access control
      LoginPage.php             ← [tjm_login]
      AuthorPortal.php          ← [tjm_author_portal]
      ReviewerDashboard.php     ← [tjm_reviewer_dashboard]
      EditorialDashboard.php    ← [tjm_editorial_dashboard]
      PublicJournal.php         ← [tjm_journal id=N]
      IndicatorsDashboard.php   ← [tjm_indicators]

    Admin/
      SettingsPage.php          ← Plugin settings menu

    Editorial/
      WorkflowManager.php       ← Status transitions
      StatusManager.php         ← Status helpers
      DecisionManager.php       ← Editorial decisions

    Submission/
      SubmissionService.php     ← Create draft, submit

    Review/
      ReviewService.php         ← Invitation, accept/decline, submit
      ReviewDeadlineService.php ← Daily cron for reminders
      ReviewerAssignmentService.php ← Least-loaded suggestion

    Notifications/
      Mailer.php                ← HTML templates + send
      TokenManager.php          ← Tokens for email links

    Roles/
      PluginRole.php            ← Multi-role per user
      RoleManager.php           ← Admin caps install/uninstall
      PermissionChecker.php     ← Centralized checks

    Tainacan/
      Integration.php           ← Detect availability
      CollectionProvisioner.php ← One collection per journal (idempotent)
      ArticleItemCreator.php    ← Create public Tainacan item from submission

    Integrations/               ← STUBS
      OrcidService.php
      DoiService.php
      CrossrefExporter.php
      OaiPmhProvider.php
      DoajExporter.php

  templates/
    frontend/                   ← Shortcode templates
    emails/                     ← HTML email templates
    admin/                      ← Admin templates

  assets/
    css/                        ← frontend.css, admin.css
    js/                         ← frontend.js
    vendor/                     ← Future: jsPDF, Chart.js (local fallback)

  languages/                    ← .po / .mo / .pot files
```

### Conventions

- PHP 8.0+ with `declare(strict_types=1)` and `final` classes
- **Namespace**: `TainacanJournalManager\`
- **Meta prefix**: `_tjm_`
- **Option prefix**: `tjm_`
- **Hook prefix**: `tjm_`
- **Nonce**: `tjm_frontend_nonce`

### Defense in depth (security layers)

1. **AuthGuard** — page-level access (template_redirect)
2. **Shortcode** — role check on render
3. **AJAX** — nonce + role check + ownership
4. **Data layer** — `PermissionChecker` for granular ownership/role logic

---

## Editorial workflow

```
   ┌──────┐  ┌──────────┐  ┌─────────┐  ┌───────────┐  ┌──────────────┐  ┌────────────┐  ┌─────────────┐
   │DRAFT │->│SUBMITTED │->│TRIAGE   │->│REVIEW     │->│DECISION      │->│COPYEDITING │->│PRODUCTION   │
   └──────┘  └──────────┘  └─────────┘  └───────────┘  └──────────────┘  └────────────┘  └──────┬──────┘
                              │                                                                  │
                              v                                                                  v
                          ┌──────┐    ┌──────────┐                                          ┌────────────┐
                          │REJECT│<───│REVISIONS │<───────────────────────────────────────  │PUBLISHED   │
                          └──────┘    └──────────┘                                          └────────────┘
```

Allowed transitions are explicitly enumerated in `WorkflowManager::TRANSITIONS`. Every transition is recorded in `_tjm_status_history` with timestamp, user, and optional note. The hook `tjm_status_transition` is fired on every change for notifications and integrations.

**The editorial decision is always made by a human editor** — the system NEVER auto-decides based on review scores. Recommendations from reviewers are surfaced to help, not to replace, the editor's judgment.

---

## Roles and permissions

Unlike single-role plugins, **users can have multiple roles** — both globally and per journal.

### Role keys

| Role | Description |
|------|-------------|
| `journal_manager` | Configures journal, policies, users, sections, issues |
| `editor_chefe` | Coordinates editorial flow and final decisions |
| `editor_secao` | Manages submissions for a specific section |
| `autor` | Submits articles, tracks process |
| `avaliador` | Performs peer reviews |
| `copyeditor` | Text revision and normalization |
| `layout_editor` | Final files (PDF, HTML, XML) |
| `leitor` | Followers / subscribers |
| `admin_institucional` | Cross-journal administration |

### Storage

- **Global roles**: user meta `_tjm_roles` (JSON array)
- **Per-journal roles**: user meta `_tjm_journal_roles` (JSON map: `{journal_id: [role1, role2]}`)

This allows complex permission scenarios:
- Editor of Journal A
- Author submitting to Journal B
- Reviewer for Journal C

…all in the same WordPress user account.

### API

```php
use TainacanJournalManager\Roles\PluginRole;

PluginRole::add_role($user_id, PluginRole::AUTHOR);
PluginRole::add_journal_role($user_id, $journal_id, PluginRole::EDITOR_CHIEF);
PluginRole::has_journal_role($user_id, $journal_id, PluginRole::REVIEWER);
PluginRole::is_editor($user_id, $journal_id); // checks any editor variant
```

---

## Tainacan integration

**Tainacan is used as the publishing layer** — not for the editorial workflow.

| Stage | Where data lives |
|-------|------------------|
| Submission in flow | CPT `tjm_submission` (private) |
| Peer reviews | CPT `tjm_review` (confidential) |
| Issues / volumes | CPT `tjm_issue` |
| Journals | CPT `tjm_journal` |
| **Published articles** | **Tainacan collection** (one per journal, auto-provisioned) |

### Collection provisioning

When a journal is created, calling `CollectionProvisioner::provision_for_journal($journal_id)` creates:

- A Tainacan collection named "{Journal Name} — Articles"
- 17 metadata fields (Dublin Core / OJS-compatible):
  - Title (and alternate language)
  - Abstract (and English abstract)
  - Keywords (and English keywords)
  - Authors (with affiliations and ORCID)
  - Section, Language, References, License
  - DOI, Funding agency
  - Submission, Acceptance, and Publication dates

The collection ID is stored in `wp_options.tjm_collection_for_journal_{journal_id}`. The provisioning is **idempotent** — calling it multiple times is safe and only creates missing fields.

### Why not Tainacan for everything?

Submissions and reviews need:
- Privacy (manuscripts in flow are confidential)
- Versioning of file uploads
- Status transitions with rich metadata
- Per-user access control beyond Tainacan's collection-level permissions

Tainacan excels at the **published face**: search, faceted browsing, exporters, OAI harvesting. The plugin combines both: WordPress CPTs for workflow, Tainacan for publication.

---

## Shortcodes

| Shortcode | Suggested page slug | Roles allowed | Purpose |
|-----------|--------------------|---------------|---------|
| `[tjm_login]` | `journal-login` | Public | Custom login form |
| `[tjm_author_portal]` | `author-portal` | autor + admin | Submissions, status, new submission |
| `[tjm_reviewer_dashboard]` | `reviewer-dashboard` | avaliador + admin | Invitations, in-progress, completed reviews |
| `[tjm_editorial_dashboard]` | `editorial-dashboard` | editors + admin_institucional | Cards, lists, decisions, assignments |
| `[tjm_journal id=N]` | Public page | Public | Journal homepage (about, scope, team) |
| `[tjm_indicators]` | `journal-indicators` | Public | Editorial metrics |

---

## Hooks (actions and filters)

### Actions fired by the plugin

| Hook | When | Args |
|------|------|------|
| `tjm_status_transition` | Submission status changes | `$submission_id, $from, $to` |
| `tjm_decision_recorded` | Editor records decision | `$submission_id, $decision, $editor_id` |
| `tjm_submission_submitted` | Author submits draft | `$submission_id` |
| `tjm_review_invited` | Reviewer is invited | `$review_id, $submission_id, $reviewer_id` |
| `tjm_review_accepted` | Reviewer accepts | `$review_id` |
| `tjm_review_declined` | Reviewer declines | `$review_id` |
| `tjm_review_submitted` | Reviewer completes | `$review_id` |

Use these hooks to integrate notifications, indexing, analytics, etc.

---

## Database options

| Option | Description | Default |
|--------|-------------|---------|
| `tjm_version` | Plugin version | 0.1.0 |
| `tjm_activated_at` | First activation timestamp | — |
| `tjm_emails_enabled` | Master switch for emails | `true` |
| `tjm_email_from_name` | Email sender name | `Tainacan Journal Manager` |
| `tjm_email_from_address` | Email sender address | (admin email) |
| `tjm_review_deadline_days` | Default review deadline | 30 |
| `tjm_token_validity_days` | Token expiration | 60 |
| `tjm_collection_for_journal_{N}` | Tainacan collection ID per journal | — |
| `tjm_meta_*_id` | Tainacan metadata IDs | — |
| `tjm_token_*` | Active tokens (auto-cleaned on expiration) | — |

---

## Installation

1. **Clone or upload** to `wp-content/plugins/tainacan-journal-manager/`
2. **Activate** via WordPress admin → Plugins
3. **Recommended**: install **Tainacan** plugin first for full publishing capabilities (the editorial workflow works without Tainacan, but you cannot publish articles)
4. **Configure** at WP Admin → Journal Manager → Settings

### Optional: Composer

If running in development:

```bash
composer install
```

This installs PSR-4 autoload (faster than the bundled fallback). For production, the plugin includes a fallback autoloader so Composer is optional.

---

## Configuration

Navigate to **WP Admin → Journal Manager → Settings**:

| Field | Description |
|-------|-------------|
| Enable emails | Master switch (turn off during testing) |
| Email "From" name / address | Sender identity for all notifications |
| Default review deadline | Days reviewers have to complete |
| Token validity | How long invitation tokens stay valid |

### Frontend pages

Create WordPress pages with these slugs and matching shortcodes:

```
journal-login         → [tjm_login]
author-portal         → [tjm_author_portal]
reviewer-dashboard    → [tjm_reviewer_dashboard]
editorial-dashboard   → [tjm_editorial_dashboard]
journal-indicators    → [tjm_indicators]
```

The AuthGuard enforces login + role checks for the protected pages.

### Creating users

1. Create WordPress user (any role — even `subscriber`)
2. Assign editorial role via PHP:
   ```php
   \TainacanJournalManager\Roles\PluginRole::add_role($user_id, \TainacanJournalManager\Roles\PluginRole::AUTHOR);
   ```
   (A UI for role assignment will arrive in Phase 2)
3. The user can then log in via `[tjm_login]` and access their portal

---

## Usage by role

### Author

1. Log in at `/journal-login`
2. Visit `/author-portal`
3. Click "+ New Submission" (Phase 2: full wizard with file upload, coauthors, ORCID, declarations)
4. Track status through editorial workflow
5. Receive emails at each transition
6. Approve final proof
7. Article appears in public Tainacan collection

### Reviewer

1. Receive email invitation with one-click accept/decline link
2. If accepted, log in at `/reviewer-dashboard`
3. Read manuscript (with anonymization based on review type)
4. Submit recommendation (accept / minor revisions / major revisions / resubmit / reject)
5. Receive thank-you email after submission

### Editor

1. Log in at `/editorial-dashboard`
2. Triage new submissions (assign to a section editor or send to peer review)
3. Invite reviewers (system suggests least-loaded)
4. Read review reports
5. Make editorial decision (NEVER auto-scored — always human judgment)
6. Send to copyediting → production → publish
7. Article goes live in Tainacan public collection

### Journal manager

- Configure journal sections, policies, editorial team
- Assign editor roles per journal
- Customize email templates (Phase 2)
- View statistics and indicators

### Institutional administrator

- Cross-journal view of all submissions
- Audit logs (Phase 2)
- User and role management UI (Phase 2)

---

## Development roadmap

### Phase 1 — MVP foundation ✅ (current)
- [x] Plugin scaffold + autoload
- [x] CPTs and taxonomies
- [x] Multi-role permission system
- [x] Workflow engine with status transitions
- [x] Mailer with template system
- [x] Token manager
- [x] Settings page
- [x] Basic shortcodes (skeleton)
- [x] Tainacan collection provisioning

### Phase 2 — Submission and review UIs
- [ ] Multi-step submission wizard with file upload
- [ ] Coauthor management UI
- [ ] ORCID validation in form
- [ ] Originality / conflict-of-interest declarations
- [ ] Reviewer assignment UI in editorial dashboard
- [ ] Review form (configurable per journal)
- [ ] Anonymization for blind/double-blind reviews
- [ ] User role assignment UI

### Phase 3 — Production and publishing
- [ ] Copyediting workflow with versioned files
- [ ] Galley manager (PDF, HTML, XML uploads)
- [ ] Author proof approval
- [ ] Article publisher (full Tainacan integration)
- [ ] Public article page template
- [ ] Issue manager (volume / number / dossier)

### Phase 4 — Indicators and reports
- [ ] Chart.js indicators dashboard (similar to Pontos de Memória)
- [ ] PDF/XLSX export of reports
- [ ] Top reviewers, top journals, geographic distribution

### Phase 5 — Interoperability
- [ ] DOI minting via Crossref
- [ ] ORCID OAuth sign-in
- [ ] OAI-PMH 2.0 endpoint
- [ ] DOAJ XML/JSON export
- [ ] JATS XML production
- [ ] Google Scholar metadata tags
- [ ] REST API for headless consumption

### Phase 6 — Advanced
- [ ] Spanish translations
- [ ] Email template editor in admin
- [ ] Multiple journals administration
- [ ] Editorial reports (PDF)
- [ ] Audit logs

---

## Implementation status

### Fully working
- Plugin activation/deactivation without errors
- CPTs registered and visible in admin
- Multi-role system (read/write/check)
- Workflow status transitions with history
- Decision recording with workflow integration
- Review invitation flow (PHP layer)
- Email sending with HTML templates
- Token generation and validation
- AuthGuard for protected pages
- Settings page

### Functional but minimal UI
- Author portal (lists submissions)
- Reviewer dashboard (lists reviews)
- Editorial dashboard (status counts)
- Public journal page
- Indicators (basic stats)

### Stubs (architecture in place, full implementation deferred)
- ORCID, DOI, Crossref, OAI-PMH, DOAJ services
- Article publishing to Tainacan (creates item but doesn't populate all metadata)
- Indicators charts
- Email templates beyond the 6 included

### Not yet started
- Submission form wizard
- File upload for manuscripts
- Review form
- Copyediting / production UIs
- User role assignment admin UI

---

## Security and privacy

- All AJAX handlers use `check_ajax_referer` (nonce verification)
- All user input sanitized via `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw`, etc.
- All output escaped via `esc_html`, `esc_attr`, `esc_url`
- File access controlled (Phase 2: signed URLs for manuscript downloads)
- Reviews are private CPTs — never directly publicly queryable
- Submissions are private — only owner, coauthors, assigned reviewers, and editors can access (`PermissionChecker::can_view_submission`)
- LGPD/GDPR compliant: personal data of authors is not auto-published; reviewer identities can be anonymized per journal config

---

## Internationalization

Plugin text domain: `tainacan-journal-manager`

All user-facing strings use WP i18n functions: `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()`, `_x()`.

Translation files location: `languages/`

Planned translations:
- ✅ English (default)
- 🚧 Portuguese (Brazil) — base language for development
- 📋 Spanish — Phase 6

To generate `.pot` file:

```bash
wp i18n make-pot . languages/tainacan-journal-manager.pot
```

---

## License

GPL-2.0-or-later — Same as WordPress.

---

## Credits

Inspired by the editorial flow and architectural patterns of:
- **OJS — Open Journal Systems** (Public Knowledge Project)
- **Pontos de Memória** plugin (IBRAM certification system)
- **Tainacan** (digital repository platform)

Built with WordPress, PHP 8, and modern web standards.
