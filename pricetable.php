<?php

/*
Plugin Name: Price Table
Plugin URI: http://siteorigin.com/pricetable-wordpress-plugin
Description: Creates a price table custom post type with a drag and drop builder. Based on the dashing price table design by Orman Clark
Author: Greg Priday
Version: 1.0
Author URI: http://siteorigin.com/
License: GPL
*/

define('PRICETABLE_FEATURED_WEIGHT', 1.175);

function siteorigin_pricetable_activate(){
	if(function_exists('siteorigin_designsense_generate_css')){
		// Rebuild the CSS to include price table stuff
		//siteorigin_designsense_generate_css();
	}
}
register_activation_hook(__FILE__, 'siteorigin_pricetable_activate');

/**
 * Register the price table post type
 */
function siteorigin_pricetable_register(){
	register_post_type('pricetable',array(
		'labels' => array(
			'name' => __('Price Tables'),
			'singular_name' => __('Price Table'),
			'not_found' =>  __('No price tables found'),
		),
		'public' => true,
		'has_archive' => false,
		'supports' => array( 'title', 'editor', 'revisions', 'thumbnail', 'excerpt' ),
		'menu_icon' => plugins_url('images/icon.png', __FILE__),
	));
}
add_action( 'init', 'siteorigin_pricetable_register');

// Custom columns for the pricetable
function siteorigin_pricetable_register_custom_columns($cols){
	unset($cols['title']);
	unset($cols['date']);
	
	$cols['title'] = __('Title', 'siteorigin');
	$cols['options'] = __('Options', 'siteorigin');
	$cols['features'] = __('Features', 'siteorigin');
	$cols['featured'] = __('Featured Option', 'siteorigin');
	$cols['date'] = __('Date', 'siteorigin');
	return $cols;
}
add_filter( 'manage_pricetable_posts_columns', 'siteorigin_pricetable_register_custom_columns');

function siteorigin_pricetable_custom_column($column_name){
	global $post;
	switch($column_name){
	case 'options' :
		$table = get_post_meta($post->ID, 'price_table', true);
		print count($table);
		break;
	case 'features' :
	case 'featured' :
		$table = get_post_meta($post->ID, 'price_table', true);
		foreach($table as $col){
		if(!empty($col['featured']) && $col['featured'] == 'true'){
			if($column_name == 'featured') print $col['title'];
			else print count($col['features']);
			break;
		}
		}
		break;
	}
}
add_action( 'manage_pricetable_posts_custom_column', 'siteorigin_pricetable_custom_column');

/**
 * Enqueue the pricetable scripts
 */
function siteorigin_pricetable_scripts(){
	global $post;
	if(is_singular() && (($post->post_type == 'pricetable') || ($post->post_type != 'pricetable' && preg_match( '#\[ *price_table([^\]])*\]#i', $post->post_content )))){
		wp_enqueue_script('jquery');
		wp_enqueue_style('pricetable',  plugins_url( 'css/pricetable.css', __FILE__), null, null);
	}
}
add_action('wp_enqueue_scripts', 'siteorigin_pricetable_scripts');

/**
 * Add administration scripts
 */
function siteorigin_pricetable_admin_scripts($page){
	if($page == 'post-new.php' || $page == 'post.php'){
		global $post;
		
		if(!empty($post) && $post->post_type == 'pricetable'){
			// Scripts for building the pricetable
			wp_enqueue_script('placeholder', plugins_url( 'js/placeholder.jquery.js', __FILE__), array('jquery'), '1.1.1', true);
			wp_enqueue_script('jquery-ui');
			wp_enqueue_script('pricetable-admin', plugins_url( 'js/pricetable.build.js', __FILE__), array('jquery'), null, true);
			
			wp_localize_script('pricetable-admin', 'pt_messages', array(
				'delete_column' => __('Are you sure you want to delete this column?', 'siteorigin'),
				'delete_feature' => __('Are you sure you want to delete this feature?', 'siteorigin'),
			));
			
			wp_enqueue_style('pricetable-admin',  plugins_url( 'css/pricetable.admin.css', __FILE__), array(), null);
			wp_enqueue_style('jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.0/themes/base/jquery-ui.css', array(), '1.7.0');
		}
	}
}
add_action('admin_enqueue_scripts', 'siteorigin_pricetable_admin_scripts');

/**
 * Metaboxes because we're boss
 */
function siteorigin_pricetable_meta_boxes(){
	add_meta_box('pricetable', __('Price Table', 'siteorigin'), 'siteorigin_pricetable_metabox', 'pricetable', 'normal', 'high');
	add_meta_box('pricetable-shortcode', __('Shortcode', 'siteorigin'), 'siteorigin_pricetable_metabox_shortcode', 'pricetable', 'side', 'low');
}
add_action( 'add_meta_boxes', 'siteorigin_pricetable_meta_boxes' );

/**
 * Display the price table building interface
 */
function siteorigin_pricetable_metabox($post, $metabox){
	wp_nonce_field( plugin_basename( __FILE__ ), 'siteorigin_pricetable_nonce' );
	
	$table = get_post_meta($post->ID, 'price_table', true);
	if(empty($table)) $table = array();
	
	include(dirname(__FILE__).'/pricetable.build.phtml');
}

function siteorigin_pricetable_metabox_shortcode($post, $metabox){
	?>
		<code>[price_table id=<?php print $post->ID ?>]</code>
		<small class="description"><?php _e('Displays pricetable.', 'siteorigin') ?></small>
	<?php
}

/**
 * Save the price table
 */
function siteorigin_pricetable_save($post_id){
	// Authorization, verification this is my vocation 
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( !wp_verify_nonce( @$_POST['siteorigin_pricetable_nonce'], plugin_basename( __FILE__ ) ) ) return;
	if ( !current_user_can( 'edit_post', $post_id ) ) return;
	
	// Create the price table from the post variables
	$table = array();
	foreach($_POST as $name => $val){
		if(substr($name,0,6) == 'price_'){
			$parts = explode('_', $name);
			
			$i = intval($parts[1]);
			if($parts[2] == 'feature'){
				// Adding a feature
				$fi = intval($parts[3]);
				$fn = $parts[4];
				
				if(empty($table[$i]['features'])) $table[$i]['features'] = array();
				$table[$i]['features'][$fi][$fn] = $val;
			}
			else{
				// Adding a field
				$table[$i][$parts[2]] = $val;
			}
		}
	}
	
	// Clean up the features
	foreach($table as $i => $col){
		foreach($col['features'] as $fi => $feature){
			if(empty($feature['title']) && empty($feature['sub']) && empty($feature['description'])){
				unset($table[$i]['features'][$fi]);
			}
		}
		$table[$i]['features'] = array_values($table[$i]['features']);
	}
	
	if(isset($_POST['price_recommend'])){
		$table[intval($_POST['price_recommend'])]['featured'] = 'true';
	}
	
	$table = array_values($table);
	
	update_post_meta($post_id,'price_table', $table);
}
add_action( 'save_post', 'siteorigin_pricetable_save' );

/**
 * The price table shortcode.
 */
function siteorigin_pricetable_shortcode($atts = array()) {
	global $post;
	
	extract( shortcode_atts( array(
		'id' => null,
		'width' => 100,
	), $atts ) );
	
	if($id == null) $id = $post->ID;
	
	$table = get_post_meta($id , 'price_table', true);
	if(empty($table)) $table = array();
	
	// Set all the classes
	$featured_index = null;
	foreach($table as $i => $column) {
		$table[$i]['classes'] = array('column');
		$table[$i]['classes'][] = $table[$i]['featured'] === 'true' ? 'featured' : 'standard';
		
		if($table[$i]['featured'] == 'true') $featured_index = $i;
		if($table[$i+1]['featured'] == 'true') $table[$i]['classes'][] = 'before-featured';
		if($table[$i-1]['featured'] == 'true') $table[$i]['classes'][] = 'after-featured';
	}
	$table[0]['classes'][] = 'first';
	$table[count($table)-1]['classes'][] = 'last';
	
	// Calculate the widths
	$width_total;
	foreach($table as $i => $column){
		if($column['featured'] === 'true') $width_total += PRICETABLE_FEATURED_WEIGHT;
		else $width_total++;
	}
	$width_sum = 0;
	foreach($table as $i => $column){
		if($column['featured'] === 'true'){
			// The featured column takes any width left over after assigning to the normal columns
			$table[$i]['width'] = 100 - (floor(100/$width_total) * ($width_total-PRICETABLE_FEATURED_WEIGHT));
		}
		else{
			$table[$i]['width'] = floor(100/$width_total);
		}
		$width_sum += $table[$i]['width'];
	}
	
	// Create fillers
	for($i = 0; $i < count($table[0]['features']); $i++){
		$has_title = false;
		$has_sub = false;
		
		foreach($table as $column){
			$has_title = ($has_title || !empty($column['features'][$i]['title']));
			$has_sub = ($has_sub || !empty($column['features'][$i]['sub']));
		}
		
		foreach($table as $j => $column){
			if($has_title && empty($table[$j]['features'][$i]['title'])) $table[$j]['features'][$i]['title'] = '&nbsp;';
			if($has_sub && empty($table[$j]['features'][$i]['sub'])) $table[$j]['features'][$i]['sub'] = '&nbsp;';
		}
	}
	
	ob_start();
	include(dirname(__FILE__).'/pricetable.phtml');
	$pricetable = ob_get_clean();
	
	if($width != 100){
		$pricetable = '<div style="width:'.$width.'%; margin: 0 auto;">'.$pricetable.'</div>';
	}
	
	$post->pricetable_inserted = true;
	
	return $pricetable;
}
add_shortcode( 'price_table', 'siteorigin_pricetable_shortcode' );

function siteorigin_pricetable_the_content_filter($the_content){
	global $post;
	
	if(is_single() && $post->post_type == 'pricetable' && empty($post->pricetable_inserted)){
		$the_content = siteorigin_pricetable_shortcode().$the_content;
	}
	return $the_content;
}
// Filter the content after WordPress has had a chance to do shortcodes (priority 10)
add_filter('the_content', 'siteorigin_pricetable_the_content_filter',11);