# Tainacan Journal Manager — Plugin WordPress

## Visao Geral
Plugin WordPress que transforma uma instalacao Tainacan em plataforma completa
de gestao de revistas eletronicas cientificas, com fluxo editorial inspirado
no OJS (Open Journal Systems) mas adaptado ao ecossistema WordPress/Tainacan.

Inspirado arquiteturalmente no plugin **Pontos de Memoria** (mesma instalacao,
em `wp-content/plugins/pontos-de-memoria/`), mas com modelagem propria adequada
ao dominio editorial cientifico.

**Status**: 0.5.0 — Phases 4 e 5 entregues. Indicadores com Chart.js
(StatsService cacheado, 6 cards, 5 graficos, export CSV, print/PDF
do navegador). Interoperabilidade: ORCID Mod-11-2 real + OAuth 3-legged,
DOI helpers + Crossref deposit XML 5.3.1 e API submit, DOAJ Articles
JSON + API submit, OAI-PMH 2.0 endpoint completo (Identify,
ListMetadataFormats, ListSets, ListIdentifiers, ListRecords, GetRecord)
em `?tjm_oai=1`, JATS 1.2 XML exporter, Google Scholar citation_*
metatags via wp_head. Painel Admin de Integracoes para credenciais.

## Arquitetura
- **Namespace**: `TainacanJournalManager`
- **Estrutura**: `src/` (classes), `templates/` (views), `assets/` (CSS/JS)
- **Integracao**: Tainacan (camada de publicacao final)
- **Dependencias**: nenhuma externa no MVP (mPDF, Chart.js, jsPDF virao em Phase 2-4)
- **PHP**: 8.0+ • **WordPress**: 6.0+ • **License**: GPL-2.0-or-later

### Fluxo editorial (substitui o fluxo do Pontos de Memoria)

```
SUBMISSAO -> TRIAGEM -> AVALIACAO POR PARES -> DECISAO -> COPYEDITING -> PRODUCAO -> PUBLICACAO
```

(Antes, no Pontos de Memoria: Inscricao -> Homologacao -> Avaliacao -> Certificacao -> Publicacao.
A logica e similar, mas as entidades e nomenclatura sao do dominio cientifico.)

### Estrutura de Diretorios
```
src/
  Config.php                  - Constantes (CPTs, statuses, options)
  Plugin.php                  - Singleton orquestrador
  Activator.php               - On activation
  Deactivator.php             - Clean up cron, flush rewrites
  Autoloader.php              - PSR-4 fallback (sem Composer)

  PostTypes/                  - Custom Post Types
    Journal.php               - tjm_journal (publico)
    Submission.php            - tjm_submission (PRIVADO - manuscritos)
    Review.php                - tjm_review (CONFIDENCIAL - pareceres)
    Issue.php                 - tjm_issue (publico)
    Taxonomies.php            - secoes, palavras-chave, idiomas

  Frontend/                   - Shortcodes publicos
    AuthGuard.php             - Controle de acesso por URL
    LoginPage.php             - [tjm_login]
    AuthorPortal.php          - [tjm_author_portal]
    ReviewerDashboard.php     - [tjm_reviewer_dashboard]
    EditorialDashboard.php    - [tjm_editorial_dashboard]
    PublicJournal.php         - [tjm_journal id=N]
    IndicatorsDashboard.php   - [tjm_indicators]
    Ajax/                     - (vazio no MVP — Phase 2)

  Admin/
    SettingsPage.php          - Menu admin + settings

  Editorial/
    WorkflowManager.php       - Transicoes de status com historico
    StatusManager.php         - Helpers de status
    DecisionManager.php       - Decisao editorial (sempre humana)

  Submission/
    SubmissionService.php     - Criar draft, submeter

  Review/
    ReviewService.php         - Convidar, aceitar/recusar, submeter parecer
    ReviewDeadlineService.php - Cron diario de lembretes
    ReviewerAssignmentService.php - Sugestao least-loaded

  Notifications/
    Mailer.php                - HTML templates + wp_mail
    TokenManager.php          - Tokens para links email (one-click accept/decline)

  Roles/
    PluginRole.php            - Multi-role por usuario E por periodico
    RoleManager.php           - Caps do admin WP
    PermissionChecker.php     - Verificacoes centralizadas

  Tainacan/
    Integration.php           - Detectar disponibilidade
    CollectionProvisioner.php - Cria colecao publica por periodico (idempotente)
    ArticleItemCreator.php    - Cria item Tainacan a partir de submission

  Integrations/               - STUBS (Phase 5)
    OrcidService.php          - validacao Mod-11-2 + format()
    DoiService.php            - format_url + is_valid (regex 10.xxxx/...)
    CrossrefExporter.php      - export_article (TODO)
    OaiPmhProvider.php        - handle_request (TODO)
    DoajExporter.php          - export_article (TODO)

templates/
  frontend/                   - Templates dos shortcodes
  emails/                     - 6 templates HTML (base-layout + 5)
  admin/                      - (vazio no MVP)

assets/
  css/                        - frontend.css (responsivo, badges, cards)
  js/                         - frontend.js (login AJAX)
  images/                     - (vazio)
  vendor/                     - (vazio - Phase 4 para libs de export)

languages/                    - (vazio - i18n preparado mas sem .po/.mo)
```

## Decisoes Arquiteturais

### Por que CPTs e nao tudo no Tainacan?

Submissoes e pareceres precisam de:
- Privacidade (manuscritos em fluxo sao confidenciais)
- Versionamento de uploads
- Transicoes de status com metadados ricos
- Controle de acesso por usuario alem da permissao de colecao do Tainacan

Tainacan brilha como camada publica: busca, navegacao facetada, exports OAI.
O plugin combina ambos: WordPress CPTs para workflow, Tainacan para publicacao.

### Por que multi-role?

Diferente do Pontos de Memoria (1 role por usuario), aqui um usuario pode ser:
- Editor da revista A
- Autor submetendo na revista B
- Avaliador da revista C

Tudo na mesma conta WordPress. Implementacao:
- Global: user meta `_tjm_roles` (JSON array)
- Por periodico: user meta `_tjm_journal_roles` (JSON: `{journal_id: [roles]}`)

### Por que decisao editorial sempre humana?

Sistemas que decidem por nota agregada (como Pontos de Memoria com >= 70 pontos)
nao sao adequados para periodicos cientificos. Editores levam em conta o ineditismo,
o ajuste ao escopo, o impacto potencial e nuances que pareceristas individuais
nao capturam. O sistema **registra** decisoes mas nunca as toma.

## Roles e Permissoes

| Role | Funcao |
|------|--------|
| `journal_manager` | Configura periodicos, politicas, usuarios, secoes |
| `editor_chefe` | Coordena fluxo editorial e decisoes finais |
| `editor_secao` | Gerencia submissoes de secao especifica |
| `autor` | Submete artigos e acompanha processo |
| `avaliador` | Realiza pareceres |
| `copyeditor` | Revisao de texto e normalizacao |
| `layout_editor` | Prepara arquivos finais (PDF, HTML, XML) |
| `leitor` | Acompanha publicacoes |
| `admin_institucional` | Acompanha todos os periodicos da instalacao |

### API de roles (nao mais usar `current_user_can`)

```php
use TainacanJournalManager\Roles\PluginRole;

PluginRole::add_role($user_id, PluginRole::AUTHOR);
PluginRole::add_journal_role($user_id, $journal_id, PluginRole::EDITOR_CHIEF);
PluginRole::has_journal_role($user_id, $journal_id, PluginRole::REVIEWER);
PluginRole::is_editor($user_id, $journal_id);
```

### Camadas de Seguranca (Defesa em Profundidade)

1. **AuthGuard** - Page-level (template_redirect) com PAGE_ROLE_MAP
2. **Shortcode** - Verificacao de role no render()
3. **AJAX** - Nonce + role check
4. **Dados** - `PermissionChecker::can_view_submission()`, `can_review()`, etc.

## Convencoes
- PHP 8.0+, `declare(strict_types=1)`, classes `final`
- **Namespace**: `TainacanJournalManager\`
- **Meta prefix**: `_tjm_`
- **Option prefix**: `tjm_`
- **Hook prefix**: `tjm_`
- **Nonce**: `tjm_frontend_nonce`
- **Text domain**: `tainacan-journal-manager`

## Tainacan
- **Detecao**: `Integration::is_available()` (verifica `\Tainacan\Repositories\Items`)
- **Plugin funciona sem Tainacan** para workflow editorial (so nao publica)
- **Colecao por periodico**: provisionada via `CollectionProvisioner::provision_for_journal($journal_id)`
- **ID da colecao**: armazenado em `wp_options.tjm_collection_for_journal_{journal_id}`
- **Idempotente**: chamadas multiplas sao seguras, so cria metadados faltantes
- **17 metadados** Dublin Core / OJS-compativel (titulo, abstract, keywords, autores,
  secao, idioma, referencias, licenca, DOI, datas, agencia de fomento, etc.)

### Provisionamento

Quando um periodico e criado (ou ao chamar `CollectionProvisioner::provision_for_journal()`):
1. Cria colecao Tainacan "{Journal Name} — Articles"
2. Cria 17 metadados (Text/Textarea/Date)
3. Salva IDs em `wp_options` para uso futuro
4. `upgrade_metadata()` adiciona campos faltantes em colecoes existentes (idempotente)

### Mapeamento de IDs Tainacan

Os IDs sao dinamicos (criados pelo Tainacan na instalacao). O plugin armazena
os IDs criados em options `tjm_meta_*_id`:

```
tjm_meta_title_id          - Titulo
tjm_meta_title_alt_id      - Titulo em outro idioma
tjm_meta_abstract_id       - Resumo
tjm_meta_abstract_en_id    - Abstract (ingles)
tjm_meta_keywords_id       - Palavras-chave
tjm_meta_keywords_en_id    - Keywords (ingles)
tjm_meta_authors_id        - Autores (com afiliacao + ORCID)
tjm_meta_section_id        - Secao
tjm_meta_issue_id          - Volume/numero
tjm_meta_language_id       - Idioma
tjm_meta_references_id     - Referencias
tjm_meta_license_id        - Licenca CC
tjm_meta_doi_id            - DOI
tjm_meta_submitted_at_id   - Data de submissao
tjm_meta_accepted_at_id    - Data de aceite
tjm_meta_published_at_id   - Data de publicacao
tjm_meta_funding_id        - Agencia de fomento
```

## Shortcodes e Paginas

| Shortcode | Slug sugerido | Roles permitidos |
|-----------|---------------|------------------|
| `[tjm_login]` | `journal-login` | Publico |
| `[tjm_author_portal]` | `author-portal` | autor, admin |
| `[tjm_reviewer_dashboard]` | `reviewer-dashboard` | avaliador, admin |
| `[tjm_editorial_dashboard]` | `editorial-dashboard` | journal_manager, editor_chefe, editor_secao, admin_institucional |
| `[tjm_journal id=N]` | Pagina publica | Publico |
| `[tjm_indicators]` | `journal-indicators` | Publico |

## CPTs (Custom Post Types)

### tjm_journal
- **Publico**: SIM (artigos pesquisaveis)
- **Suporte**: title, editor, thumbnail, excerpt
- **Meta usados**:
  - `_tjm_review_type` (open/blind/double_blind/editorial)
  - `_tjm_section_editors` (array de user_ids por secao)

### tjm_submission
- **Publico**: NAO (privado, em fluxo)
- **Suporte**: title, editor, author
- **Meta usados**:
  - `_tjm_journal_id` - Periodico vinculado
  - `_tjm_status` - Status atual (ver SUBMISSION_STATUSES)
  - `_tjm_status_history` - Array com transicoes
  - `_tjm_coauthors` - Array de user_ids
  - `_tjm_reviewers` - Array de user_ids dos pareceristas
  - `_tjm_decisions` - Array de decisoes editoriais
  - `_tjm_submitted_at` - Timestamp da submissao
  - `_tjm_tainacan_item_id` - Vinculo com item publico Tainacan apos publicacao

### tjm_review
- **Publico**: NAO (confidencial)
- **Suporte**: title, editor, author
- **Meta usados**:
  - `_tjm_submission_id` - Submissao avaliada
  - `_tjm_reviewer_id` - Avaliador
  - `_tjm_invited_by` - Editor que convidou
  - `_tjm_review_status` - invited/accepted/declined/submitted/overdue
  - `_tjm_invited_at`, `_tjm_accepted_at`, `_tjm_submitted_at`
  - `_tjm_deadline` - Data limite (Y-m-d)
  - `_tjm_author_comments` - Parecer ao autor
  - `_tjm_editor_comments` - Parecer confidencial ao editor
  - `_tjm_recommendation` - accept/revisions_minor/revisions_major/resubmit/reject
  - `_tjm_decline_reason` - Motivo de recusa

### tjm_issue
- **Publico**: SIM
- **Suporte**: title, editor, thumbnail, excerpt
- **Meta usados**:
  - `_tjm_journal_id` - Periodico vinculado
  - `_tjm_volume`, `_tjm_number`, `_tjm_year`
  - `_tjm_publication_type` - regular/special/dossier/continuous

## Workflow de Submissao

`Editorial\WorkflowManager` controla transicoes de status com array explicito:

```
draft       -> submitted, withdrawn
submitted   -> triage, rejected, withdrawn
triage      -> review, revision, rejected
review      -> decision, revision, rejected
revision    -> triage, review, withdrawn
decision    -> copyediting, revision, rejected
copyediting -> production, revision
production  -> published, copyediting
published   -> (terminal)
rejected    -> (terminal)
withdrawn   -> (terminal)
```

Cada transicao registra em `_tjm_status_history` com timestamp, user_id e nota.
Hook `tjm_status_transition` e disparado para integracoes (notificacoes, indices).

## Sistema de Avaliacao por Pares

Diferente do Pontos de Memoria (8 criterios com pesos, nota maxima 120, decisao
automatica >= 70), aqui o sistema:

- **NAO calcula nota agregada** - cada parecer e textual + recommendation
- **NAO decide automaticamente** - decisao editorial e sempre humana
- **Suporta 4 tipos de revisao** (configurado por periodico):
  - `open` - identidades visiveis
  - `blind` - autor nao ve avaliador
  - `double_blind` - nem autor nem avaliador veem o outro
  - `editorial` - apenas editor avalia
- **Recomendacoes**:
  - `accept` (aceitar)
  - `revisions_minor` (revisoes menores)
  - `revisions_major` (revisoes maiores)
  - `resubmit_review` (resubmeter para nova rodada)
  - `reject` (rejeitar)

### Sugestao de avaliador (least-loaded)

`ReviewerAssignmentService::suggest_least_loaded()` retorna o user_id do
avaliador com **menos pareceres pendentes** (status invited OU accepted, NAO
submitted/declined). Cache transient 5min.

> **Licao do Pontos de Memoria**: contar score=-1 OR NOT EXISTS evitava bug
> de avaliador parecer livre quando meta nao existia. Aqui o equivalente e
> usar `IN (invited, accepted)` para contar pendentes.

## Notificacoes por Email

`Notifications\Mailer` envia HTML templates de `templates/emails/`:

- **base-layout.php** - Layout HTML responsivo (header azul, footer)
- **submission-received.php** - Para autor: confirma recebimento
- **review-invitation.php** - Para avaliador: convite com botoes accept/decline
- **decision-accept.php** - Para autor: aceito
- **decision-reject.php** - Para autor: rejeitado
- **editor-new-submission.php** - Para editor: nova submissao

Subject lines centralizadas em `Mailer::subject_for($key, $data)` com prefixo
`[Site Name]`.

### Emails ja mapeados (Phase 2 vai criar templates)

```
'submission-received', 'submission-in-triage', 'submission-in-review',
'review-invitation', 'review-reminder', 'review-overdue', 'review-thanks',
'decision-accept', 'decision-reject', 'decision-revisions',
'submission-published', 'editor-new-submission', 'editor-review-received'
```

## Cron Jobs

- `tjm_send_review_reminders` (diario) - Envia lembretes 7d, 3d, 1d antes do
  prazo + email de overdue para pareceres atrasados
- `tjm_cleanup_tokens` (semanal, futuro) - Remove tokens expirados de
  `wp_options.tjm_token_*`

## Integracoes (Stubs - Phase 5)

| Stub | Status | Pendencia |
|------|--------|-----------|
| `OrcidService` | Format + is_valid (regex) | Mod-11-2 checksum + OAuth |
| `DoiService` | format_url + is_valid (regex) | Crossref/DataCite mint |
| `CrossrefExporter` | TODO | XML schema export |
| `OaiPmhProvider` | TODO 501 | OAI-PMH 2.0 protocol |
| `DoajExporter` | TODO | DOAJ XML/JSON |

Estrutura pronta, basta implementar `// TODO` quando integracoes forem priorizadas.

## Database Options

| Option | Uso |
|--------|-----|
| `tjm_version` | Versao instalada |
| `tjm_activated_at` | Primeira ativacao |
| `tjm_emails_enabled` | Master switch (testes) |
| `tjm_email_from_name` / `tjm_email_from_address` | Remetente |
| `tjm_review_deadline_days` | Default 30 |
| `tjm_token_validity_days` | Default 60 |
| `tjm_collection_for_journal_{N}` | Colecao Tainacan por periodico |
| `tjm_meta_*_id` | IDs dos metadados Tainacan |
| `tjm_token_{token}` | Tokens ativos com expiracao |

## Hooks Proprios (Actions)

| Hook | Disparado quando | Args |
|------|------------------|------|
| `tjm_status_transition` | Status muda | `$submission_id, $from, $to` |
| `tjm_decision_recorded` | Editor decide | `$submission_id, $decision, $editor_id` |
| `tjm_submission_submitted` | Autor submete | `$submission_id` |
| `tjm_review_invited` | Avaliador convidado | `$review_id, $submission_id, $reviewer_id` |
| `tjm_review_accepted` | Avaliador aceita | `$review_id` |
| `tjm_review_declined` | Avaliador recusa | `$review_id` |
| `tjm_review_submitted` | Parecer concluido | `$review_id` |

## Roadmap

### Phase 1 - MVP foundation - DONE
Estrutura completa, CPTs, roles, workflow, mailer, shortcodes esqueleto.

### Phase 2 - Submission/Review UIs (atual) - DONE
- Wizard de submissao multi-step com upload de manuscrito (`templates/frontend/submission-wizard.php`)
- UI de coautores + ORCID (formatado via `OrcidService::format`)
- Declaracoes (originalidade, COI, copyright, etica) com validacao em `SubmissionService::is_complete`
- UI de atribuicao de avaliadores no editorial dashboard (`templates/frontend/editorial-detail.php`)
- Formulario de parecer configuravel por periodico (`Review\ReviewFormConfig` + metabox no Journal CPT)
- Anonimizacao para blind/double-blind (`Submission\AnonymizationService`)
- UI de gerenciamento de roles (shortcode `[tjm_role_management]`, slug `journal-roles`)
- AJAX handlers em `src/Frontend/Ajax/` (Submission, Editorial, Review, Roles)
- Tokens one-click accept/decline para convites por email (`ReviewAjax::handle_token_link`)

### Phase 3 - Producao e publicacao (atual) - DONE
- Copyediting com versoes (`Production\CopyeditingService`)
- Galley manager PDF/HTML/XML/EPUB/JATS (`Production\GalleyService`)
- Aprovacao de prova pelo autor (`Production\ProofApprovalService`)
- ArticlePublisher completo populando os 17 metadados Tainacan (`Tainacan\ArticlePublisher`)
- Pagina publica do artigo via shortcode `[tjm_article id=N]` (`Frontend\PublicArticle`)
- Gerenciamento de edicoes via UI editorial `?issues=1` + metabox Issue CPT (`Issues\IssueManager`)
- Shortcode `[tjm_copyediting_dashboard]` (slug `copyediting-dashboard`) para copyeditor/layout editor
- AJAX handlers `Frontend\Ajax\ProductionAjax` e `Frontend\Ajax\IssueAjax`
- E-mail templates novos: `copyediting-version`, `proof-request`, `decision-revisions`, `submission-published`

### Phase 4 - Indicadores - DONE
- Chart.js dashboard cacheado em transient 15min (`Indicators\StatsService`)
- 6 cards (submissions, published, reviews, journals, issues, acceptance rate)
- 5 graficos (status, monthly trend, per-journal, top journals, top reviewers)
- Export CSV com BOM UTF-8 (Excel-friendly), print via window.print()
- Cache invalidado nos hooks tjm_status_transition / tjm_review_submitted /
  tjm_decision_recorded / tjm_article_published / tjm_issue_published
- Chart.js 4.x carregado de `assets/js/vendor/chart.umd.min.js` (vide README local)

### Phase 5 - Interoperabilidade - DONE
- ORCID validacao Mod-11-2 real (`Integrations\OrcidService::is_valid` agora
  computa o checksum) + OAuth 3-legged (`Integrations\OrcidOAuthService`)
  com endpoints `?tjm_orcid=connect|callback`
- DOI helpers (`Integrations\DoiService`: format/normalize/is_valid)
- Crossref deposit XML 5.3.1 (`Integrations\CrossrefExporter`) + API submit
  multipart (`Integrations\CrossrefDeposit::submit`)
- DOAJ Articles JSON (`Integrations\DoajExporter::build_article`) + API submit
- OAI-PMH 2.0 completo em `?tjm_oai=1` (`Integrations\OaiPmhProvider`):
  Identify, ListMetadataFormats, ListSets (1 set por periodico),
  ListIdentifiers, ListRecords, GetRecord; metadata format `oai_dc`
- JATS 1.2 XML (`Integrations\JatsExporter`)
- Google Scholar citation_* metatags via wp_head em paginas com
  `[tjm_article]` (`Integrations\ScholarMetadata`)
- AJAX endpoints em `Frontend\Ajax\IntegrationsAjax`:
  tjm_export_crossref, tjm_export_doaj, tjm_export_jats, tjm_doi_mint,
  tjm_doaj_submit, tjm_doi_set
- Submenu Admin "Integrations" para credenciais (ORCID, Crossref, DOAJ)

### Phase 6 - Avancado
- Tradução para espanhol
- Editor de templates de email no admin
- Relatorios PDF para gestores
- Audit logs

## Licoes do Pontos de Memoria Aplicadas

1. **Multi-role flexivel**: nao depender de role unico - suportar combinacoes
2. **Counter-based queries com NOT EXISTS**: avaliadores sem meta tambem sao livres
3. **Cache invalidation por hooks**: `save_post_*`, `updated_post_meta`, `set_object_terms`
4. **Catch \Throwable** (nao so Exception) em codigo critico
5. **vendor/ commitado** quando producao nao tem composer
6. **Libs JS de export locais** (sem CDN - firewalls institucionais)
7. **CPTs separadas para fluxo vs publicacao** (privadas vs publicas)
8. **Templates de email com base-layout reutilizavel**
9. **Shortcodes com class register() padronizado**
10. **Slug-based AuthGuard com PAGE_ROLE_MAP**

## Repositorio
- **GitHub**: https://github.com/marcossigismundo/tainacan-journal-manager
- **Branch principal**: `main`

## Comandos uteis

```bash
# Verificar sintaxe de todos os PHPs
find . -name "*.php" -not -path "./vendor/*" | xargs -I {} php -l {}

# Provisionar colecao publica para um periodico (PHP REPL)
\TainacanJournalManager\Tainacan\CollectionProvisioner::provision_for_journal(123);

# Atribuir role a um usuario
\TainacanJournalManager\Roles\PluginRole::add_role(45, \TainacanJournalManager\Roles\PluginRole::AUTHOR);
\TainacanJournalManager\Roles\PluginRole::add_journal_role(45, 123, \TainacanJournalManager\Roles\PluginRole::EDITOR_CHIEF);

# Forcar transicao de status
\TainacanJournalManager\Editorial\WorkflowManager::transition($submission_id, 'review', $editor_id, 'note');
```
