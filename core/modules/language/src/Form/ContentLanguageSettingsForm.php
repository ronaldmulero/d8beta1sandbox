<?php

/**
 * @file
 * Contains \Drupal\language\Form\ContentLanguageSettingsForm.
 */

namespace Drupal\language\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the content language settings for this site.
 */
class ContentLanguageSettingsForm extends ConfigFormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a ContentLanguageSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityManagerInterface $entity_manager) {
    parent::__construct($config_factory);

    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_content_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_types = $this->entityManager->getDefinitions();
    $labels = array();
    $default = array();

    $bundles = $this->entityManager->getAllBundleInfo();
    $language_configuration = array();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type->isTranslatable()) {
        continue;
      }
      $labels[$entity_type_id] = $entity_type->getLabel() ?: $entity_type_id;
      $default[$entity_type_id] = FALSE;

      // Check whether we have any custom setting.
      foreach ($bundles[$entity_type_id] as $bundle => $bundle_info) {
        $conf = language_get_default_configuration($entity_type_id, $bundle);
        if (!empty($conf['language_show']) || $conf['langcode'] != 'site_default') {
          $default[$entity_type_id] = $entity_type_id;
        }
        $language_configuration[$entity_type_id][$bundle] = $conf;
      }
    }

    asort($labels);

    $form = array(
      '#labels' => $labels,
      '#attached' => array(
        'library' => array(
          'language/drupal.language.admin',
        ),
      ),
    );

    $form['entity_types'] = array(
      '#title' => $this->t('Custom language settings'),
      '#type' => 'checkboxes',
      '#options' => $labels,
      '#default_value' => $default,
    );

    $form['settings'] = array('#tree' => TRUE);

    foreach ($labels as $entity_type_id => $label) {
      $entity_type = $entity_types[$entity_type_id];

      $form['settings'][$entity_type_id] = array(
        '#title' => $label,
        '#type' => 'container',
        '#entity_type' => $entity_type_id,
        '#theme' => 'language_content_settings_table',
        '#bundle_label' => $entity_type->getBundleLabel() ?: $label,
        '#states' => array(
          'visible' => array(
            ':input[name="entity_types[' . $entity_type_id . ']"]' => array('checked' => TRUE),
          ),
        ),
      );

      foreach ($bundles[$entity_type_id] as $bundle => $bundle_info) {
        $form['settings'][$entity_type_id][$bundle]['settings'] = array(
          '#type' => 'item',
          '#label' => $bundle_info['label'],
          'language' => array(
            '#type' => 'language_configuration',
            '#entity_information' => array(
              'entity_type' => $entity_type_id,
              'bundle' => $bundle,
            ),
            '#default_value' => $language_configuration[$entity_type_id][$bundle],
          ),
        );
      }
    }

    $form = parent::buildForm($form, $form_state);
    // @todo Remove this override. There are tests that check for explicitly for
    //   the button label which need to be adapted for that.
    //   https://drupal.org/node/2241727
    $form['actions']['submit']['#value'] = $this->t('Save');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('language.settings');
    foreach ($form_state->getValue('settings') as $entity_type => $entity_settings) {
      foreach ($entity_settings as $bundle => $bundle_settings) {
        $config->set(language_get_default_configuration_settings_key($entity_type, $bundle), array(
          'langcode' => $bundle_settings['settings']['language']['langcode'],
          'language_show' => $bundle_settings['settings']['language']['language_show'],
        ));
      }
    }
    $config->save();
    drupal_set_message($this->t('Settings successfully updated.'));
  }

}
