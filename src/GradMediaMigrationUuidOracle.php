<?php

namespace Drupal\grad_migration;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Entity\EntityTypeManagerInterface;

use Drupal\media_migration\MediaMigrationUuidOracleInterface;

/**
 * Our custom Media Migration's UUID oracle.
 *
 * NOTE: The main class from media_migration is not extended here in this case because
 * MediaMigrationUuidOracle uses the final class keyword, which prevents it from being
 * extended. So, most of the code here is copy-pasted from that class and then modified
 * to suit our use case and fix the issue of not properly translating source-destination
 * IDs.
 *
 * Predicts the UUID property of a media entity that does not yet exist.
 */
final class GradMediaMigrationUuidOracle implements MediaMigrationUuidOracleInterface
{

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
    protected $database;

    /**
     * The media entity storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $mediaStorage;

    /**
     * The UUID generator service.
     *
     * @var \Drupal\Component\Uuid\UuidInterface
     */
    protected $uuidGenerator;

    /**
     * Constructs MediaMigrationUuidOracle.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
     *   The UUID generator service.
     */
    public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, UuidInterface $uuid_generator)
    {
        $this->database = $database;
        $this->mediaStorage = $entity_type_manager->getStorage('media');
        $this->uuidGenerator = $uuid_generator;
    }

    /**
     * {@inheritdoc}
     */
    public function getMediaUuid(int $source_id, bool $generate = true): ?string
    {
        $query = $this->database
          ->select('migrate_map_ua_gc_gcof_file', 'map')
          ->condition('sourceid1', $source_id);
        $query->join('media__field_media_az_image', 'media_az_image', 'media_az_image.field_media_az_image_target_id = map.destid1');
        $query->fields('media_az_image', ['entity_id']);
        $destination_id = $query->execute()->fetchField();

        if ($destination_id && $media = $this->mediaStorage->load($destination_id)) {
            return $media->uuid();
        }

        // If the media does not exist, try to get its UUID from our prophecy table.
        if (!($uuid_prophecy = $this->getMediaUuidProphecy($source_id)) && $generate) {
            $uuid_prophecy = $this->setMediaProphecy($source_id);
        }

        return $uuid_prophecy;
    }

    /**
     * Returns the UUID prophecy if it exists.
     *
     * @param int $source_id
     *   The source media entity's identifier.
     *
     * @return string|null
     *   The UUID, or NULL if it does not exist at the moment.
     */
    private function getMediaUuidProphecy(int $source_id): ?string
    {
        $results = $this->database->select(MediaMigration::MEDIA_UUID_PROPHECY_TABLE, 'mupt')
      ->fields('mupt')
      ->condition('mupt.' . MediaMigration::MEDIA_UUID_PROPHECY_SOURCEID_COL, $source_id)
      ->execute()->fetchAll();

        return isset($results[0]->{MediaMigration::MEDIA_UUID_PROPHECY_UUID_COL}) ?
      $results[0]->{MediaMigration::MEDIA_UUID_PROPHECY_UUID_COL} :
      null;
    }

    /**
     * Saves a UUID prophecy if it doesn't exist.
     *
     * @param int $source_id
     *   The source media entity's identifier.
     *
     * @return string
     *   The UUID to save.
     *
     * @throws \Exception
     */
    private function setMediaProphecy(int $source_id)
    {
        $uuid = $this->uuidGenerator->generate();

        try {
            $this->database->insert(MediaMigration::MEDIA_UUID_PROPHECY_TABLE)
        ->fields([
          MediaMigration::MEDIA_UUID_PROPHECY_SOURCEID_COL => $source_id,
          MediaMigration::MEDIA_UUID_PROPHECY_UUID_COL => $uuid,
        ])
        ->execute();

            return $uuid;
        } catch (DatabaseExceptionWrapper $e) {
            throw new \LogicException(sprintf('Cannot create prophecy for the media entity with source id %i', $source_id));
        }
    }
}
