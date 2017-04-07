<?php
/*
   Plugin Name: PropSpace Importer
   Plugin URI: http://wordpress.org/extend/plugins/propspace-importer/
   Version: 0.1
   Author: Waseem
   Description: 
   Text Domain: propspace-importer
   License: GPLv3
  */

require_once('plugin-admin.php');

class AAA_Importer {
  public $options = array();
  function __construct(){
    $this->options = get_option( 'aaa-importer' );
    
    add_action('init', array($this, 'post_types_init'));
    add_action('aqua_import_event', array($this, 'daily'));

    
  }

  function activate() {

    wp_schedule_event(time(), 'daily', 'aqua_import_event');
  }

  function post_types_init() {
    $labels = array(
      'name'               => _x( 'Agents', 'post type general name', 'your-plugin-textdomain' ),
      'singular_name'      => _x( 'Agent', 'post type singular name', 'your-plugin-textdomain' ),
      'menu_name'          => _x( 'Agents', 'admin menu', 'your-plugin-textdomain' ),
      'name_admin_bar'     => _x( 'Agent', 'add new on admin bar', 'your-plugin-textdomain' ),
      'add_new'            => _x( 'Add New', 'agent', 'your-plugin-textdomain' ),
      'add_new_item'       => __( 'Add New Agent', 'your-plugin-textdomain' ),
      'new_item'           => __( 'New Agent', 'your-plugin-textdomain' ),
      'edit_item'          => __( 'Edit Agent', 'your-plugin-textdomain' ),
      'view_item'          => __( 'View Agent', 'your-plugin-textdomain' ),
      'all_items'          => __( 'All Agents', 'your-plugin-textdomain' ),
      'search_items'       => __( 'Search Agents', 'your-plugin-textdomain' ),
      'parent_item_colon'  => __( 'Parent Agents:', 'your-plugin-textdomain' ),
      'not_found'          => __( 'No Agents found.', 'your-plugin-textdomain' ),
      'not_found_in_trash' => __( 'No Agents found in Trash.', 'your-plugin-textdomain' )
    );

    $args = array(
      'labels'             => $labels,
                  'description'        => __( 'Description.', 'your-plugin-textdomain' ),
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'show_in_menu'       => true,
      'query_var'          => true,
      'rewrite'            => array( 'slug' => 'agent' ),
      'capability_type'    => 'post',
      'has_archive'        => true,
      'hierarchical'       => false,
      'menu_position'      => null,
      'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
    );

    register_post_type( 'agent', $args );
  }

  function daily() {
      $count=1;
      try{
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        set_time_limit (50000);
        ini_set('max_execution_time', 50000);
        ini_set('memory_limit', "1G");

          
    error_log("Propspace: Starting Download \n");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $this->options['url']);    // get the url contents
    curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
    $data = curl_exec($ch); // execute curl request
    file_put_contents(ABSPATH.'/wp-content/propspace.xml', $data);
    curl_close($ch);
    error_log("Propspace: XML Downloaded: " .ABSPATH."wp-content/propspace.xml, Parsing... \n");
    if(file_exists(ABSPATH.'wp-content/propspace.xml')){
    $z = new XMLReader;
    $z->open(ABSPATH.'/wp-content/propspace.xml', 'UTF-8');
    while ($z->read() && $z->name !== 'Listing');
    while ($z->name == 'Listing')
    {
          /*if($count < 600){
              $z->next('Listing');
              $count ++;
          }*/
        try{
            $listing = new SimpleXMLElement($z->readOuterXML());
        }catch(Exception $e){
            error_log("Propspace: Error: ".$e->getMessage().$z->readOuterXML()."  \n");
            continue;
        }
        $property_id = (string) $listing->Property_Ref_No;
        if(trim($property_id) == ""){
          continue;
        }
      //}
      //$xml = simplexml_load_string($data);
      
      //foreach ($xml as $listing) {

        // echo $count++;
        //$args = $listing->Property_Title;

        $property_status = (string) $listing->Ad_Type;
        $Property_type = (string) $listing->Unit_Type;
        $Property_size = (string) $listing->Unit_Builtup_Area;
        $size_postfix_text =(string) $listing->unit_measure;
        $strno =(string) $listing->Strno;
        $bathroom = (string) $listing->No_of_Bathroom;
        $property_title = (string)($listing->Property_Title);
        $property_decription = (string) html_entity_decode($listing->Web_Remarks);
        $phone = $fax = $website = "";
        $property_parse = explode("<br />", $property_decription);
        foreach($property_parse as $sv){
          if(stripos($sv, 'Office fax no:') !== false){
            $fax = trim(str_replace('Office fax no:', '', $sv));
          }
          if(stripos($sv, 'Office phone no:') !== false){
            $phone = trim(str_replace('Office phone no:', '', $sv));
          }
          
          if(stripos($sv, 'Website:') !== false){
            $website = trim(str_replace('Website:', '', $sv));
          }
        }
        $term_features = array();
        if($listing->Facilities && $listing->Facilities->facility){
          $property_features = $listing->Facilities->facility;
          foreach($property_features as $feature) {
            if(trim($feature) != ""){
              if(!term_exists($feature, 'aqua-features')){
                $term_ids = wp_insert_term(
                  $feature, // the term 
                  'aqua-features', // the taxonomy
                  array(
                    'slug' => $feature
                  )
                );
                $term_features[] = intval($term_ids['term_id']);
              }else{
                $term_ids = term_exists($feature, 'aqua-features');
                $term_features[] = intval($term_ids['term_id']);
              }
            } 
          }
        }
        $images = $listing->Images;
        $listing_images = array();
        if(isset($images->image)){
          $listing_images= (array)$images->image;
        }
        $property_location = (string) $listing->Community;
        $property_sub_location = (string) $listing->Property_Name;
        
        $property_price = (float) $listing->Price;
        $bedroom = (string) $listing->Bedrooms;
        $rooms = (string) $listing->No_of_Rooms;
        $latitute = (string) $listing->Latitude;
        $longitute = (string) $listing->Longitude;
        $video_url= (string) $listing->Web_Tour;
        $company_name= (string) $listing->company_name;
        $video_image= (string) isset($listing_images[0]) ? $listing_images[0] : '';
        $Featured = (string) $listing->Featured;
        $agent_name = (string) $listing->Listing_Agent;
        $agent_phone = (string) $listing->Listing_Agent_Phone;
        $agent_email = (string) $listing->Listing_Agent_Email;
        $listingdate = (string) $listing->Listing_Date;
        $featured = (int) $listing->Featured;
        //$emirate = (string) $listing->Emirate;
        //echo $address = $property_title . ' ' . $property_location . ' ' . $emirate; die();
        $agent = $this->save_agent($agent_name, $agent_phone, $agent_email);

        $property = $this->save_property($property_title, $property_decription, $property_price, $bedroom, $bathroom, $property_id, $agent_name, $property_status, $Property_type, $term_features, $Property_size, $size_postfix_text, $property_location, $listing_images, $latitute, $longitute, $video_url, $video_image, $strno, $company_name, $phone, $fax, $website, $agent_email, $featured, $property_sub_location);

        update_post_meta($property, 'aqua_property_agent_id',$agent); 
        /*echo "\nMemory:".memory_get_peak_usage(false)/(1024*1024);
        if(memory_get_peak_usage(false)/(1024*1024) > 256 |){
          //echo $count;
          break;
        }*/
        if($count % 10 == 0){
            echo $count." Properties Updated; \n";
            //flush();
            //ob_flush();
          //break;
        }
        $count++; 
        echo "Property Updated \n";
        $z->next('Listing');
      } 
      echo "\n".$count." Properties Loaded !";
        error_log("Propspace: Total: ".$count." Updated \n");
    }
    }catch(Exception $e){
    error_log("Propspace: Fatal Error: ".$e->getMessage()."  \n");
}
    // do something every hour
    $to      = 'icu090@gmail.com';
    $subject = 'Aqua Import Event';
    $message = $count.' Properties Finished Importing @'.date('F j, Y', time());
    $headers = 'X-Mailer: PHP/' . phpversion();

    mail($to, $subject, $message, $headers);
    die();
  }
  function save_agent($agentname, $agentphone, $agentemail) {
    global $wpdb;
    
    $_post = $wpdb->get_row("select $wpdb->posts.ID FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON ($wpdb->posts.ID=$wpdb->postmeta.post_id) WHERE $wpdb->postmeta.meta_key='aqua_agent_email_id' and $wpdb->postmeta.meta_value='$agentemail'");


    $post_array = array(
      'comment_status' => 'close',
      'ping_status' => 'open',
      'post_author' => '1',
      'post_category' => '',
      'post_content' => '',
      'post_date' => date('Y-m-d H:i:s', time()), //The time post was made.
      'post_date_gmt' => gmdate('Y-m-d H:i:s', time()), //The time post was made, in GMT.
      'post_excerpt' => '', //For all your post excerpt needs.
      'post_name' => str_replace(' ', '-', strtolower($agentname)), // The name (slug) for your post
      'post_parent' => 0, //Sets the parent of the new post.
      'post_password' => '', //password for post?
      'post_status' => 'publish',
      'post_title' => $agentname, //The title of your post.
      'post_type' => 'agent'
    );
    if (!$_post) {
      $post_id = wp_insert_post($post_array);
    }else{
      $post_array['ID'] = $_post->ID;
      $post_id = wp_update_post($post_array);
    }
    update_post_meta($post_id, 'aqua_agent_mobile_number', $agentphone);
    update_post_meta($post_id, 'aqua_agent_agent_email', $agentemail);
    //echo $post_id;
    return $post_id;
  }
  function delete_post_media( $post_id ) {

      $attachments = get_posts( array(
          'post_type'      => 'attachment',
          'posts_per_page' => -1,
          'post_status'    => 'any',
          'post_parent'    => $post_id
      ) );

      foreach ( $attachments as $attachment ) {
          if ( false === wp_delete_attachment( $attachment->ID ) ) {
              // Log failure to delete attachment.
          }
      }
  }
  function save_property($property_title, $property_decription, $property_price, $bedroom, $bathroom, $property_id, $agent_name, $property_status, $Property_type, $property_features_15, $Property_size, $size_postfix_text, $property_location, $listing_images, $latitute, $longitute, $video_url, $video_image, $strno, $company_name, $phone, $fax, $website, $email, $featured, $property_sub_location){
    global $wpdb;
    $_post = $wpdb->get_row("select $wpdb->posts.ID FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON $wpdb->posts.ID=$wpdb->postmeta.post_id WHERE $wpdb->postmeta.meta_key='aqua_property_ref-no' and $wpdb->postmeta.meta_value='$property_id'");
//echo "select $wpdb->posts.ID FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON $wpdb->posts.ID=$wpdb->postmeta.post_id WHERE $wpdb->postmeta.meta_key='aqua_property_ref-no' and $wpdb->postmeta.meta_value='$property_id'";
    $_post_fields = array(
      'comment_status' => 'close',
      'ping_status' => 'open',
      'post_author' => '1',
      'post_category' => '',
      'post_content' => '',
      'post_date' => date('Y-m-d H:i:s', time()), //The time post was made.
      'post_date_gmt' => gmdate('Y-m-d H:i:s', time()), //The time post was made, in GMT.
      'post_excerpt' => '', //For all your post excerpt needs.
      'post_name' => str_replace(' ', '-', strtolower($property_title)), // The name (slug) for your post
      'post_parent' => 0, //Sets the parent of the new post.
      'post_password' => '', //password for post?
      'post_status' => 'publish',
      'post_title' => $property_title, //The title of your post.
      'post_type' => 'property',
      'post_content'   => $property_decription
    ); 
    if ($_post && $_post->ID > 0) {
//return;
      $_post_fields['ID'] = $_post->ID;
      $this->delete_post_media($_post->ID); // remove the post media
      wp_update_post($_post_fields);
      //echo 'updating';
      //die();
      $post_id = $_post->ID;
    }else{
      $post_id = wp_insert_post($_post_fields);
    }
    if(!$post_id || $post_id === 0){
      return false;
    }
    wp_set_object_terms($post_id, $property_features_15, 'aqua-features',true); 
    update_post_meta($post_id, 'aqua_property_location', $latitute.','.$longitute); 
    
    $field_name = "field_56eceae3a5de9";
    $value = array("address" => $property_location, "lat" => $latitute, "lng" => $longitute);
    update_field($field_name, $value, $post_id);
    
    update_post_meta($post_id, 'aqua_tour_video_image', $video_image); 
    update_post_meta($post_id, 'aqua_tour_video_url', $video_url);
    
    require_once(ABSPATH.'wp-admin/includes/image.php');
    require_once(ABSPATH.'wp-admin/includes/media.php');
    require_once(ABSPATH.'wp-admin/includes/file.php');
    if($listing_images){
        global $wpdb;
        $listing_images = array_values($listing_images);
        $gallery = array();
        $featured_done = false;
        $vimage = array();
      foreach($listing_images as $key => $listing_image){
        $image = $listing_image;
        $found = false;
        $var = $wpdb->get_results("SELECT * FROM wp_posts INNER JOIN wp_postmeta ON ( wp_posts.ID = wp_postmeta.post_id ) WHERE 1=1 AND ( ( wp_postmeta.meta_key = 'propspace_link' AND wp_postmeta.meta_value = '".$listing_image."' ) ) AND wp_posts.post_type = 'attachment'");
        if(sizeof($var) > 0 && !is_wp_error($var)){
          
          $gallery[] = $var[0]->ID;
          continue;
        }
        // magic sideload image returns an HTML image, not an ID
        $media = media_sideload_image($image, $post_id);

        // therefore we must find it so we can set it as featured ID
        if(!empty($media) && !is_wp_error($media)){
            $args = array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'post_parent' => $post_id
            );

            // reference new image to set as featured
            $attachments = get_posts($args);
	
            if(isset($attachments) && is_array($attachments)){
                foreach($attachments as $attachment){
                    // grab source of full size images (so no 300x150 nonsense in path)
                    $ximage = wp_get_attachment_image_src($attachment->ID, 'full');
                    // determine if in the $media image we created, the string of the URL exists
                    if(strpos($media, $ximage[0]) !== false){
                        // if so, we found our image. set it as thumbnail
                        $vimage[] = $image;
                        update_post_meta($attachment->ID, 'propspace_link', $image);
                        
                        $gallery[] = $attachment->ID;
                        // only want one image
                        break;
                    }
                }
            }

        }
      }
if(sizeof($gallery) > 0){

update_post_meta( $post_id, '_thumbnail_id', $gallery[0]);
}
      update_field("field_56ed55b9ca915", $gallery, $post_id);
      update_post_meta($post_id, 'aqua_image', $listing_images[0]);
    }
    update_post_meta($post_id, 'aqua_property_bedrooms', $bedroom);
    update_post_meta($post_id, 'aqua_property_bathrooms', $bathroom);
    update_post_meta($post_id, 'aqua_property_ref-no', $property_id);
    update_post_meta($post_id, 'aqua_property_price', $property_price);
    update_post_meta($post_id, 'aqua_property_area', $Property_size);
    update_post_meta($post_id, 'aqua_property_size_postfix', $size_postfix_text);
    update_post_meta($post_id, 'aqua_property_address', $property_location);
    update_post_meta($post_id, 'aqua_property_str-no_description', $strno);
    update_post_meta($post_id, 'aqua_property_company', $company_name);
    
    update_post_meta($post_id, 'aqua_property_phone', strip_tags($phone));
    update_post_meta($post_id, 'aqua_property_fax', strip_tags($fax));
    update_post_meta($post_id, 'aqua_property_website', strip_tags($website));
    update_post_meta($post_id, 'aqua_property_email', $email);
    
    update_post_meta($post_id, 'aqua_property_featured', $featured);
    $property_city_13 = term_exists($property_location, 'aqua-location');
    if(!$property_city_13){
      if(trim( $property_location) != ""){
        $term_ids = wp_insert_term(
          $property_location, // the term 
          'aqua-location', // the taxonomy
          array(
            'slug' => $property_location
          )
        );
        if(!is_wp_error($property_city_13)){
          $property_city_13 = intval($term_ids['term_id']);
        }
      }
    }else{
      $property_city_13 = $property_city_13["term_id"];
    }
    if($property_city_13 !== false){
      //$property_city_13 = array_unique(array_map( 'intval', (array)$property_city_13));
      wp_set_object_terms($post_id, (array)intval($property_city_13), 'aqua-location',false);
      //echo "updated location".$post_id.":".implode(",", $property_city_13);
    }

    if( !empty($property_sub_location) && !empty($property_city_13)){
        $term = term_exists($property_sub_location, 'aqua-location');
        if ($term !== 0 && $term !== null) {
          $sub_location_id = $term['term_id']; 
          wp_update_term($sub_location_id, 'aqua-location', array(
            'parent' => $property_city_13
          ));
        }else{
          $sub_location_id = wp_insert_term(
            $property_sub_location, // the term 
            'aqua-location', // the taxonomy
            array(
              'parent' => $property_city_13,
              'slug' => $property_sub_location
            )
          );
        }
        $sub_location_id = array_unique(array_map( 'intval', (array)$sub_location_id));
        wp_set_object_terms($post_id, $sub_location_id, 'aqua-location',true);
    }
    /*if( $sub_location_id !== false ) {
      
      wp_set_object_terms($post_id, $sub_location_id, 'aqua-location',false);
      echo "updated sub location".$post_id.":".implode(",", $sub_location_id);
    }*/

    $property_status_12 = term_exists($property_status, 'aqua-status');
    if(!$property_status_12){
      if(trim($property_status) != ""){
      $term_ids = wp_insert_term(
        $property_status, // the term 
        'aqua-status', // the taxonomy
        array(
          'slug' => $property_status
        )
      );
      $property_status_12 = intval($term_ids['term_id']);
      }
    }
    if($property_status_12 !== false){
      $property_status_12 =array_unique(array_map( 'intval', (array)$property_status_12));
      wp_set_object_terms($post_id, $property_status_12, 'aqua-status',false);
    }

    $Property_type_13 = term_exists($Property_type, 'aqua-type');
    if(!$Property_type_13){
      if(trim($Property_type) != ""){
      $term_ids = wp_insert_term( 
        $Property_type, 
        'aqua-type', 
        array(
          'slug' => $Property_type
        )
      );
      $Property_type_13 = intval($term_ids['term_id']);
      }
    }
    if($Property_type_13 !== false){
      $Property_type_13 = array_unique(array_map( 'intval', (array)$Property_type_13));
      wp_set_object_terms($post_id, $Property_type_13, 'aqua-type',false);
    }
    echo "Property ID: ".$post_id." Updated; \n";
    error_log("Propspace: Property ID: ".$post_id." Updated; \n");
    return $post_id;
  }
}
global $AAA_Importer;
$AAA_Importer = new AAA_Importer();
register_deactivation_hook(__FILE__, 'Clear_Events');
function Clear_Events() {
	wp_clear_scheduled_hook( 'aqua_import_event');
}
register_activation_hook(__FILE__, array($AAA_Importer,'activate'));
?>