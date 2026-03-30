<?php

declare(strict_types=1);

namespace Drupal\ai_content_audit\Controller;

use Drupal\ai_content_audit\Repository\AiContentAssessmentRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the AI assessment history tab on node pages.
 */
class AiAssessmentController extends ControllerBase {

  public function __construct(
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly AiContentAssessmentRepository $assessmentRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('date.formatter'),
      $container->get('ai_content_audit.assessment_repository'),
    );
  }

  /**
   * Renders the assessment history for a node.
   *
   * The {node} parameter is automatically upcasted to NodeInterface by
   * Drupal's EntityConverter ParamConverter.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose assessment history to display.
   *
   * @return array
   *   A render array containing the history table.
   */
  public function history(NodeInterface $node): array {
    $assessments = $this->assessmentRepository->getAllForNode((int) $node->id(), 25);

    $rows = [];
    foreach ($assessments as $assessment) {
      $rows[] = [
        $this->dateFormatter->format(
          (int) $assessment->get('created')->value,
          'short',
        ),
        $assessment->getScore() . '/100',
        $assessment->get('provider_id')->value,
        $assessment->get('model_id')->value,
         [
           'data' => [
             '#type'  => 'link',
             '#title' => $this->t('View'),
             '#url'   => Url::fromRoute('entity.ai_content_assessment.canonical', ['ai_content_assessment' => $assessment->id()]),
           ],
         ],
      ];
    }

    $build['table'] = [
      '#type'   => 'table',
      '#header' => [
        $this->t('Date'),
        $this->t('Score'),
        $this->t('Provider'),
        $this->t('Model'),
        $this->t('Operations'),
      ],
      '#rows'  => $rows,
      '#empty' => $this->t('No assessments have been run for this node yet.'),
    ];

    $build['#cache'] = [
      'tags'     => array_merge(
        $node->getCacheTags(),
        ['ai_content_assessment_list:node:' . $node->id()],
      ),
      'contexts' => ['url'],
    ];

    return $build;
  }

}
