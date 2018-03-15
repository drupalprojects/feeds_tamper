<?php

namespace Drupal\feeds_tamper\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\feeds\FeedTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to manage tamper plugins for a feed type.
 *
 * @package Drupal\feeds_tamper\Form
 */
class TamperListForm extends FormBase {

  use TamperFormTrait;

  /**
   * An array of the feed type's tamper plugins.
   *
   * @var array
   */
  protected $tampers;

  /**
   * An array of the feed type's sources.
   *
   * @var array
   */
  protected $sources;

  /**
   * An array of the feed type's targets.
   *
   * @var array
   */
  protected $targets;

  /**
   * An array of the grouped mappings.
   *
   * @var array
   */
  protected $groupedMappings;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var self $form */
    $form = parent::create($container);
    $form->setTamperMetaManager($container->get('feeds_tamper.feed_type_tamper_manager'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'feeds_tamper_list_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\feeds\FeedTypeInterface $feeds_feed_type
   *   The feed that we are adding a tamper plugin to.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, FeedTypeInterface $feeds_feed_type = NULL) {
    $this->feedsFeedType = $feeds_feed_type;
    $tamper_meta = $this->feedTypeTamperManager->getTamperMeta($this->feedsFeedType);
    $this->tampers = $tamper_meta->getTampersGroupedBySource();
    $this->sources = $this->feedsFeedType->getMappingSources();
    $this->targets = $this->feedsFeedType->getMappingTargets();
    $mappings = $this->feedsFeedType->getMappings();
    $args = [
      '@url' => Url::fromRoute('entity.feeds_feed_type.mapping', [
        'feeds_feed_type' => $this->feedsFeedType->id(),
      ])->toString(),
    ];

    if (!$mappings) {
      drupal_set_message($this->t('There are no <a href="@url">mappings</a> defined for this importer.', $args), 'warning');
      return $form;
    }

    // Help message at the top. I have a seceret, I added the link back to the
    // mappings because it makes my life a lot easier while testing this.
    $message = $this->t('Configure plugins to modify Feeds data before it gets saved. Each <a href="@url">mapping</a> can be manipulated individually.', $args);
    $form['help'] = [
      '#type' => 'item',
      '#markup' => '<div class="help"><p>' . $message . '</p></div>',
    ];

    // Build mapping grouped by source>targets>columns.
    foreach ($mappings as $mapping) {
      foreach ($mapping['map'] as $column => $source) {
        if ($source == '') {
          continue;
        }
        $this->groupedMappings[$source][$mapping['target']][$column] = $mapping;
      }
    }

    $i = 0;
    $form['#tree'] = TRUE;
    foreach ($this->groupedMappings as $source => $targets) {
      $form[$source] = $this->buildTampersTable($form, $form_state, $source, $targets);
      $i++;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * Builds a table of tampers for the specified source -> targets.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $source
   *   The source name.
   * @param array $targets
   *   An array of the source targets.
   *
   * @return array
   *   Form table element.
   */
  protected function buildTampersTable(array $form, FormStateInterface $form_state, $source, array $targets) {
    $header = [
      'description' => $this->t('Description'),
      'weight' => $this->t('Weight'),
      'plugin' => $this->t('Plugin'),
      'operations' => $this->t('Operations'),
      // @todo Implement enabled.
      // 'enabled' => $this->t('Enabled'),
    ];
    $url_parameters = ['feeds_feed_type' => $this->feedsFeedType->id()];
    $view_tampers_url = Url::fromRoute('entity.feeds_feed_type.tamper', $url_parameters)->toString();
    $destination_query = ['destination' => $view_tampers_url];
    $target_labels = [];

    $item = [
      '#type' => 'table',
      '#header' => $header,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'tamper-weight',
        ],
      ],
    ];

    foreach ($targets as $target_key => $columns) {
      /* @var \Drupal\feeds\FieldTargetDefinition $target */
      $target = $this->targets[$target_key];
      $source_label = Html::escape($this->sources[$source]['label']);
      $label = Html::escape($target->getLabel()) . ': ';

      foreach ($columns as $column => $mapping) {
        if (count($mapping['map']) > 1) {
          $target_labels[] = $label . $target->getPropertyLabel($column);
        }
        else {
          $target_labels[] = $label . ($target->getDescription() ?: $column);
        }
      }
    }

    $item['#caption'] = $source_label . ' -> ' . implode(', ', $target_labels);

    if (!empty($this->tampers[$source])) {
      // @todo Make sure tampers are sorted by weight.
      /** @var \Drupal\tamper\TamperInterface $tamper */
      foreach ($this->tampers[$source] as $id => $tamper) {
        $row = [
          '#attributes' => ['class' => ['draggable']],
          '#weight' => $tamper->getWeight(),
        ];
        // Plugin instance description.
        $row['description'] = [
          '#plain_text' => $tamper->getPluginDefinition()['description'],
        ];

        // Weight field.
        $row['weight'] = [
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#type' => 'number',
          '#default_value' => $tamper->getWeight(),
          '#attributes' => ['class' => ['tamper-weight']],
        ];
        $row['plugin'] = [
          '#plain_text' => $tamper->getPluginDefinition()['label'],
        ];
        $operations_params = $url_parameters + ['tamper_uuid' => $id];
        $row['operations'] = [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute('entity.feeds_feed_type.tamper_edit', $operations_params, [
                'query' => $destination_query,
              ]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.feeds_feed_type.tamper_delete', $operations_params),
            ],
          ],
        ];
        // @todo Implement enabled.
        // $row['enabled'] = '';
        $item[$id] = $row;
      }
    }

    $add_tamper_url = Url::fromRoute('entity.feeds_feed_type.tamper_add', $url_parameters + [
      'source_field' => $source,
    ], ['query' => $destination_query]);
    $item['add']['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add plugin'),
      '#url' => $add_tamper_url,
      '#wrapper_attributes' => ['colspan' => count($header)],
    ];
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->groupedMappings as $source => $targets) {
      if ($tampers = $form_state->getValue($source)) {
        foreach ($tampers as $tamper_uuid => $values) {
          // @todo Implement weight & enabled.
          $tamper_meta = $this->feedTypeTamperManager->getTamperMeta($this->feedsFeedType);

          $tamper = $tamper_meta->getTamper($tamper_uuid);
          drupal_set_message($this->t('Your changes have been saved.'));
        }
      }
    }
  }

}
