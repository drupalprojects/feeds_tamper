<?php

/**
 * @file
 * Version agnostic parts of feeds_tamper.module.
 */

/**
 * Removes plugin instances whos source was removed.
 *
 * @param FeedsImporter $importer
 *   The importer to rectify against.
 */
function feeds_tamper_rectify_instances(FeedsImporter $importer) {
  ctools_include('export');
  $instances = ctools_export_load_object('feeds_tamper', 'conditions', array('importer' => $importer->id));
  $sources = feeds_tamper_get_unique_source_list($importer, FALSE);

  foreach ($instances as $instance) {
    if (in_array($instance->source, $sources)) {
      continue;
    }

    if ($instance->export_type == EXPORT_IN_DATABASE) {
      feeds_tamper_delete_instance($instance);
    }
    else {
      $instance->disabled = TRUE;
      feeds_tamper_save_instance($instance);
    }
  }
}

/**
 * Returns a unique list of sources in order.
 *
 * @param FeedsImporter $importer
 *   The importer.
 *
 * @return array
 *   A list of sources.
 */
function feeds_tamper_get_unique_source_list($importer, $lower = TRUE) {
  $sources = array();
  $is_csv = $lower && ($importer->parser instanceof FeedsCSVParser);

  foreach ($importer->processor->getMappings() as $mapping) {
    if ($is_csv) {
      $sources[] = drupal_strtolower($mapping['source']);
    }
    else {
      $sources[] = $mapping['source'];
    }
  }

  return array_unique($sources);
}

/**
 * Return a machine name safe version of a string.
 *
 * @param string $string
 *   String to get machine nameized.
 *
 * @return string
 *   A lowercase string with all values not in [a-zA-Z0-9] replaced with an
 *   underscore and shortened to 128 characters.
 */
function feeds_tamper_make_machine($string) {
  return drupal_substr(preg_replace('/[^a-z0-9-]/u', '_', drupal_strtolower($string)), 0, 127);
}

/**
 * @} End of "feeds_tamper_api".
 */

/**
 * Menu access callback.
 *
 * @param string|FeedsImporter $importer
 *   The importer or importer id being tampered with.
 * @param string|stdClass $instance
 *   (optional) If set, the importer attached to $instance will be tried first.
 *   Defaults to NULL.
 *
 * @return bool
 *   TRUE if the user has acces, FALSE if not.
 */
function feeds_tamper_access($importer, $instance = NULL) {
  if (isset($instance)) {
    if (is_object($instance)) {
      $importer = $instance->importer;
    }
    else {
      $importer = feeds_tamper_load_instance($instance)->importer;
    }
  }
  elseif (is_object($importer)) {
    $importer = $importer->id;
  }

  // Verify actual input if above failed.
  if ($importer) {
    // Check for permissions, otherwise return FALSE.
    if (user_access('administer feeds_tamper') || user_access('tamper ' . $importer)) {
      return TRUE;
    }
  }
  return FALSE;
}
