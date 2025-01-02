<?php

declare(strict_types=1);

namespace Drupal\custom_module\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a lastthreearticles block.
 *
 * @Block(
 *   id = "custom_module_lastthreearticles",
 *   admin_label = @Translation("Last Three Articles"),
 *   category = @Translation("Custom"),
 * )
 */
final class LastthreearticlesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new LastthreearticlesBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
 public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('status', 1) // Only published nodes
      ->condition('type', 'article') // Only articles
      ->sort('created', 'DESC') // Sort by newest first
      ->accessCheck(FALSE) // Bypass access checks
      ->range(0, 3); // Limit to 3 nodes

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $items = $nodeids = [];
    foreach ($nodes as $node) {
      $nodeids[] = 'node:' . $node->id();
      $items[] = [
        '#markup' => $node->toLink()->toString(), // Render the title as a link
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['last-three-articles']],
      '#cache' => [
        'tags' => ['node_list'] + $nodeids, // Add cache tags to clear when nodes are updated
        'contexts' => ['url'],  // Add cache contexts for dynamic results
      ],
    ];
  }

}
