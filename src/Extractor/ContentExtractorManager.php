<?php

declare(strict_types=1);

namespace Drupal\ai_content_audit\Extractor;

use Drupal\ai_content_audit\Enum\RenderMode;

/**
 * Collects and routes between registered content extractors.
 *
 * This service acts as a strategy registry for ContentExtractorInterface
 * implementations. It is populated by the DI container via the tagged
 * service pattern: any service tagged with 'ai_content_audit.content_extractor'
 * is automatically added via addExtractor().
 *
 * Usage in AiAssessmentService:
 * @code
 *   $extractor = $this->extractorManager->getExtractorForMode($mode);
 *   $content = $extractor->extract($node);
 * @endcode
 *
 * To register a new extractor, tag it in your services.yml:
 * @code
 *   my_module.extractor.html:
 *     class: Drupal\my_module\Extractor\HtmlExtractor
 *     tags:
 *       - { name: ai_content_audit.content_extractor, priority: 10 }
 * @endcode
 *
 * @see \Drupal\ai_content_audit\Extractor\ContentExtractorInterface
 */
class ContentExtractorManager {

  /**
   * The registered content extractors, keyed by mode string.
   *
   * @var \Drupal\ai_content_audit\Extractor\ContentExtractorInterface[]
   */
  protected array $extractors = [];

  /**
   * Adds a content extractor to the registry.
   *
   * Called by the DI container for each service tagged with
   * 'ai_content_audit.content_extractor'.
   *
   * @param \Drupal\ai_content_audit\Extractor\ContentExtractorInterface $extractor
   *   The extractor to register.
   */
  public function addExtractor(ContentExtractorInterface $extractor): void {
    $this->extractors[$extractor->getMode()] = $extractor;
  }

  /**
   * Returns the extractor for the given render mode.
   *
   * @param string $mode
   *   A RenderMode enum value string. Defaults to RenderMode::TEXT.
   *
   * @return \Drupal\ai_content_audit\Extractor\ContentExtractorInterface
   *   The matching extractor.
   *
   * @throws \InvalidArgumentException
   *   If no extractor is registered for the given mode.
   */
  public function getExtractorForMode(string $mode = ''): ContentExtractorInterface {
    if (empty($mode)) {
      $mode = RenderMode::default()->value;
    }

    if (!isset($this->extractors[$mode])) {
      $available = implode(', ', array_keys($this->extractors));
      throw new \InvalidArgumentException(
        sprintf(
          'No content extractor registered for render mode "%s". Available modes: %s.',
          $mode,
          $available ?: 'none'
        )
      );
    }

    return $this->extractors[$mode];
  }

  /**
   * Returns all registered render mode strings.
   *
   * @return string[]
   *   Array of registered mode strings (e.g., ['text', 'html']).
   */
  public function getAvailableModes(): array {
    return array_keys($this->extractors);
  }

  /**
   * Returns whether an extractor is registered for the given mode.
   *
   * @param string $mode
   *   A RenderMode enum value string.
   *
   * @return bool
   *   TRUE if an extractor is registered for the mode.
   */
  public function hasExtractorForMode(string $mode): bool {
    return isset($this->extractors[$mode]);
  }

}
