<?php 
/*
Plugin Name: AMP Contact FORM 7
Description: Enable Contact Form 7 plugin support in AMP.
Author: Arshid
Author URI: https://ciphercoin.com
Text Domain: amp-contact-form-7
Version: 1.0.1
*/

function ampcf7_render_post(){
	
	add_filter( 'amp_content_sanitizers', 'ampcf7_add_form_sanitizer' );
	add_filter( 'amp_blacklisted_tags', 'ampcf7_amp_blacklisted_tags' );
	add_filter('wpcf7_form_action_url', 'ampcf7_form_action_url');
	add_filter( 'wpcf7_form_elements', 'ampcf7_form_elements' );

	add_action('amp_post_template_css', 'ampcf7_template_css');
	add_action('wpcf7_contact_form', 'ampcf7_load_script');

}
add_action( 'pre_amp_render_post', 'ampcf7_render_post' );

/**
 * Add class
 * 
 **/
function ampcf7_add_form_sanitizer( $sanitizer ){

    if( ! isset( $sanitizer['AMP_Form_Sanitizer'] ) ){
   	    if( ! class_exists( 'AMP_Form_Sanitizer' ) )
  			require_once 'includes/class-amp-form-sanitizer.php';
    }
  
  $sanitizer['AMP_Form_Sanitizer'] = array();
  return $sanitizer;
}
/**
 * Remove black listed elements
 * 
 **/
function ampcf7_amp_blacklisted_tags( $tags ){  

	if( $key = array_search( 'form', $tags ) ){
		unset( $tags[ $key ] );
	}
	if( $key = array_search( 'input', $tags ) ){
		unset( $tags[ $key ] );
	}
	if( $key = array_search( 'label', $tags ) ){
		unset( $tags[ $key ] );
	}
	if( $key = array_search( 'textarea', $tags ) ){
		unset( $tags[ $key ] );
	}
	return $tags;
}

/**
 *  Internal css
 * 
 **/
function ampcf7_template_css(){

	$css_path = plugin_dir_path( __FILE__ ).'css/cf7.css';
	$css      = file_get_contents( $css_path );
	echo apply_filters( 'ampcf7_css', $css );
}

/**
 * Filter action url
 * 
 **/
function ampcf7_form_action_url( $url ) {
	return admin_url( 'admin-ajax.php' ).'?action=ampcf7_submit_form';
}

/**
 * Load script
 * 
 **/
function ampcf7_load_script(){
 	global $scriptComponent;
	if ( empty( $scriptComponent['amp-mustache'] ) ) {
			$scriptComponent['amp-mustache'] = 'https://cdn.ampproject.org/v0/amp-mustache-0.1.js';
	}
 }

 
/**
 * Append status element
 **/
function ampcf7_form_elements( $elements ){
	ob_start(); ?>
  <div submitting>
    <template type="amp-mustache">
    </template>
  </div>
  <div submit-success>
    <template type="amp-mustache">
       <div class="ampcf7-success">{{msg}}</div>
    </template>
  </div>
  <div submit-error>
    <template type="amp-mustache">
      <div class="ampcf7-error">
      	{{msg}}
      	{{#verifyErrors}}
            <p>{{message}}</p>
        {{/verifyErrors}}
      </div>
    </template>
  </div>
	<?php  $elements .= ob_get_clean();
	return $elements;	
}



/**
 * Ajax handling 
 **/
function ampcf7_submit_form(){

	$domain_url = (isset($_SERVER['HTTPS']) ? "https":"http")."://$_SERVER[HTTP_HOST]";

    header("Content-type: application/json");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Origin: *.ampproject.org");
    header("AMP-Access-Control-Allow-Source-Origin: ".$domain_url);
       
 
	$form_id    = isset( $_POST['_wpcf7'] ) ? $_POST['_wpcf7'] : '';
    $form       = WPCF7_ContactForm::get_instance( $form_id );
	$submission = WPCF7_Submission::get_instance( $form );
	
	$response   = $submission->get_response();
	$status     = $submission->get_status();
	// $output     = array( 'message' =>  $response );

	switch ( $status ) {
		case 'validation_failed':
			header('HTTP/1.1 403 Forbidden');
			break;
		case 'acceptance_missing':
			header('HTTP/1.1 403 Forbidden');
			break;
		case 'spam':
			header('HTTP/1.1 403 Forbidden');
			break;
		case 'aborted':
			header('HTTP/1.1 403 Forbidden');
			break;
		case 'mail_sent':
			header('HTTP/1.1 200 OK');
			break;
		case 'mail_failed':
			header('HTTP/1.1 403 Forbidden');
			break;
		default:
		   break;
	}
	$verifyErrors = array();
	foreach( $submission->get_invalid_fields() as $key => $val ){

		$key = str_replace( '-', ' ', $key );
		$key = ucfirst( $key );
		$verifyErrors[]['name']    = $key;
		$verifyErrors[]['message'] = $key.' : '.$val['reason'];
	}
	
	$output['msg'] = $response;

	if( $status == 'validation_failed' ) $output['msg'] = '';


	$output['verifyErrors'] = apply_filters( 'ampcf7_verify_errors', $verifyErrors );
	
	echo json_encode( $output );
	die;
}

add_action( 'wp_ajax_ampcf7_submit_form', 'ampcf7_submit_form' );
add_action( 'wp_ajax_nopriv_ampcf7_submit_form', 'ampcf7_submit_form' );

/**
 * Activate plugin
 **/
function ampcf7_on_activate(){

	update_option( 'ampcf7_install_date', date('Y-m-d G:i:s'), false);
	update_option( 'ampcf7_ignore_notice', 'false',  false);
}

register_activation_hook( __FILE__, 'ampcf7_on_activate' );

/**
 * Adming notice
 **/
function ampcf7_admin_notices(){


	$install_date = get_option( 'ampcf7_install_date', '');
    $install_date = date_create( $install_date );
    $date_now     = date_create( date('Y-m-d G:i:s') );
    $date_diff    = date_diff( $install_date, $date_now );
    $view_notice  = get_option( 'ampcf7_ignore_notice', '' );

    if ( isset( $_GET['ampcf7-ignore-notice'] ) && ( '1' == $_GET['ampcf7-ignore-notice'] ) ) {

        update_option( 'ampcf7_ignore_notice', 'true', false );
        $view_notice = 'true';
    }
    if ( ( $date_diff->format("%d") < 7 ) || ( $view_notice == 'true' ) ){

        return false;
    }


	echo '<div class="updated"><p>';

        printf(__( 'Awesome, you\'ve been using <a href="https://wordpress.org/plugins/amp-contact-form-7/" target="_blank">AMP Contact Form 7</a> for more than 1 week. May we ask you to give it a 5-star rating on WordPress?  <a href="%2$s" target="_blank">Ok, you deserved it</a> | <a href="%1$s">I already did</a> | <a href="%1$s">No, not good enough</a>', 'amp-contact-form-7' ), 'admin.php?page=wpcf7&ampcf7-ignore-notice=1',
        'https://wordpress.org/plugins/amp-contact-form-7/');
        echo "</p></div>";
}
add_action( 'wpcf7_admin_notices' , 'ampcf7_admin_notices' );

/**
 * Deactivation 
 **/
function ampcf7_on_deactivate(){
 	delete_option( 'ampcf7_install_date' );
 	delete_option( 'ampcf7_ignore_notice' );
}

register_deactivation_hook( __FILE__, 'ampcf7_on_deactivate' );