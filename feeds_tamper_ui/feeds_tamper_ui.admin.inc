<?php

/**
 * @file
 * Forms and their accompanying validation and submit functions for Feeds Tamper
 * UI.
 */

function feeds_tamper_ui_list_form(&$form_state, $importer) {
  $mappings = $importer->processor->config['mappings'];
  $instances = feeds_tamper_load_by_importer($importer->id, TRUE);

  $form = array();
  $form['mappings'] = array();
  $form['#importer'] = $importer;

  foreach ($mappings as $mapping) {
    $form['mappings'][$mapping['source']] = array(
      '#type' => 'fieldset',
      '#title' => check_plain($mapping['source']) . ' -> ' . check_plain($mapping['target']),
      '#collapsible' => TRUE,
      '#tree' => TRUE,
    );
    $form['mappings'][$mapping['source']]['table'] = array();

    if (!isset($instances[$mapping['source']])) {
      $instances[$mapping['source']] = array();
    }
    foreach ($instances[$mapping['source']] as $instance) {
      $plugin = feeds_tamper_get_plugin($instance->plugin_id);
      if ($instance->export_type == EXPORT_IN_CODE) {
        $status = t('Default');
        $edit = t('Override');
        $delete = '';
      }
      elseif ($instance->export_type == EXPORT_IN_DATABASE) {
        $status = t('Normal');
        $edit = t('Edit');
        $delete = t('Delete');
      }
      elseif ($instance->export_type == (EXPORT_IN_CODE | EXPORT_IN_DATABASE)) {
        $status = t('Overridden');
        $edit = t('Edit');
        $delete = t('Revert');
      }
      $instance->disabled = !isset($instance->disabled) ? FALSE : $instance->disabled;
      $checkbox = array('#type' => 'checkbox', '#default_value' => !$instance->disabled);
      $form['mappings'][$mapping['source']]['table'][$instance->id]['enabled'] = $checkbox;
      $data = array(
        !empty($instance->description) ? check_plain($instance->description) : $instance->id,
        $plugin['name'],
        $status,
        l(t($edit), 'admin/build/feeds/tamper/edit/' . $instance->id) .
        (empty($delete) ? '' :  ' | ' . l(t($delete), 'admin/build/feeds/tamper/delete/'. $instance->id)),
      );
      $form['mappings'][$mapping['source']]['table'][$instance->id]['data'] = array(
        '#type' => 'value',
        '#value' => $data,
      );
      $form['mappings'][$mapping['source']]['table'][$instance->id]['weight-' . $instance->id] = array(
        '#type' => 'textfield',
        '#size' => 5,
        '#default_value' => $instance->weight,
        '#attributes' => array('class' => 'weight'),
      );
    }
    $add = l(t('Add plugin'), 'admin/build/feeds/tamper/add/' . $importer->id . '/' . $mapping['source']);
    $form['mappings'][$mapping['source']]['add_link']['#value'] = $add;
  }
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save changes'),
  );
  return $form;
}

function feeds_tamper_ui_list_form_submit($form, &$form_state) {
  $mappings = element_children($form['mappings']);
  $disabled = array();
  foreach ($mappings as $mapping) {
    if (isset($form_state['values'][$mapping])) {
      foreach ($form_state['values'][$mapping]['table'] as $id => $value) {
        $instance = feeds_tamper_load_instance($id);
        $weight = (int) $value['weight-' . $id];
        $disabled[$id] = !$value['enabled'];
        if ($instance->weight != $weight) {
          $instance->weight = $weight;
          ctools_export_crud_save('feeds_tamper', $instance);
        }
      }

    }
  }
  variable_set('default_feeds_tamper', $disabled);
}

function theme_feeds_tamper_ui_list_form($form) {
  $header = array(t('Description'), t('Plugin'), t('Status'), t('Operations'), t('Enabled'), t('Weight'));
  $output = '';
  $mappings = element_children($form['mappings']);
  if (empty($mappings)) {
    drupal_set_message(t('There are no !mappings defined for this importer.', array('!mappings' => l(t('mappings'), 'admin/build/feeds/edit/' . $form['#importer']->id . '/mapping'))), 'warning');
    return $output;
  }
  foreach ($mappings as $source) {
    $table_rows = array();
    $children = element_children($form['mappings'][$source]['table']);
    if (empty($children)) {
      $help_text = t('No plugins defined for this source.');
      $table_rows[] = array('data' => array(array('data' => $help_text, 'colspan' => 6)));
    }
    else {
      foreach ($children as $id) {
        $this_row = $form['mappings'][$source]['table'][$id]['data']['#value'];
        $this_row[] = drupal_render($form['mappings'][$source]['table'][$id]['enabled']);
        $this_row[] = drupal_render($form['mappings'][$source]['table'][$id]['weight-' . $id]);
        $table_rows[] = array('data' => $this_row, 'class' => 'draggable');
      }
    }
    $table_id = preg_replace('/[^a-z0-9-]/', '',
      preg_replace('/_+/', '-',
        strtolower($source . '-table')
      )
    );
    $table = theme_table($header, $table_rows, array('id' => $table_id));
    $form['mappings'][$source]['#value'] = $table;
    drupal_add_tabledrag($table_id, 'order', 'sibling', 'weight');
    $form['mappings'][$source]['#value'] .= $form['mappings'][$source]['add_link']['#value'];
    unset($form['mappings'][$source]['add_link']);
    $output .= drupal_render($form['mappings'][$source]);
  }
  $output .= drupal_render($form);
  return $output;
}

function feeds_tamper_ui_add_plugin_form(&$form_state, $importer, $source) {
  // Set breadcrumb.
  $crumb = drupal_get_breadcrumb();
  $crumb[] = l(t('Tamper: @importer', array('@importer' => $importer->config['name'])), 'admin/build/feeds/tamper/list/' . $importer->id);
  drupal_set_breadcrumb($crumb);

  // Set importer and source for use in validate and submit.
  $form_state['storage']['importer'] = $importer;
  $form_state['storage']['source'] = $source;

  // Build plugin select list.
  $feeds_tamper_plugins = feeds_tamper_get_plugins();
  $plugins = array();
  foreach ($feeds_tamper_plugins as $plugin_id => $plugin) {
    $plugins[t($plugin['category'])][$plugin_id] = t($plugin['name']);
  }
  ksort($plugins);
  foreach ($plugins as &$p) {
    asort($p);
  }

  $form = array();

  $machine_name = key(reset($plugins));
  if (!empty($form_state['values']['plugin_id'])) {
    $machine_name = $form_state['values']['plugin_id'];
  }

  $plugin = feeds_tamper_get_plugin($machine_name);

  drupal_add_js(drupal_get_path('module', 'feeds_ui') .'/feeds_ui.js');

  $form['plugin_id'] = array(
    '#title' => 'The plugin to add',
    '#type' => 'select',
    '#options' => $plugins,
    '#default_value' => '',
    '#tree' => TRUE,
    '#ahah' => array(
      'path'    => 'feeds_tamper/js',
      'wrapper' => 'plugin-wrapper',
    ),
    '#suffix' => '<br/ >',
  );
  $form['update'] = array(
    '#type'  => 'submit',
    '#value' => t('Update plugin form'),
    '#submit' => array('feeds_tamper_ui_ajax_submit'),
    '#attributes' => array('class' => 'no-js'),
  );
  $form['plugin']['#prefix'] = '<div id="plugin-wrapper">';
  $form['plugin']['#suffix'] = '</div>';

  $form['plugin']['description'] = array(
    '#title' => t('Description'),
    '#type' => 'textfield',
    '#default_value' => !empty($plugin['default description']) ? $plugin['default description'] : $plugin['name'],
    '#required' => TRUE,
    '#description' => t('A useful description of what this plugin is doing.'),
    '#tree' => TRUE,
    '#attributes' => array('class' => 'feed-name'),
    '#required' => TRUE,
  );
  $form['plugin']['id'] = array(
    '#title' => t('Machine name'),
    '#type' => 'textfield',
    '#default_value' => '',
    '#maxlength' => 32,
    '#disabled' => FALSE,
    '#tree' => TRUE,
    '#attributes' => array('class' => 'feed-id'),
    '#required' => TRUE,
  );
  $form['plugin']['settings'] = array(
    '#title' => t('Configure @name', array('@name' => $plugin['name'])),
    '#type' => 'fieldset',
    '#tree' => TRUE,
  );
  $form['plugin']['settings'] += $plugin['form']($importer, $source, array());

  $form['add'] = array(
    '#type' => 'submit',
    '#value' => t('Add'),
  );
  return $form;
}

function feeds_tamper_ui_add_plugin_form_validate($form, &$form_state) {
  $plugin_id = $form_state['values']['plugin_id'];
  $plugin = feeds_tamper_get_plugin($plugin_id);
  if (!empty($plugin['validate']) && isset($form_state['values']['settings'])) {
    $plugin['validate']($form_state['values']['settings']);
  }
  $form_state['values']['id'] =
    $form_state['storage']['importer']->id .
    '-' .
    feeds_tamper_make_machine($form_state['storage']['source']) .
    '-' .
    $form_state['values']['id'];
  if (feeds_tamper_load_instance($form_state['values']['id'])) {
    form_set_error('id', t('Id is taken.'));
  }
}

function feeds_tamper_ui_add_plugin_form_submit($form, &$form_state) {
  $obj = new stdClass();
  $obj->plugin_id = $form_state['values']['plugin_id'];
  if (isset($form_state['values']['settings'])) {
    $obj->settings = $form_state['values']['settings'];
  }
  $obj->importer = $form_state['storage']['importer']->id;
  $obj->source = $form_state['storage']['source'];
  $obj->export_type = NULL;
  $obj->description = $form_state['values']['description'];
  $obj->id = $form_state['values']['id'];
  unset($form_state['storage']);

  feeds_tamper_save_instance($obj);
  $form_state['redirect'] = 'admin/build/feeds/tamper/list/' . $obj->importer;
}

function feeds_tamper_ui_edit_plugin_form(&$form_state, $instance) {
  // Set breadcrumb.
  $importer = feeds_importer($instance->importer);
  $crumb = drupal_get_breadcrumb();
  $crumb[] = l(t('Tamper: @importer', array('@importer' => $importer->config['name'])), 'admin/structure/feeds/tamper/list/' . $instance->importer);
  drupal_set_breadcrumb($crumb);

  $form_state['storage']['instance'] = $instance;
  $plugin = feeds_tamper_get_plugin($instance->plugin_id);
  $form = array();
  $form['#tree'] = TRUE;

  $form['description'] = array(
    '#title' => t('Description'),
    '#type' => 'textfield',
    '#description' => t('A useful description of what this plugin is doing.'),
    '#default_value' => $instance->description,
  );
  $form['settings'] = array(
    '#title' => t('Configure @plugin', array('@plugin' => $plugin['name'])),
    '#type' => 'fieldset',
    '#tree' => TRUE,
  );
  $form['settings'] += $plugin['form']($importer, $instance->source, $instance->settings);

  $form['save'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  return $form;
}

function feeds_tamper_ui_edit_plugin_form_validate($form, &$form_state) {
  $plugin_id = $form_state['storage']['instance']->plugin_id;
  $plugin = feeds_tamper_get_plugin($plugin_id);
  if (!empty($plugin['validate'])) {
    $plugin['validate']($form_state['values']['settings']);
  }
}

function feeds_tamper_ui_edit_plugin_form_submit($form, &$form_state) {
  $instance = $form_state['storage']['instance'];
  if (isset($form_state['values']['settings'])) {
    $instance->settings = $form_state['values']['settings'];
  }
  $instance->description = $form_state['values']['description'];

  feeds_tamper_save_instance($instance);
  unset($form_state['storage']);
  $form_state['redirect'] = 'admin/build/feeds/tamper/list/' . $instance->importer;
}

function feeds_tamper_ui_delete_form(&$form_state, $instance) {
  $form = array();
  // Set breadcrumb.
  $importer = feeds_importer($instance->importer);
  $crumb = drupal_get_breadcrumb();
  $crumb[] = l(t('Tamper: @importer', array('@importer' => $importer->config['name'])), 'admin/structure/feeds/tamper/list/' . $instance->importer);
  drupal_set_breadcrumb($crumb);

  $form_state['storage']['instance'] = $instance;

  if ($instance->export_type & EXPORT_IN_CODE) {
    $question = t('Would you really like to revert the plugin @instance?', array('@instance' => $instance->description));
    $button_label = t('Revert');
  }
  else {
    $question = t('Would you really like to delete the plugin @instance?', array('@instance' => $instance->description));
    $button_label = t('Delete');
  }
  return confirm_form(
    $form,
    $question,
    'admin/build/feeds/tamper/list/' . $instance->importer,
    NULL,
    $button_label
  );
}

function feeds_tamper_ui_delete_form_submit($form, &$form_state) {
  $instance = $form_state['storage']['instance'];
  feeds_tamper_delete_instance($instance);
  unset($form_state['storage']);
  $form_state['redirect'] = 'admin/build/feeds/tamper/list/' . $instance->importer;
}

/**
 * Ajax callback for add form.
 */
function feeds_tamper_ui_js() {
  $form_state = array('storage' => NULL, 'submitted' => FALSE);
  $form_build_id = $_POST['form_build_id'];
  $form = form_get_cache($form_build_id, $form_state);
  $args = $form['#parameters'];
  $form_id = array_shift($args);
  $form_state['post'] = $form['#post'] = $_POST;
  $form['#programmed'] = $form['#redirect'] = FALSE;
  $form_state['submitted'] = TRUE;
  if (!isset($_POST['op'])) {
    $form['plugin']['description']['#required'] = FALSE;
    $form['plugin']['id']['#required'] = FALSE;
    $form['#validate'] = array();
    $form['#submit'] = array();
  }
  drupal_process_form($form_id, $form, $form_state);
  $form = drupal_rebuild_form($form_id, $form_state, $args, $form_build_id);
  $plugin_form = $form['plugin'];
  unset($plugin_form['#prefix'], $plugin_form['#suffix']); // Prevent duplicate wrappers.
  $javascript = drupal_add_js(NULL, NULL, 'header');
  $settings = call_user_func_array('array_merge_recursive', $javascript['setting']);
  drupal_json(array(
    'status'   => TRUE,
    'data'     => theme('status_messages') . drupal_render($plugin_form),
    'settings' => $settings,
  ));
}

function feeds_tamper_ui_ajax_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}
