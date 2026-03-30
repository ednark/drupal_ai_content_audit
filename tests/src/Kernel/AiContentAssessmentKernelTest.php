<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_content_audit\Kernel;

use Drupal\ai_content_audit\AiContentAssessmentListBuilder;
use Drupal\ai_content_audit\Entity\AiContentAssessment;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use ReflectionProperty;

/**
 * Kernel coverage for AI Content Assessment behaviors.
 */
final class AiContentAssessmentKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'node',
    'text',
    'ai_content_audit',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('ai_content_assessment');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
  }

  /**
   * Verifies that nullable scores persist and round-trip through getScore().
   */
  public function testScoreNullabilityPersists(): void {
    $user = $this->createUserWithName('Assessing User');
    $node = $this->createNodeForUser($user, 'Scored node');

    $assessment = $this->createAssessment($node, $user, NULL);

    $storage = $this->assessmentStorage();
    $reloaded = $storage->load($assessment->id());

    $this->assertNotNull($reloaded);
    $this->assertNull($reloaded->get('score')->value, 'Database value should remain NULL.');
    $this->assertNull($reloaded->getScore(), 'Accessor must return NULL for an unset score.');

    $reloaded->set('score', 88);
    $reloaded->save();
    $this->assertSame(88, $storage->load($reloaded->id())->getScore());
  }

  /**
   * Ensures list builder prefetches users and renders cells without N+1 queries.
   */
  public function testListBuilderPrefetchesRunByUsersAndRendersFallbacks(): void {
    $user = $this->createUserWithName('List Builder Runner');
    $node = $this->createNodeForUser($user, 'Example node');

    $withUser = $this->createAssessment($node, $user, NULL);
    $withoutUser = $this->createAssessment($node, NULL, 50);

    $list_builder = $this->container->get('entity_type.manager')->getListBuilder('ai_content_assessment');
    assert($list_builder instanceof AiContentAssessmentListBuilder);

    $entities = $list_builder->load();
    $this->assertArrayHasKey($withUser->id(), $entities);
    $this->assertArrayHasKey($withoutUser->id(), $entities);

    $reflection = new ReflectionProperty($list_builder, 'runByUsers');
    $reflection->setAccessible(TRUE);
    $run_by_users = $reflection->getValue($list_builder);

    $this->assertArrayHasKey($user->id(), $run_by_users, 'Run-by users should be prefetched once.');
    $this->assertSame($user->id(), $run_by_users[$user->id()]->id());

    $row_with_user = $list_builder->buildRow($entities[$withUser->id()]);
    $this->assertSame('List Builder Runner', $row_with_user['run_by']);
    $this->assertSame('n/a', (string) $row_with_user['score']);
    $this->assertSame('link', $row_with_user['node']['data']['#type']);
    $this->assertSame('Example node', $row_with_user['node']['data']['#title']);

    $row_without_user = $list_builder->buildRow($entities[$withoutUser->id()]);
    $this->assertSame('50/100', $row_without_user['score']);
    $this->assertSame('Cron/queue', (string) $row_without_user['run_by']);
  }

  /**
   * Provides convenient access to the assessment storage.
   */
  private function assessmentStorage(): EntityStorageInterface {
    return $this->container->get('entity_type.manager')->getStorage('ai_content_assessment');
  }

  /**
   * Creates and saves an assessment entity for testing.
   */
  private function createAssessment(Node $node, ?User $user, ?int $score): AiContentAssessment {
    $values = [
      'target_node' => $node->id(),
      'provider_id' => 'openai',
      'model_id' => 'gpt-4o-mini',
    ];

    if ($user) {
      $values['run_by'] = $user->id();
    }

    $assessment = $this->assessmentStorage()->create($values);
    $assessment->set('score', $score);
    $assessment->save();

    // Reload to mimic real storage round-trips.
    return $this->assessmentStorage()->load($assessment->id());
  }

  /**
   * Creates and saves a user entity.
   */
  private function createUserWithName(string $name): User {
    $user = User::create([
      'name' => $name,
    ]);
    $user->save();
    return $user;
  }

  /**
   * Creates and saves a node for the provided user.
   */
  private function createNodeForUser(User $user, string $title): Node {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'uid' => $user->id(),
    ]);
    $node->save();
    return $node;
  }

}
