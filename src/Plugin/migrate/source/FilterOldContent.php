<?php

namespace Drupal\grad_migration\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node as D7Node;

/**
 * Drupal 7 file source (optionally filtered by type) from database.
 *
 * @MigrateSource(
 *   id = "filter_old_content"
 * )
 */
class FilterOldContent extends D7Node
{

  /**
   * {@inheritdoc}
   */
    public function query()
    {
        /**
         * Filter date from migration script can be a
         * string date or a UNIX timestamp so get
         * it into correct format
         */
        if (is_int($this->configuration['filter_date'])) {
            $date_cutoff = $this->configuration['filter_date'];
        } else {
            $date_cutoff = (new \DateTime($this->configuration['filter_date']))->getTimestamp();
        }

        $query = parent::query();
        $query->condition('n.changed', $date_cutoff, '>');

        return $query;
    }
}
