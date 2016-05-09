<?php

/**
 * @file
 * Contains Drupal\studiobridge_live_shoot_page\StudioBridgeLiveShootingForm
 *
 * @Note : As of now this are only development code
 */

namespace Drupal\studiobridge_live_shoot_page\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Routing\TrustedRedirectResponse;
use \Drupal\node\Entity\Node;
use Drupal\studiobridge_commons\Sessions;
use Drupal\studiobridge_commons\Products;
use Drupal\studiobridge_commons\StudioImages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;

class StudioBridgeLiveShootingForm extends FormBase
{

  public function getFormId()
  {
    return 'studiobridge_live_shoot_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $user = \Drupal::currentUser();
    $uid = $user->id();

    // current open session
    $session_id = Sessions::openSessionRecent(array('open','pause'));

    // if no session found then redirect to some other page
    if (!$session_id) {
      drupal_set_message('No open sessions found', 'warning');
      return new RedirectResponse(base_path() . 'view-sessions2');
    }

    $new_or_old_product_nid = 0;

    $identifier_hidden = '';
    $identifier_hidden = \Drupal::state()->get('last_scan_product_' . $uid . '_' . $session_id, false);

    // todo : identifier might available in query
    // todo : get default product (current open product last)
    if (!empty($_GET['identifier']) && isset($_GET['reshoot'])  &&  ($identifier_hidden != $_GET['identifier'])) {
      $identifier_hidden = $_GET['identifier'];

      $result = Products::getProductByIdentifier($_GET['identifier']);

      if ($result) {
        $new_or_old_product_nid = reset($result);
      } else {
        drupal_set_message('Invalid identifier', 'warning');
        return new RedirectResponse(base_path());
      }

      if ($new_or_old_product_nid) {

        $last_scan_product = \Drupal::state()->get('last_scan_product_' . $uid . '_' . $session_id, false);
        Products::AddEndTimeToProduct($session_id,false,$last_scan_product);


        \Drupal::state()->set('last_scan_product_nid' . $uid . '_' . $session_id, $new_or_old_product_nid);
        //studiobridge_store_images_update_product_as_open($_GET['identifier']);
        Products::updateProductState($_GET['identifier'], 'open');
        \Drupal::state()->set('last_scan_product_' . $uid . '_' . $session_id, $_GET['identifier']);

        $product_obj = Node::load($new_or_old_product_nid);

        // Add product to session.
        Sessions::UpdateLastProductToSession($session_id, $product_obj);

        // Add reshoot product to session.
        Sessions::addReshootProductToSession($session_id,$product_obj);

        // Add product to session; todo : double check workflow other things are working fine.
        Products::addProductToSession($session_id, $product_obj);

        Products::AddStartTimeToProduct($session_id, $new_or_old_product_nid);

        // if reshoot that means product under scan state closing ofter above process was done.
        \Drupal::state()->set('productscan_' . $session_id, false);

        // todo test all functions are
        return new RedirectResponse(base_path() . 'live-shooting-page1');

      }


    } else {
      $result = Products::getProductByIdentifier($identifier_hidden);
      if ($result) {
        $new_or_old_product_nid = reset($result);
      }
    }

    $identifier = $identifier_hidden;

    $form['session'] = array(
      '#type' => 'hidden',
      '#value' => $session_id,
      '#default_value' => $session_id,

    );
    $form['identifier'] = array(
      '#type' => 'textfield',
      '#description' => $this->t('description will come here'),
      '#default_value' => $identifier,
      '#title' => t(''),

    );

    $form['identifier_hidden'] = array(
      '#type' => 'hidden',
      '#value' => $identifier_hidden,
      '#default_value' => $identifier_hidden,
    );
    $form['identifier_nid'] = array(
      '#type' => 'hidden',
      '#value' => $new_or_old_product_nid,
      '#default_value' => $new_or_old_product_nid,
    );

    $images = array();
    $pid = \Drupal::state()->get('last_scan_product_nid' . $uid . '_' . $session_id, false);
    if ($pid) {
      //$images = self::getProductImages($pid);
      $images = Products::getProductImages($pid);
    } else {

      $result = Products::getProductByIdentifier($identifier);
      if ($result) {
        //$images = self::getProductImages(reset($result));
        $images = Products::getProductImages(reset($result));
      }
    }
    // @ashar : seperate this image container so we can apply theme formatting to it

    $form['resequence'] = array(
      '#markup' => '<a id="studio-resequence-bt" class="btn btn-xs btn-complete">Resequence</a>',
    );
    $form['delete'] = array(
      '#markup' => '<a id="studio-delete-bt" class="btn btn-xs btn-danger">Delete</a>',
    );
    $form['misc'] = array(
      '#markup' => '<div id="studio-img-container"></div><div id="js-holder"></div><div id="msg-up"></div>',
    );
    $form['random_user'] = array(
      '#type' => 'button',
      '#value' => 'Apply',
      '#attributes' => array('class' => array('hidden')),
      '#ajax' => array(
        'callback' => 'Drupal\studiobridge_live_shoot_page\Form\StudioBridgeLiveShootingForm::productGetOrUpdateCallback',
        'event' => 'click',

      ),
    );

    $productdetails = Products::getProductInformation($identifier_hidden);

    if ($productdetails) {
      $session = Node::load($session_id);
      $session_products = $session->field_product->getValue();
      $form['productdetails'] = array(
        'concept' => $productdetails['concept'],
        'styleno' => $productdetails['styleno'],
        'colorvariant' => $productdetails['colorvariant'],
        'gender' => $productdetails['gender'],
        'color' => $productdetails['color'],
        'description' => $productdetails['description'],
        'identifier' => $identifier,
        'image_count' => $productdetails['image_count'],
        'total_products' => count($session_products),
      );
    }

    $array_images = array();
    $i = 1;
    foreach ($images as $fid => $src) {

      $array_images[] = array('url' => $src['uri'],
        'name' => $src['name'],
        'fid' => $fid,
        'id' => $i,
        'tag' => $src['tag'],
      );
      $i++;


    }

    $form['images'] = array(
      'images' => $array_images,

    );
    $form['#attributes'] = array();

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $v = $form_state->getValues();
    $form_state->setRebuild(TRUE);

    drupal_set_message('Nothing Submitted. Just an Example.');
  }

  public function productUpdateSeqCallback(array &$form, FormStateInterface $form_state)
  {
    $v = $form_state->getValues();
  }

  public function productGetOrUpdateCallback(array &$form, FormStateInterface $form_state)
  {

    $user = \Drupal::currentUser();
    $uid = $user->id();

    // Generate new ajax response object
    $ajax_response = new AjaxResponse();

    // Get current session
    $session_id = Sessions::openSessionRecent();
    // If no session found then redirect to some other page
    if (!$session_id) {
      $same_identifier = '<script>alert("Session in closed/pause state, cannot proceed. Update session and refresh the page.")</script>';
      // return ajax here.
      $ajax_response->addCommand(new HtmlCommand('#js-holder', $same_identifier));
      return $ajax_response;
    }

    $reshoot = false;
    $is_unmapped_product = false;

    //get last product from form
    $identifier = $form_state->getValue('identifier');
    $identifier_old = $form_state->getValue('identifier_hidden');  // @note : this will be the recent product.

    $last_scan_product = \Drupal::state()->get('last_scan_product_' . $uid . '_' . $session_id, false);

    if (empty(trim($identifier))) {
      //return js with error message

      $inject_script = '<script>
    Command: toastr["error"]("No identifier has been scanned. Please scan the tag to continue.")

    toastr.options = {
      "closeButton": false,
      "debug": false,
      "newestOnTop": false,
      "progressBar": false,
      "positionClass": "toast-top-right",
      "preventDuplicates": false,
      "onclick": null,
      "showDuration": "300",
      "hideDuration": "1000",
      "timeOut": "5000",
      "extendedTimeOut": "1000",
      "showEasing": "swing",
      "hideEasing": "linear",
      "showMethod": "fadeIn",
      "hideMethod": "fadeOut"
    }

    </script>';
      //
      $ajax_response->addCommand(new HtmlCommand('#smartnotification', $inject_script));
      return $ajax_response;
    }

    //if identifier found in our products then skip importing.
    $result = Products::getProductByIdentifier($identifier);

    \Drupal::state()->set('productscan_' . $session_id, true);

    if (!$result) {
      // Get product from server
      $product = Products::getProductExternal($identifier);
      $product = json_decode($product);
      // validate product
      if (isset($product->msg)) {
        // product not found on the server so save it as unmapped product.
        //studiobridge_store_images_create_unmapped_product(array(),$session_id,$identifier,false);
        $un_mapped_node = Products::createUnmappedProduct(array(), $session_id, $identifier, false);
        $new_or_old_product_nid = $un_mapped_node->id();
        $is_unmapped_product = true;
        // todo : update product to session.
        Sessions::UpdateLastProductToSession($session_id, $un_mapped_node);
        $inject_script_mapping = '<script>
      Command: toastr["error"]("Identifier was not found, an unmapped product has been created.")

      toastr.options = {
        "closeButton": false,
        "debug": false,
        "newestOnTop": false,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
      }

      </script>';
        //



      } else {
        // import it in our drupal.
        $new_product = Products::createMappedProduct($product, $identifier);
        $new_or_old_product_nid = $new_product->id();
        $inject_script_mapping = '<script>
      Command: toastr["success"]("New mapped product found")

      toastr.options = {
        "closeButton": false,
        "debug": false,
        "newestOnTop": false,
        "progressBar": false,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
      }

      </script>';
      }
    } else {
      // todo : code for product re shoot
      $new_or_old_product_nid = reset($result);

      $db = \Drupal::database();
      $sessions_nids = $db->select('node__field_product', 'c')
        ->fields('c')
        ->condition('field_product_target_id', $new_or_old_product_nid)
        ->execute()->fetchAll();

      // todo : if count is more than 1
      if (count($sessions_nids)) {
        // $session_id
        foreach ($sessions_nids as $field) {
          if ($field->entity_id != $session_id) {
            $reshoot = true;
            break;
          }
        }
      }
      // If current product is reshoot then prompt user to confirm
      if ($reshoot && $_GET['identifier'] !== $identifier) {
        $inject_script = '<script>
      swal({
        title: "Reshoot Product?",
        text: "This product already exists, do you want to reshoot this product?",
        type: "info",
        showCancelButton: true,
        confirmButtonColor: "#DD6B55",
        confirmButtonText: "Reshoot",
        closeOnConfirm: false
      },function () {
        window.location="' . base_path() . 'live-shooting-page1?reshoot&identifier=' . $identifier . '"
      });


    </script>';
        // return ajax here.
        $ajax_response->addCommand(new HtmlCommand('#js-holder', $inject_script));
        return $ajax_response;
      }

      // update the current product as open status
      //studiobridge_store_images_update_product_as_open($identifier);
      Products::updateProductState($_GET['identifier'], 'open');

      Products::AddStartTimeToProduct($session_id, $new_or_old_product_nid);
    }

    if ($last_scan_product != $identifier) {
      // todo : update last product as closed status
      Products::updateProductState($last_scan_product, 'completted');
      Products::AddEndTimeToProduct($session_id,false,$last_scan_product);
    }

    // Once product is scanned update it to session
    if (!$is_unmapped_product) {
      //studiobridge_store_images_add_product_to_session($session_id, \Drupal\node\Entity\Node::load($new_or_old_product_nid));
      Products::addProductToSession($session_id, Node::load($new_or_old_product_nid));
    }

    \Drupal::state()->set('last_scan_product_' . $uid . '_' . $session_id, $identifier);

    if ($new_or_old_product_nid) {

      $product_node = Node::load($new_or_old_product_nid);

      \Drupal::state()->set('last_scan_product_nid' . $uid . '_' . $session_id, $new_or_old_product_nid);
      // todo : add product to session.
      Sessions::UpdateLastProductToSession($session_id, $product_node);

      // Add fullshot image to next product; if not exist
      $full_shot_img_fid = \Drupal::state()->get('full_shot' . '_' . $session_id,false);
      if($full_shot_img_fid){
        StudioImages::FullShootImage($product_node,$full_shot_img_fid);
        \Drupal::state()->set('full_shot' . '_' . $session_id, false);
      }
    }


    $images = Products::getProductImages($new_or_old_product_nid);

//$block = '<div id="sortable" class="ui-sortable">';
    $block = '<div id="imagecontainer" name="imagecontainer" class="ui-sortable">';
    $i = 1;
    foreach ($images as $fid => $src) {
      //$block = '';
      $block .= '<div class="bulkviewfiles imagefile ui-sortable-handle" id="warpper-img-' . $fid . '">';

      if($src['tag']!==1){
        $block .= '<div class="ribbon"><span class="for-tag" id="seq-' . $fid . '">' . $i . '</span></div>';
      } else {
        $block .= '<div class="ribbon"><span class="for-tag tag" id="seq-' . $fid . '">Tag</span></div>';
      }

      $block .= '<div class="scancontainer">';
      $block .= '<img src="' . $src['uri'] . '" class="scanpicture">';
      $block .= '</div>';
      //$block .=  "<input name='image[" . $fid . "]' type='hidden' value='" . $fid . "'/>";

      $block .= '<div class="file-name">';
      $block .= '<div id="tag-seq-img-'.$fid.'" type="hidden"></div>';

      $block .= '<div class="row">';

      $block .= '<div class="col col-sm-8">
  <span id= "'.$fid.'" >
  <a class="label label-info"><i class="fa fa-lg fa-fw fa-arrows-alt"></i></a>
  <a class="label label-warning studio-img-tag" ><i class="fa fa-lg fa-fw fa-tag"></i></a>
  <a class="label label-success studio-img-fullshot"><i class="fa fa-lg fa-fw fa-copy"></i></a>
  </span>
  </div>';
      $block .= '                    <div class="col col-sm-4">
  <div class="onoffswitch2 pull-right">
  <span id="'.$fid.'">
  <input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox form-checkbox" id="del-img-'.$fid.'" value="'.$fid.'">
  <label class="onoffswitch-label" for="del-img-'.$fid.'">
  <span class="onoffswitch-inner"></span>
  <span class="onoffswitch-switch"></span>
  </label>
  </span>
  </div>
  </div>';

      $block .= '</div>';
      $block .= '</div>';
      $block .= '<div class="studio-img-weight"><input type="hidden" value="'.$fid.'"></div>';
      $block .= '</div>';
      $i++;
    }
    $block .= '</div>';

    $sort_js = '<script>!function(e){e(function(){e("#imagecontainer").sortable(({
  tolerance: \'pointer\',
  start: function(event, ui){
    ui.placeholder.html("<div class=\'bulkviewfiles file gray-bkground\' style=\'width: 250px; height: 250px; background: #D2D2D2;\'></div>");
  },
  stop: function(event, ui){
    ui.placeholder.html("");
  }
})),e("#imagecontainer").disableSelection()})}(jQuery);</script>';

    $ajax_response->addCommand(new HtmlCommand('#imagecontainer-wrapper', $block));
    $ajax_response->addCommand(new HtmlCommand('#studio-img-container', ''));
    $ajax_response->addCommand(new HtmlCommand('#js-holder', $sort_js));
//$ajax_response->addCommand(new HtmlCommand('#sortable', $block));
    $ajax_response->addCommand(new InvokeCommand('#edit-identifier-hidden', 'val', array($identifier)));
    $ajax_response->addCommand(new InvokeCommand('#edit-identifier-nid', 'val', array($new_or_old_product_nid)));
    $ajax_response->addCommand(new InvokeCommand('#edit-identifier-hidden', 'change'));


// update in product details section   todo : later remove it @ ashar

    $productdetails = Products::getProductInformation($identifier);

    if ($productdetails) {
      $session = Node::load($session_id);
      $session_products = $session->field_product->getValue();


      $ajax_response->addCommand(new HtmlCommand('#dd-identifier', $identifier));
      $ajax_response->addCommand(new HtmlCommand('#dd-styleno', $productdetails['styleno']));
      $ajax_response->addCommand(new HtmlCommand('#dd-concept', $productdetails['concept']));
      $ajax_response->addCommand(new HtmlCommand('#dd-colorvariant', $productdetails['colorvariant']));
      $ajax_response->addCommand(new HtmlCommand('#dd-gender', $productdetails['gender']));
      $ajax_response->addCommand(new HtmlCommand('#dd-color', $productdetails['color']));
      $ajax_response->addCommand(new HtmlCommand('#dd-description', $productdetails['description']));
      $ajax_response->addCommand(new HtmlCommand('#product-img-count', $productdetails['image_count']));
      $ajax_response->addCommand(new HtmlCommand('#session-total-products', count($session_products)));
      $ajax_response->addCommand(new HtmlCommand('#smartnotification', $inject_script_mapping));

    }

    \Drupal::state()->set('productscan_' . $session_id, false);
    return $ajax_response;
  }

}