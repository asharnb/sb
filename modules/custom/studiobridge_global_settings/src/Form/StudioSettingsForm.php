<?php

/**
 * @file
 * Contains Drupal\studiobridge_global_settings\Form\StudioSettingsForm.
 */

namespace Drupal\studiobridge_global_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class StudioSettingsForm.
 *
 * @package Drupal\studiobridge_global_settings\Form
 */
class StudioSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'studiobridge_global_settings.studiosettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'studio_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('studiobridge_global_settings.studiosettings');
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('Enter server url to check products. Ex: http://beta.contentcentral.co/service/product-data?_format=json&product_identifier='),
      '#maxlength' => 200,
      '#size' => 200,
      '#default_value' => $config->get('url'),
    ];
    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
      '#description' => $this->t('Enter server user name to authenticate'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('user_name'),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('password'),
    ];
    $form['image_destination'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination folder for saving images?'),
      '#description' => $this->t('Example : /var/www/studio/files/'),
      '#maxlength' => 200,
      '#size' => 200,
      '#default_value' => $config->get('image_destination'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $directory = $form_state->getValue('image_destination');
    $is_writable = is_dir($directory) && is_writable($directory);
    if(!$is_writable){
      $form_state->setErrorByName('image_destination','Directory not writable(Should have 0777 permissions) OR directory not exists');
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('studiobridge_global_settings.studiosettings')
      ->set('url', $form_state->getValue('url'))
      ->set('user_name', $form_state->getValue('user_name'))
      ->set('password', $form_state->getValue('password'))
      ->set('image_destination', $form_state->getValue('image_destination'))
      ->save();
  }

}
