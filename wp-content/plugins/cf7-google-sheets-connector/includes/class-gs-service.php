<?php
/**
 * Service class for Google Sheet Connector
 * @since 1.0
 */
if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

/**
 * Gs_Connector_Service Class
 *
 * @since 1.0
 */
class Gs_Connector_Service {

	private $allowed_tags = array( 'text', 'email', 'url', 'tel', 'number', 'range', 'date', 'textarea', 'select', 'checkbox', 'radio', 'acceptance', 'quiz', 'file', 'hidden' );

	private $special_mail_tags = array( 'date', 'time', 'serial_number', 'remote_ip', 'user_agent', 'url', 'post_id', 'post_name', 'post_title', 'post_url', 'post_author', 'post_author_email', 'site_title', 'site_description', 'site_url', 'site_admin_email', 'user_login', 'user_email', 'user_display_name' ); 
   
   /**
    *  Set things up.
    *  @since 1.0
    */
   public function __construct() {
      add_action( 'wp_ajax_verify_gs_integation', array( $this, 'verify_gs_integation' ) );
      add_action( 'wp_ajax_gs_clear_log', array( $this, 'gs_clear_logs' ) );

      // Add new tab to contact form 7 editors panel
      add_filter( 'wpcf7_editor_panels', array( $this, 'cf7_gs_editor_panels' ) );

      add_action( 'wpcf7_after_save', array( $this, 'save_gs_settings' ) );
      add_action( 'wpcf7_mail_sent', array( $this, 'cf7_save_to_google_sheets' ) );
   }

   /**
    * AJAX function - verifies the token
    *
    * @since 1.0
    */
   public function verify_gs_integation() {
      // nonce checksave_gs_settings
      check_ajax_referer( 'gs-ajax-nonce', 'security' );

      /* sanitize incoming data */
      $Code = sanitize_text_field( $_POST["code"] );

      update_option( 'gs_access_code', $Code );

      if ( get_option( 'gs_access_code' ) != '' ) {
         include_once( GS_CONNECTOR_ROOT . '/lib/google-sheets.php');
         CF7GSC_googlesheet::preauth( get_option( 'gs_access_code' ) );
         update_option( 'gs_verify', 'valid' );
         wp_send_json_success();
      } else {
         update_option( 'gs_verify', 'invalid' );
         wp_send_json_error();
      }
   }
   
   /**
    * AJAX function - clear log file
    * @since 2.1
    */
   public function gs_clear_logs() {
      // nonce check
      check_ajax_referer( 'gs-ajax-nonce', 'security' );
      
      $handle = fopen ( GS_CONNECTOR_PATH . 'logs/log.txt', 'w');
      fclose( $handle );
      
      wp_send_json_success();
   }

   /**
    * Add new tab to contact form 7 editors panel
    * @since 1.0
    */
   public function cf7_gs_editor_panels( $panels ) {
      $panels['google_sheets'] = array(
         'title' => __( 'Google Sheets', 'contact-form-7' ),
         'callback' => array( $this, 'cf7_editor_panel_google_sheet' )
      );

      return $panels;
   }

   /**
    * Set Google sheet settings with contact form
    * @since 1.0
    */
   public function save_gs_settings( $post ) {
      $default = array(
         "sheet-name" => "",
         "sheet-tab-name" => ""
      );
      $sheet_data = isset( $_POST['cf7-gs'] ) ? $_POST['cf7-gs'] : $default;
      update_post_meta( $post->id(), 'gs_settings', $sheet_data );
   }

   /**
    * Function - To send contact form data to google spreadsheet
    * @param object $form
    * @since 1.0
    */
   public function cf7_save_to_google_sheets( $form ) {
      
      $submission = WPCF7_Submission::get_instance();
      // get form data
      $form_id = $form->id();
      $form_data = get_post_meta( $form_id, 'gs_settings' );

      $data = array();
      
      // if contact form sheet name and tab name is not empty than send data to spreedsheet
      if ( $submission && (! empty( $form_data[0]['sheet-name'] ) ) && (! empty( $form_data[0]['sheet-tab-name'] ) ) ) {
         $posted_data = $submission->get_posted_data();
         // make sure the form ID matches the setting otherwise don't do anything
         try {
            include_once( GS_CONNECTOR_ROOT . "/lib/google-sheets.php" );
            $doc = new CF7GSC_googlesheet();
            $doc->auth();
            $doc->settitleSpreadsheet( $form_data[0]['sheet-name'] );
            $doc->settitleWorksheet( $form_data[0]['sheet-tab-name'] );

            // Special Mail Tags  
            $meta = array();

            $special_mail_tags = array( 'serial_number', 'remote_ip', 'user_agent', 'url', 'date', 'time', 'post_id', 'post_name', 'post_title', 'post_url', 'post_author', 'post_author_email', 'site_title', 'site_description', 'site_url', 'site_admin_email', 'user_login', 'user_email', 'user_url', 'user_first_name', 'user_last_name', 'user_nickname', 'user_display_name' );

            foreach ( $special_mail_tags as $smt ) {
               $meta[$smt] = apply_filters( 'wpcf7_special_mail_tags', '', sprintf( '_%s', $smt ), false );
            }

            if ( ! empty( $meta ) ) {
               $data["date"] = $meta["date"];
               $data["time"] = $meta["time"];
               $data["serial-number"] = $meta["serial_number"];
               $data["remote-ip"] = $meta["remote_ip"];
               $data["user-agent"] = $meta["user_agent"];
               $data["url"] = $meta["url"];
               $data["post-id"] = $meta["post_id"];
               $data["post-name"] = $meta["post_name"];
               $data["post-title"] = $meta["post_title"];
               $data["post-url"] = $meta["post_url"];
               $data["post-author"] = $meta["post_author"];
               $data["post-author-email"] = $meta["post_author_email"];
               $data["site-title"] = $meta["site_title"];
               $data["site-description"] = $meta["site_description"];
               $data["site-url"] = $meta["site_url"];
               $data["site-admin-email"] = $meta["site_admin_email"];
               $data["user-login"] = $meta["user_login"];
               $data["user-email"] = $meta["user_email"];
               $data["user-url"] = $meta["user_url"];
               $data["user-first-name"] = $meta["user_first_name"];
               $data["user-last-name"] = $meta["user_last_name"];
               $data["user-nickname"] = $meta["user_nickname"];
               $data["user-display-name"] = $meta["user_display_name"];
            }

            foreach ( $posted_data as $key => $value ) {
               // exclude the default wpcf7 fields in object
               if ( strpos( $key, '_wpcf7' ) !== false || strpos( $key, '_wpnonce' ) !== false ) {
                  // do nothing
               } else {
                  // handle strings and array elements
                  if ( is_array( $value ) ) {
                     $data[$key] = implode( ', ', $value );
                  } else {
                     $data[$key] = $value;
                  }
               }
            }
            $doc->add_row( $data );
         } catch ( Exception $e ) {
            $data['ERROR_MSG'] = $e->getMessage();
            $data['TRACE_STK'] = $e->getTraceAsString();
            Gs_Connector_Utility::gs_debug_log( $data );
         }
      }
   }

   /*
    * Google sheet settings page  
    * @since 1.0
    */

   public function cf7_editor_panel_google_sheet( $post ) {
      $form_id = sanitize_text_field( $_GET['post'] );
      $form_data = get_post_meta( $form_id, 'gs_settings' );
      ?>
      <form method="post">
         <div class="gs-fields">
            <h2><span><?php echo esc_html( __( 'Google Sheet Settings', 'gsconnector' ) ); ?></span><span class="gs-info"><?php echo __( '( Fetch your sheets automatically using <a href="https://www.gsheetconnector.com/cf7-google-sheet-connector-pro" target="__blank">PRO version</a> )', 'gsconnector'); ?> </span></h2>
            <p>
               <label><?php echo esc_html( __( 'Google Sheet Name', 'gsconnector' ) ); ?></label>
               <input type="text" name="cf7-gs[sheet-name]" id="gs-sheet-name" 
                      value="<?php echo ( isset( $form_data[0]['sheet-name'] ) ) ? esc_attr( $form_data[0]['sheet-name'] ) : ''; ?>"/>
               <a href="" class=" gs-name help-link"><?php echo esc_html( __( 'Where do i get Google Sheet Name ?', 'gsconnector' ) ); ?><span class='hover-data'><?php echo esc_html( __( 'Go to your google account and click on"Google apps" icon and than click "Sheets". Select the name of the appropriate sheet you want to link your contact form or create new sheet.', 'gsconnector' ) ); ?> </span></a>
            </p>
            <p>
               <label><?php echo esc_html( __( 'Google Sheet Tab Name', 'gsconnector' ) ); ?></label>
               <input type="text" name="cf7-gs[sheet-tab-name]" id="gs-sheet-tab-name"
                      value="<?php echo ( isset( $form_data[0]['sheet-tab-name'] ) ) ? esc_attr( $form_data[0]['sheet-tab-name'] ) : ''; ?>"/>
               <a href="" class=" gs-name help-link"><?php echo esc_html( __( 'Where do i get Google Sheet Tab Name ?', 'gsconnector' ) ); ?><span class='hover-data'><?php echo esc_html( __( 'Open your Google Sheet with which you want to link your contact form . You will notice a tab names at bottom of the screen. Copy the tab name where you want to have an entry of contact form.', 'gsconnector' ) ); ?></span></a>
            </p>
         </div> 
		 		 
      </form>
	  <?php
		include( GS_CONNECTOR_PATH . "includes/pages/gs-field-list.php" );
		
		include( GS_CONNECTOR_PATH . "includes/pages/gs-special-mailtags.php" );
      
      include( GS_CONNECTOR_PATH . "includes/pages/gs-custom-mail-tags.php" );
	  
		include( GS_CONNECTOR_PATH . "includes/pages/gs-demo-details.php" );
   }
   
   /**
    * Function - fetch contact form list that is connected with google sheet
    * @since 2.1
    */
   public function get_forms_connected_to_sheet() {
      global $wpdb;
      
      $query = $wpdb->get_results("SELECT ID, post_title, meta_value from ".$wpdb->prefix."posts as p JOIN ".$wpdb->prefix."postmeta as pm on p.ID = pm.post_id where pm.meta_key='gs_settings' AND p.post_type='wpcf7_contact_form' ORDER BY p.ID");
      return $query;
   }
   
   /**
    * Function - display contact form fields to be mapped to google sheet
    * @param int $form_id
    * @since 1.0
    */
   public function display_form_fields( $form_id ) { ?>
      
      <?php
      // fetch saved fields
      $saved_mail_tags = get_post_meta( $form_id, 'gs_map_mail_tags' );
      
      // fetch mail tags
      $assoc_arr = [ ];
      $meta = get_post_meta( $form_id, '_form', true );
      $fields = $this->get_contact_form_fields( $meta );
      if( $fields ) {
         foreach ( $fields as $field ) {
            $single = $this->get_field_assoc( $field );
            if ( $single ) {
               $assoc_arr[] = $single;
            }
         }
      }
      
      if( ! empty( $assoc_arr ) ) {
      ?>
      <table class="gs-field-list">
      <?php
      $count = 0;
      foreach ( $assoc_arr as $key => $value ) {
         foreach ( $value as $k => $v ) {
            $saved_val = "";
            $checked = "";
            if( ! empty( $saved_mail_tags ) && array_key_exists( $v, $saved_mail_tags[0] ) ) :
               $saved_val = $saved_mail_tags[0][$v];
               $checked = "checked";
            endif;
            
            $placeholder = preg_replace('/[\\_]|\\s+/', '-', $v );
            ?>
               <tr>
                  <td><input type="checkbox" checked="checked" disabled="disabled" name="gs-custom-ck[<?php echo $count; ?>]" value="1" <?php echo $checked; ?> ></td>
                  <td><?php echo $v; ?> : </td>
                  <td>
                     <input type="text" name="gs-custom-header[<?php echo $count; ?>]" value="<?php echo $saved_val; ?>" placeholder="<?php echo $placeholder; ?>" disabled>
                  </td>
               </tr>
         <?php 
         $count++;
         }
      }
      ?>
      </table>
      <?php
      } else {
         echo '<p><span class="gs-info">' . __( 'No mail tags available.','gsconnector' ) . '</span></p>';
      }
   }
   
   public function get_contact_form_fields( $meta ) {
      $regexp = '/\[.*\]/';
      $arr = [];
      if ( preg_match_all($regexp, $meta, $arr) == false) {
          return false;
      }
      return $arr[0];
   }
   
   public function get_field_assoc($content) {
      $regexp_type = '/(?<=\[)[^\s\*]*/';
      $regexp_name = '/(?<=\s)[^\s\]]*/';
      $arr_type = [];
      $arr_name = [];
      if (preg_match($regexp_type, $content, $arr_type) == false) {
          return false;
      }
      if (!in_array($arr_type[0], $this->allowed_tags)) {
          return false;
      }
      if (preg_match($regexp_name, $content, $arr_name) == false) {
          return false;
      }
      return array($arr_type[0] => $arr_name[0]);
   }
   
   /**
    * Function - display contact form special mail tags to be mapped to google sheet
    * @since 2.6
    */
   public function display_form_special_tags( $form_id ) {
      
      $tags_count = count( $this->special_mail_tags );
      $num_of_cols = 2;
      ?>
      <h2><span class="gs-info"><?php echo esc_html( __( 'Map special mail tags with custom header name and save automatically to google sheet. ', 'gsconnector' ) ); ?></span></h2>
      <table class="gs-field-list special">
         <?php 
            echo '<tr>';
            for ( $i = 0; $i <= $tags_count; $i++ ) {
               if ( $i == $tags_count ) {
                  break;
               }  
               $tag_name = $this->special_mail_tags[ $i ];
                           
               $placeholder = str_replace( '_', '-', $tag_name );
            
               echo '<td><input type="checkbox" disabled="disabled" name="gs-st-ck['. $i . ']" value="1"></td>';
               echo '<td class="special-tags">[_' . $tag_name . '] </td>';
               echo '<td class="gs-r-pad"><input type="text" class="name-field" name="gs-st-custom-header['. $i . ']" value="" disabled placeholder="'. $placeholder .'"> </td>';
               if ( $i % $num_of_cols == 1 ) {
                     echo '</tr><tr>';
                  }
               }
         ?>
      </table>
      <?php
   }
   
   function display_form_custom_tag( $form_id ){
		$custom_mail_tags = array();
		$num_of_cols = 2;
	   
      if ( has_filter( "gscf7_special_mail_tags" ) ) {
         // Filter hook for custom mail tags
         $custom_tags = apply_filters( "gscf7_special_mail_tags", $custom_mail_tags, $form_id );
         $custom_tags_count = count( $custom_tags );
         $num_of_cols = 2;
         // fetch saved fields
         $saved_cmail_tags = get_post_meta( $form_id, 'gs_map_custom_mail_tags' );
      ?>
         <table class="gs-field-list">
            <?php 
               echo '<tr>';
               for ( $i = 0; $i <= $custom_tags_count; $i++ ) {
                  if ( $i == $custom_tags_count ) {
                     break;
                  } 
                  $tag_name = $custom_tags[ $i ];
                  $modify_tag = ltrim( $tag_name, '_' );
                  $saved_val = "";
                  $checked = "";
                  if( ! empty( $saved_cmail_tags ) && array_key_exists( $modify_tag, $saved_cmail_tags[0] ) ) :
                     $saved_val = $saved_cmail_tags[0][$modify_tag];
                     $checked = "checked";
                  endif;
                  
                  //hack - todo
                  $placeholder_explode = explode( '_', $tag_name, 2 );
                  $placeholder = str_replace( '_', '-', $placeholder_explode[1] );
               
                  echo '<td><input type="checkbox" name="gs-ct-ck['. $i . ']" value="1" ' . $checked . '></td>';
                  echo '<td>[' . $tag_name . ']</td>';
                  echo '<td class="gs-r-pad"><input type="hidden" name="gs-ct-key['. $i . ']" value="' . $tag_name . '" ><input type="hidden" name="gs-ct-placeholder['. $i . ']" value="' . $placeholder . '" ><input type="text" name="gs-ct-custom-header['. $i . ']" value="' . $saved_val . '" placeholder="'. $placeholder .'"></td>';
                  if ( $i % $num_of_cols == 1 ) {
                        echo '</tr><tr>';
                     }
                  }
            ?>
         </table>
      <?php 
      } else {
         echo '<p><span class="gs-info">' . __( 'No custom mail tags available.','gsconnector' ) . '</span></p>';
      } 
	   
   }

}

$gs_connector_service = new Gs_Connector_Service();


