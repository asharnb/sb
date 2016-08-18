<?php

/**
 * @file
 * Contains \Drupal\studiobridge_rest_resources\Plugin\rest\resource\studiobridge_rest_resources.
 */

namespace Drupal\studiobridge_rest_resources\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;
use Drupal\file\Entity\File;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "qc_operations",
 *   label = @Translation("[Studio] Qc operations"),
 *   serialization_class = "Drupal\node\Entity\Node",
 *   uri_paths = {
 *     "canonical" = "/qc/operation/{type}",
 *     "https://www.drupal.org/link-relations/create" = "/qc/operation/{type}/post"
 *   }
 * )
 */
class QcOperations extends ResourceBase {
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  protected $nodeStorage;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user, $entity_manager, $studioQc) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->nodeStorage = $entity_manager->getStorage('node');
    $this->studioQc = $studioQc;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('studio.qc')
    );
  }

  /**
   * Responds to POST requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @type
   *  - reject_all
   *  - approve_all
   *  - notes
   *  - reject_img
   *  - approve_img
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($type, $data) {
    $pid = $data->body->value['pid'];
    $sid = $data->body->value['sid'];
    $fids = $data->body->value['images'];
    $state = $data->body->value['state'];

    switch($type){

      case  'reject_all':
        $this->rejectAll($sid, $pid, $fids);
        break;

      case  'approve_all':
        break;

      case  'notes':
        break;

      case  'reject_img':
        break;

      case  'approve_img':
        break;

      default:
        return new ResourceResponse("Implement REST State POST!");

    }


    return new ResourceResponse(array(rand(1, 999999), array($type, $data)));

  }

  /*
   *
   */
  public function get($type){
    \Drupal::service('page_cache_kill_switch')->trigger();


    switch($type){

      case  'reject_all':
        //$this->rejectAll();
        break;

      default:
        return new ResourceResponse("Implement REST State GET!");

    }


    return new ResourceResponse($_GET);
  }


  /*
   *
   */
  public function rejectAll($sid, $pid, $fids){
    $images = File::loadMultiple($fids);

    foreach($images as $image){
      $image->field_qc_state->setValue(array('value'=>'rejected'));
      $image->save();
    }

    // update product as rejected
    $this->studioQc->addQcRecord($pid, $sid, '', 'rejected');

  }

  public function approveAll($sid, $pid, $fids){
    $images = File::loadMultiple($fids);

    foreach($images as $image){
      $image->field_qc_state->setValue(array('value'=>'approved'));
      $image->save();
    }

    // update product as rejected
    $this->studioQc->addQcRecord($pid, $sid, '', 'approved');
  }


  public function notes($sid, $pid, $fids, $notes){
    $this->studioQc->addQcRecord($pid, $sid, $notes, 'approved');
  }

  public function rejectImg($fid){
    $image = File::load($fid);
    $image->field_qc_state->setValue(array('value'=>'rejected'));
    $image->save();
  }

  public function approveImg($fid){
    $image = File::load($fid);
    $image->field_qc_state->setValue(array('value'=>'approved'));
    $image->save();
  }

}