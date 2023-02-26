<?php

namespace Drupal\token_filter_remove_p_wrapper\Plugin\Filter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Utility\Token;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\token\TokenEntityMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter that removes the <p> wrapper from around tokens.
 *
 * @Filter(
 *   id = "token_filter_remove_p_wrapper",
 *   title = @Translation("Remove the &lt;p&gt; wrapper from around tokens"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class TokenRemoveWrapperFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The token entity mapper service.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a token filter plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mapper
   *   The token entity mapper service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The route match service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Token $token, TokenEntityMapperInterface $token_entity_mapper, RendererInterface $renderer, RouteMatchInterface $current_route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
    $this->tokenEntityMapper = $token_entity_mapper;
    $this->renderer = $renderer;
    $this->routeMatch = $current_route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token'),
      $container->get('token.entity_mapper'),
      $container->get('renderer'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $entity = drupal_static('token_filter_entity', NULL);
    $cache = new BubbleableMetadata();
    if (!is_null($entity) && $entity instanceof ContentEntityInterface) {
      $cache->addCacheableDependency($entity);
    }

    $tokens = $this->token->scan($text);

    foreach($tokens as $tokentype) {
      foreach ($tokentype as $token) {
        $text = str_replace("<p>$token</p>", $token, $text);
      }
    }

    return (new FilterProcessResult($text))->merge($cache);
  }

}
