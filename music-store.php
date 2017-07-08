<?php
/*
Plugin Name: Music Store
Plugin URI: http://musicstore.dwbooster.com
Version: 1.0.75
Author: CodePeople
Author URI: http://musicstore.dwbooster.com
Description: Music Store is an online store for selling audio files: music, speeches, narratives, everything audio. With Music Store your sales will be safe, with all the security PayPal offers.
Text Domain: MS_TEXT_DOMAIN
 */

require_once 'banner.php';
$codepeople_promote_banner_plugins[ 'codepeople-music-store' ] = array(
	'plugin_name' => 'Music Store',
	'plugin_url'  => 'https://wordpress.org/support/plugin/music-store/reviews/#new-post'
);

 // CONSTANTS
 define( 'MS_FILE_PATH', dirname( __FILE__ ) );
 define( 'MS_URL', plugins_url( '', __FILE__ ) );
 define( 'MS_H_URL', rtrim( get_home_url( get_current_blog_id() ), "/" ).( ( strpos( get_current_blog_id(), '?' ) === false ) ? "/" : "" ) );
 define( 'MS_DOWNLOAD', dirname( __FILE__ ).'/ms-downloads' );
 define( 'MS_OLD_DOWNLOAD_LINK', 3); // Number of days considered old download links
 define( 'MS_DOWNLOADS_NUMBER', 3);  // Number of downloads by purchase
 define( 'MS_CORE_IMAGES_URL',  MS_URL . '/ms-core/images' );
 define( 'MS_CORE_IMAGES_PATH', MS_FILE_PATH . '/ms-core/images' );
 define( 'MS_TEXT_DOMAIN', 'MS_TEXT_DOMAIN' );
 define( 'MS_MAIN_PAGE', false ); // The location to the music store main page
 define( 'MS_SECURE_PLAYBACK_TEXT', 'Audio is played partially for security reasons' );
 define( 'MS_REMOTE_TIMEOUT', 300 ); // wp_remote_get timeout

 // PAYPAL CONSTANTS
 define( 'MS_PAYPAL_EMAIL', '' );
 define( 'MS_PAYPAL_ENABLED', true );
 define( 'MS_PAYPAL_CURRENCY', 'USD' );
 define( 'MS_PAYPAL_CURRENCY_SYMBOL', '$' );
 define( 'MS_PAYPAL_LANGUAGE', 'EN' );
 define( 'MS_PAYPAL_BUTTON', 'button_d.gif' );

 // NOTIFICATION CONSTANTS
 define( 'MS_NOTIFICATION_FROM_EMAIL', 'put_your@email_here.com' );
 define( 'MS_NOTIFICATION_TO_EMAIL', 'put_your@email_here.com' );
 define( 'MS_NOTIFICATION_TO_PAYER_SUBJECT', 'Thank you for your purchase...' );
 define( 'MS_NOTIFICATION_TO_SELLER_SUBJECT','New product purchased...' );
 define( 'MS_NOTIFICATION_TO_PAYER_MESSAGE', "We have received your purchase notification with the following information:\n\n%INFORMATION%\n\nThe download link is assigned an expiration time, please download the purchased product now.\n\nThank you.\n\nBest regards." );
 define( 'MS_NOTIFICATION_TO_SELLER_MESSAGE', "New purchase made with the following information:\n\n%INFORMATION%\n\nBest regards." );

 // SAFE PLAYBACK
 define('MS_SAFE_DOWNLOAD', false);

 // DISPLAY CONSTANTS
 define('MS_ITEMS_PAGE', 10);
 define('MS_ITEMS_PAGE_SELECTOR', true);
 define('MS_FILTER_BY_TYPE', false);
 define('MS_FILTER_BY_GENRE', true);
 define('MS_FILTER_BY_ARTIST', false);
 define('MS_FILTER_BY_ALBUM', false);
 define('MS_ORDER_BY_POPULARITY', true);
 define('MS_ORDER_BY_PRICE', true);

 // TABLE NAMES
 define( 'MSDB_POST_DATA', 'msdb_post_data');
 define( 'MSDB_PURCHASE', 'msdb_purchase');

 include "ms-core/ms-functions.php";
 include "ms-core/ms-song.php";
 include "ms-core/tpleng.class.php";

 // Load the addons
 function ms_loading_add_ons()
 {
	$path = dirname( __FILE__ ).'/ms-addons';
	if( file_exists( $path ) )
	{
		$addons = dir( $path );
		while( false !== ( $entry = $addons->read() ) )
		{
			if( strlen( $entry ) > 3 && strtolower( pathinfo( $entry, PATHINFO_EXTENSION) ) == 'php' )
			{
				require_once $addons->path.'/'.$entry;
			}
		}
	}
 }
 ms_loading_add_ons();

 if ( !class_exists( 'MusicStore' ) ) {
 	 /**
	 * Main Music_Store Class
	 *
	 * Contains the main functions for Music Store, stores variables, and handles error messages
	 *
	 * @class MusicStore
	 * @version	1.0.1
	 * @since 1.4
	 * @package	MusicStore
	 * @author CodePeople
	 */

	class MusicStore{

		var $music_store_slug = 'music-store-menu';
		var $layouts = array();
		var $layout = array();

		/**
		* MusicStore constructor
		*
		* @access public
		* @return void
		*/
		function __construct(){
			global $music_store_settings;

			$this->_load_settings(); // Load the global settings to prevent read them in each section of website
			add_action('init', array(&$this, 'init'), 1);
			add_action('admin_init', array(&$this, 'admin_init'), 1);
			// Set the menu link
			add_action('admin_menu', array(&$this, 'menu_links'), 10);

			// Load selected layout
			if ( false !== $music_store_settings[ 'ms_layout' ] )
			{
				$this->layout = $music_store_settings[ 'ms_layout' ];
			}
		} // End __constructor

/** INITIALIZE PLUGIN FOR PUBLIC WORDPRESS AND ADMIN SECTION **/

		/**
		* Init MusicStore when WordPress Initialize
		*
		* @access public
		* @return void
		*/
		function init(){

			global $music_store_settings;

			// I18n
			load_plugin_textdomain(MS_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');

			$this->init_taxonomies(); // Init MusicStore taxonomies
			$this->init_post_types(); // Init MusicStore custom post types

			if ( ! is_admin()){
				global $wpdb;
                add_filter('get_pages', array( &$this, '_ms_exclude_pages') ); // for download-page

                if(isset($_REQUEST['ms-action'])){
                    switch(strtolower($_REQUEST['ms-action'])){
                        case 'buynow':
                            include_once MS_FILE_PATH.'/ms-core/ms-submit.php';exit;
                        break;
                        case 'registerfreedownload':
							if( !empty( $_REQUEST[ 'id' ] ) )
							{
								global $wpdb;
								$mssg = __( 'Product distributed for free', MS_TEXT_DOMAIN );
								$id = $_REQUEST[ 'id' ];
								$data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM '.$wpdb->prefix.MSDB_POST_DATA.' WHERE id=%d', array( $id ) ) );
								if( !empty( $data ) ) // The Product exists
								{
									// Check if paypal is enabled
									$ms_paypal_email = $music_store_settings[ 'ms_paypal_email' ];
									if( $music_store_settings[ 'ms_paypal_enabled' ] && !empty( $ms_paypal_email ) )
									{
										//Check if the product has assigned a price and it is different to zero
										$price = @floatval( $data->price );
										if( $price )
										{
											exit;
										}
									}

									$current_user = wp_get_current_user();
									$current_user_email = '';
									if( $current_user->ID !== 0 )
									{
										$current_user_email = $current_user->user_email;
									}
									else
									{
										$current_user_email = ms_getIP();
										$current_user_email = str_replace( '_', '.', $current_user_email );
									}

									// Insert download in database
									$wpdb->insert(
										$wpdb->prefix.MSDB_PURCHASE,
										array(
											'product_id'  => $id,
											'purchase_id' => 0,
											'date'		  => date( 'Y-m-d H:i:s'),
											'email'		  => $current_user_email,
											'amount'	  => 0,
											'paypal_data' => ''
										),
										array('%d', '%s', '%s', '%s', '%f', '%s')
									);
								}
							}
							exit;
						break;
						case 'popularity':
							if( !empty( $_POST[ 'id' ] ) )
							{
								$_POST[ 'id' ] = @intval( $_POST[ 'id' ] );
								if( session_id() == "" ) @session_start();
								if( !isset( $_SESSION[ 'ms-popularity-voted' ] ) ) $_SESSION[ 'ms-popularity-voted' ] = array();
								if( !in_array( $_POST[ 'id' ], $_SESSION[ 'ms-popularity-voted' ] ) )
								{
									if( $wpdb->query( $wpdb->prepare( 'UPDATE '.$wpdb->prefix.'msdb_post_data SET plays=plays+1 WHERE id=%d', $_POST[ 'id' ] ) ) )
									{
										$_SESSION[ 'ms-popularity-voted' ][] = $_POST[ 'id' ];
									}
								}
								print $wpdb->get_var( $wpdb->prepare( 'SELECT plays FROM '.$wpdb->prefix.'msdb_post_data WHERE id=%d', $_POST[ 'id' ] ) );
							}
							exit;
						break;
						case 'f-download':
							require_once MS_FILE_PATH.'/ms-core/ms-download.php';
							ms_download_file();
							exit;
						break;
						default:
							if(stripos($_REQUEST['ms-action'],'ipn|') !== false)
							{
								include_once MS_FILE_PATH.'/ms-core/ms-ipn.php';exit;
							}
                        break;

                    }

                }

				// Set custom post_types on search result
				add_filter('pre_get_posts', array(&$this, 'add_post_type_to_results'));
				add_shortcode('music_store', array(&$this, 'load_store'));
                add_filter( 'the_content', array( &$this, '_ms_the_content' ), 1 ); // For download-page
                add_filter( 'the_excerpt', array( &$this, '_ms_the_excerpt' ), 1 ); // For search results
                add_action( 'wp_head', array( &$this, 'load_meta'));
				$this->load_templates(); // Load the music store template for songs display

				// Load public resources
				add_action( 'wp_enqueue_scripts', array(&$this, 'public_resources'), 99);

				// Search functions
				if( $music_store_settings[ 'ms_search_taxonomy' ] )
				{
					add_filter( 'posts_where', array( &$this, 'custom_search_where' ) );
					add_filter( 'posts_join', array( &$this, 'custom_search_join' ) );
					add_filter( 'posts_groupby', array( &$this, 'custom_search_groupby' ) );
				}
			}
			// Init action
			do_action( 'musicstore_init' );
		} // End init

        function load_meta( ){
            global $post;
            if( isset( $post ) ){
                if( $post->post_type == 'ms_song' ) $obj = new MSSong( $post->ID );
                if( !empty( $obj ) )
				{
					$output = '';

					if( isset( $obj->cover ) ) $output .= '<meta property="og:image" content="'.esc_attr( $obj->cover ).'" />';

					if( !empty( $obj->post_title ) ) $output .= '<meta property="og:title" content="'.esc_attr( $obj->post_title ).'" />';

					if( !empty( $obj->post_excerpt ) ) $output .= '<meta property="og:description" content="'.esc_attr( $obj->post_excerpt ).'" />';
					elseif( !empty( $obj->post_content ) ) $output .= '<meta property="og:description" content="'.esc_attr( wp_trim_words( $obj->post_content ) ).'" />';

					if( is_array( $obj->artist ) && count( $obj->artist ) )
					{
						$artists_names = array();
						foreach( $obj->artist as $artist )
						{
							if( !empty( $artist->name ) ) $artists_names[] = $artist->name;
						}

						if( !empty( $artists_names ) ) $output .= '<meta property="article:author" content="'.esc_attr( implode( ',', $artists_names ) ).'" />';
					}

					$output .= '<meta property="og:url" content="'.esc_attr( get_permalink( $obj->ID ) ).'" />';
					$output .= '<meta property="og:type" content="song" />';

					print $output;
				}
            }
        }

/** CODE REQUIRED FOR DOWNLOAD PAGE **/
		function _ms_create_pages( $slug, $title ){
			if( session_id() == "" ) @session_start();
			if( isset( $_SESSION[ $slug ] ) ) return $_SESSION[ $slug ];

            $page = get_page_by_path( $slug );
			if( is_null( $page ) ){
				if( is_admin() ){
					if( false != ($id = wp_insert_post(
								array(
									'comment_status' => 'closed',
									'post_name' => $slug,
									'post_title' => __( $title, MS_TEXT_DOMAIN ),
									'post_status' => 'publish',
									'post_type' => 'page'
								)
							)
						)
					){
						$_SESSION[ $slug ] =  get_permalink($id);
					}
				}
			}else{
				if( is_admin() && $page->post_status != 'publish' ){
					$page->post_status = 'publish';
					wp_update_post( $page );
				}
				$_SESSION[ $slug ] =  get_permalink($page->ID);
			}

            $_SESSION[ $slug ] = ( isset( $_SESSION[ $slug ] ) ) ? $_SESSION[ $slug ] : MS_H_URL;
            return $_SESSION[ $slug ];
        }

        function _ms_exclude_pages( $pages ){
            $exclude = array();
			$new_pages = array();

            $p = get_page_by_path( 'ms-download-page' );
            if( !is_null( $p ) ) $exclude[] = $p->ID;

            foreach ( $pages as $page ) {
                if ( !in_array( $page->ID, $exclude ) ) {
                    $new_pages[] = $page;
                }
            }

            return $new_pages;
        }

		function _ms_the_excerpt( $the_excerpt ){
			global $post;
			if( is_search() && isset( $post) ){
				if( $post->post_type == 'ms_song' ){
					$tpl = new music_store_tpleng(dirname(__FILE__).'/ms-templates/', 'comment');
					$obj = new MSSong( $post->ID );
					return $obj->display_content( 'multiple', $tpl, 'return');
				}
			}

			return $the_excerpt;
		}

        function _ms_the_content( $the_content  ){
			global $post, $ms_errors, $download_links_str, $music_store_settings;

			if( isset( $_REQUEST ) && isset( $_REQUEST[ 'ms-action' ] ) && strtolower( $_REQUEST[ 'ms-action' ] ) == 'download' ){

				require_once MS_FILE_PATH.'/ms-core/ms-download.php';
				ms_generate_downloads();

				if( empty( $ms_errors ) ){
					$the_content .= __('Download Links:', MS_TEXT_DOMAIN).'<div>'.music_store_strip_tags($download_links_str).'</div>';
				}else{
					$error = ( !empty( $_REQUEST[ 'error_mssg' ] ) ) ? $_REQUEST[ 'error_mssg' ] : '';
					if( is_array($error) )
					{
						foreach( $error as $error_key => $error_message )
						{
							$error[ $error_key ] = music_store_strip_tags($error_message);
						}
					}
					else
					{
						$error = music_store_strip_tags( $error );
					}

					if( ( !$music_store_settings[ 'ms_safe_download' ] && !empty( $ms_errors ) ) || !empty( $_SESSION[ 'ms_user_email' ] ) ){
						$error .= '<li>'.implode( '</li><li>', music_store_strip_tags($ms_errors) ).'</li>';
					}

					$the_content .= ( !empty( $error ) )  ? '<div class="music-store-error-mssg"><ul>'.$error.'</ul></div>' : '';

					if( $music_store_settings[ 'ms_safe_download' ] ){
						$dlurl = $GLOBALS['music_store']->_ms_create_pages( 'ms-download-page', 'Download Page' );
						$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ).'ms-action=download'.( ( isset( $_REQUEST[ 'purchase_id' ] ) ) ? '&purchase_id='.$_REQUEST[ 'purchase_id' ] : '' );
						$the_content .= '
							<form action="'.esc_url($dlurl).'" method="POST" >
								<div style="text-align:center;">
									<div>
										'.__( 'Type the email address used to purchase our products', MS_TEXT_DOMAIN ).'
									</div>
									<div>
										<input type="text" name="ms_user_email" /> <input type="submit" value="Get Products" />
									</div>
								</div>
							</form>
						';
					}
				}
			}
			return $the_content;
        }
/** END OF DOWNLOAD PAGE CODE **/

		/**
		* Init MusicStore when the WordPress is open for admin
		*
		* @access public
		* @return void
		*/
		function admin_init(){
			global $wpdb;
			$this->_create_db_structure();
			if( isset( $_REQUEST[ 'ms-action' ] ) && $_REQUEST[ 'ms-action' ] == 'paypal-data' ){
				if( isset( $_REQUEST[ 'data' ] ) && isset( $_REQUEST[ 'from' ] ) && isset( $_REQUEST[ 'to' ] ) ){
					$where = 'DATEDIFF(date, "'.$_REQUEST[ 'from' ].'")>=0 AND DATEDIFF(date, "'.$_REQUEST[ 'to' ].'")<=0';
					switch( $_REQUEST[ 'data' ] ){
						case 'residence_country':
							$where .= ' AND amount<>0';
							print music_store_getFromPayPalData( array( 'residence_country' => 'residence_country'), 'COUNT(*) AS count', '', $where, array( 'residence_country' ), array( 'count' => 'DESC' ) );
						break;
						case 'mc_currency':
							$where .= ' AND amount<>0';
							print music_store_getFromPayPalData( array( 'mc_currency' => 'mc_currency'), 'SUM(amount) AS sum', '', $where, array( 'mc_currency' ), array( 'sum' => 'DESC' ) );
						break;
						case 'product_name':
							$where .= ' AND amount<>0';
							$json =  music_store_getFromPayPalData( array( 'mc_currency' => 'mc_currency'), 'SUM(amount) AS sum, post_title', $wpdb->posts.' AS posts', $where.' AND product_id = posts.ID', array( 'product_id', 'mc_currency' ) );
							$obj = json_decode( $json );
							foreach( $obj as $key => $value){
								$obj[ $key ]->post_title .= ' ['.$value->mc_currency.']';
							}
							print json_encode( $obj );
						break;
						case 'download_by_product':
							$results = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT CONCAT(post.post_title,\' [\',COUNT(*),\']\') as post_title, COUNT(*) AS count FROM '.$wpdb->prefix.'posts AS post, '.$wpdb->prefix.MSDB_PURCHASE.' AS purchase WHERE DATEDIFF(purchase.date, %s)>=0 AND DATEDIFF(purchase.date, %s)<=0 AND purchase.amount=0 AND post.ID=purchase.product_id GROUP BY purchase.product_id ORDER BY post.post_title ASC', array($_REQUEST[ 'from' ], $_REQUEST[ 'to' ]) ) );
							if( !empty( $results ) )
							{
								print json_encode( $results );
							}
						break;
					}
				}
				exit;
			}

            // Init the metaboxs for song
			add_meta_box('ms_song_metabox', __("Song's data", MS_TEXT_DOMAIN), array(&$this, 'metabox_form'), 'ms_song', 'normal', 'high');
			add_action('save_post', array(&$this, 'save_data'));

            add_meta_box('ms_song_metabox_discount', __("Programming Discounts", MS_TEXT_DOMAIN), array(&$this, 'metabox_discount'), 'ms_song', 'normal', 'high');

			if (current_user_can('delete_posts')) add_action('delete_post', array(&$this, 'delete_post'));

			// Load admin resources
			add_action('admin_enqueue_scripts', array(&$this, 'admin_resources'), 10);

			// Set a new media button for music store insertion
			add_action('media_buttons', array(&$this, 'set_music_store_button'), 100);

			$plugin = plugin_basename(__FILE__);
			add_filter('plugin_action_links_'.$plugin, array(&$this, 'customizationLink'));

            $this->_ms_create_pages( 'ms-download-page', 'Download Page' ); // for download-page and download-page

			// Init action
			do_action( 'musicstore_admin_init' );
		} // End init

		function customizationLink($links){
			$settings_link = '<a href="https://wordpress.org/support/plugin/music-store/#new-post">'.__('Help').'</a>';
			array_unshift($links, $settings_link);
			$settings_link = '<a href="http://musicstore.dwbooster.com/customization" target="_blank">'.__('Request custom changes').'</a>';
			array_unshift($links, $settings_link);
			$settings_link = '<a href="admin.php?page=music-store-menu-settings">'.__('Settings').'</a>';
			array_unshift($links, $settings_link);
			return $links;
		} // End customizationLink


/** READ THE STORE SETTINGS **/

		/*
		 * Read the store settings and create a global variable with data
		 *
		 * @access private
		 * @return void
		 */
		private function _load_settings(){
			global $music_store_settings;

			$music_store_settings = array(
				'ms_main_page' 				=> stripcslashes(get_option( 'ms_main_page', MS_MAIN_PAGE )),
				'ms_filter_by_genre' 		=> get_option( 'ms_filter_by_genre', MS_FILTER_BY_GENRE ),
				'ms_filter_by_artist' 		=> get_option( 'ms_filter_by_artist', MS_FILTER_BY_ARTIST ),
				'ms_filter_by_album' 		=> get_option( 'ms_filter_by_album', MS_FILTER_BY_ALBUM ),
				'ms_search_taxonomy' 		=> get_option( 'ms_search_taxonomy' ),
				'ms_items_page_selector' 	=> get_option( 'ms_items_page_selector', MS_ITEMS_PAGE_SELECTOR ),
				'ms_friendly_url' 			=> get_option( 'ms_friendly_url', 0 ),

				'ms_items_page' 			=> stripcslashes(get_option( 'ms_items_page', MS_ITEMS_PAGE )),
				'ms_layout' 				=> get_option( 'ms_layout' ),

				'ms_paypal_email'			=> stripcslashes(get_option( 'ms_paypal_email', MS_PAYPAL_EMAIL )),
				'ms_paypal_button' 			=> get_option( 'ms_paypal_button', MS_PAYPAL_BUTTON ),
				'ms_paypal_currency' 		=> stripcslashes(get_option('ms_paypal_currency', MS_PAYPAL_CURRENCY )),
				'ms_paypal_currency_symbol' => stripcslashes(get_option( 'ms_paypal_currency_symbol', MS_PAYPAL_CURRENCY_SYMBOL )),
				'ms_paypal_language' 		=> stripcslashes(get_option( 'ms_paypal_language', MS_PAYPAL_LANGUAGE )),
				'ms_paypal_enabled' 		=> get_option( 'ms_paypal_enabled', MS_PAYPAL_ENABLED ),
				'ms_paypal_sandbox' 		=> get_option( 'ms_paypal_sandbox' ),

				'ms_notification_from_email' 		=> stripcslashes(get_option( 'ms_notification_from_email', MS_NOTIFICATION_FROM_EMAIL )),
				'ms_notification_to_email' 			=> stripcslashes(get_option( 'ms_notification_to_email', MS_NOTIFICATION_TO_EMAIL )),
				'ms_notification_to_payer_subject' 	=> stripcslashes(get_option( 'ms_notification_to_payer_subject', MS_NOTIFICATION_TO_PAYER_SUBJECT )),
				'ms_notification_to_payer_message' 	=> stripcslashes(get_option( 'ms_notification_to_payer_message', MS_NOTIFICATION_TO_PAYER_MESSAGE )),
				'ms_notification_to_seller_subject' => stripcslashes(get_option( 'ms_notification_to_seller_subject', MS_NOTIFICATION_TO_SELLER_SUBJECT )),
				'ms_notification_to_seller_message' => stripcslashes(get_option( 'ms_notification_to_seller_message', MS_NOTIFICATION_TO_SELLER_MESSAGE )),


				'ms_old_download_link' 		=> stripcslashes(get_option( 'ms_old_download_link', MS_OLD_DOWNLOAD_LINK )),
				'ms_downloads_number' 		=> stripcslashes(get_option( 'ms_downloads_number', MS_DOWNLOADS_NUMBER )),
				'ms_safe_download' 			=> get_option( 'ms_safe_download', MS_SAFE_DOWNLOAD ),
				'ms_play_all' 				=> get_option( 'ms_play_all', 0 ),

                'ms_social_buttons' 		=> get_option( 'ms_social_buttons' ),
				'ms_facebook_app_id'		=> stripcslashes(get_option( 'ms_facebook_app_id', '')),

				'ms_download_link_for_registered_only' 	=> get_option( 'ms_download_link_for_registered_only' ),
				'ms_buy_button_for_registered_only' 	=> get_option( 'ms_buy_button_for_registered_only' ),

				'ms_license_for_regular' 	=> stripcslashes(get_option( 'ms_license_for_regular', '' )),
				'ms_license_for_free' 		=> stripcslashes(get_option( 'ms_license_for_free', '' ))
			);
		} // End _load_settings

/** MANAGE DATABASES FOR ADITIONAL POST DATA **/

		/*
		*  Create database tables
		*
		*  @access public
		*  @return void
		*/
		function register($networkwide){
			global $wpdb;

			if (function_exists('is_multisite') && is_multisite()) {
				if ($networkwide) {
					$old_blog = $wpdb->blogid;
					// Get all blog ids
					$blogids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
					foreach ($blogids as $blog_id) {
						switch_to_blog($blog_id);
						$this->_create_db_structure( true );
						update_option('ms_social_buttons', true);
					}
					switch_to_blog($old_blog);
					return;
				}
			}
			$this->_create_db_structure( true );
            update_option('ms_social_buttons', true);

		}  // End register

		/*
		* A new blog has been created in a multisite WordPress
		*/
		function install_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ){
			global $wpdb;
			if ( is_plugin_active_for_network() )
			{
				$current_blog = $wpdb->blogid;
				switch_to_blog( $blog_id );
				$this->_create_db_structure( true );
				update_option('ms_social_buttons', true);
				switch_to_blog( $current_blog );
			}
		}

		/*
		* Create the Music Store tables
		*
		* @access private
		* @return void
		*/
		private function _create_db_structure( $installing = false ){
            try{
                global $wpdb;

                if( !$installing && !empty( $_SESSION[ 'msdb_created_db' ] ) )
                {
                    return;
                }

                $_SESSION[ 'msdb_created_db' ] = true;


                $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.MSDB_POST_DATA." (
                    id mediumint(9) NOT NULL,
                    time VARCHAR(25) NULL,
                    plays mediumint(9) NOT NULL DEFAULT 0,
                    purchases mediumint(9) NOT NULL DEFAULT 0,
                    file VARCHAR(255) NULL,
                    demo VARCHAR(255) NULL,
                    protect TINYINT(1) NOT NULL DEFAULT 0,
                    info VARCHAR(255) NULL,
                    cover VARCHAR(255) NULL,
                    price FLOAT NULL,
                    year VARCHAR(25),
					isrc VARCHAR(50) NULL,
                    as_single TINYINT(1) NOT NULL DEFAULT 0,
                    UNIQUE KEY id (id)
                 );";
                $wpdb->query($sql);

                $result = $wpdb->get_results("SHOW COLUMNS FROM ".$wpdb->prefix.MSDB_POST_DATA." LIKE 'isrc'");
                if(empty($result)){
                    $sql = "ALTER TABLE ".$wpdb->prefix.MSDB_POST_DATA." ADD isrc VARCHAR(50) NULL";
					$wpdb->query($sql);
				}

                $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.MSDB_PURCHASE." (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    product_id mediumint(9) NOT NULL,
                    purchase_id varchar(50) NOT NULL UNIQUE,
                    date DATETIME NOT NULL,
                    checking_date DATETIME,
                    email VARCHAR(255) NOT NULL,
                    amount FLOAT NOT NULL DEFAULT 0,
                    downloads INT NOT NULL DEFAULT 0,
                    paypal_data TEXT,
                    UNIQUE KEY id (id)
                 );";
                $wpdb->query($sql);

                $result = $wpdb->get_results("SHOW COLUMNS FROM ".$wpdb->prefix.MSDB_PURCHASE." LIKE 'downloads'");
                if(empty($result)){
                    $sql = "ALTER TABLE ".$wpdb->prefix.MSDB_PURCHASE." ADD downloads INT NOT NULL DEFAULT 0";
                    $wpdb->query($sql);
                }

                $result = $wpdb->get_results("SHOW COLUMNS FROM ".$wpdb->prefix.MSDB_PURCHASE." LIKE 'checking_date'");
                if(empty($result)){
                    $sql = "ALTER TABLE ".$wpdb->prefix.MSDB_PURCHASE." ADD checking_date DATETIME";
                    $wpdb->query($sql);
                }
            }
            catch( Exception $exp )
            {
            }
        } // End _create_db_structure

/** REGISTER POST TYPES AND TAXONOMIES **/

		/**
		* Init MusicStore post types
		*
		* @access public
		* @return void
		*/
		function init_post_types(){
			global $music_store_settings;

			if(post_type_exists('ms_song')) return;

			// Post Types
			// Create song post type
			register_post_type( 'ms_song',
				array(
					'description'		   => __('This is where you can add new song to your music store.', MS_TEXT_DOMAIN),
					'capability_type'      => 'post',
					'supports'             => array( 'title', 'editor', 'thumbnail', 'comments' ),
					'exclude_from_search'  => false,
					'public'               => true,
					'show_ui'              => true,
					'show_in_nav_menus'    => true,
					'show_in_menu'    	   => $this->music_store_slug,
					'labels'               => array(
						'name'               => __( 'Songs', MS_TEXT_DOMAIN),
						'singular_name'      => __( 'Song', MS_TEXT_DOMAIN),
						'add_new'            => __( 'Add New', MS_TEXT_DOMAIN),
						'add_new_item'       => __( 'Add New Song', MS_TEXT_DOMAIN),
						'edit_item'          => __( 'Edit Song', MS_TEXT_DOMAIN),
						'new_item'           => __( 'New Song', MS_TEXT_DOMAIN),
						'view_item'          => __( 'View Song', MS_TEXT_DOMAIN),
						'search_items'       => __( 'Search Songs', MS_TEXT_DOMAIN),
						'not_found'          => __( 'No songs found', MS_TEXT_DOMAIN),
						'not_found_in_trash' => __( 'No songs found in Trash', MS_TEXT_DOMAIN),
						'menu_name'          => __( 'Songs for Sale', MS_TEXT_DOMAIN),
						'parent_item_colon'  => '',
					),
					'query_var'            => true,
					'has_archive'		   => true,
					//'register_meta_box_cb' => 'wpsc_meta_boxes',
					'rewrite'              => ( ( $music_store_settings[ 'ms_friendly_url' ]*1 ) ? true : false )
				)
			);

			add_filter('manage_ms_song_posts_columns' , 'MSSong::columns');
			add_action('manage_ms_song_posts_custom_column', 'MSSong::columns_data', 2 );

			if( $music_store_settings[ 'ms_friendly_url' ]*1 && empty( $_SESSION[ 'music_store_flush_rewrite_rules' ] ) )
			{
				flush_rewrite_rules();
				$_SESSION[ 'music_store_flush_rewrite_rules' ] = 1;
			}
		}// End init_post_types

		/**
		* Init MusicStore taxonomies
		*
		* @access public
		* @return void
		*/
		function init_taxonomies(){


			if ( taxonomy_exists('ms_genre') ) return;

			do_action( 'musicstore_register_taxonomy' );

			// Create Genre taxonomy
			register_taxonomy(
				'ms_genre',
				array(
					'ms_song'
				),
				array(
					'hierarchical'	=> true,
					'label' 	   	=> __('Genres', MS_TEXT_DOMAIN),
					'labels' 		=> array(
						'name' 				=> __( 'Genres', MS_TEXT_DOMAIN),
	                    'singular_name' 	=> __( 'Genre', MS_TEXT_DOMAIN),
						'search_items' 		=> __( 'Search Genres', MS_TEXT_DOMAIN),
	                    'all_items' 		=> __( 'All Genres', MS_TEXT_DOMAIN),
						'edit_item' 		=> __( 'Edit Genre', MS_TEXT_DOMAIN),
	                    'update_item' 		=> __( 'Update Genre', MS_TEXT_DOMAIN),
	                    'add_new_item' 		=> __( 'Add New Genre', MS_TEXT_DOMAIN),
						'new_item_name' 	=> __( 'New Genre Name', MS_TEXT_DOMAIN),
						'menu_name'			=> __( 'Genres', MS_TEXT_DOMAIN)
	                ),
					'public' => true,
					'show_ui' => true,
					'show_admin_column' => true,
					'query_var' => true
				)
			);

			// Register artist taxonomy
			register_taxonomy(
				'ms_artist',
				array(
					'ms_song'
				),
				array(
					'hierarchical'	=> false,
					'label' 	   	=> __('Artists', MS_TEXT_DOMAIN),
					'labels' 		=> array(
						'name' 				=> __( 'Artists', MS_TEXT_DOMAIN),
	                    'singular_name' 	=> __( 'Artist', MS_TEXT_DOMAIN),
						'search_items' 		=> __( 'Search Artists', MS_TEXT_DOMAIN),
	                    'all_items' 		=> __( 'All Artists', MS_TEXT_DOMAIN),
						'edit_item' 		=> __( 'Edit Artist', MS_TEXT_DOMAIN),
	                    'update_item' 		=> __( 'Update Artist', MS_TEXT_DOMAIN),
	                    'add_new_item' 		=> __( 'Add New Artist', MS_TEXT_DOMAIN),
						'new_item_name' 	=> __( 'New Artist Name', MS_TEXT_DOMAIN),
						'menu_name'			=> __( 'Artists', MS_TEXT_DOMAIN)
	                ),
					'public' => true,
					'show_ui' => true,
					'show_admin_column' => true,
					'query_var' => true
				)
			);

			// Register album taxonomy
			register_taxonomy(
				'ms_album',
				array(
					'ms_song'
				),
				array(
					'hierarchical'	=> false,
					'label' 	   	=> __('Albums', MS_TEXT_DOMAIN),
					'labels' 		=> array(
						'name' 				=> __( 'Albums', MS_TEXT_DOMAIN),
	                    'singular_name' 	=> __( 'Album', MS_TEXT_DOMAIN),
						'search_items' 		=> __( 'Search Albums', MS_TEXT_DOMAIN),
	                    'all_items' 		=> __( 'All Albums', MS_TEXT_DOMAIN),
						'edit_item' 		=> __( 'Edit Album', MS_TEXT_DOMAIN),
	                    'update_item' 		=> __( 'Update Album', MS_TEXT_DOMAIN),
	                    'add_new_item' 		=> __( 'Add New Album', MS_TEXT_DOMAIN),
						'new_item_name' 	=> __( 'New Album Name', MS_TEXT_DOMAIN),
						'menu_name'			=> __( 'Albums', MS_TEXT_DOMAIN)
	                ),
					'public' => true,
					'show_ui' => true,
					'show_admin_column' => true,
					'query_var' => true
				)
			);

			add_action( 'admin_menu' , array(&$this, 'remove_meta_box') );
		} // End init_taxonomies

		/**
		*	Remove the taxonomies metabox
		*
		* @access public
		* @return void
		*/
		function remove_meta_box(){
			remove_meta_box( 'tagsdiv-ms_artist', 'ms_song', 'side' );
			remove_meta_box( 'tagsdiv-ms_album', 'ms_song', 'side' );
		} // End remove_meta_box

/** METABOXS FOR ENTERING POST_TYPE ADDITIONAL DATA **/

		/**
		* Save data of store products
		*
		* @access public
		* @return void
		*/
		function save_data(){
			global $post;
			if(isset($post->post_type) && $post->post_type == 'ms_song'){
				MSSong::save_data();
			}
		} // End save_data

		/**
		* Print metabox for post song
		*
		* @access public
		* @return void
		*/
		function metabox_form($obj){
			global $post;

			if($obj->post_type == 'ms_song'){
				MSSong::print_metabox();
			}

		} // End metabox_form

        function metabox_discount($obj){
			if($obj->post_type == 'ms_song'){
				MSSong::print_discount_metabox();
			}
		} // End metabox_form


/** SETTINGS PAGE FOR MUSIC STORE CONFIGURATION AND SUBMENUS**/

		// highlight the proper top level menu for taxonomies submenus
		function tax_menu_correction($parent_file) {
			global $current_screen;
			$taxonomy = $current_screen->taxonomy;
			if ($taxonomy == 'ms_genre' || $taxonomy == 'ms_artist' || $taxonomy == 'ms_album')
				$parent_file = $this->music_store_slug;
			return $parent_file;
		} // End tax_menu_correction

		/*
		* Create the link for music store menu, submenus and settings page
		*
		*/
		function menu_links(){
			if(is_admin()){
				add_options_page('Music Store', 'Music Store', 'manage_options', $this->music_store_slug.'-settings1', array(&$this, 'settings_page'));

				add_menu_page('Music Store', 'Music Store', 'edit_pages', $this->music_store_slug, null, MS_CORE_IMAGES_URL."/music-store-menu-icon.png", 4.55555555555555);

				//Submenu for taxonomies
				add_submenu_page($this->music_store_slug, __( 'Genres', MS_TEXT_DOMAIN), __( 'Set Genres', MS_TEXT_DOMAIN), 'edit_pages', 'edit-tags.php?taxonomy=ms_genre');
				add_submenu_page($this->music_store_slug, __( 'Artists', MS_TEXT_DOMAIN), __( 'Set Artists', MS_TEXT_DOMAIN), 'edit_pages', 'edit-tags.php?taxonomy=ms_artist');
				add_submenu_page($this->music_store_slug, __( 'Albums', MS_TEXT_DOMAIN), __( 'Set Albums', MS_TEXT_DOMAIN), 'edit_pages', 'edit-tags.php?taxonomy=ms_album');

				add_action('parent_file', array(&$this, 'tax_menu_correction'));

				// Settings Submenu
				add_submenu_page($this->music_store_slug, __( 'Music Store Settings', MS_TEXT_DOMAIN ), __( 'Store Settings', MS_TEXT_DOMAIN ), 'edit_pages', $this->music_store_slug.'-settings', array(&$this, 'settings_page'));

				// Sales report submenu
				add_submenu_page($this->music_store_slug, __( 'Music Store Sales Report', MS_TEXT_DOMAIN ), __( 'Sales Report', MS_TEXT_DOMAIN ), 'edit_pages', $this->music_store_slug.'-reports', array(&$this, 'settings_page'));

				// Importer submenu
				add_submenu_page($this->music_store_slug, __( 'Songs Importer', MS_TEXT_DOMAIN ), __( 'Songs Importer', MS_TEXT_DOMAIN ), 'edit_pages', $this->music_store_slug.'-importer', array(&$this, 'importer'));

				// Help
				add_submenu_page($this->music_store_slug, __( 'Online Help', MS_TEXT_DOMAIN ), __( 'Online Help', MS_TEXT_DOMAIN ), 'edit_pages', $this->music_store_slug.'-help', array(&$this, 'help'));


			}
		} // End menu_links

		public function help()
		{
			print '<p>Redirecting...</p>';
			print '<script>document.location.href="https://wordpress.org/support/plugin/music-store/#new-post";</script>';
			exit;
		}
		/*
		*	Create tabs for setting page and payment stats
		*/
		function settings_tabs($current = 'reports'){
			$tabs = array( 'settings' => __( 'Music Store Settings', MS_TEXT_DOMAIN ), 'song' => __( 'Music Store Songs', MS_TEXT_DOMAIN ), 'collection' => __( 'Music Store Collections', MS_TEXT_DOMAIN ),'reports' => __( 'Sales Report', MS_TEXT_DOMAIN ), 'importer' => __( 'Songs Importer', MS_TEXT_DOMAIN ) );
			echo '<h2 class="nav-tab-wrapper">';
			$h1 = '';
			foreach( $tabs as $tab => $name ){
				$class = '';
				if( $tab == $current )
				{
					$class = ' nav-tab-active';
					$h1 = $name;
				}
				if($tab == 'song' )
					echo "<a class='nav-tab$class' href='edit.php?post_type=ms_$tab'>$name</a>";
				elseif($tab == 'collection')
					echo "<a class='nav-tab$class' href='javascript:void(0);' onclick='window.alert(\"Collections only available for commercial version of plugin\")'>$name</a>";
				else
					echo "<a class='nav-tab$class' href='admin.php?page={$this->music_store_slug}-$tab&tab=$tab'>$name</a>";

			}
			echo '</h2>';
			echo '<h1>'.$h1.'</h1>';
		} // End settings_tabs

		/**
		* Get the list of available layouts
		*/
		function _layouts(){
			$tpls_dir = dir( MS_FILE_PATH.'/ms-layouts' );
			while( false !== ( $entry = $tpls_dir->read() ) )
			{
				if ( $entry != '.' && $entry != '..' && is_dir( $tpls_dir->path.'/'.$entry ) && file_exists( $tpls_dir->path.'/'.$entry.'/config.ini' ) )
				{
					if( ( $ini_array = parse_ini_file( $tpls_dir->path.'/'.$entry.'/config.ini' ) ) !== false )
					{
						if( !empty( $ini_array[ 'style_file' ] ) ) $ini_array[ 'style_file' ] = 'ms-layouts/'.$entry.'/'.$ini_array[ 'style_file' ];
						if( !empty( $ini_array[ 'script_file' ] ) ) $ini_array[ 'script_file' ] = 'ms-layouts/'.$entry.'/'.$ini_array[ 'script_file' ];
						if( !empty( $ini_array[ 'thumbnail' ] ) ) $ini_array[ 'thumbnail' ] = MS_URL.'/ms-layouts/'.$entry.'/'.$ini_array[ 'thumbnail' ];
						$this->layouts[ $ini_array[ 'id' ] ] = $ini_array;
					}
				}
			}
		}

		/**
		* Get the list of possible paypal butt
		*/
		function _paypal_buttons(){
			global $music_store_settings;

			$b = $music_store_settings[ 'ms_paypal_button' ];
			$p = MS_FILE_PATH.'/paypal_buttons';
			$d = dir($p);
			$str = "";
			while (false !== ($entry = $d->read())) {
				if($entry != "." && $entry != ".." && is_file("$p/$entry"))
					$str .= "<input type='radio' name='ms_paypal_button' value='".esc_attr($entry)."' ".(($b == $entry) ? "checked" : "")." />&nbsp;<img src='".esc_url(MS_URL."/paypal_buttons/$entry")."'/>&nbsp;&nbsp;";
			}
			$d->close();
			return $str;
		} // End _paypal_buttons

		function importer()
		{
			$_REQUEST[ 'tab' ] = 'importer';
			$this->settings_page();

		} // End Importer

		/*
		* Set the music store settings
		*/
		function settings_page(){
			global $music_store_settings;

			print '<div class="wrap">'; // Open Wrap
			global $wpdb;
			$this->_layouts(); // Load the available layouts

			if ( isset( $_POST['ms_settings'] ) && wp_verify_nonce( $_POST['ms_settings'], plugin_basename( __FILE__ ) ) ){
				update_option('ms_main_page', esc_url_raw($_POST['ms_main_page']));
				update_option('ms_filter_by_genre', ((isset($_POST['ms_filter_by_genre'])) ? true : false));
				update_option('ms_filter_by_artist', ((isset($_POST['ms_filter_by_artist'])) ? true : false));
                update_option('ms_filter_by_album', ((isset($_POST['ms_filter_by_album'])) ? true : false));
				update_option('ms_search_taxonomy', ((isset($_POST['ms_search_taxonomy'])) ? true : false));
				update_option('ms_items_page_selector', ((isset($_POST['ms_items_page_selector'])) ? true : false));
				update_option('ms_friendly_url', ((isset($_POST['ms_friendly_url'])) ? 1 : 0));
				update_option('ms_items_page', @intval($_POST['ms_items_page']));
				if( !empty( $_POST[ 'ms_layout' ] ) && isset($this->layouts[$_POST['ms_layout']]) )
				{
					$this->layout = $this->layouts[ $_POST[ 'ms_layout' ] ];
					update_option( 'ms_layout', $this->layout );
				}
				else
				{
					delete_option( 'ms_layout' );
					$this->layout = array();
				}
				update_option('ms_paypal_email', sanitize_email($_POST['ms_paypal_email']));
				update_option(
					'ms_paypal_button',
					(
						in_array(
							$_POST['ms_paypal_button'],
							array( 'button_a.gif', 'button_b.gif', 'button_c.gif', 'button_d.gif' )
						)
					) ? $_POST['ms_paypal_button'] : 'button_d.gif'
				);
				update_option('ms_paypal_currency', strip_tags(html_entity_decode($_POST['ms_paypal_currency'])));
				update_option('ms_paypal_currency_symbol', strip_tags(html_entity_decode($_POST['ms_paypal_currency_symbol'])));
				update_option('ms_paypal_language', strip_tags(html_entity_decode($_POST['ms_paypal_language'])));
				update_option('ms_paypal_enabled', ((isset($_POST['ms_paypal_enabled'])) ? true : false));
				update_option('ms_paypal_sandbox', ((isset($_POST['ms_paypal_sandbox'])) ? true : false));
				update_option('ms_notification_from_email', sanitize_email($_POST['ms_notification_from_email']));
				update_option('ms_notification_to_email', sanitize_email($_POST['ms_notification_to_email']));
				update_option('ms_notification_to_payer_subject', wp_kses_data($_POST['ms_notification_to_payer_subject']));
				update_option('ms_notification_to_payer_message', wp_kses_data($_POST['ms_notification_to_payer_message']));
				update_option('ms_notification_to_seller_subject', wp_kses_data($_POST['ms_notification_to_seller_subject']));
				update_option('ms_notification_to_seller_message', wp_kses_data($_POST['ms_notification_to_seller_message']));
				update_option('ms_old_download_link', @intval($_POST['ms_old_download_link']));
				update_option('ms_downloads_number', @intval($_POST['ms_downloads_number']));
				update_option('ms_safe_download', ((isset($_POST['ms_safe_download'])) ? true : false));
				update_option('ms_play_all', ((isset($_POST['ms_play_all'])) ? 1 : 0));
				update_option('ms_social_buttons', ((isset($_POST['ms_social_buttons'])) ? true : false));
				update_option('ms_facebook_app_id', ((!empty($_POST['ms_facebook_app_id'])) ? trim($_POST['ms_facebook_app_id']) : ''));

				// Restrictions
				update_option('ms_download_link_for_registered_only', (isset($_POST['ms_download_link_for_registered_only'])) ? 1 : 0 );
				update_option('ms_buy_button_for_registered_only', (isset($_POST['ms_buy_button_for_registered_only'])) ? 1 : 0 );

				// Licenses
				update_option('ms_license_for_regular', esc_url_raw(trim($_POST['ms_license_for_regular'])));
				update_option('ms_license_for_free', esc_url_raw(trim($_POST['ms_license_for_free'])));

				do_action( 'ms_save_settings' );

				$this->_load_settings();
				unset( $_SESSION[ 'music_store_flush_rewrite_rules' ] );

?>
				<div class="updated" style="margin:5px 0;"><strong><?php _e("Settings Updated", MS_TEXT_DOMAIN); ?></strong></div>
<?php
			}

			$current_tab = (isset($_REQUEST['tab']) && in_array($_REQUEST['tab'], array( 'reports', 'settings', 'importer' ))) ? $_REQUEST['tab'] : (($_REQUEST['page'] == 'music-store-menu-reports') ? 'reports' : 'settings');

			$this->settings_tabs(
				$current_tab
			);
?>
			<p style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
				To get commercial version of Music Store, <a href="http://musicstore.dwbooster.com" target="_blank">CLICK HERE</a><br />
				For reporting an issue or to request a customization, <a href="http://musicstore.dwbooster.com/contact-us" target="_blank">CLICK HERE</a><br />
				If you want test the premium version of Music Store go to the following links:<br/> <a href="http://demos.net-factor.com/music-store/wp-login.php" target="_blank">Administration area: Click to access the administration area demo</a><br/>
				<a href="http://demos.net-factor.com/music-store/" target="_blank">Public page: Click to access the Store Page</a>
			</p>
<?php
			switch($current_tab){
				case 'settings':
?>
					<form method="post" action="<?php echo admin_url('admin.php?page=music-store-menu-settings&tab=settings'); ?>">
					<input type="hidden" name="tab" value="settings" />
					<!-- STORE CONFIG -->
					<div class="postbox">
						<h3 class='hndle' style="padding:5px;"><span><?php _e('Store page config', MS_TEXT_DOMAIN); ?></span></h3>
						<div class="inside">
							<table class="form-table">
								<tr valign="top">
									<th><?php _e('URL of store page', MS_TEXT_DOMAIN); ?></th>
									<td>
										<input type="text" name="ms_main_page" size="40" value="<?php echo esc_attr(esc_url($music_store_settings[ 'ms_main_page' ])); ?>" />
										<br />
										<em><?php _e('Set the URL of page where the music store was inserted', MS_TEXT_DOMAIN); ?></em>
									</td>
								</tr>
								<tr valign="top">
									<th><?php _e('Allow searching by taxonomies', MS_TEXT_DOMAIN); ?></th>
									<td><input type="checkbox" name="ms_search_taxonomy" value="1" <?php if( $music_store_settings[  'ms_search_taxonomy' ] ) echo 'checked'; ?> />
									<br />Including albums, artists, and genres
									</td>
								</tr>
								<tr valign="top">
									<th><?php _e('Allow filtering by type', MS_TEXT_DOMAIN); ?></th>
									<td>
										<input type="checkbox" name="ms_filter_by_type" disabled  />
										<em style="color:#FF0000;"><?php _e('The option is not available because the free version allows to create only songs', MS_TEXT_DOMAIN); ?></em>
									</td>
								</tr>
								<tr valign="top">
									<th><?php _e('Allow filtering by genre', MS_TEXT_DOMAIN); ?></th>
									<td><input type="checkbox" name="ms_filter_by_genre" value="1" <?php if ($music_store_settings[ 'ms_filter_by_genre' ]) echo 'checked'; ?> /></td>
								</tr>
								<tr valign="top">
									<th><?php _e('Allow filtering by artist', MS_TEXT_DOMAIN); ?></th>
									<td><input type="checkbox" name="ms_filter_by_artist" value="1" <?php if ($music_store_settings[ 'ms_filter_by_artist' ] ) echo 'checked'; ?> /></td>
								</tr>
                                <tr valign="top">
									<th><?php _e('Allow filtering by album', MS_TEXT_DOMAIN); ?></th>
									<td><input type="checkbox" name="ms_filter_by_album" value="1" <?php if ($music_store_settings[ 'ms_filter_by_album' ]) echo 'checked'; ?> /></td>
								</tr>
								<tr valign="top">
									<th><?php _e('Allow multiple pages', MS_TEXT_DOMAIN); ?></th>
									<td><input type="checkbox" name="ms_items_page_selector" value="1" <?php if ($music_store_settings[ 'ms_items_page_selector' ]) echo 'checked'; ?> /></td>
								</tr>
								<tr valign="top">
									<th><?php _e('Items per page', MS_TEXT_DOMAIN); ?></th>
									<td><input type="text" name="ms_items_page" value="<?php echo @intval($music_store_settings[ 'ms_items_page' ]); ?>" /></td>
								</tr>
								<tr valign="top">
									<th><?php _e('Use friendly URLs on products', MS_TEXT_DOMAIN); ?></th>
									<td><input type="checkbox" name="ms_friendly_url" value="1" <?php if ($music_store_settings[ 'ms_friendly_url' ]) echo 'checked'; ?> /></td>
								</tr>
								<tr valign="top">
									<th><?php _e('Store layout', MS_TEXT_DOMAIN); ?></th>
									<td>
										<select name="ms_layout" id="ms_layout">
											<option value=""><?php _e( 'Default layout', MS_TEXT_DOMAIN ); ?></option>
										<?php
											foreach( $this->layouts as $id => $layout )
											{
												print '<option value="'.esc_attr($id).'" '.( ( !empty( $this->layout ) && $id == $this->layout[ 'id' ] ) ? 'SELECTED' : '' ).' thumbnail="'.$layout[ 'thumbnail' ].'">'.$layout[ 'title' ].'</option>';
											}
										?>
										</select>
										<div id="ms_layout_thumbnail">
										<?php
											if( !empty( $this->layout ) )
											{
												print '<img src="'.esc_url($this->layout[ 'thumbnail' ]).'" title="'.esc_attr($this->layout[ 'title' ]).'" />';
											}
										?>
										</div>
									</td>
								</tr>
								<tr valign="top">
									<th><?php _e('Player style', MS_TEXT_DOMAIN); ?></th>
									<td>
										<table>
											<tr>
												<td><input name="ms_player_style" type="radio" value="mejs-classic" DISABLED CHECKED /></td>
												<td><img src="<?php print MS_URL; ?>/ms-core/images/skin1.png" /> <em style="color:#FF0000;"><?php _e('The free version of the plugin includes only the classic design for the music player', MS_TEXT_DOMAIN); ?></em></td>
											</tr>

											<tr>
												<td><input name="ms_player_style" type="radio" value="mejs-ted" DISABLED /></td>
												<td><img src="<?php print MS_URL; ?>/ms-core/images/skin2.png" /></td>
											</tr>

											<tr>
												<td><input name="ms_player_style" type="radio" value="mejs-wmp" DISABLED /></td>
												<td><img src="<?php print MS_URL; ?>/ms-core/images/skin3.png" /></td>
											</tr>
										</table>
										<em><?php _e( 'For MIDI audio files only the play/pause button will be displayed on players.' , MS_TEXT_DOMAIN); ?></em>
									</td>
								</tr>
								<tr valign="top">
									<th><?php _e('Play all', MS_TEXT_DOMAIN); ?></th>
									<td>
										<input name="ms_play_all" type="checkbox" <?php echo (( $music_store_settings[ 'ms_play_all' ] == 1 ) ? 'checked' : '') ;?> /> <br /><em><?php _e( 'Play all songs in the webpage, one after the other', MS_TEXT_DOMAIN ); ?></em>
									</td>
								</tr>
								<tr valign="top">
									<th><?php _e('Percent of audio used for protected playbacks', MS_TEXT_DOMAIN); ?></th>
									<td>
										<input type="text" name="ms_file_percent" disabled /> % <br />
										<em><?php _e('To prevent unauthorized copying of audio files, the files will be partially accessible',MS_TEXT_DOMAIN);?>
										</em>
										<em style="color:#FF0000;"><?php _e('The commercial version of plugin generates a truncated version of the audio file for selling to be used as demo', MS_TEXT_DOMAIN); ?>
										</em>
									</td>
								</tr>

								<tr valign="top">
									<th><?php _e('Explain text for protected playbacks', MS_TEXT_DOMAIN); ?></th>
									<td>
										<input type="text" name="ms_secure_playback_text" size="40" disabled /><br />
										<em><?php _e('The text will be shown below of the music player when secure playback is checked.', MS_TEXT_DOMAIN); ?>
										</em>
										<em style="color:#FF0000;">
										<?php _e('The secure playback is available only in the commercial version of plugin', MS_TEXT_DOMAIN); ?>
										</em>

									</td>
								</tr>
                                <tr valign="top">
									<th><?php _e('Share in social networks', MS_TEXT_DOMAIN); ?></th>
									<td>
										<input type="checkbox" name="ms_social_buttons" <?php echo (($music_store_settings[ 'ms_social_buttons' ]) ? 'CHECKED' : ''); ?> /><br />
										<em><?php _e('The option enables the buttons for share the pages of songs and collections in social networks', MS_TEXT_DOMAIN); ?></em>

									</td>
								</tr>
								<tr valign="top">
									<th><?php _e('Facebook app id for sharing in Facebook', MS_TEXT_DOMAIN); ?></th>
									<td>
										<input type="text" name="ms_facebook_app_id" value="<?php echo esc_attr($music_store_settings[ 'ms_facebook_app_id' ]); ?>" size="40" /><br />
										<em><?php _e('Click the link to generate the Facebook App and get its ID: <a target="_blank" href="https://developers.facebook.com/apps">https://developers.facebook.com/apps</a>', MS_TEXT_DOMAIN); ?></em>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- PAYPAL BOX -->
                    <p class="ms_more_info" style="display:block;">The Music Store uses PayPal only as payment gateway, but depending of your PayPal account, it is possible to charge the purchase directly from the Credit Cards of customers.</p>
					<div class="postbox">
						<h3 class='hndle' style="padding:5px;"><span><?php _e('Paypal Payment Configuration', MS_TEXT_DOMAIN); ?></span></h3>
						<div class="inside">

						<table class="form-table">
							<tr valign="top">
							<th scope="row"><?php _e('Enable Paypal Payments?', MS_TEXT_DOMAIN); ?></th>
							<td><input type="checkbox" name="ms_paypal_enabled" value="1" <?php if ($music_store_settings[ 'ms_paypal_enabled' ]) echo 'checked'; ?> /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Use Paypal Sandbox', MS_TEXT_DOMAIN); ?></th>
							<td><input type="checkbox" name="ms_paypal_sandbox" value="1" <?php if ($music_store_settings[ 'ms_paypal_sandbox' ] ) echo 'checked'; ?> /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Paypal email', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_paypal_email" size="40" value="<?php echo esc_attr($music_store_settings[ 'ms_paypal_email' ]); ?>" />
                            <span class="ms_more_info_hndl" style="margin-left: 10px;"><a href="javascript:void(0);" onclick="ms_display_more_info( this );">[ + more information]</a></span>
                            <div class="ms_more_info">
                                <p>If let empty the email associated to PayPal, the Music Store assumes the product will be distributed for free, and displays a download link in place of the button for purchasing</p>
                                <a href="javascript:void(0)" onclick="ms_hide_more_info( this );">[ + less information]</a>
                            </div>

                            </td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Currency', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_paypal_currency" value="<?php echo esc_attr($music_store_settings[ 'ms_paypal_currency' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Currency Symbol', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_paypal_currency_symbol" value="<?php echo esc_attr($music_store_settings[ 'ms_paypal_currency_symbol' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Paypal language', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_paypal_language" value="<?php echo esc_attr($music_store_settings[ 'ms_paypal_language' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Paypal button', MS_TEXT_DOMAIN); ?></th>
							<td><?php print $this->_paypal_buttons(); ?></td>
							</tr>

                            <tr valign="top">
							<th scope="row"><?php _e("or use a shopping cart", MS_TEXT_DOMAIN); ?></th>
							<td>
								<input type='radio' value='shopping_cart' disabled />
								<img src="<?php echo MS_URL; ?>/paypal_buttons/shopping_cart/button_e.gif" />
								<img src="<?php echo MS_URL; ?>/paypal_buttons/shopping_cart/button_f.gif" />
                                <em style="color:#FF0000;"><?php _e('The shopping cart is available only in the commercial version of plugin', MS_TEXT_DOMAIN); ?></em>
							</td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Hide the shopping cart icon from the store and products pages?', MS_TEXT_DOMAIN); ?></th>
							<td><input type="checkbox" disabled /><em style="color:#FF0000;"><?php _e('The shopping cart is available only in the commercial version of plugin', MS_TEXT_DOMAIN); ?></em></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Pay what you want', MS_TEXT_DOMAIN); ?></th>
							<td><input type="checkbox" disabled /> <?php _e( 'The prices of products are hidden in the public webpage and in their places are displayed input boxes to let the buyers pay the amount they consider adequate. If the "Pay what you want" option is enabled then the exclusive prices are not taken into account. The amounts entered by the buyers are compared with the base price of the products and if the values are under the base prices then the Music Store displays the text entered in the "price under minimum" message.', MS_TEXT_DOMAIN); ?><br /><em style="color:#FF0000;"><?php _e('The option "Pay what you want" is available only in the commercial version of plugin', MS_TEXT_DOMAIN); ?></em></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Label of price box', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" size="40" disabled /><br /> <?php _e('Text to display above the price box if the "Pay what you want" option is ticked.', MS_TEXT_DOMAIN)?><br /><em style="color:#FF0000;"><?php _e('The option "Pay what you want" is available only in the commercial version of plugin', MS_TEXT_DOMAIN); ?></em></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Price under minimum message', MS_TEXT_DOMAIN); ?></th>
							<td><textarea  cols="60" disabled></textarea><br /> <?php _e('Text to display if the "Pay what you want" option is ticked, and the amount entered by the buyer is under the base price defined in the product.', MS_TEXT_DOMAIN)?><br /><em style="color:#FF0000;"><?php _e('The option "Pay what you want" is available only in the commercial version of plugin', MS_TEXT_DOMAIN); ?></em></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Download link valid for', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_old_download_link" value="<?php echo @floatval($music_store_settings[ 'ms_old_download_link' ]); ?>" /> <?php _e('day(s)', MS_TEXT_DOMAIN)?></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Download link valid for', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_old_download_link" value="<?php echo @floatval($music_store_settings[ 'ms_old_download_link' ]); ?>" /> <?php _e('day(s)', MS_TEXT_DOMAIN)?></td>
							</tr>

                            <tr valign="top">
							<th scope="row"><?php _e('Number of downloads allowed by purchase', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_downloads_number" value="<?php echo @intval($music_store_settings[ 'ms_downloads_number' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Increase the download page security', MS_TEXT_DOMAIN); ?></th>
							<td><input type="checkbox" name="ms_safe_download" <?php echo ( ( $music_store_settings[ 'ms_safe_download' ]) ? 'CHECKED' : '' ); ?> /> <?php _e('The customers must enter the email address used in the product\'s purchasing to access to the download link. The Music Store verifies the customer\'s data, from the file link too.', MS_TEXT_DOMAIN)?></td>
							</tr>
							<tr valign="top">
							<th scope="row"><?php _e('Pack all purchased audio files as a single ZIP file', MS_TEXT_DOMAIN); ?></th>
							<td><input type="checkbox" disabled >
                            <em style="color:#FF0000;"><?php _e('Downloading all purchased products, packaged in a same zipped file, is only available in the commercial version of plugin', MS_TEXT_DOMAIN); ?></em>
							<?php
								if(!class_exists('ZipArchive'))
									echo '<br /><span class="explain-text">'.__("Your server can't create Zipped files dynamically. Please, contact to your hosting provider for enable ZipArchive in the PHP script", MS_TEXT_DOMAIN).'</span>';
							?>
							</td>
							</tr>
							<tr>
								<td colspan="2">
									<div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
										<p style="font-size:1.3em;">If you detect any issue with the payments or downloads please: <a href="#" onclick="jQuery('.ms-troubleshoot-area').show();return false;">CLICK HERE [ + ]</a></p>
										<div class="ms-troubleshoot-area" style="display:none;">
											<h3>An user has paid for a product but has not received the download link</h3>
											<p><b>Possible causes:</b></p>
											<p><span style="font-size:1.3em;">*</span> The Instant Payment Notification (IPN) is not enabled in your PayPal account, in whose case the website won't notified about the payments. Please, visit the following link: <a href="https://developer.paypal.com/docs/classic/ipn/integration-guide/IPNSetup/#id089EG030E5Z" target="_blank">How to enable the IPN?</a>. PayPal needs the URL to the IPN Script in your website, however, you simply should enter the URL to the home page, because the store will send the correct URL to the IPN Script.</p>
											<p><span style="font-size:1.3em;">*</span> The status of the payment is different to "Completed". If the payment status is different to "Completed" the Music Store won't generate the download link, or send the notification emails, to protect the sellers against frauds. PayPal will contact to the store even if the payment is "Pending" or has "Failed".</p>
											<p><b>But if the IPN is enabled, how can be detected the cause of issue?</b></p>
											<p>In this case you should check the IPN history (<a href="https://developer.paypal.com/docs/classic/ipn/integration-guide/IPNOperations/" target="_blank">CLICK HERE</a>)  for checking all variables that your PayPal account has sent to your website, and pays special attention to the "payment_status" variable (<a href="https://developer.paypal.com/docs/classic/ipn/integration-guide/IPNandPDTVariables/#id091EB04C0HS" target="_blank">CLICK HERE</a>)</p>
											<p><b>The IPN is enabled, and the status of the payment in the PayPal account is "Completed", the purchase has been registered in the sales reports of the Music Store (the menu option in your WordPress: "Music Store/Sales Report") but the buyer has not received the notification email. What is the cause?</b></p>
											<p><span style="font-size:1.3em;">*</span> Enter an email address belonging to your website's domain through the attribute: "Notification "from" email" in the store's settings ( accessible from the menu option: "Music Store/Store Settings"). The email services (like AOL, YAHOO, MSN, etc.) check the email addresses in the "Sender" header of the emails, and if they do not belong to the websites that send the emails, can be classified as spam or even worst, as "Phishing" emails.</p>
											<p><span style="font-size:1.3em;">*</span> The email address in the "From" attribute belongs to the store's domain, but the buyer is not receiving the notification email. In this case you should ask the hosting provider the accesses to the SMTP server (all hosting providers include one), and install any of the plugin for SMTP connection distributed for free through the WordPress directory.</p>
											<p><b>The buyer has received the notification email with the download link, but cannot download the audio files.</b></p>
											<p><span style="font-size:1.3em;">*</span> The Music Store prevents the direct access to the audio files for security reasons. From the download page, the Music Store checks the number of downloads, the buyer email, or the expiration time for the download link, so, the plugin works as proxy between the browser, and the audio file, so, the PHP Script should have assigned sufficient memory to load the audio file. Pay attention, the amount of memory assigned to the PHP Script in the web server can be bigger than the file's size, however, you should to consider that all the concurrent accesses to your website are sharing the same PHP memory, and if two buyers are downloading a same file at the same time, the PHP Script in the server should to load in memory the file twice.</p>
											<p><a href="#" onclick="jQuery('.ms-troubleshoot-area').hide();return false;">CLOSE SECTION [ - ]</a></p>
										</div>
									</div>
								</td>
							</tr>
							<tr><td colspan="2"><hr /></td></tr>
							<tr>
							<th scope="row">
							<?php _e( 'Restrict the access to registered users only', MS_TEXT_DOMAIN ); ?>
							</th>
							<td>
								<input type="checkbox" name="ms_download_link_for_registered_only" <?php print $music_store_settings[  'ms_download_link_for_registered_only' ] ? 'CHECKED' : ''; ?> />
								<?php _e('Display the free download links only for registered users', MS_TEXT_DOMAIN ); ?><br />
								<input type="checkbox" name="ms_buy_button_for_registered_only" <?php print $music_store_settings[  'ms_buy_button_for_registered_only' ] ? 'CHECKED' : ''; ?> />
								<?php _e('Include the "Buy Now" or "Shopping Cart" buttons only for registered users', MS_TEXT_DOMAIN ); ?><br />
							</td>
							</tr>
							<tr><td colspan="2"><hr /></td></tr>
							<tr>
							<th scope="row">
							<?php _e( 'Licenses', MS_TEXT_DOMAIN ); ?>
							</th>
							<td>
								<?php _e( 'Enter the URL to the webpage, or file, with the license for products downloaded for free.' , MS_TEXT_DOMAIN); ?><br />
								<input type="text" name="ms_license_for_free" value="<?php esc_attr_e( $music_store_settings[  'ms_license_for_free' ] ); ?>"  style="width:100%;" />
								<?php _e( 'Enter the URL to the webpage, or file, with the license for regular purchases.' , MS_TEXT_DOMAIN); ?><br />
								<input type="text" name="ms_license_for_regular" value="<?php esc_attr_e( $music_store_settings[  'ms_license_for_regular' ] ); ?>"  style="width:100%;" />
								<?php _e( 'Enter the URL to the webpage, or file, with the license for exclusive purchases.' , MS_TEXT_DOMAIN); ?><br />
								<input type="text" style="width:100%;" DISABLED /><br>
								<em style="color:#FF0000;"><?php _e('The exclusive sales are available only in the commercial version of the plugin, similar to the license', MS_TEXT_DOMAIN); ?></em>
								<p style="font-style:italic;">
								<strong><?php
								_e( 'Note:', MS_TEXT_DOMAIN );
								?></strong>
								<?php
								_e( 'The links to the corresponding licenses are sent to the buyers in the notification emails, and in the case of license for free downloads, are inserted beside the download links.', MS_TEXT_DOMAIN );
								?>
								</p>
							</td>
							</tr>
						 </table>
					  </div>
					</div>
					<?php $currency = $music_store_settings[ 'ms_paypal_currency' ]; ?>
                    <!--DISCOUNT BOX -->
                    <div class="postbox">
                        <h3 class='hndle' style="padding:5px;"><span><?php _e('Discount Settings', MS_TEXT_DOMAIN); ?></span></h3>
						<div class="inside">
                            <em style="color:#FF0000;"><?php _e('The discounts are only available for commercial version of plugin'); ?></em>
                            <div><input type="checkbox" DISABLED /> <?php _e('Display discount promotions in the music store page', MS_TEXT_DOMAIN)?></div>
                            <h4><?php _e('Scheduled Discounts', MS_TEXT_DOMAIN);?></h4>
                            <input type="hidden" name="ms_discount_list" id="ms_discount_list" />
                            <table class="form-table ms_discount_table" style="border:1px dotted #dfdfdf;">
                                <tr>
                                    <td style="font-weight:bold;"><?php _e('Percent of discount', MS_TEXT_DOMAIN); ?></td>
                                    <td style="font-weight:bold;"><?php _e('In Sales over than ... ', MS_TEXT_DOMAIN); echo($currency); ?></td>
                                    <td style="font-weight:bold;"><?php _e('Valid from dd/mm/yyyy', MS_TEXT_DOMAIN); ?></td>
                                    <td style="font-weight:bold;"><?php _e('Valid to dd/mm/yyyy', MS_TEXT_DOMAIN); ?></td>
                                    <td style="font-weight:bold;"><?php _e('Promotional text', MS_TEXT_DOMAIN); ?></td>
                                    <td style="font-weight:bold;"><?php _e('Status', MS_TEXT_DOMAIN); ?></td>
                                    <td></td>
                                </tr>
                            </table>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row"><?php _e('Percent of discount (*)', MS_TEXT_DOMAIN); ?></th>
                                    <td><input type="text" DISABLED /> %</td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Valid for sales over than (*)', MS_TEXT_DOMAIN); ?></th>
                                    <td><input type="text" DISABLED /> <?php echo $currency; ?></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Valid from (dd/mm/yyyy)', MS_TEXT_DOMAIN); ?></th>
                                    <td><input type="text" DISABLED /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Valid to (dd/mm/yyyy)', MS_TEXT_DOMAIN); ?></th>
                                    <td><input type="text" DISABLED /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Promotional text', MS_TEXT_DOMAIN); ?></th>
                                    <td><textarea DISABLED cols="60"></textarea></td>
                                </tr>
                                <tr><td colspan="2"><input type="button" class="button" value="<?php esc_attr_e(__('Add/Update Discount')); ?>" DISABLED /></td></tr>
                            </table>
                        </div>
                    </div>

					<!-- NOTIFICATIONS BOX -->
					<div class="postbox">
						<h3 class='hndle' style="padding:5px;"><span><?php _e('Notification Settings', MS_TEXT_DOMAIN); ?></span></h3>
						<div class="inside">

						<table class="form-table">
							<tr valign="top">
							<th scope="row"><?php _e('Notification "from" email', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_notification_from_email" size="40" value="<?php echo esc_attr($music_store_settings[ 'ms_notification_from_email' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Send notification to email', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_notification_to_email" size="40" value="<?php echo esc_attr($music_store_settings[ 'ms_notification_to_email' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Email subject confirmation to user', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_notification_to_payer_subject" size="40" value="<?php echo esc_attr($music_store_settings[ 'ms_notification_to_payer_subject' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Email confirmation to user', MS_TEXT_DOMAIN); ?></th>
							<td><textarea name="ms_notification_to_payer_message" cols="60" rows="5"><?php echo esc_textarea($music_store_settings[ 'ms_notification_to_payer_message' ]); ?></textarea></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Email subject notification to admin', MS_TEXT_DOMAIN); ?></th>
							<td><input type="text" name="ms_notification_to_seller_subject" size="40" value="<?php echo esc_attr($music_store_settings[ 'ms_notification_to_seller_subject' ]); ?>" /></td>
							</tr>

							<tr valign="top">
							<th scope="row"><?php _e('Email notification to admin', MS_TEXT_DOMAIN); ?></th>
							<td><textarea name="ms_notification_to_seller_message"  cols="60" rows="5"><?php echo esc_textarea($music_store_settings[ 'ms_notification_to_seller_message' ]); ?></textarea></td>
							</tr>
						 </table>
					  </div>
					</div>
					<?php
						do_action( 'ms_show_settings' );
						wp_nonce_field( plugin_basename( __FILE__ ), 'ms_settings' );
					?>
					<div class="submit"><input type="submit" class="button-primary" value="<?php _e('Update Settings', MS_TEXT_DOMAIN); ?>" />
					</form>

<?php
				break;
				case 'reports':
					if ( isset($_POST['ms_purchase_stats']) && wp_verify_nonce( $_POST['ms_purchase_stats'], plugin_basename( __FILE__ ) ) ){
						if(isset($_POST['delete_purchase_id'])){ // Delete the purchase
							$wpdb->query($wpdb->prepare(
								"DELETE FROM ".$wpdb->prefix.MSDB_PURCHASE." WHERE id=%d",
								$_POST['delete_purchase_id']
							));
						}

						if(isset($_POST['reset_purchase_id'])){ // Reset downloads and time interval
							$wpdb->query($wpdb->prepare(
								"UPDATE ".$wpdb->prefix.MSDB_PURCHASE." SET checking_date = NOW(),downloads = 0 WHERE id=%d",
								$_POST['reset_purchase_id']
							));
						}

						if(isset($_POST['show_purchase_id'])){ // Display paypal details
							$paypal_data = '<div class="ms-paypal-data"><h3>' . __( 'PayPal data', MS_TEXT_DOMAIN ) . '</h3>' . $wpdb->get_var($wpdb->prepare(
								"SELECT paypal_data FROM ".$wpdb->prefix.MSDB_PURCHASE." WHERE id=%d",
								$_POST['show_purchase_id']
							)) . '</div>';
							$paypal_data = preg_replace( '/\n+/', '<br />', $paypal_data );
						}

						if( isset( $_POST[ 'old_email' ] )  && isset( $_POST[ 'new_email' ] ) )
						{
							$old_email = sanitize_email(trim( $_POST[ 'old_email' ] ));
							$new_email = sanitize_email(trim( $_POST[ 'new_email' ] ));

							if( !empty( $old_email ) && !empty( $new_email ) )
							{
								$wpdb->query($wpdb->prepare(
									"UPDATE ".$wpdb->prefix.MSDB_PURCHASE." SET email=%s WHERE email=%s",
									$new_email,
									$old_email
								));

								if ( !empty( $_POST['buyer'] ) && $_POST['buyer'] == $old_email ) $_POST[ 'buyer' ] = $new_email;
							}
						}
					}

					$group_by_arr = array(
										'no_group'  => 'Group by',
										'ms_artist'    => 'Artist',
										'ms_genre' 	=> 'Genre',
										'ms_album' 	=> 'Album'
									);

					$from_day = intval((isset($_POST['from_day'])) ? $_POST['from_day'] : date('j'));
					$from_month = intval((isset($_POST['from_month'])) ? $_POST['from_month'] : date('m'));
					$from_year = intval((isset($_POST['from_year'])) ? $_POST['from_year'] : date('Y'));
					$buyer = ( !empty( $_POST['buyer'] ) ) ? $_POST[ 'buyer' ] : '';
					$buyer = sanitize_email(trim(stripcslashes($buyer)));

					$to_day = intval((isset($_POST['to_day'])) ? $_POST['to_day'] : date('j'));
					$to_month = intval((isset($_POST['to_month'])) ? $_POST['to_month'] : date('m'));
					$to_year = intval((isset($_POST['to_year'])) ? $_POST['to_year'] : date('Y'));

					$group_by = (isset($_POST['group_by'])) ? $_POST['group_by'] : 'no_group';
					$to_display = (isset($_POST['to_display'])) ? $_POST['to_display'] : 'sales';

					$_select = "";
					$_from 	 = " FROM ".$wpdb->prefix.MSDB_PURCHASE." AS purchase, ".$wpdb->prefix."posts AS posts ";
					$_where  = " WHERE posts.ID = purchase.product_id
									  AND (posts.post_type = 'ms_song' OR posts.post_type = 'ms_collection')
									  AND DATEDIFF(purchase.date, '{$from_year}-{$from_month}-{$from_day}')>=0
									  AND DATEDIFF(purchase.date, '{$to_year}-{$to_month}-{$to_day}')<=0 ";

					if( isset( $_REQUEST[ 'list_purchases' ] ) )
					{
						if( $_REQUEST[ 'list_purchases' ] == 'paid' ) $_where .= " AND purchase.amount<>0 ";
						elseif( $_REQUEST[ 'list_purchases' ] == 'free' ) $_where .= " AND purchase.amount=0 ";
					}

                    if( !empty( $buyer ) )
                    {
                        $_where .= $wpdb->prepare( "AND purchase.email LIKE '%s'", "%".$wpdb->esc_like($buyer)."%" );
					}

					$_group  = "";
					$_order  = "";
					$_date_dif = floor( max( abs( strtotime( $to_year.'-'.$to_month.'-'.$to_day ) - strtotime( $from_year.'-'.$from_month.'-'.$from_day ) ) / ( 60*60*24 ), 1 ) );
                    $_table_header = array( __( 'Date', MS_TEXT_DOMAIN ), __( 'Product', MS_TEXT_DOMAIN ), __( 'Buyer', MS_TEXT_DOMAIN ), __( 'Amount', MS_TEXT_DOMAIN ), __( 'Currency', MS_TEXT_DOMAIN ), __( 'Download link', MS_TEXT_DOMAIN ), '' );

					if( $group_by == 'no_group' )
					{
						if( $to_display == 'sales' )
						{
							$_select .= "SELECT purchase.*, posts.*";
						}
						else
						{
							$_select .= "SELECT SUM(purchase.amount)/{$_date_dif} as purchase_average, SUM(purchase.amount) as purchase_total, COUNT(posts.ID) as purchase_count, posts.*";
							$_group   = " GROUP BY posts.ID";
							if( $to_display == 'amount' )
							{
								$_table_header = array( 'Product', 'Amount of Sales', 'Total' );
								$_order = " ORDER BY purchase_count DESC";
							}
							else
							{
								$_table_header = array( 'Product', 'Daily Average', 'Total' );
								$order =  " ORDER BY purchase_average DESC";
							}
						}
					}
					else
					{
						$_select .= "SELECT SUM(purchase.amount)/{$_date_dif} as purchase_average, SUM(purchase.amount) as purchase_total, COUNT(posts.ID) as purchase_count, terms.name as term_name, terms.slug as term_slug";

						$_from   .= ", {$wpdb->prefix}term_taxonomy as taxonomy,
								     {$wpdb->prefix}term_relationships as term_relationships,
								     {$wpdb->prefix}terms as terms";
						$_where  .=" AND taxonomy.taxonomy = '{$group_by}'
									 AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id
									 AND term_relationships.object_id=posts.ID
									 AND taxonomy.term_id=terms.term_id";
						$_group  = " GROUP BY terms.term_id";
						$_order  = " ORDER BY terms.slug;";

						if( $to_display == 'amount' )
						{
							$_order = " ORDER BY purchase_count DESC";
							$_table_header = array( $group_by_arr[ $group_by ], 'Amount of Sales', 'Total' );
						}
						else
						{
							$order =  " ORDER BY purchase_average DESC";
							if( $to_display == 'sales' )
							{
								$_table_header = array( $group_by_arr[ $group_by ], 'Total' );
							}
							else
							{
								$_table_header = array( $group_by_arr[ $group_by ], 'Daily Average', 'Total' );
							}
						}
					}
					$purchase_list = $wpdb->get_results( $_select.$_from.$_where.$_group.$_order );

?>
					<form method="post" action="<?php echo admin_url('admin.php?page=music-store-menu-reports&tab=reports'); ?>" id="purchase_form">
					<?php wp_nonce_field( plugin_basename( __FILE__ ), 'ms_purchase_stats' ); ?>
					<input type="hidden" name="tab" value="reports" />
					<!-- FILTER REPORT -->
					<div class="postbox">
						<h3 class='hndle' style="padding:5px;"><span><?php _e('Filter the sales reports', MS_TEXT_DOMAIN); ?></span></h3>
						<div class="inside">
							<div>
								<h4><?php _e('Filter by date', MS_TEXT_DOMAIN); ?></h4>
								<?php
									$months_list = array(
										'01' => __('January', MS_TEXT_DOMAIN),
										'02' => __('February', MS_TEXT_DOMAIN),
										'03' => __('March', MS_TEXT_DOMAIN),
										'04' => __('April', MS_TEXT_DOMAIN),
										'05' => __('May', MS_TEXT_DOMAIN),
										'06' => __('June', MS_TEXT_DOMAIN),
										'07' => __('July', MS_TEXT_DOMAIN),
										'08' => __('August', MS_TEXT_DOMAIN),
										'09' => __('September', MS_TEXT_DOMAIN),
										'10' => __('October', MS_TEXT_DOMAIN),
										'11' => __('November', MS_TEXT_DOMAIN),
										'12' => __('December', MS_TEXT_DOMAIN),
									);
								?>
                                <label><?php _e('Buyer: ', MS_TEXT_DOMAIN); ?></label><input type="text" name="buyer" id="buyer" value="<?php esc_attr_e($buyer); ?>" />
								<label><?php _e('From: ', MS_TEXT_DOMAIN); ?></label>
								<select name="from_day">
								<?php
									for($i=1; $i <=31; $i++) print '<option value="'.esc_attr($i).'"'.(($from_day == $i) ? ' SELECTED' : '').'>'.$i.'</option>';
								?>
								</select>
								<select name="from_month">
								<?php
									foreach($months_list as $month => $name) print '<option value="'.esc_attr($month).'"'.(($from_month == $month) ? ' SELECTED' : '').'>'.$name.'</option>';
								?>
								</select>
								<input type="text" name="from_year" value="<?php print esc_attr( $from_year ); ?>" />

								<label><?php _e('To: ', MS_TEXT_DOMAIN); ?></label>
								<select name="to_day">
								<?php
									for($i=1; $i <=31; $i++) print '<option value="'.esc_attr($i).'"'.(($to_day == $i) ? ' SELECTED' : '').'>'.$i.'</option>';
								?>
								</select>
								<select name="to_month">
								<?php
									foreach($months_list as $month => $name) print '<option value="'.esc_attr($month).'"'.(($to_month == $month) ? ' SELECTED' : '').'>'.$name.'</option>';
								?>
								</select>
								<input type="text" name="to_year" value="<?php esc_attr_e($to_year); ?>" />
								<input type="submit" value="<?php esc_attr_e(__('Search', MS_TEXT_DOMAIN)); ?>" class="button-primary" />
							</div>

							<div style="float:left;margin-right:20px;">
								<h4><?php _e('Grouping the sales', MS_TEXT_DOMAIN); ?></h4>
								<label><?php _e('By: ', MS_TEXT_DOMAIN); ?></label>
								<select name="group_by">
								<?php
									foreach( $group_by_arr as $key => $value )
									{
										print '<option value="'.esc_attr($key).'"'.( ( isset( $group_by ) && $group_by == $key ) ? ' SELECTED' : '' ).'>'.music_store_strip_tags($value,true).'</option>';
									}
								?>
								</select>
							</div>
							<div style="float:left;margin-right:20px;">
								<h4><?php _e('Display', MS_TEXT_DOMAIN); ?></h4>
								<label><input type="radio" name="to_display" <?php echo ( ( !isset( $to_display ) || $to_display == 'sales' ) ? 'CHECKED' : '' ); ?> value="sales" /> <?php _e('Sales', MS_TEXT_DOMAIN); ?></label>
								<label><input type="radio" name="to_display" <?php echo ( ( isset( $to_display ) && $to_display == 'amount' ) ? 'CHECKED' : '' ); ?> value="amount" /> <?php _e('Amount of sales', MS_TEXT_DOMAIN); ?></label>
								<label><input type="radio" name="to_display" <?php echo ( ( isset( $to_display ) && $to_display == 'average' ) ? 'CHECKED' : '' ); ?> value="average" /> <?php _e('Daily average', MS_TEXT_DOMAIN); ?></label>
							</div>
							<div style="clear:both;"></div>
						</div>
					</div>
					<!-- PURCHASE LIST -->
					<div class="postbox">
						<h3 class='hndle' style="padding:5px;"><span><?php _e('Store sales report', MS_TEXT_DOMAIN); ?></span></h3>
						<div class="inside">
							<?php
								if( !empty( $paypal_data ) ) print $paypal_data;
								if(count($purchase_list)){
									print '
										<div>
											<label style="margin-right: 20px;" ><input type="checkbox" onclick="ms_load_report(this, \'sales_by_country\', \''.esc_js(__( 'Sales by country', MS_TEXT_DOMAIN )).'\', \'residence_country\', \'Pie\', \'residence_country\', \'count\');" /> '.__( 'Sales by country', MS_TEXT_DOMAIN ).'</label>
											<label style="margin-right: 20px;" ><input type="checkbox" onclick="ms_load_report(this, \'sales_by_currency\', \''.esc_js(__( 'Sales by currency', MS_TEXT_DOMAIN )).'\', \'mc_currency\', \'Bar\', \'mc_currency\', \'sum\');" /> '.__( 'Sales by currency', MS_TEXT_DOMAIN ).'</label>
											<label style="margin-right: 20px;" ><input type="checkbox" onclick="ms_load_report(this, \'sales_by_product\', \''.esc_js(__( 'Sales by product', MS_TEXT_DOMAIN )).'\', \'product_name\', \'Bar\', \'post_title\', \'sum\');" /> '.__( 'Sales by product', MS_TEXT_DOMAIN ).'</label>
											<label><input type="checkbox" onclick="ms_load_report(this, \'download_by_product\', \''.esc_js(__( 'Downloads free products', MS_TEXT_DOMAIN )).'\', \'download_by_product\', \'Pie\', \'post_title\', \'count\');" /> '.__( 'Downloads free products', MS_TEXT_DOMAIN ).'</label>
										</div>';
								}
							?>
						    <div id="charts_content" >
								<div id="sales_by_country"></div>
								<div id="sales_by_currency"></div>
								<div id="sales_by_product"></div>
								<div id="download_by_product"></div>
							</div>
							<div class="ms-section-title"><?php _e( 'Products List', MS_TEXT_DOMAIN ); ?></div>
							<div>
								<input type="radio" name="list_purchases" value="all" <?php if( !isset( $_REQUEST[ 'list_purchases' ] ) || $_REQUEST[ 'list_purchases' ] == 'all' ) print 'CHECKED';  ?> onchange="ms_filtering_products_list(this);"><?php _e( 'List all products', MS_TEXT_DOMAIN ); ?>&nbsp;&nbsp;&nbsp;
								<input type="radio" name="list_purchases" value="paid" <?php if( isset( $_REQUEST[ 'list_purchases' ] ) && $_REQUEST[ 'list_purchases' ] == 'paid' ) print 'CHECKED';  ?> onchange="ms_filtering_products_list(this);"><?php _e( 'List only the purchased products', MS_TEXT_DOMAIN ); ?>&nbsp;&nbsp;&nbsp;
								<input type="radio" name="list_purchases" value="free" <?php if( isset( $_REQUEST[ 'list_purchases' ] ) && $_REQUEST[ 'list_purchases' ] == 'free' ) print 'CHECKED';  ?> onchange="ms_filtering_products_list(this);"><?php _e( 'List only the products downloaded for free', MS_TEXT_DOMAIN ); ?>
							</div>
							<table class="form-table" style="border-bottom:1px solid #CCC;margin-bottom:10px;">
								<THEAD>
									<TR style="border-bottom:1px solid #CCC;">
								<?php
									foreach( $_table_header as $_header )
									{
										print "<TH>{$_header}</TH>";
									}
								?>
									</TR>
								</THEAD>
								<TBODY>
								<?php

								$totals = array('UNDEFINED'=>0);
                                $dlurl = $this->_ms_create_pages( 'ms-download-page', 'Download Page' );
                                $dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' );

								if(count($purchase_list)){

									foreach($purchase_list as $purchase){

										if( $group_by == 'no_group' )
										{

											if( $to_display == 'sales' )
											{
												if(preg_match('/mc_currency=([^\s]*)/', $purchase->paypal_data, $matches)){
													$currency = strtoupper($matches[1]);
													if(!isset($totals[$currency])) $totals[$currency] = $purchase->amount;
														else $totals[$currency] += $purchase->amount;
												}else{
													$currency = '';
													$totals['UNDEFINED'] += $purchase->amount;
												}

												echo '
													<TR>
														<TD>'.$purchase->date.'</TD>
														<TD><a href="'.get_permalink($purchase->ID).'" target="_blank">'.( ( empty( $purchase->post_title ) ) ? $purchase->ID : music_store_strip_tags($purchase->post_title) ).'</a></TD>
														<TD>'.music_store_strip_tags($purchase->email).'</TD>
														<TD>'.music_store_strip_tags($purchase->amount).'</TD>
														<TD>'.music_store_strip_tags($currency).'</TD>
														<TD><a href="'.esc_url($dlurl.'ms-action=download&purchase_id='.$purchase->purchase_id).'" target="_blank">Download Link</a></TD>
														<TD style="white-space:nowrap;">
															<input type="button" class="button-primary" onclick="delete_purchase('.esc_js($purchase->id).');" value="Delete"> '.
														(
															( $purchase->amount) ?
															'<input type="button" class="button-primary" onclick="reset_purchase('.esc_js($purchase->id).');" value="Reset Time and Downloads">
															<input type="button" class="button-primary" onclick="show_purchase('.esc_js($purchase->id).');" value="PayPal Info">' : ''
														)
														.'</TD>
													</TR>
												';
											}elseif( $to_display == 'amount' ){
												echo '
													<TR>
														<TD><a href="'.get_permalink($purchase->ID).'" target="_blank">'.music_store_strip_tags($purchase->post_title).'</a></TD>
														<TD>'.(round( $purchase->purchase_count*100 )/100).'</TD>
														<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
													</TR>
												';
											}else{
												echo '
													<TR>
														<TD><a href="'.get_permalink($purchase->ID).'" target="_blank">'.music_store_strip_tags($purchase->post_title).'</a></TD>
														<TD>'.$purchase->purchase_average.'</TD>
														<TD>'.(round($purchase->purchase_total*100)/100).'</TD>
													</TR>
												';
											}
										}
										else
										{

											if( $to_display == 'sales' ){
												echo '
														<TR>
															<TD><a href="'.get_term_link($purchase->term_slug, $group_by ).'" target="_blank">'.music_store_strip_tags($purchase->term_name).'</a></TD>
															<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
														</TR>
													';
											}elseif(  $to_display == 'amount'  ){
												echo '
														<TR>
															<TD><a href="'.get_term_link($purchase->term_slug, $group_by ).'" target="_blank">'.music_store_strip_tags($purchase->term_name).'</a></TD>
															<TD>'.(round( $purchase->purchase_count*100)/100).'</TD>
															<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
														</TR>
													';
											}else{
												echo '
														<TR>
															<TD><a href="'.get_term_link($purchase->term_slug, $group_by ).'" target="_blank">'.music_store_strip_tags($purchase->term_name).'</a></TD>
															<TD>'.$purchase->purchase_average.'</TD>
															<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
														</TR>
													';
											}
										}

									}
								}else{
									echo '
										<TR>
											<TD COLSPAN="'.count( $_table_header ).'">
												'.__('There are not sales registered with those filter options', MS_TEXT_DOMAIN).'
											</TD>
										</TR>
									';
								}
								?>
								</TBODY>
							</table>
							<table style="width:100%;">
								<tr>
									<td>
							<?php
								if(count($totals) > 1 || $totals['UNDEFINED']){
							?>
									<table style="border: 1px solid #CCC;">
										<TR><TD COLSPAN="2" style="border-bottom:1px solid #CCC;">TOTALS</TD></TR>
										<TR><TD style="border-bottom:1px solid #CCC;">CURRENCY</TD><TD style="border-bottom:1px solid #CCC;">AMOUNT</TD></TR>
									<?php
										foreach($totals as $currency=>$amount)
											if($amount)
												print "<TR><TD><b>{$currency}</b></TD><TD>{$amount}</TD></TR>";
									?>
									</table>
									</td>
									<td align="right">
										<table>
											<tr>
												<td>
													Buyer email:
													<input type="email" name="old_email" />
												</td>
												<td>
													New email:
													<input type="email" name="new_email" />
												</td>
												<td>
													<input type="submit" value="<?php esc_attr_e(__( 'Replace', MS_TEXT_DOMAIN )); ?>" class="button-primary" />
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
							<?php
								}
							?>
						</div>
					</div>
					</form>
<?php
				break;
				case 'importer':
				?>
					<p style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
						The feature is only available in the commercial version of Music Store.
					</p>
				<?php
				break;
			}
			print '</div>'; // Close Wrap
		} // End settings_page

/** LOADING PUBLIC OR ADMINSITRATION RESOURCES **/

		/**
		* Load public scripts and styles
		*/
		function public_resources(){
			global $music_store_settings;
			wp_enqueue_style('ms-mediacore-style', plugin_dir_url(__FILE__).'ms-styles/mediaelementplayer.min.css');
			wp_enqueue_style('ms-style', plugin_dir_url(__FILE__).'ms-styles/ms-public.css', array( 'ms-mediacore-style' ) );

			wp_enqueue_script('jquery');
			wp_enqueue_script('ms-mediacore-script', plugin_dir_url(__FILE__).'ms-script/mediaelement-and-player.min.js', array('jquery'));
			wp_enqueue_script('ms-media-script', plugin_dir_url(__FILE__).'ms-script/codepeople-plugins.js', array('ms-mediacore-script'), null);

			// Load resources of layout
			if( !empty( $this->layout) )
			{
				if( !empty( $this->layout[ 'style_file' ] ) ) wp_enqueue_style('ms-css-layout', plugin_dir_url(__FILE__).$this->layout[ 'style_file' ] , array( 'ms-style' ) );
				if( !empty( $this->layout[ 'script_file' ] ) ) wp_enqueue_script('ms-js-layout', plugin_dir_url(__FILE__).$this->layout[ 'script_file' ] , array( 'ms-media-script' ), false);
			}

			$play_all = ( isset($music_store_settings) && isset($music_store_settings[ 'ms_play_all' ])) ? $music_store_settings[ 'ms_play_all' ] : 0;

			wp_localize_script('ms-media-script', 'ms_global', array('hurl' => esc_url_raw(MS_H_URL), 'play_all' => $play_all));

		} // End public_resources

		/**
		* Load admin scripts and styles
		*/
		function admin_resources($hook){
			global $post;
			if(strpos($hook, "music-store") !== false){
				wp_enqueue_script('ms-admin-script-chart', plugin_dir_url(__FILE__).'ms-script/Chart.min.js', array('jquery'), null, true);
                wp_enqueue_script('ms-admin-script', plugin_dir_url(__FILE__).'ms-script/ms-admin.js', array('jquery'), null, true);
                wp_enqueue_style('ms-admin-style', plugin_dir_url(__FILE__).'ms-styles/ms-admin.css');
				wp_localize_script('ms-admin-script', 'ms_global', array( 'aurl' => admin_url() ));
			}
			if ( $hook == 'post-new.php' || $hook == 'post.php' || $hook == 'index.php') {
                wp_enqueue_script('jquery-ui-core');
				wp_enqueue_script('jquery-ui-sortable');
				wp_enqueue_script('jquery-ui-draggable');
				wp_enqueue_script('jquery-ui-droppable');
                wp_enqueue_script('jquery-ui-dialog');
				wp_enqueue_script('ms-admin-script', plugin_dir_url(__FILE__).'ms-script/ms-admin.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-dialog', 'media-upload'), null, true);

				if(isset($post) && $post->post_type == "ms_song"){
					// Scripts and styles required for metaboxs
					wp_enqueue_style('ms-admin-style', plugin_dir_url(__FILE__).'ms-styles/ms-admin.css');
					wp_localize_script('ms-admin-script', 'music_store', array('post_id'  	=> $post->ID));
				}else{
					// Scripts required for music store insertion
					wp_enqueue_style('wp-jquery-ui-dialog');

					// Set the variables for insertion dialog
					$tags = '';
					// Load genres
					$genre_list = get_terms('ms_genre', array( 'hide_empty' => 0 ));
					// Load artists
					$artist_list = get_terms('ms_artist', array( 'hide_empty' => 0 ));
					// Album
					$album_list = get_terms('ms_album', array( 'hide_empty' => 0 ));

					$tags .= '<div title="'.esc_attr(__('Insert Music Store', MS_TEXT_DOMAIN)).'"><div style="padding:20px;">';

					$tags .= '<div>'.__('Filter results by products type:', MS_TEXT_DOMAIN).'<br /><select id="load" name="load" style="width:100%"><option value="all">'.__('All types', MS_TEXT_DOMAIN).'</option></select><br /><em style="color:#FF0000;">'.__('Filter by product types is only available for commercial version of plugin').'</em></div><div>'.__('Columns:', MS_TEXT_DOMAIN).' <br /><input type="text" name="columns" id="columns" style="width:100%" value="1" /></div>';

					$tags .= '<div>'.__('Filter results by genre:', MS_TEXT_DOMAIN).'<br /><select id="genre" name="genre" style="width:100%"><option value="all">'.__('All genres', MS_TEXT_DOMAIN).'</option>';

					foreach($genre_list as $genre){
							$tags .= '<option value="'.esc_attr($genre->term_id).'">'.music_store_strip_tags($genre->name,true).'</option>';
					}

					$tags .= '</select></div><div>'.__('-or- filter results by artist:', MS_TEXT_DOMAIN).'<br /><select id="artist" name="artist" style="width:100%"><option value="all">'.__('All artists', MS_TEXT_DOMAIN).'</option>';

					foreach($artist_list as $artist){
							$tags .= '<option value="'.esc_attr($artist->term_id).'">'.music_store_strip_tags($artist->name,true).'</option>';
					}
					$tags .= '</select></div><div>'.__('-or- filter results by album:', MS_TEXT_DOMAIN).'<br /><select id="album" name="album" style="width:100%"><option value="all">'.__('All albums', MS_TEXT_DOMAIN).'</option>';

					foreach($album_list as $album){
							$tags .= '<option value="'.esc_attr($album->term_id).'">'.music_store_strip_tags($album->name,true).'</option>';
					}
					$tags .= '</select></div></div></div>';

					wp_localize_script('ms-admin-script', 'music_store', array('tags' => $tags));
				}
			}
		} // End admin_resources


/** LOADING MUSIC STORE AND ITEMS ON WORDPRESS SECTIONS **/

		/**
		*	Add custom post type to the search result
		*/
		function add_post_type_to_results($query){
			global $wpdb;
			if ( $query->is_search){
				$not_in = array();
				$restricted_list = $wpdb->get_results("SELECT posts.ID FROM ".$wpdb->prefix.MSDB_POST_DATA." as post_data,".$wpdb->prefix."posts as posts  WHERE posts.post_type='ms_song' AND posts.ID=post_data.id AND posts.post_status='publish' AND post_data.as_single=0");

				foreach($restricted_list as $restricted){
					$not_in[] = $restricted->ID;
				}

				if(!empty($not_in))
					$query->set('post__not_in', $not_in);
			}
			return $query;
		} // End add_post_type_to_results

		/**
		* Replace the music_store shortcode with correct items
		*
		*/
		function load_store($atts, $content, $tag){
			global $wpdb, $music_store_settings;

            $page_id = 'ms_page_'.get_the_ID();

            if( !isset( $_SESSION[ $page_id ] ) ) $_SESSION[ $page_id ] = array();

			// Generated music store
			$music_store = "";
			$page_links = "";
			$header = "";
			$items_summary = "";

			// Extract the music store attributes
			extract(shortcode_atts(array(
					'load' 		=> 'all',
					'genre' 	=> 'all',
					'artist'	=> 'all',
					'album'		=> 'all',
					'columns'  	=> 1
				), $atts)
			);

			// Extract query_string variables correcting music store attributes
            if(
                isset( $_REQUEST['filter_by_genre']  ) ||
                isset( $_REQUEST['filter_by_artist'] ) ||
                isset( $_REQUEST['filter_by_album']  )
            )
            {
                unset( $_SESSION[ $page_id ]['ms_post_type'] );
                unset( $_SESSION[ $page_id ]['ms_genre'] );
                unset( $_SESSION[ $page_id ]['ms_artist'] );
                unset( $_SESSION[ $page_id ]['ms_album'] );
            }

			if(isset($_REQUEST['filter_by_type']) && in_array($_REQUEST['filter_by_type'], array('all', 'singles'))){
				$_SESSION[ $page_id ]['ms_post_type'] = $_REQUEST['filter_by_type'];
			}

			if(isset($_REQUEST['filter_by_genre'])){
				$_SESSION[ $page_id ]['ms_genre'] = $_REQUEST['filter_by_genre'];
			}

            if(isset($_REQUEST['filter_by_album'])){
				$_SESSION[ $page_id ]['ms_album'] = $_REQUEST['filter_by_album'];
			}

			if(isset($_REQUEST['filter_by_artist'])){
				$_SESSION[ $page_id ]['ms_artist'] = $_REQUEST['filter_by_artist'];
			}

			if(isset($_SESSION[ $page_id ]['ms_post_type'])){
				$load = $_SESSION[ $page_id ]['ms_post_type'];
			}

            if(isset($_SESSION[ $page_id ]['ms_genre'])){
				$genre = $_SESSION[ $page_id ]['ms_genre'];
			}

            if(isset($_SESSION[ $page_id ]['ms_album'])){
				$album = $_SESSION[ $page_id ]['ms_album'];
            }

			if(isset($_SESSION[ $page_id ]['ms_artist'])){
				$artist = $_SESSION[ $page_id ]['ms_artist'];
			}

			if(isset($_REQUEST['ordering_by']) && in_array($_REQUEST['ordering_by'], array('plays', 'price', 'post_title', 'post_date'))){
				$_SESSION[ $page_id ]['ms_ordering'] = $_REQUEST['ordering_by'];
			}elseif( !isset( $_SESSION[ $page_id ]['ms_ordering'] ) ){
                $_SESSION[ $page_id ]['ms_ordering'] = ( isset( $atts[ 'order_by' ] ) ) ? $atts[ 'order_by' ] : "post_date";
			}

			// Extract info from music_store options
			$allow_filter_by_genre = ( isset( $atts[ 'filter_by_genre' ] ) ) ? $atts[ 'filter_by_genre' ] * 1 : $music_store_settings[ 'ms_filter_by_genre' ];
			$allow_filter_by_artist = ( isset( $atts[ 'filter_by_artist' ] ) ) ? $atts[ 'filter_by_artist' ] * 1 : $music_store_settings[ 'ms_filter_by_artist' ];
            $allow_filter_by_album  = ( isset( $atts[ 'filter_by_album' ] ) ) ? $atts[ 'filter_by_album' ] * 1 : $music_store_settings[ 'ms_filter_by_album' ];

			// Items per page
			$items_page 			= max($music_store_settings[ 'ms_items_page' ], 1);
			// Display pagination
			$items_page_selector 	= $music_store_settings[ 'ms_items_page_selector' ];

			// Query clauses
			$_select 	= "SELECT SQL_CALC_FOUND_ROWS DISTINCT posts.*, posts_data.*";
			$_from 		= "FROM ".$wpdb->prefix."posts as posts,".$wpdb->prefix.MSDB_POST_DATA." as posts_data";
			$_where 	= "WHERE posts.ID = posts_data.id AND posts.post_status='publish'";
			$_order_by 	= "ORDER BY ".(($_SESSION[ $page_id ]['ms_ordering'] == "post_title" || $_SESSION[ $page_id ]['ms_ordering'] == "post_date") ? "posts" : "posts_data").".".$_SESSION[ $page_id ]['ms_ordering']." ".(($_SESSION[ $page_id ]['ms_ordering'] == "plays" || $_SESSION[ $page_id ]['ms_ordering'] == "post_date") ? "DESC" : "ASC");
			$_limit 	= "";


			if($artist !== 'all' || $genre !== 'all' || $album !== 'all'){
				// Load the taxonomy tables
				if($genre !== 'all'){
					$_from .= ", ".$wpdb->prefix."term_taxonomy as taxonomy, ".$wpdb->prefix."term_relationships as term_relationships, ".$wpdb->prefix."terms as terms";

					$_where .= " AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id AND term_relationships.object_id=posts.ID AND taxonomy.term_id=terms.term_id ";

					// Search for genres assigned directly to the posts
					$_where .= "AND taxonomy.taxonomy='ms_genre' AND ";
					if( is_numeric( $genre ) )
					{
						$_where .= $wpdb->prepare("terms.term_id=%d", $genre);
					}
					else
					{
						$_where .= $wpdb->prepare("terms.slug=%s", $genre);
					}
				}

				if($artist !== 'all'){
					$_from .= ", ".$wpdb->prefix."term_taxonomy as taxonomy1, ".$wpdb->prefix."term_relationships as term_relationships1, ".$wpdb->prefix."terms as terms1";

					$_where .= " AND taxonomy1.term_taxonomy_id=term_relationships1.term_taxonomy_id AND term_relationships1.object_id=posts.ID AND taxonomy1.term_id=terms1.term_id ";

					// Search for artist assigned directly to the posts
					$_where .= "AND taxonomy1.taxonomy='ms_artist' AND ";
					if( is_numeric( $artist ) )
					{
						$_where .= $wpdb->prepare( "terms1.term_id=%d", $artist );
					}
					else
					{
						$_where .= $wpdb->prepare( "terms1.slug=%s", $artist );
					}
				}

				if($album !== 'all'){
					$_from .= ", ".$wpdb->prefix."term_taxonomy as taxonomy2, ".$wpdb->prefix."term_relationships as term_relationships2, ".$wpdb->prefix."terms as terms2";

					$_where .= " AND taxonomy2.term_taxonomy_id=term_relationships2.term_taxonomy_id AND term_relationships2.object_id=posts.ID AND taxonomy2.term_id=terms2.term_id ";

					// Search for albums assigned directly to the posts
					$_where .= "AND taxonomy2.taxonomy='ms_album' AND ";
					if( is_numeric( $album ) )
					{
						$_where .= $wpdb->prepare( "terms2.term_id=%d", $album );
					}
					else
					{
						$_where .= $wpdb->prepare( "terms2.slug=%s", $album );
					}
				}
				// End taxonomies
			}

			$_where .= " AND (";

			if($load == 'all' || $load == 'singles'){
				$_where .= "(post_type='ms_song' AND posts_data.as_single=1)";
			}
			$_where .= ")";


			// Create pagination section
			if($items_page_selector && $items_page){
				// Checking for page parameter or get page from session variables
				// Clear the page number if filtering option change
				if( isset($_REQUEST['filter_by_type']) || isset($_REQUEST['filter_by_genre']) || isset($_REQUEST['filter_by_artist']) ){
					$_SESSION[ $page_id ]['ms_page_number'] = 0;
				}
                if(isset($_GET['page_number'])){
					$_SESSION[ $page_id ]['ms_page_number'] = intval( $_GET['page_number'] );
				}
                if(!isset($_SESSION[ $page_id ]['ms_page_number'])){
					$_SESSION[ $page_id ]['ms_page_number'] = 0;
				}

				$_limit = "LIMIT ".($_SESSION[ $page_id ]['ms_page_number']*$items_page).", $items_page";

				// Create items section
				$query = $_select." ".$_from." ".$_where." ".$_order_by." ".$_limit;
				$results = $wpdb->get_results($query);

				// Get total records for pagination
				$query = "SELECT FOUND_ROWS()";
				$total = $wpdb->get_var($query);
				$total_pages = ceil($total/max($items_page,1));

				if( $total )
				{
					$min_in_page = ( $_SESSION[ $page_id ][ 'ms_page_number' ] - 1 ) * $items_page + $items_page + 1;
					$max_in_page = min( $_SESSION[ $page_id ][ 'ms_page_number' ] * $items_page + $items_page, $total );

					$items_summary = '<div class="music-store-filtering-result">'.$min_in_page.'-'.$max_in_page.' '.__( 'of', MS_TEXT_DOMAIN ).' '.$total.'</div>';
				}

				if($total_pages > 1){

					// Make page links
					$page_links .= "<DIV class='music-store-pagination'>";
					$page_href = '?'.((!empty($_SERVER['QUERY_STRING'])) ? preg_replace('/(&)?page_number=\d+/', '', $_SERVER['QUERY_STRING']).'&' : '');


					for($i=0, $h = $total_pages; $i < $h; $i++){
						if($_SESSION[ $page_id ]['ms_page_number'] == $i)
							$page_links .= "<span class='page-selected'>".($i+1)."</span> ";
						else
							$page_links .= "<a class='page-link' href='".esc_attr($page_href)."page_number=".$i."'>".($i+1)."</a> ";
					}
					$page_links .= "</DIV>";
				}
			}else{
				// Create items section
				$query = $_select." ".$_from." ".$_where." ".$_order_by." ".$_limit;
				$results = $wpdb->get_results($query);
			}


			$tpl = new music_store_tpleng(dirname(__FILE__).'/ms-templates/', 'comment');

			$width = 100/$columns;
			$music_store .= "<div class='music-store-items'>";
			$item_counter = 0;
			foreach($results as $result){
				$obj = new MSSong($result->ID, (array)$result);
				$music_store .= "<div style='width:{$width}%;' data-width='{$width}%' class='music-store-item'>".$obj->display_content('store', $tpl, 'return')."</div>";
				$item_counter++;
				if($item_counter % $columns == 0)
					$music_store .= "<div style='clear:both;'></div>";
			}
			$music_store .= "<div style='clear:both;'></div>";
			$music_store .= "</div>";
			$header .= "
						<form method='get'>
						<div class='music-store-header'>
						";

			foreach( $_GET as $var => $value )
            {
                if( !in_array( $var , array( 'filter_by_type', 'filter_by_genre', 'filter_by_artist', 'filter_by_album', 'page_number', 'ordering_by') ) )
                {
                    $header .= "<input type='hidden' name='".esc_attr($var)."' value='".esc_attr($value)."' />";
                }
            }

            // Create filter section
			if(
				$allow_filter_by_genre ||
				$allow_filter_by_artist ||
				$allow_filter_by_album ||
				!isset( $atts[ 'show_order_by' ] ) ||
				$atts[ 'show_order_by' ] * 1
			){
				$header .= "<div class='music-store-filters'>";
				if(
					$allow_filter_by_genre ||
					$allow_filter_by_artist ||
					$allow_filter_by_album
				){
					$header .= "<span>".__('Filter by: ', MS_TEXT_DOMAIN)."</span>";
				}
				if($allow_filter_by_genre){
					$header .= "<span><select id='filter_by_genre' name='filter_by_genre' onchange='this.form.submit();'>
							<option value='all'>".__('All genres', MS_TEXT_DOMAIN)."</option>
							";
					$genres = get_terms("ms_genre");
					foreach($genres as $genre_item){
						$header .= "<option value='".esc_attr($genre_item->slug)."' ".(($genre == $genre_item->slug || $genre == $genre_item->term_id) ? "SELECTED" : "").">".music_store_strip_tags($genre_item->name,true)."</option>";
					}
					$header .= "</select></span>";
				}

                if($allow_filter_by_album){
					$header .= "<span><select id='filter_by_album' name='filter_by_album' onchange='this.form.submit();'>
							<option value='all'>".__('All albums', MS_TEXT_DOMAIN)."</option>
							";
					$albums = get_terms("ms_album");
					foreach($albums as $album_item){
						$header .= "<option value='".esc_attr($album_item->slug)."' ".(($album == $album_item->slug || $album == $album_item->term_id ) ? "SELECTED" : "").">".music_store_strip_tags($album_item->name,true)."</option>";
					}
					$header .= "</select></span>";
				}

				if($allow_filter_by_artist){
					$header .= "<span><select id='filter_by_artist' name='filter_by_artist' onchange='this.form.submit();'>
							<option value='all'>".__('All artists', MS_TEXT_DOMAIN)."</option>
							";
					$artists = get_terms("ms_artist");
					foreach($artists as $artist_item){
						$header .= "<option value='".esc_attr($artist_item->slug)."' ".(($artist == $artist_item->slug || $artist == $artist_item->term_id ) ? "SELECTED" : "").">".music_store_strip_tags($artist_item->name,true)."</option>";
					}
					$header .= "</select></span>";
				}
				$header .="</div>";
				// Create order filter
				if( !isset( $atts[ 'show_order_by' ] ) || $atts[ 'show_order_by' ] * 1 )
				{
					$header .= "<div class='music-store-ordering'>".
									__('Order by: ', MS_TEXT_DOMAIN).
									"<select id='ordering_by' name='ordering_by' onchange='this.form.submit();'>
										<option value='post_date' ".(($_SESSION[ $page_id ]['ms_ordering'] == 'post_date') ? "SELECTED" : "").">".__('Date', MS_TEXT_DOMAIN)."</option>
										<option value='post_title' ".(($_SESSION[ $page_id ]['ms_ordering'] == 'post_title') ? "SELECTED" : "").">".__('Title', MS_TEXT_DOMAIN)."</option>
										<option value='plays' ".(($_SESSION[ $page_id ]['ms_ordering'] == 'plays') ? "SELECTED" : "").">".__('Popularity', MS_TEXT_DOMAIN)."</option>
										<option value='price' ".(($_SESSION[ $page_id ]['ms_ordering'] == 'price') ? "SELECTED" : "").">".__('Price', MS_TEXT_DOMAIN)."</option>
									</select>
								</div>";
				}

			}
			$header .= "<div style='clear:both;'></div>
						</div>
						</form>
						";
			return $header.$items_summary.$music_store.$page_links;
		} // End load_store

/** MODIFY CONTENT OF POSTS LOADED **/

		/*
		* Load the music store templates for songs display
		*/
		function load_templates(){
            add_filter('the_content', array(&$this, 'display_content'), 1 );
		} // End load_templates

		/**
		* Display content of songs through templates
		*/
		function display_content($content){
			global $post;
			if(
				/* in_the_loop() &&  */
				$post &&
				$post->post_type == 'ms_song'
			)
			{
				remove_filter( 'the_content', 'wpautop' );
                remove_filter( 'the_excerpt', 'wpautop' );
                remove_filter( 'comment_text', 'wpautop', 30 );
                $tpl = new music_store_tpleng(dirname(__FILE__).'/ms-templates/', 'comment');
				$song = new MSSong($post->ID);
				return $song->display_content(((is_singular()) ? 'single' : 'multiple'), $tpl, 'return');
			}else{
				return $content;
			}
		} // End display_content


		/**
		* Set a media button for music store insertion
		*/
		function set_music_store_button(){
			global $post;

			if(isset($post) && $post->post_type != 'ms_song')
			print '<a href="javascript:open_insertion_music_store_window();" title="'.esc_attr(__('Insert Music Store')).'"><img src="'.esc_url(MS_CORE_IMAGES_URL.'/music-store-icon.png').'" alt="'.esc_attr(__('Insert Music Store')).'" /></a>';
		} // End set_music_store_button


		/**
		*	Check for post to delete and remove the metadata saved on additional metadata tables
		*/
		function delete_post($pid){
			global $wpdb;
			if($wpdb->get_var($wpdb->prepare("SELECT id FROM ".$wpdb->prefix.MSDB_POST_DATA." WHERE id=%d;", $pid))){
				return  $wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix.MSDB_POST_DATA." WHERE id=%d;",$pid));
			}
			return false;
		} // End delete_post

		/******* SEARCHING METHODS *******/

		function custom_search_where($where)
		{
			global $wpdb;
			if( is_search() && get_search_query() )
			{
				$where .= " OR ((t.name LIKE '%".get_search_query()."%' OR t.slug LIKE '%".get_search_query()."%') AND tt.taxonomy IN ('ms_artist', 'ms_album', 'ms_genre') AND {$wpdb->posts}.post_status = 'publish')";
			}
			return $where;
		}

		function custom_search_join($join)
		{
			global $wpdb;
			if( is_search() && get_search_query() )
			{
				$join .= " LEFT JOIN ({$wpdb->term_relationships} tr INNER JOIN ({$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id) ON tr.term_taxonomy_id = tt.term_taxonomy_id) ON {$wpdb->posts}.ID = tr.object_id ";
			}
			return $join;
		}

		function custom_search_groupby($groupby)
		{
			global $wpdb;

			// we need to group on post ID
			$groupby_id = "{$wpdb->posts}.ID";
			if( !is_search() || strpos( $groupby, $groupby_id ) !== false || !get_search_query() )
			{
				return $groupby;
			}
			// groupby was empty, use ours
			if( !strlen( trim( $groupby ) ) )
			{
				return $groupby_id;
			}
			// wasn't empty, append ours
			return $groupby.", ".$groupby_id;
		}

	} // End MusicStore class

	// Initialize MusicStore class
	@session_start();
	$GLOBALS['music_store'] = new MusicStore;

	register_activation_hook( __FILE__, array( &$GLOBALS[ 'music_store' ], 'register' ) );
	add_action( 'wpmu_new_blog', array( &$GLOBALS[ 'music_store' ], 'install_new_blog' ), 10, 6 );

} // Class exists check


?>