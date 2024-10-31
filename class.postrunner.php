<?php

class Postrunner{
  public $approving = array();
  public $deleting  = array();
  public $notices   = array();
  public $pr_posts  = array();
  
  public function trash_post($post_id){
    if(get_post_meta($post_id, '_remote_id')){
      // This is a Postrunner submitted post.  delete it all the way.
      wp_delete_post($post_id, true);
    }
  }
  
  function delete_post($post_id){
    // The delete action runs twice, and we don't want to send 2 declines
    // Also ignore posts that are being handled by the approval function
    if(in_array($post_id, $this->approving) || in_array($post_id, $this->deleting)){
      return;
    }
    $this->deleting[] = $post_id;
    $post = get_post($post_id);
  
    // Ensure we're not working with a revision
    if($post->post_parent != 0){
      return; 
    }
    
    // Get dashboard post id and password
    $parent_id = get_post_meta($post_id, '_remote_id', true);

    // Make sure this is a postrunner post
    if($remote_id == 0){
      return;
    }
    PostrunnerClient::approve_post($post_id, $parent_id, 'deleted', '', 0, '');
  }

  function settings(){
    register_setting('general', 'pr_share_pageviews');
   	// Add the section to reading settings so we can add our
   	// fields to it
   	add_settings_section('postrunner_settings_section',
  		'Postrunner Settings',
  		array($this, 'display_settings_page'),
  		'general');
   	
   	// Add the field with the names and function to use for our new
   	// settings, put it in our new section

   	add_settings_field('pr_share_pageviews',
  		'Share Traffic Data',
  		array($this, 'share_pageviews_setting_callback'),
  		'general',
  		'postrunner_settings_section');

  }
  
  function display_settings_page(){
    // No intro text yet.
  }

 function share_pageviews_setting_callback() {
 	echo '<input name="pr_share_pageviews" id="pr_share_pageviews" type="checkbox" value="1" class="code" ' . checked( 1, get_option('pr_share_pageviews'), false ) . ' /> <label for="pr_share_pageviews">Share very basic site traffic data with Postrunner.</label><p class="description">Only (rough) number of daily visitors will be shared.  No identifiable or unique information will be shared.  Data is used to display a basic traffic level for your site in Postrunner listings.</p>';
 }
  function post_page_setup() {
    global $post;
    // Remove normal publish box
    $remote_id = get_post_meta($post->ID, '_remote_id', true);
    // Ensure this post came from postrunner, and the current user is an admin 
    if(current_user_can('manage_options') && $remote_id != 0){
    	remove_meta_box( 'submitdiv', 'post', 'side' );
      add_meta_box('submitdiv', 'Publish <small> or </small> Decline Post', array($this, 'approval_box'), 'post', 'side', 'high');
    }
  }
  // Adds the actual option box
  function approval_box() {
    global $post;
    $post_score = intval(get_post_meta($post->ID, '_pr_score', true));
    $feedback   = get_post_meta($post->ID, '_pr_feedback', true);
  	$post_type = $post->post_type;
  	$post_type_object = get_post_type_object($post_type);
  	$can_publish = current_user_can($post_type_object->cap->publish_posts);
    ?>
<div class="submitbox" id="submitpost">
<div id="minor-publishing">

  <div style="display:none;"><p class="submit"><input type="submit" name="save" id="save" class="button" value="Save"  /></p></div>
  
  <div id="pr-score" class="misc-pub-section" style="padding:10px">
    <?php if($_GET['pr_message'] == 1): ?><p style="color:#ff0000;padding:0;margin:0 0 5px 0;text-align:center">Please score this post</p><?php endif; ?>
    <select name="pr_score" style="width:100%;font-size:13px"  <?php if($post->post_status == 'publish' && $post_score){ echo "disabled"; } ?> >
      <option value="0">Score this Post</option>
      <option value="1" <?php echo selected($post_score, 1); ?>>Very Poor</option>
      <option value="2" <?php echo selected($post_score, 2); ?>>Poor</option>
      <option value="3" <?php echo selected($post_score, 3); ?>>Average</option>
      <option value="4" <?php echo selected($post_score, 4); ?>>Good</option>
      <option value="5" <?php echo selected($post_score, 5); ?>>Very Good</option>
    </select>
  </div>
  <div class="misc-pub-section">
    <div style="font-size:14px;font-weight:bold;margin:5px;">Send feedback to the author:</div>
    <textarea id="pr_feedback" name="pr_feedback" <?php if($post->post_status == 'publish'){ echo "disabled"; } ?> rows="3" cols="20" style="width:100%"><?php echo $feedback; ?></textarea>
  </div>
  <div class="misc-pub-section">
    <input type="checkbox" name="pr_blacklist" id="pr_blacklist" value="blacklist">
    <label for="pr_blacklist" title="Blacklisting will prevent this author from submitting to this site in the future.">Blacklist this author</label>
  </div>
</div>

<div id="minor-publishing">

<?php // Hidden submit button early on so that the browser chooses the right button when form is submitted with Return key ?>
<div style="display:none;">
<?php submit_button( __( 'Save' ), 'button', 'save' ); ?>
</div>

<div id="minor-publishing-actions">
<div id="save-action">
<?php if ( 'publish' != $post->post_status && 'future' != $post->post_status && 'pending' != $post->post_status )  { ?>
<input <?php if ( 'private' == $post->post_status ) { ?>style="display:none"<?php } ?> type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save Draft'); ?>" tabindex="4" class="button button-highlighted" />
<?php } elseif ( 'pending' == $post->post_status && $can_publish ) { ?>
<input type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save as Pending'); ?>" tabindex="4" class="button button-highlighted" />
<?php } ?>
<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" id="draft-ajax-loading" alt="" />
</div>

<div id="preview-action">
<?php
if ( 'publish' == $post->post_status ) {
	$preview_link = esc_url( get_permalink( $post->ID ) );
	$preview_button = __( 'Preview Changes' );
} else {
	$preview_link = get_permalink( $post->ID );
	if ( is_ssl() )
		$preview_link = str_replace( 'http://', 'https://', $preview_link );
	$preview_link = esc_url( apply_filters( 'preview_post_link', add_query_arg( 'preview', 'true', $preview_link ) ) );
	$preview_button = __( 'Preview' );
}
?>
<a class="preview button" href="<?php echo $preview_link; ?>" target="wp-preview" id="post-preview" tabindex="4"><?php echo $preview_button; ?></a>
<input type="hidden" name="wp-preview" id="wp-preview" value="" />
</div>

<div class="clear"></div>
</div><?php // /minor-publishing-actions ?>

<div id="misc-publishing-actions">

<div class="misc-pub-section<?php if ( !$can_publish ) { echo ' misc-pub-section-last'; } ?>"><label for="post_status"><?php _e('Status:') ?></label>
<span id="post-status-display">
<?php
switch ( $post->post_status ) {
	case 'private':
		_e('Privately Published');
		break;
	case 'publish':
		_e('Published');
		break;
	case 'future':
		_e('Scheduled');
		break;
	case 'pending':
		_e('Pending Review');
		break;
	case 'draft':
	case 'auto-draft':
		_e('Draft');
		break;
}
?>
</span>
<?php if ( 'publish' == $post->post_status || 'private' == $post->post_status || $can_publish ) { ?>
<a href="#post_status" <?php if ( 'private' == $post->post_status ) { ?>style="display:none;" <?php } ?>class="edit-post-status hide-if-no-js" tabindex='4'><?php _e('Edit') ?></a>

<div id="post-status-select" class="hide-if-js">
<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr( ('auto-draft' == $post->post_status ) ? 'draft' : $post->post_status); ?>" />
<select name='post_status' id='post_status' tabindex='4'>
<?php if ( 'publish' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'publish' ); ?> value='publish'><?php _e('Published') ?></option>
<?php elseif ( 'private' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'private' ); ?> value='publish'><?php _e('Privately Published') ?></option>
<?php elseif ( 'future' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'future' ); ?> value='future'><?php _e('Scheduled') ?></option>
<?php endif; ?>
<option<?php selected( $post->post_status, 'pending' ); ?> value='pending'><?php _e('Pending Review') ?></option>
<?php if ( 'auto-draft' == $post->post_status ) : ?>
<option<?php selected( $post->post_status, 'auto-draft' ); ?> value='draft'><?php _e('Draft') ?></option>
<?php else : ?>
<option<?php selected( $post->post_status, 'draft' ); ?> value='draft'><?php _e('Draft') ?></option>
<?php endif; ?>
</select>
 <a href="#post_status" class="save-post-status hide-if-no-js button"><?php _e('OK'); ?></a>
 <a href="#post_status" class="cancel-post-status hide-if-no-js"><?php _e('Cancel'); ?></a>
</div>

<?php } ?>
</div><?php // /misc-pub-section ?>

<div class="misc-pub-section " id="visibility">
<?php _e('Visibility:'); ?> <span id="post-visibility-display"><?php

if ( 'private' == $post->post_status ) {
	$post->post_password = '';
	$visibility = 'private';
	$visibility_trans = __('Private');
} elseif ( !empty( $post->post_password ) ) {
	$visibility = 'password';
	$visibility_trans = __('Password protected');
} elseif ( $post_type == 'post' && is_sticky( $post->ID ) ) {
	$visibility = 'public';
	$visibility_trans = __('Public, Sticky');
} else {
	$visibility = 'public';
	$visibility_trans = __('Public');
}

echo esc_html( $visibility_trans ); ?></span>
<?php if ( $can_publish ) { ?>
<a href="#visibility" class="edit-visibility hide-if-no-js"><?php _e('Edit'); ?></a>

<div id="post-visibility-select" class="hide-if-js">
<input type="hidden" name="hidden_post_password" id="hidden-post-password" value="<?php echo esc_attr($post->post_password); ?>" />
<?php if ($post_type == 'post'): ?>
<input type="checkbox" style="display:none" name="hidden_post_sticky" id="hidden-post-sticky" value="sticky" <?php checked(is_sticky($post->ID)); ?> />
<?php endif; ?>
<input type="hidden" name="hidden_post_visibility" id="hidden-post-visibility" value="<?php echo esc_attr( $visibility ); ?>" />


<input type="radio" name="visibility" id="visibility-radio-public" value="public" <?php checked( $visibility, 'public' ); ?> /> <label for="visibility-radio-public" class="selectit"><?php _e('Public'); ?></label><br />
<?php if ( $post_type == 'post' && current_user_can( 'edit_others_posts' ) ) : ?>
<span id="sticky-span"><input id="sticky" name="sticky" type="checkbox" value="sticky" <?php checked( is_sticky( $post->ID ) ); ?> tabindex="4" /> <label for="sticky" class="selectit"><?php _e( 'Stick this post to the front page' ); ?></label><br /></span>
<?php endif; ?>
<input type="radio" name="visibility" id="visibility-radio-password" value="password" <?php checked( $visibility, 'password' ); ?> /> <label for="visibility-radio-password" class="selectit"><?php _e('Password protected'); ?></label><br />
<span id="password-span"><label for="post_password"><?php _e('Password:'); ?></label> <input type="text" name="post_password" id="post_password" value="<?php echo esc_attr($post->post_password); ?>" /><br /></span>
<input type="radio" name="visibility" id="visibility-radio-private" value="private" <?php checked( $visibility, 'private' ); ?> /> <label for="visibility-radio-private" class="selectit"><?php _e('Private'); ?></label><br />

<p>
 <a href="#visibility" class="save-post-visibility hide-if-no-js button"><?php _e('OK'); ?></a>
 <a href="#visibility" class="cancel-post-visibility hide-if-no-js"><?php _e('Cancel'); ?></a>
</p>
</div>
<?php } ?>

</div><?php // /misc-pub-section ?>

<?php
// translators: Publish box date formt, see http://php.net/date
$datef = __( 'M j, Y @ G:i' );
if ( 0 != $post->ID ) {
	if ( 'future' == $post->post_status ) { // scheduled for publishing at a future date
		$stamp = __('Scheduled for: <b>%1$s</b>');
	} else if ( 'publish' == $post->post_status || 'private' == $post->post_status ) { // already published
		$stamp = __('Published on: <b>%1$s</b>');
	} else if ( '0000-00-00 00:00:00' == $post->post_date_gmt ) { // draft, 1 or more saves, no date specified
		$stamp = __('Publish <b>immediately</b>');
	} else if ( time() < strtotime( $post->post_date_gmt . ' +0000' ) ) { // draft, 1 or more saves, future date specified
		$stamp = __('Schedule for: <b>%1$s</b>');
	} else { // draft, 1 or more saves, date specified
		$stamp = __('Publish on: <b>%1$s</b>');
	}
	$date = date_i18n( $datef, strtotime( $post->post_date ) );
} else { // draft (no saves, and thus no date specified)
	$stamp = __('Publish <b>immediately</b>');
	$date = date_i18n( $datef, strtotime( current_time('mysql') ) );
}

if ( $can_publish ) : // Contributors don't get to choose the date of publish ?>
<div class="misc-pub-section curtime misc-pub-section-last">
	<span id="timestamp">
	<?php printf($stamp, $date); ?></span>
	<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" tabindex='4'><?php _e('Edit') ?></a>
	<div id="timestampdiv" class="hide-if-js"><?php touch_time(($action == 'edit'),1,4); ?></div>
</div><?php // /misc-pub-section ?>
<?php endif; ?>

<?php do_action('post_submitbox_misc_actions'); ?>
</div>
<div class="clear"></div>
</div>
<script type="text/javascript">
<!--
function confirm_delete() {
	var answer = confirm("Are you sure you want to delete this post, and send it back to Postrunner?  This cannot be undone.")
	if (answer){
    return true;
	}else{
    return false;
	}
}
//-->
</script>

<div id="major-publishing-actions">
<?php do_action('post_submitbox_start'); ?>
<div id="delete-action">
<?php if($post->post_status == 'publish'): ?>
<input type="submit" name="save" value="Delete Post" style="color:#ff0000;font-weight:bold" class="button button-highlighted" onClick="if(confirm_delete()){ return true; }else{ location.reload(true); return false; }">
<?php else: ?>
<input type="submit" name="save" value="Decline Post" style="color:#ff0000;font-weight:bold" class="button button-highlighted">
<?php endif; ?>
</div>

<div id="publishing-action">
<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" id="ajax-loading" alt="" />
<?php
if ( !in_array( $post->post_status, array('publish', 'future', 'private') ) || 0 == $post->ID ) {
	if ( $can_publish ) :
		if ( !empty($post->post_date_gmt) && time() < strtotime( $post->post_date_gmt . ' +0000' ) ) : ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Schedule') ?>" />
		<?php submit_button( __( 'Schedule' ), 'primary', 'publish', false, array( 'tabindex' => '5', 'accesskey' => 'p' ) ); ?>
<?php	else : ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Publish') ?>" />
		<?php submit_button( __( 'Publish' ), 'primary', 'publish', false, array( 'tabindex' => '5', 'accesskey' => 'p' ) ); ?>
<?php	endif;
	else : ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Submit for Review') ?>" />
		<?php submit_button( __( 'Submit for Review' ), 'primary', 'publish', false, array( 'tabindex' => '5', 'accesskey' => 'p' ) ); ?>
<?php
	endif;
} else { ?>
		<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Update') ?>" />
		<input name="save" type="submit" class="button-primary" id="publish" tabindex="5" accesskey="p" value="<?php esc_attr_e('Update') ?>" />
<?php
} ?>
</div>
<div class="clear"></div>
</div>
</div>
    <?php
  }

  function get_total_post_count(){
    global $wpdb;
    return $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_parent = 0 and post_status = 'publish' and post_type = 'post'"); 
  }
  
  function get_pr_post_count(){
    global $wpdb;
    return $wpdb->get_var("select count(ID) from $wpdb->posts p join $wpdb->postmeta pm on p.ID = pm.post_id where meta_key = '_remote_id' AND post_parent = 0  and post_status = 'publish' and post_type = 'post'"); 
  }
  

  function handle_post_save($data, $postarr){
    global $wpdb;

    // $data holds content that is about to be inserted.
    // $postarr holds array from argument to wp_insert_post
    $post_id = $postarr['ID'];
    $this->approving[] = $post_id;
    // Prevent this code from running on revisions - we only want to run it when the modified post is being 
    // inserted over the original parent post.
    if(DOING_AUTOSAVE === true || $data['post_parent'] != 0 ){
      return $data;
    }
    
    // Only work on Postrunner posts
    $parent_id = get_post_meta($post_id, '_remote_id', true);

    if(!$parent_id) return $data;

    $status = get_post_meta($post_id, '_pr_status', true);

    if(isset($_POST['pr_score'])){
      $score = intval($_POST['pr_score']);
    }else{
      $score = get_post_meta($post_id, '_pr_score', true);
    }
    
    if(isset($_POST['pr_feedback'])){
      $feedback = $_POST['pr_feedback'];
    }else{
      $feedback = get_post_meta($post_id, '_pr_feedback', true);
    }
    
    if($_POST['pr_blacklist'] == 'blacklist'){
      $blacklist = true;
    }else{
      $blacklist = false;
    }
    // Check if we're declining
    if($_POST['save'] == 'Decline Post' || $_POST['save'] == 'Delete Post'){
      if($score < 1){
        $this->notices[] = "In order to decline or approve this post, you must choose a post score.";
    		$location = add_query_arg( 'pr_message', 1, get_edit_post_link( $post_id, 'url' ) );
    		header('LOCATION: '.$location);
        die();
      }
      $approve = $_POST['save'] == 'Delete Post' ? 'deleted' : 'declined';
      $permalink = '';
      PostrunnerClient::approve_post($post_id, $parent_id, $approve, $permalink, $score, $feedback, $blacklist);
      wp_delete_post($post_id, true);
      return $data;
    }

    switch($data['post_status']){
      case 'publish':
        // Check if this post has already been approved
        if($status != 'live'){
          if($score < 1){
            $this->notices[] = "In order to decline or approve this post, you must choose a post score.";
        		$location = add_query_arg( 'pr_message', 1, get_edit_post_link( $post_id, 'url' ) );
        		header('LOCATION: '.$location);
            die();
          }
          $permalink = get_permalink($post_id);
          update_post_meta($post_id, '_pr_status', 'live');
          update_post_meta($post_id, '_pr_feedback', $feedback);
          update_post_meta($post_id, '_pr_score', $score);
          $approve = 'approved';
          PostrunnerClient::approve_post($post_id, $parent_id, $approve, $permalink, $score, $feedback, $blacklist);
        }
        break;
      case 'pending':
      case 'draft':
      case 'private':
      default:
        update_post_meta($post_id, '_pr_feedback', $feedback);
        update_post_meta($post_id, '_pr_score', $score);
        break;
    }
    return $data;
  }

  function display_notices(){
    if(isset($_GET['pr_message'])){
      switch($_GET['pr_message']){
        case '1':
          $notice['type'] = 'error';
          $notice['message'] = 'You must give this post a score before you can approve or decline it.';
          $this->notices[] = $notice;
          break;
      }
    }
    if( is_array($this->notices) && !empty($this->notices) ){
      foreach($this->notices as $notice){
        if(!is_array($notice)){
          $notice['type']    = 'updated';
          $notice['message'] = $notice;
        }
        echo "<div class=\"".$notice['type']." below-h2\">";
        echo "<p>".$notice['message']."</p>\n";
        echo "</div>";
      }
    }
  }
  function post_date_column_time($time, $post, $column_name, $mode){
    if($this->is_from_postrunner($post->ID)){
      if($post->post_status != 'publish'){
        $time = "Rec'd ".human_time_diff($this->get_post_receipt_time($post->ID))." ago";
        $sendback_date = strtotime('+'.PR_REVIEW_PERIOD, $this->get_post_receipt_time($post->ID));
        // Total review time in seconds
        $total_review_time = strtotime('+'.PR_REVIEW_PERIOD, 0);
        // Color the "returned" text
        // Greater then 2/3 review time left
        if($sendback_date - time() > ($total_review_time/3)*2){
          $color = '#108f25';
        // Greater than 1/3 review time left
        }elseif($sendback_date - time() > ($total_review_time/3)){
          $color = '#d2c106';
        }else{
          $color = '#ff0000';
        }
        
        if($sendback_date < time()){
          $sendback_date = time();
        }
        $time .= '<br><span style="color:'.$color.'">'.human_time_diff($sendback_date).' until rejection</span>';
      }else{
  
      }
    }
    return $time;
  }
  
  // Fetches list of pr posts.  Caches on a per pageload basis
  function is_from_postrunner($post_id = 0){
    global $wpdb;
    if(empty($this->pr_posts)){
      $pr_post_results = $wpdb->get_results("select post_id, meta_value from $wpdb->postmeta where meta_key = '_pr_sent'", ARRAY_A);
      if(is_array($pr_post_results) && !empty($pr_post_results)){
        foreach($pr_post_results as $pr_post){
          $this->pr_posts[$pr_post['post_id']] = $pr_post['meta_value'];
        }
      }
    }
    if(in_array($post_id, array_keys($this->pr_posts))){
      return true;
    }else{
      return false;
    }
  }
  
  function display_post_states($states){
    global $post;
    if($this->is_from_postrunner($post->ID)){
      if($post->post_status == 'pending'){
        $states = array();
        $states[] = '<br><span style="color:#108f25">PostRunner</span> - Waiting to be Reviewed';
      }
    }
    return $states;
  }

  function posts_join($join){
    global $wpdb;
    if( !empty($_GET['postrunner_status'])) {
      $join .= " join $wpdb->postmeta pm  ON $wpdb->posts.ID = pm.post_id";
    }
    return $join;
  }
  
  function posts_where($where){
    global $wpdb;
    if(!empty($_GET['postrunner_status'])){
      $pr_post_status = $wpdb->escape($_GET['postrunner_status']);
      switch($pr_post_status){
        case 'approved':
          $where .= " AND pm.meta_key = '_pr_sent' AND post_status = 'publish'";
          break;
        case 'waiting':
          $where .= " AND pm.meta_key = '_pr_sent' AND post_status != 'publish'";
          break;
        case 'any':
        default:
          $where .= " AND pm.meta_key = '_pr_sent'";
          break;
      }
    }
    return $where;
  }

  function post_page_dropdowns($args) {
    global $pagenow;
    if($pagenow = 'edit.php'){
      ?>
      <select name="postrunner_status" id="postrunner_filter">
        <option value="" >Postrunner</option>
        <option value="any" <?php selected('all', $_GET['postrunner_status']); ?>>All</option>
        <option value="approved" <?php selected('approved', $_GET['postrunner_status']); ?>>Approved</option>
        <option value="waiting" <?php selected('waiting', $_GET['postrunner_status']); ?>>Waiting for Approval</option>
      </select>
      <?php
    }
  }

  function get_post_receipt_time($post_id){
    if(!is_array($pr_posts)){
      $this->is_from_postrunner();
    }
    return $this->pr_posts[$post_id];
  }
  
  function dashboard_setup(){
    wp_add_dashboard_widget('pr_dashboard_widget', 'PostRunner', array($this, 'dashboard_widget'));
  }

  function dashboard_widget(){
    global $wpdb;
    $total_posts = $wpdb->get_var("select count(ID) from $wpdb->posts p join $wpdb->postmeta pm on p.ID = pm.post_id where meta_key = '_pr_sent' AND post_parent = 0");
    $approved_posts = $wpdb->get_var("select count(ID) from $wpdb->posts p join $wpdb->postmeta pm on p.ID = pm.post_id where meta_key = '_pr_sent' AND post_parent = 0 AND post_status = 'publish'");
    $waiting_approval = $total_posts-$approved_posts;
  ?>
    <div id="postrunner-dashboard">
      <div class="table">
        <table>
          <tr class="first">
            <td class="first b b-tags"><a href="edit.php?postrunner_status=waiting"><?php echo $total_posts; ?></a></td>
            <td class="t"><a href="edit.php?postrunner_status=any">Posts received from Postrunner</a></td>
            <td class="last t"></td>
          </tr>
          <tr>
            <td class="first b b-tags"><a href="edit.php?postrunner_status=waiting"><?php echo $approved_posts; ?></a></td>
            <td class="t"><a href="edit.php?postrunner_status=approved">Posts approved</a></td>
          </tr>
          <tr>
            <td class="first b b-tags"><a href="edit.php?postrunner_status=waiting"><?php echo $waiting_approval; ?></a></td>
            <td class="t"><a href="edit.php?postrunner_status=waiting">Posts waiting for approval</a></td>
          </tr>
        </table>
      </div>
    </div>
  <?php
  }
  
  function dashboard_css(){
    global $pagenow;
    if($pagenow != 'index.php') return; ?>
  <style type="text/css">
  #postrunner-dashboard p.sub,
  #postrunner-dashboard .table, #postrunner-dashboard .versions {
  	margin: -12px;
  }
  #postrunner-dashboard .inside {
  	font-size: 12px;
  }
  #postrunner-dashboard p.sub {
  	font-style: italic;
  	font-family: Georgia, "Times New Roman", "Bitstream Charter", Times, serif;
  	padding: 5px 10px 15px;
  	color: #777;
  	font-size: 13px;
  }
  #postrunner-dashboard .table {
  	background: #f9f9f9;
  	border-top: #ececec 1px solid;
  	border-bottom: #ececec 1px solid;
  	margin: 0 -9px 10px;
  	padding: 0 10px;
  }
  #postrunner-dashboard table {
  	width: 100%;
  }
  #postrunner-dashboard table  td {
  	border-top: #ececec 1px solid;
  	padding: 5px 0;
  	white-space: nowrap;
  }
  #postrunner-dashboard table tr.first td {
  	border-top: none;
  }
  #postrunner-dashboard td.b {
  	padding-right: 6px;
  	text-align: right;
  	font-family: Georgia, "Times New Roman", "Bitstream Charter", Times, serif;
  	font-size: 14px;
  }
  #postrunner-dashboard td.b{
  	font-size: 18px;
  }
  #postrunner-dashboard td.b a:hover {
  	color: #d54e21;
  }
  #postrunner-dashboard .t {
  	font-size: 12px;
  	padding-right: 12px;
  	padding-top: 6px;
  	color: #777;
  }
  #postrunner-dashboard td.first,
  #postrunner-dashboard td.last {
  	width: 1px;
  }
  #postrunner-dashboard td{
    font-size:12px;
  }
  </style>
<? }

  public function send_back_old_posts(){
    global $wpdb;
    ini_set('display_errors', true);
  	$time = strtotime('-'.PR_REVIEW_PRERIOD);
    $old_posts = $wpdb->get_results("select p.ID, 
      (select meta_value from $wpdb->postmeta where post_id = p.id and meta_key = '_remote_id') as parent_id, 
      (select meta_value from $wpdb->postmeta where post_id = p.id and meta_key = '_pr_sent') as pr_sent from $wpdb->posts p join $wpdb->postmeta m on p.id = m.post_id where post_status != 'publish' AND meta_key = '_remote_id' HAVING pr_sent <= $time", ARRAY_A);

    
    if(is_array($old_posts)){
      foreach($old_posts as $old_post){
        $post_id   = $old_post['ID'];
        $parent_id = $old_post['parent_id'];
        $approve   = 'ignored';
        $permalink = '';
        $score     = 0;
        $feedback  = '';
        PostrunnerClient::approve_post($post_id, $parent_id, $approve, $permalink, $score, $feedback);
        wp_delete_post($post_id, true);
      }
    }
  }
  
  public function track_pageviews(){
    // If it's a new day, truncate the pageviews table
    if(date('m d y') != get_option('pr_pageview_day')){
      global $wpdb;
      update_option('pr_pageview_day', date('m d y'));
      $yesterdays_count = $this->get_visitor_count();
      update_option('pr_pageview_yesterday', $yesterdays_count);
      $wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'postrunner_pageviews');
    }
    if(!is_admin() && !is_user_logged_in()){
      global $wpdb;
      $wpdb->query('INSERT IGNORE INTO '.$wpdb->prefix.'postrunner_pageviews (ip_address) VALUES (INET_ATON(\''.$_SERVER['REMOTE_ADDR'].'\'))' );
    }
  }
  
  public static function get_visitor_count(){
    global $wpdb;
    $count = $wpdb->get_var('SELECT count(*) FROM '.$wpdb->prefix.'postrunner_pageviews');
    return $count;
  }
  public function activate_plugin(){
    $share = get_option('pr_share_pageviews', true);
    update_option('pr_share_pageviews', $share);
    
    global $wpdb;
    $wpdb->query('CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.'postrunner_pageviews` (
      `ip_address` int(11) unsigned NOT NULL AUTO_INCREMENT,
      PRIMARY KEY (`ip_address`)
    ) ENGINE=myisam DEFAULT CHARSET=latin1;');
  }
}