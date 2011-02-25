<?php

$plugin = array(
  'form'     => 'feeds_tamper_required_form',
  'callback' => 'feeds_tamper_required_callback',
  'name'     => 'Required field',
  'multi'    => 'direct',
  'category' => 'Filter',
);

function feeds_tamper_required_form($importer, $source, $settings) {
  $form = array();
  $form['help'] = array(
    '#markup' => t('Makes this field required. If it is empty, the feed item will not be saved.'),
  );
  return $form;
}

function feeds_tamper_required_callback($source, $item_key, $element_key, &$field, $values) {
  if (empty($field)) {
    unset($source->batch->items[$item_key]);
  }
}
