<?php

declare(strict_types=1);

namespace Drupal\custom_module\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\user\ContextProvider\CurrentUserContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an user email block.
 *
 * @Block(
 *   id = "custom_module_user_email",
 *   admin_label = @Translation("User Email"),
 *   category = @Translation("Custom"),
 * )
 */
final class UserEmailBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly CurrentUserContext $userCurrentUserContext,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.current_user_context'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
      // Get the current user from the context.
      $current_user = $this->userCurrentUserContext->getAvailableContexts()['current_user']->getContextData()->getEntity();

      // Check if the user entity is available and fetch the email address.
      $email = $current_user?->getEmail() ?? $this->t('No email available');

      // Build the render array with the user's email.
      $build['content'] = [
        '#markup' => $this->t('Current user email: @email', ['@email' => $email]),
      ];

      // Add cache context for the current user.
      $build['#cache'] = [
        'contexts' => ['user'],
      ];

      return $build;
  }

}
