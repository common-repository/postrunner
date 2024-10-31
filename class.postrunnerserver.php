<?php

add_action('xmlrpc_methods', 'pr_xmlrpc_methods');
$postrunner_server = new PostrunnerServer;
function pr_xmlrpc_methods($methods) {
    $methods['pr.VerifyNonce']              = array('PostrunnerServer', 'verify_child_nonce');
    $methods['pr.GetStatus']                = array('PostrunnerServer', 'get_status');
    $methods['pr.CreatePost']               = array('PostrunnerServer', 'create_post');
    $methods['pr.PostStatusRequest']        = array('PostrunnerServer', 'post_status_request');
    return $methods;
}
class PostrunnerServer{  
  public static function verify_child_nonce($args){
    $nonce    = $args[1][0];
    $action   = $args[1][1];
    
  	$i = wp_nonce_tick();
  
  	if ( substr(wp_hash($i . $action, 'nonce'), -12, 10) == $nonce ){
    	// Nonce generated 0-12 hours ago
  		return 'NONCE VERIFIED';
    } else{
    	// Invalid nonce
    	return 'INVALID NONCE';
    }
  }
  
  public static function get_status($args){
    $nonce = $args[0];
    if(!PostrunnerClient::verify_parent_nonce($nonce, 'pr.GetStatus')){
      return "AUTHORIZATION FAILURE";
    }
  
    $return['status']           = 'success';
    $return['name']             = get_option('blogname');
    $return['home']             = get_option('home');
    $return['siteurl']          = get_option('siteurl');
    
    // Get Plugin Version
    $return['plugin-version']   = PR_PLUGIN_VERSION;
  
    $return['total-post-count'] = Postrunner::get_total_post_count();
    $return['pr-post-count']    = Postrunner::get_pr_post_count();
  
    // Get WP Version
    include ABSPATH.'/wp-includes/version.php';
    $return['wp-version']       = $wp_version;
    
    $return['traffic'] = get_option('pr_pageview_yesterday');
    
    $postrunner = new Postrunner();
    $postrunner->send_back_old_posts();
    return $return;
  }
  
  public static function create_post($args){
	global $wpdb;
    $nonce = $args[0];
    if(!PostrunnerClient::verify_parent_nonce($nonce, 'pr.CreatePost')){
      return "AUTHORIZATION FAILURE";
    }else{
      $data = $args[1];
    }
  
    // Create Post
    $post['post_title']   = $data['title'];
    $post['post_content'] = $data['content'];
  
    $post['post_status']  = 'pending';
    $pr_receive_time = mktime();
    //$post['post_author']  = 1;
  
    // Flag as coming from PostRunner
    $child_id = wp_insert_post($post, true);
    if ( is_wp_error( $child_id ) ) {
       $errors = $child_id->get_error_codes();
       foreach($errors as $key=>$code){
         $return['errors'][$code] = $child_id->get_error_messages($code);
       }
       $return['status'] = 'error';
    }else{
      $return['status'] = 'success';
      update_post_meta($child_id, '_remote_id', $data['parent_id'], true);
      update_post_meta($child_id, '_pr_sent',      $pr_receive_time, true);
      update_post_meta($child_id, '_pr_status',    'sent', true);
      $return['child_id']      = $child_id;
      $return['parent_id']     = $data['parent_id'];
      $return['received_time'] = $pr_receive_time;
    }
  
    return $return;
  }

  function post_status_request($args){
    $nonce = $args[0];
    if(!PostrunnerClient::verify_parent_nonce($nonce, 'pr.PostStatusRequest')){
      return "AUTHORIZATION FAILURE";
    }
    global $wpdb;
    $post_parent_ids = $args[1]['post_requests'];
    $check_type = $args[1]['check_type'];
    if($check_type == 'full'){
      // Postrunner has sent over a complete list of posts waiting on responses
      // Any post waiting for review which is not in the list from postrunner
      // should be deleted.
      $pending_posts = $wpdb->get_results("select post_id, meta_value as parent_id from wp_local_postmeta meta JOIN wp_local_posts posts ON posts.id = meta.post_id WHERE meta_key = '_remote_id' AND posts.post_status !='publish'", ARRAY_A);
      if(is_array($pending_posts)){
        foreach($pending_posts as $pending_post){
          if(!in_array($pending_post['parent_id'], $post_parent_ids)){
            wp_trash_post($pending_post['post_id'], true);
          }
        }   
      }
      
    }
    foreach($post_parent_ids as $post_parent_id){
      // Check if post exists
      $post_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_value = %d AND meta_key = '_remote_id'", $post_parent_id));
      if($post_id){
        $post = get_post($post_id);
        $status[$post_parent_id]['exists']      = true;
          $status[$post_parent_id]['child_id']  = $post_id;
        if($post->post_status == 'publish'){
          $status[$post_parent_id]['permalink'] = get_permalink($post_id);
          $status[$post_parent_id]['score']     = get_post_meta($post_id, '_pr_score', true);
          $status[$post_parent_id]['feedback']  = get_post_meta($post_id, '_pr_feedback', true);
          $status[$post_parent_id]['status']    = 'live';
          update_post_meta($post_id, '_pr_status', 'live');
        }else{
          $status[$post_parent_id]['status']      = get_post_meta($post_id, '_pr_status', true);
        }
      }else{
        $status[$post_parent_id]['exists']      = false;
        $status[$post_parent_id]['status']      = false;
        $status[$post_parent_id]['child_id']    = -1;
      }
    }
    $return['status'] = 'success';
    $return['post_statuses'] = $status;
        
    return $return;
  }
  // Nonce functions pulled from 
  // WP's nonce functionality
  static function create_nonce($action){
  	$i = wp_nonce_tick();
  	return substr(wp_hash($i . $action, 'nonce'), -12, 10);
  } 
}

