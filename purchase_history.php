<?php
/*
Plugin Name: WP e-Commerce user roles and purchase history
Description: Show purchase history log from WP e-Commerce plugin in the user profile
Version: 1.2
Author: Mahmudur, GetShopped, mychelle
Author URI: www.getshopped.org
*/

global $current_user, $wpdb, $table_prefix;

// define the role names
define("ROLE_CUSTOMER", "customer");
define("ROLE_PRODUCT_MANAGER", "product_manager");
define("ROLE_SHOP_OWNER", "shop_manager");
// define the database table names
define("WPSC_TABLE_PURCHASE_LOGS", "{$table_prefix}wpsc_purchase_logs");
define("WPSC_TABLE_CART_CONTENTS", "{$table_prefix}wpsc_cart_contents");
define("WPSC_TABLE_DOWNLOAD_STATUS", "{$table_prefix}wpsc_download_status");

require (ABSPATH . WPINC . '/pluggable.php');
include_once(WP_PLUGIN_DIR . "/wp-e-commerce/wpsc-includes/processing.functions.php");

// create new role - customer
$result = add_role(ROLE_CUSTOMER, 'Customer', array('read' => 1, 'level_0' => 1));			

// create new role - shop_owner
$result = add_role(ROLE_PRODUCT_MANAGER, 'Product Manager', array('read' => true, // True allows that capability
    'edit_posts' => true,
    'delete_posts' => true,
    'manage_categories' => true,
    'moderate_comments' => false,
    'publish_posts' => true,
    'manage_products_only' => true));	
    
$result = add_role(ROLE_SHOP_OWNER, 'Store Manager', array('read' => true, // True allows that capability
    'administrator' => true,
    'edit_posts' => true,
    'delete_posts' => true,
    'manage_categories' => true,
    'moderate_comments' => false,
    'publish_posts' => true,
    'shop_manager' => true));	

add_action('plugins_loaded','ph_user_role');
add_action('show_user_profile', 'ph_show_purchase_history');

// add role to user
function ph_user_role() {	
	global $current_user, $wpdb, $table_prefix;
	get_currentuserinfo();
	$user_id = intval( $current_user->ID );
	$sql = "SELECT COUNT(*) FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `user_ID` IN ('".$user_id."') ORDER BY `date` DESC";
	$purchase_count= $wpdb->get_var($wpdb->prepare($sql)) ;		
	if( ! $user_id ) {
		return false;
	}
	if ( $purchase_count > 0 ) { // add customer role to current user if user has made some purchase
		$user = new WP_User( $user_id ); 		
		if ( !in_array( ROLE_CUSTOMER, $user->roles, false ) ) {		
			$user->add_role( ROLE_CUSTOMER );		
		} 
		return $user;
	} else {
		return false;
	}
}

//show purchase history for current user
function ph_show_purchase_history() {
	echo '<h3>Purchase History</h3>';
	echo "<div style='float:left; width:200px; padding:10px;'>";    
	echo 'Customer Sales Log';	
	echo '</div>';
		
	global $current_user, $wpdb, $table_prefix;
	get_currentuserinfo();
	$grand_total = 0;
	
	if(is_numeric($current_user->ID) && ($current_user->ID > 0)) {		
		$sql = "SELECT p.`id`, c.`name`, p.`date`, p.`totalprice`, p.`processed`, p.`sessionid` FROM `".WPSC_TABLE_PURCHASE_LOGS."` AS p, `".WPSC_TABLE_CART_CONTENTS."` AS c WHERE p.`id`=c.`purchaseid` AND `user_ID` IN ('".$current_user->ID."') ORDER BY `date` DESC";
        $purchase_log = $wpdb->get_results($sql,ARRAY_A);	
				
		echo "<div style='float:left; width:35%;'>";
		if($purchase_log != null) {	// this user has made some purchase 
			
			echo "<table class='widefat'>";			
			foreach((array)$purchase_log as $purchase) {	
				
				$sql = "SELECT * FROM `".WPSC_TABLE_DOWNLOAD_STATUS."` WHERE `purchid`=".$purchase['id']." AND `active` IN ('1') ORDER BY `datetime` DESC";
				
				$products = $wpdb->get_results($sql,ARRAY_A) ;			
				$isOrderAccepted = $purchase['processed'];
				
				foreach ((array)$products as $product){					
					if($isOrderAccepted > 1){
						if($product['uniqueid'] == null) {  
							$links = get_option('siteurl')."?downloadid=".$product['id'];
						} else {
							$links = get_option('siteurl')."?downloadid=".$product['uniqueid'];
						}																				
						$download_count = $product['downloads'];
					}
				}								
				echo '<tr>';
				echo '<td>'.$purchase['name'].'</td>';
				echo '<td>';				
				if ($download_count > 0) {
					echo "<a href = ".$links.">download</a>";
				} else {
					echo "download";
				}
				echo '<td>';
				echo "<a href = ".get_option('transact_url')."&sessionid=".$purchase['sessionid'].">receipt</a>";
				echo '</td>';
				echo '<td>'.date("d/m/Y",$purchase['date']).'</td>';
				echo '<td>'.nzshpcrt_currency_display( $purchase['totalprice'], 1, false, false, false ).'</td>';		        
				$grand_total += $purchase['totalprice'];
				echo '</tr>';							
			}
			echo '<tr>';
			echo "<td colspan='4'><strong>Total Spent</strong></td>";
			echo '<td><strong>'.nzshpcrt_currency_display( $grand_total, 1, false, false, false ).'</strong></td>';
			echo '</tr>';
			echo '</table>';	
		} else
		{			
			echo 'No transactions found.';			
		}        
		echo '</div>';
		echo '<div style="clear:both;"></div>';
	} else {		
		echo 'You must be logged in to use this page.';
	}
}
//remove all the other menu items that gets assoicated with the capabilities for the product manager, once wpec core has better capabilities then we can just give that cap to the role.
function ph_delete_menu_items() {
	if ( current_user_can( 'manage_products_only' )){
		remove_menu_page('index.php'); // Dashboard
		remove_menu_page('edit.php'); // Posts
		remove_menu_page('upload.php'); // Media
		remove_menu_page('link-manager.php'); // Links
		remove_menu_page('edit.php?post_type=page'); // Pages
		remove_menu_page('edit-comments.php'); // Comments
		remove_menu_page('themes.php'); // Appearance
		remove_menu_page('plugins.php'); // Plugins
		remove_menu_page('users.php'); // Users
		remove_menu_page('tools.php'); // Tools
		remove_menu_page('options-general.php'); // Settings
	}
	if ( current_user_can( 'shop_manager' )){
		remove_menu_page('upload.php'); // Media
		remove_menu_page('link-manager.php'); // Links
		remove_menu_page('edit.php?post_type=page'); // Pages
		remove_menu_page('edit.php'); // Posts
		remove_menu_page('edit-comments.php'); // Comments
		remove_menu_page('themes.php'); // Appearance
		remove_menu_page('plugins.php'); // Plugins
		remove_menu_page('users.php'); // Users
	}

	
}
add_action( 'admin_init', 'ph_delete_menu_items' );
?>