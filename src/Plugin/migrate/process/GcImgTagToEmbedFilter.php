<?php

namespace Drupal\grad_migration\Plugin\migrate\process;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Database\Connection;
use \Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\media_migration\MediaMigration;
use Drupal\media_migration\MediaMigrationUuidOracleInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\Row;
use Masterminds\HTML5;
use Masterminds\HTML5\Parser\StringInputStream;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\grad_migration\GradMediaMigrationUuidOracle;
use Drupal\media_migration\Plugin\migrate\process\ImgTagToEmbedFilter;

/**
 * Transforms <img src="/files/cat.png"> tags to <drupal-media …>.
 *
 * @MigrateProcessPlugin(
 *   id = "gc_img_tag_to_embed"
 * )
 */
class GcImgTagToEmbedFilter extends ImgTagToEmbedFilter
{


    /**
     * Constructs a new GcImgTagToEmbedFilter object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
     *   The migration entity.
     * @param \Drupal\media_migration\MediaMigrationUuidOracleInterface $media_uuid_oracle
     *   The media UUID oracle.
     * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
     *   The logger.
     * @param \Drupal\entity_embed\EntityEmbedDisplay\EntityEmbedDisplayManager|null $entity_embed_display_manager
     *   The entity embed display plugin manager service, if available.
     * @param \Drupal\migrate\MigrateLookupInterface $migrate_lookup
     *   The migration lookup service.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     */
    public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      MigrationInterface $migration,
      MediaMigrationUuidOracleInterface $media_uuid_oracle,
      LoggerChannelInterface $logger,
      $entity_embed_display_manager,
      MigrateLookupInterface $migrate_lookup,
      EntityTypeManagerInterface $entity_type_manager)
    {
        parent::__construct(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $migration,
          $media_uuid_oracle,
          $logger,
          $entity_embed_display_manager,
          $migrate_lookup,
          $entity_type_manager);
    }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('media_migration.media_uuid_oracle'),
      $container->get('logger.channel.media_migration'),
      $container->get('plugin.manager.entity_embed.display', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get('migrate.lookup'),
      $container->get('entity_type.manager')
    );
  }



    /**
     * Creates a DOM element representing an embed media on the destination.
     *
     * @param \DOMDocument $dom
     *   The \DOMDocument in which the embed \DOMElement is being created.
     * @param string|int $file_id
     *   The ID of the file which should be represented by the new embed tag.
     *
     * @return \DOMElement
     *   The new embed tag as a writable \DOMElement.
     */
    protected function createEmbedNode(\DOMDocument $dom, $file_id)
    {
        $filter_destination_is_entity_embed = $this->destinationFilterPluginId === MediaMigration::MEDIA_TOKEN_DESTINATION_FILTER_ENTITY_EMBED;
        $tag = $filter_destination_is_entity_embed ?
        'drupal-entity' :
        'drupal-media';
        $display_mode_attribute = $filter_destination_is_entity_embed ?
        'data-entity-embed-display' :
        'data-view-mode';
        $embed_node = $dom->createElement($tag);
        $embed_node->setAttribute('data-entity-type', 'media');
        if (MediaMigration::getEmbedMediaReferenceMethod() === MediaMigration::EMBED_MEDIA_REFERENCE_METHOD_ID) {
            $embed_node->setAttribute('data-entity-id', $file_id);
        } else {
            $embed_node->setAttribute('data-entity-uuid', $this->mediaUuidOracle->getMediaUuid((int) $file_id));
        }
        $embed_node->setAttribute($display_mode_attribute, 'default');
        if ($filter_destination_is_entity_embed) {
            $embed_node->setAttribute('data-embed-button', 'media');
        }
        $embed_node->setAttribute($display_mode_attribute, $this->getDisplayPluginId('default', $this->destinationFilterPluginId));

        return $embed_node;
    }
}
