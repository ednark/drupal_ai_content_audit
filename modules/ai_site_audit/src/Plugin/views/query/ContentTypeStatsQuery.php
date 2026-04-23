<?php

declare(strict_types=1);

namespace Drupal\ai_site_audit\Plugin\views\query;

use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Views query plugin that returns content type audit statistics.
 *
 * @ViewsQuery(
 *   id = "ai_site_audit_content_type_stats",
 *   title = @Translation("AI Site Audit Content Type Stats"),
 *   help = @Translation("Queries aggregated content type statistics from the AI Site Audit aggregation service.")
 * )
 */
class ContentTypeStatsQuery extends QueryPluginBase {

  /**
   * {@inheritdoc}
   */
  public function ensureTable($table, $relationship = NULL) {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function addField($table, $field, $alias = '', $params = []) {
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    /** @var \Drupal\ai_site_audit\Service\SiteAggregationService $aggregation */
    $aggregation = \Drupal::service('ai_site_audit.aggregation');
    $breakdown = $aggregation->getContentTypeBreakdown();

    $index = 0;
    foreach ($breakdown as $item) {
      $row = new ResultRow([
        'content_type' => $item['type'] ?? '',
        'content_type_label' => $item['label'] ?? ucfirst(str_replace('_', ' ', $item['type'] ?? '')),
        'count' => (int) ($item['count'] ?? 0),
        'avg_score' => (float) ($item['avg_score'] ?? 0),
        'min_score' => (int) ($item['min_score'] ?? 0),
        'max_score' => (int) ($item['max_score'] ?? 0),
      ]);
      $row->index = $index++;
      $view->result[] = $row;
    }

    $view->total_rows = count($view->result);
    $view->execute_time = 0;
  }

}
