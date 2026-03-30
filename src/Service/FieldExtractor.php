<?php

declare(strict_types=1);

namespace Drupal\ai_content_audit\Service;

use Drupal\ai_content_audit\Enum\RenderMode;
use Drupal\ai_content_audit\Extractor\ContentExtractorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Extracts displayable text content from a node for AI assessment.
 */
class FieldExtractor implements ContentExtractorInterface {

  /**
   * Field types considered text-based and extractable.
   */
  const EXTRACTABLE_FIELD_TYPES = [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
    'list_string',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(string $mode): bool {
    return $mode === RenderMode::TEXT->value;
  }

  /**
   * {@inheritdoc}
   */
  public function extract(NodeInterface $node): string {
    return $this->extractForNode($node);
  }

  /**
   * {@inheritdoc}
   */
  public function getMode(): string {
    return RenderMode::TEXT->value;
  }

  /**
   * Extracts all displayable text from a node as a single string.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to extract content from.
   * @param string $view_mode
   *   The view mode to check display configuration against.
   *
   * @return string
   *   Compiled plain-text content from all extractable fields.
   *
   * @deprecated in ai_content_audit:1.x and is removed from ai_content_audit:2.x.
   *   Use \Drupal\ai_content_audit\Extractor\ContentExtractorInterface::extract() instead.
   *   @see \Drupal\ai_content_audit\Extractor\ContentExtractorInterface
   */
  public function extractForNode(NodeInterface $node, string $view_mode = 'default'): string {
    $parts = [];

    // Always include the title.
    $parts[] = 'Title: ' . $node->label();

    // Load the active entity view display to check which fields are shown.
    $display = $this->loadViewDisplay($node->bundle(), $view_mode);

    // Iterate over all node fields.
    foreach ($node->getFieldDefinitions() as $field_name => $definition) {
      // Skip computed, non-configurable, and internal fields.
      if ($field_name === 'title') {
        continue;
      }
      if (str_starts_with($field_name, 'revision_') || str_starts_with($field_name, 'status') || str_starts_with($field_name, 'uid')) {
        continue;
      }

      $field_type = $definition->getType();
      if (!in_array($field_type, self::EXTRACTABLE_FIELD_TYPES, TRUE)) {
        continue;
      }

      // Check if this field is configured in the display.
      if ($display && !$display->getComponent($field_name)) {
        continue;
      }

      $field = $node->get($field_name);
      if ($field->isEmpty()) {
        continue;
      }

      // Extract text from each field item.
      $field_label = $definition->getLabel();
      $field_text = $this->extractFieldText($node, $field_name, $field_type);
      if (!empty(trim($field_text))) {
        $parts[] = $field_label . ': ' . $field_text;
      }
    }

    return implode("\n\n", $parts);
  }

  /**
   * Loads the entity view display for a given bundle and view mode.
   */
  protected function loadViewDisplay(string $bundle, string $view_mode): ?object {
    try {
      $storage = $this->entityTypeManager->getStorage('entity_view_display');
      $display = $storage->load('node.' . $bundle . '.' . $view_mode);
      return $display ?: $storage->load('node.' . $bundle . '.default');
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Extracts plain text from a specific field on a node.
   */
  protected function extractFieldText(NodeInterface $node, string $field_name, string $field_type): string {
    $field = $node->get($field_name);
    $texts = [];

    foreach ($field as $item) {
      switch ($field_type) {
        case 'text_with_summary':
          $value = $item->value ?? '';
          $summary = $item->summary ?? '';
          if (!empty($summary)) {
            $texts[] = $this->stripHtml($summary);
          }
          if (!empty($value)) {
            $texts[] = $this->stripHtml($value);
          }
          break;

        case 'text':
        case 'text_long':
          $value = $item->value ?? '';
          if (!empty($value)) {
            $texts[] = $this->stripHtml($value);
          }
          break;

        case 'string':
        case 'string_long':
        case 'list_string':
          $value = $item->value ?? '';
          if (!empty($value)) {
            $texts[] = strip_tags($value);
          }
          break;
      }
    }

    return implode(' ', array_filter($texts));
  }

  /**
   * Strips HTML and normalizes whitespace from a string.
   */
  protected function stripHtml(string $html): string {
    // Decode HTML entities first.
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Strip all tags.
    $text = strip_tags($text);
    // Normalize whitespace.
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
  }

}
