<?php

class PostrunnerClient{
  
  private static function get_client(){
    include_once(ABSPATH . WPINC . '/class-IXR.php');
    return new IXR_Client(PR_COMM_URL);
  }


  function verify_parent_nonce($nonce, $action){
    return true;
    $response = self::get_client()->query('pr.VerifyNonce', get_option('home'), $action);
    if($response == 'NONCE VERIFIED'){
      return true;
    }else{
      return false;
    }
  }

  function approve_post($post_id, $parent_id, $approve, $permalink, $score, $feedback = '', $blacklist = false){
    $nonce = PostrunnerServer::create_nonce('pr.ApprovePost');
    $data['child_id']   = intval($post_id);
    $data['parent_id']  = intval($parent_id);
    $data['feedback']   = $feedback;
    $data['approval']   = $approve;
    $data['permalink']  = $permalink;
    $data['score']      = intval($score);
    $data['blacklist']  = $blacklist;
    $response = self::get_client()->query('pr.ApprovePost', $nonce, get_option('home'), $data);

    if(!$response){
      // HANDLE FAILURE
    }
  }
}