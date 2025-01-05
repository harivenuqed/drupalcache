<?php

declare(strict_types=1);

namespace Drupal\custom_module\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;

/**
 * Provides a preferred category block.
 *
 * @Block(
 *   id = "custom_module_preferred_category",
 *   admin_label = @Translation("Preferred Category Block"),
 *   category = @Translation("Custom"),
 * )
 */
final class PreferedCategoryBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a new PreferedCategoryBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Get the current user ID.
    $uid = $this->currentUser->id();

    // Load the user entity.
    $user = User::load($uid);

    $preferences = $preference_ids = [];
    $items = $nodeids = [];
    if ($user && $user->hasField('field_preference')) {
      // Get the taxonomy term IDs (target_id).
      $preferences = $user->get('field_preference')->getValue();

      $preference_ids = array_map(function($item) {
        return $item['target_id'];
      }, $preferences);

      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('status', 1) // Only published nodes
        ->condition('type', 'article') // Only articles
        ->condition('field_preference', $preference_ids, 'IN') // Only articles
        ->sort('created', 'DESC') // Sort by newest first
        ->accessCheck(FALSE); // Bypass access checks

      $nids = $query->execute();
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

      foreach ($nodes as $node) {
        $nodeids[] = 'node:' . $node->id();
        $items[] = [
          '#markup' => $node->toLink()->toString(), // Render the title as a link
        ];
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['last-three-articles']],
      '#cache' => [
        'tags' => ['node_list'] + $nodeids, // Add cache tags to clear when nodes are updated
        'contexts' => ['preferred_taxonomy'], // Custom cache context.
      ],
    ];
  }

}
