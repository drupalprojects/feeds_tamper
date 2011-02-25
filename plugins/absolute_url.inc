<?php

$plugin = array(
  'form' => 'feeds_tamper_absolute_url_form',
  'callback' => 'feeds_tamper_absolute_url_callback',
  'name' => 'Make URLs absolute',
  'category' => 'HTML',
  'description' => 'feeds_tamper_absolute_url_description',
  'machine_name' => 'feeds_tamper_absolute_url_machine_name',
);

function feeds_tamper_absolute_url_form($importer, $source, $settings) {
  $form = array();

  $form['info'] = array(
    '#markup' => t('This plugin has no options.<br>'),
  );

  return $form;
}

function feeds_tamper_absolute_url_description($settings) {
  return 'Make URLs absolute.';
}

function feeds_tamper_absolute_url_machine_name($settings) {
  return 'make_urls_absolute';
}

/**
 * @todo
 *   Make this not suck.
 */
function feeds_tamper_absolute_url_callback($source, $item_key, $element_key, &$field, $values) {
  static $base_url = NULL;

  if (!$base_url) {
    $base_url = rtrim($source->batch->link, '/') . '/';
  }

  preg_match_all('/<.*(href|src)(\s*=\s*)(\'|")(.*)("|\').*>/', $field, $matches, PREG_SET_ORDER);
  foreach ($matches as $match) {
    if (!stripos($match[4], 'http://') === 0 && !stripos($match[4], 'https://') === 0) {
      $find_text = implode('', $match);
      $match[4] = $base_url . ltrim($match[4], '/');
      $replace_text = implode('', $match);
      $field = str_replace($find_text, $replace_text, $field);
    }
  }
}
