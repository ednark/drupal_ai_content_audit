# AI Content Auditor

A Drupal module that assesses node content quality and AI-readiness using the
[Drupal AI module](https://www.drupal.org/project/ai) abstraction layer.

## Requirements

- Drupal 10.3+ or Drupal 11
- [drupal/ai](https://www.drupal.org/project/ai) module
- At least one AI provider module configured (e.g. `drupal/ai_provider_openai`)

## Features

- **AI Readiness Score (0–100)** for any node
- **Structured assessment** covering readability, SEO signals, content completeness, tone, and improvement suggestions
- **Assessment history** per node accessible via a dedicated tab
- **AJAX "Assess Now" button** on node edit forms (sidebar)
- **Block plugin** for displaying scores on node view pages
- **On-save background assessment** via Drupal Queue API (optional, disabled by default)
- **Drush command** for bulk assessment
- **Provider-agnostic** — works with any `drupal/ai`-compatible LLM backend

## Installation

```bash
composer require drupal/ai
drush en ai ai_content_audit
```

Configure an AI provider at `/admin/config/ai/providers`, then configure
this module at `/admin/config/ai/content-audit`.

## Permissions

| Permission | Intended for |
|------------|-------------|
| `view ai content assessment` | Editors — read-only access to results |
| `run ai content assessment` | Editors — trigger assessments |
| `administer ai content audit` | Admins — full settings access |

## Usage

### Manual assessment (UI)
1. Open any existing node's edit form
2. Click the **"AI Assessment"** sidebar tab
3. Click **"Assess Now"**
4. View the score and suggestions live
5. See full history at `/node/{nid}/ai-assessment`

### Drush
```bash
# Assess a single node synchronously
drush ai_content_audit:assess --nid=42

# Enqueue all configured node types for background assessment
drush ai_content_audit:assess --all

# Enqueue only article nodes
drush ai_content_audit:assess --all --type=article

# Process the queue
drush queue:run ai_content_audit_assessment
```

> **Note:** The deprecated `drush ai_content_audit:site-audit` command has been removed. Use `drush ai_content_audit:assess --all` to enqueue bulk assessments.
```

## Architecture

```
FieldExtractor     → reads all displayable text fields from a node
AiAssessmentService → calls ai.provider chat(), parses JSON response, saves entity
AiContentAssessment → content entity storing scores, JSON result, raw output
AiResponseSubscriber → subscribes to ai.post_generate_response for logging
AiAssessmentBlock   → displays latest score on node view pages
AiAssessmentController → history tab on node canonical
AiAssessmentQueueWorker → background cron processing
SettingsForm        → /admin/config/ai/content-audit configuration
AiContentAuditCommands → drush aca command
```

## Developer notes

- All AI calls go through `drupal/ai`'s `ProviderProxy` — pre/post events fire automatically
- JSON recovery: 3-stage parse (direct → strip markdown fences → regex extract)
- Cache invalidation: `ai_content_assessment_list:node:{nid}` custom cache tag
- On-save queueing is off by default to avoid unexpected API costs
