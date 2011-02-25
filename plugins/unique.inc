<?php

$plugin = array(
  'form'     => 'feeds_tamper_unique_form',
  'callback' => 'feeds_tamper_unique_callback',
  'name'     => 'Unique',
  'multi'    => 'direct',
  'single'   => 'skip',
  'category' => 'List',
);

function feeds_tamper_unique_form($importer, $source, $settings) {
  $form = array();

  $form['#markup'] = t('Makes the elements in a multivalued field unique.');

  return $form;
}

function feeds_tamper_unique_callback($source, $item_key, $element_key, &$field, $values) {
  $unique = array();
  foreach ($field as $f) {
    if (!in_array($f, $unique)) {
      $unique[] = $f;
    }
  }
  $field = $unique;
}
