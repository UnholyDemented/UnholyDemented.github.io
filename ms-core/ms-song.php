<?php
if( !defined( 'MS_H_URL' ) ) { echo 'Direct access not allowed.';  exit; }
if(!class_exists('MSSong')){
	class MSSong{
		/*
		* @var integer
		*/
		private $id;

		/*
		* @var object
		*/
		private $song_data 	= array();
		private $post_data 	= array();
		private $artist = array();
		private $album 	= array();
		private $genre	= array();

		/**
		* MSSong constructor
		*
		* @access public
		* @return void
		*/
		function __construct($id, $data = array() ){
			global $wpdb;

			$this->id = $id;

			if( empty( $data ) ){
				// Read general data
				$data = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix.MSDB_POST_DATA." WHERE id=%d", array($id)));
				if($data) $this->song_data = (array)$data;
				$this->post_data = get_post($id, 'ARRAY_A');
			}else{
				$this->song_data = $data;
				$this->post_data = $data;
			}

			// Read artist list
			$this->artist = (array)wp_get_object_terms($id, 'ms_artist', array( 'orderby' => 'term_order' ));

			// Read album list
			$this->album = (array)wp_get_object_terms($id, 'ms_album', array( 'orderby' => 'term_order' ));

			// Read associated genres
			$this->genre = (array)wp_get_object_terms($id, 'ms_genre');

		} // End __construct

		function __get($name){
			switch($name){
				case 'genre':
					return music_store_strip_tags($this->genre);
				break;
				case 'artist':
					return music_store_strip_tags($this->artist);
				break;
				case 'album':
					return music_store_strip_tags($this->album);
				break;
				case 'cover':
				case 'file':
				case 'demo':
					if(isset($this->song_data[$name])){
						return $this->get_file_url($this->song_data[$name]);
					}else{
						return null;
					}
				break;
				default:
					if(isset($this->song_data[$name])){
						if( $name == 'post_title' && empty($this->song_data[$name]) ) return $this->id;
						return music_store_strip_tags($this->song_data[$name]);
					}elseif(isset($this->post_data[$name])){
						if( $name == 'post_title' && empty($this->post_data[$name]) ) return $this->id;
						return music_store_strip_tags($this->post_data[$name]);
					}else{
						return null;
					}
			} // End switch
		} // End __get

		function __set($name, $value){
			global $wpdb;

			if(
				isset($this->song_data[$name]) &&
				$wpdb->update(
					$wpdb->prefix.MSDB_POST_DATA,
					array($name => $value),
					array('id' => $this->id)
				)
			){
				$this->song_data[$name] = $value;
			}
		} // End __set

		function __isset($name){
			return isset($this->song_data[$name]) || isset($this->post_data[$name]);
		} // End __isset

		/*
		* Display content
		*/
		function get_file_url($url){
			if(preg_match('/attachment_id=(\d+)/', $url, $matches)){
				return wp_get_attachment_url( $matches[1]);
			}
			return $url;
		} // End get_file_url

		function display_content($mode, $tpl_engine, $output='echo'){
			global $music_store_settings;

			$currency_symbol = $music_store_settings[ 'ms_paypal_currency_symbol' ];
            $ms_main_page = esc_url($music_store_settings[ 'ms_main_page' ]);
            $url_symbol = ( strpos( $ms_main_page, '?' ) === false ) ? '?' : '&' ;

			$song_arr = array(
				'title' => $this->post_title,
				'link'	=> esc_url(get_permalink($this->id)),
				'popularity' => @intval($this->plays),
				'cover' => null,
                'social' => null,
				'facebook_app_id' => null,
				'price' => null,
				'year' => null,
				'isrc' => null,
				'has_albums' => null,
				'has_artists' => null,
				'has_genres' => null,
				'demo' => null,
				'salesbutton' => '',

                // Labels
                'albums_label' => __( 'Album(s)', MS_TEXT_DOMAIN ),
                'genres_label' => __( 'Genre(s)', MS_TEXT_DOMAIN ),
                'duration_label' => __( 'Duration', MS_TEXT_DOMAIN ),
                'year_label' => __( 'Year', MS_TEXT_DOMAIN ),
                'isrc_label' => __( 'ISRC', MS_TEXT_DOMAIN ),
                'description_label' => __( 'Description', MS_TEXT_DOMAIN ),
                'more_label' => __( 'More Info', MS_TEXT_DOMAIN ),
                'store_page_label' => __( 'Go to the store page', MS_TEXT_DOMAIN ),
                'get_back_label' => __( 'Get back', MS_TEXT_DOMAIN ),
                'popularity_label' => __( 'popularity', MS_TEXT_DOMAIN ),
                'price_label' => __( 'Price', MS_TEXT_DOMAIN )
			);

            if(!empty($this->cover)){
                $song_arr['cover'] = esc_url( $this->cover );
            }

            if($this->time) $song_arr['time'] = strip_tags(html_entity_decode($this->time));
			if($this->year) $song_arr['year'] = @intval($this->year);
			if($this->isrc) $song_arr['isrc'] = strip_tags(html_entity_decode($this->isrc));
			if($this->info) $song_arr['info'] = esc_url($this->info);

            if($music_store_settings[ 'ms_social_buttons' ]){
                $song_arr['social'] = $song_arr[ 'link' ];
            }

			if(!empty( $music_store_settings[ 'ms_facebook_app_id' ] ))
			{
				$song_arr['facebook_app_id'] = $music_store_settings[ 'ms_facebook_app_id' ];
			}

            if(count($this->artist)){
				$song_arr['has_artists'] = true;
				$artists = array();
				foreach($this->artist as $artist){
                    $link = get_term_link($artist);
                    if( !empty( $ms_main_page ) )
                    {
                        $link = $ms_main_page.$url_symbol.'filter_by_artist='.$artist->slug;
                    }
					$artists[] = array('data' => '<a href="'.$link.'">'.music_store_strip_tags($artist->name).'</a>');
				}
				$tpl_engine->set_loop('artists', $artists);
			}

            if($music_store_settings[ 'ms_paypal_enabled' ] && $music_store_settings[ 'ms_paypal_email' ]){
                $paypal_enabled = true;
            }else{
                $paypal_enabled = false;
            }

			if(!empty($this->file)){
                if($music_store_settings[ 'ms_paypal_enabled' ] && $music_store_settings[ 'ms_paypal_email' ] && !empty($this->price)){
					$song_arr['price'] = ((!empty($currency_symbol)) ? $currency_symbol.sprintf("%.2f", $this->price) : sprintf("%.2f", $this->price).$music_store_settings[ 'ms_paypal_currency' ]);
					if(
						$music_store_settings[ 'ms_buy_button_for_registered_only' ] == false ||
						is_user_logged_in()
					)
					{
						$paypal_button = MS_URL.'/paypal_buttons/'.$music_store_settings[ 'ms_paypal_button' ];
						$song_arr['salesbutton'] = '<form action="'.esc_url(MS_H_URL).'" method="post"><input type="hidden" name="ms-action" value="buynow" /><input type="hidden" name="ms_product_type" value="single" /><input type="hidden" name="ms_product_id" value="'.esc_attr($this->id).'" /><input type="image" src="'.esc_url($paypal_button).'" style="padding-top:5px;" /></form>';
					}
                }elseif(
					$music_store_settings[ 'ms_download_link_for_registered_only' ] == false ||
					is_user_logged_in()
				)
				{
                    $song_arr['salesbutton']  = '<a href="'.esc_url($this->file).'" target="_blank" data-id="'.esc_attr($this->id).'" class="ms-download-link">'.__('Download', MS_TEXT_DOMAIN).'</a>'.( ( !empty( $music_store_settings[ 'ms_license_for_free' ] ) ) ? '|<a href="'.esc_url($music_store_settings[ 'ms_license_for_free' ]).'" target="_blank">'.__( 'License',  MS_TEXT_DOMAIN ).'</a>' : '' );
                }
			}

            $demo = $this->demo;
			if($demo)
			{
                $song_arr['demo'] = '<audio preload="none" data-product="'.$this->ID.'" ><source src="'.$demo.'" type="audio/'.music_store_get_type( $demo ).'" /></audio>';
			}else{
				$song_arr['demo'] = '';
			}

			if($mode == 'store' || $mode == 'multiple'){
				if($mode == 'store')
					$tpl_engine->set_file('song', 'song.tpl.html');
				else
					$tpl_engine->set_file('song', 'song_multiple.tpl.html');

				$tpl_engine->set_var('song', $song_arr);
            }elseif($mode == 'single'){
				$this->plays += 1;
				$tpl_engine->set_file('song', 'song_single.tpl.html');
				if($ms_main_page){
					$song_arr['store_page'] = $ms_main_page;
				}

				$demo = $this->demo;
				$song_arr['demo'] 			= ($demo) ? '<audio style="width:100%;"  data-product="'.$this->ID.'" ><source src="'.$demo.'" type="audio/'.music_store_get_type( $demo ).'" /></audio>' : '';

				if(strlen($this->post_content)){
					$song_arr['description'] 	= '<p>'.preg_replace('/[\n\r]+/', '</p><p>', $this->post_content).'</p>';
				}

				if(count($this->genre)){
					$song_arr['has_genres'] = true;
					$genres = array();
					foreach($this->genre as $genre){
                        $link = get_term_link($genre);
                        if( !empty( $ms_main_page ) )
                        {
                            $link = $ms_main_page.$url_symbol.'filter_by_genre='.$genre->slug;
                        }
                        $genres[] = array('data' => '<a href="'.$link.'">'.music_store_strip_tags($genre->name).'</a>');
					}
					$tpl_engine->set_loop('genres', $genres);
				}

				if(count($this->album)){
					$song_arr['has_albums'] = true;
					$albums = array();
					foreach($this->album as $album){
                        $link = get_term_link($album);
                        if( !empty( $ms_main_page ) )
                        {
                            $link = $ms_main_page.$url_symbol.'filter_by_album='.$album->slug;
                        }
						$albums[] = array('data' => '<a href="'.$link.'">'.music_store_strip_tags($album->name).'</a>');
					}
					$tpl_engine->set_loop('albums', $albums);
				}

				$tpl_engine->set_var('song', $song_arr);
			}

			return $tpl_engine->parse('song', $output);
		} // End display

		/*
		* Class method print_metabox, for metabox generation print
		*
		* @return void
		*/
		public static function print_metabox(){
			global $wpdb, $post, $music_store_settings;

			$query = "SELECT * FROM ".$wpdb->prefix.MSDB_POST_DATA." as data WHERE data.id = {$post->ID};";
			$data = $wpdb->get_row($query);

			$artist_post_list = wp_get_object_terms($post->ID, 'ms_artist', array( 'orderby' => 'term_order' ));
			$artist_list = get_terms('ms_artist', array( 'hide_empty' => 0, 'orderby' => 'name' ));

			$album_post_list = wp_get_object_terms($post->ID, 'ms_album', array( 'orderby' => 'term_order' ));
			$album_list = get_terms('ms_album', array( 'hide_empty' => 0, 'orderby' => 'name' ));

			wp_nonce_field( plugin_basename( __FILE__ ), 'ms_song_box_content_nonce' );
			$currency = $music_store_settings[ 'ms_paypal_currency' ];
			if( !empty( $_SESSION[ 'ms_errors' ] ) )
			{
				echo '<div class="music-store-error-mssg">'.implode( '<br>', music_store_strip_tags($_SESSION[ 'ms_errors' ]) ).'</div>';
				unset( $_SESSION[ 'ms_errors' ] );
			}
			echo '
				<table class="form-table product-data">
					<tr>
						<td valign="top">
							'.__('Sales price:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_price" id="ms_price" value="'.(($data && $data->price) ? esc_attr(sprintf("%.2f", $data->price)) : '').'" />
							'.(($currency) ? $currency : '').'
                            <span class="ms_more_info_hndl" style="margin-left: 10px;"><a href="javascript:void(0);" onclick="ms_display_more_info( this );">[ + more information]</a></span>
                            <div class="ms_more_info">
                                <p>If leave empty the product\'s prices (standard and exclusive prices), the Music Store assumes the product will be distributed for free, and displays a download link in place of the button for purchasing</p>
                                <a href="javascript:void(0)" onclick="ms_hide_more_info( this );">[ + less information]</a>
                            </div>
						</td>
					</tr>
					<tr>
						<td valign="top">
							'.__('Sales price (Exclusively):', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" disabled />
							'.(($currency) ? $currency : '').'
							<span class="ms_more_info_hndl" style="margin-left: 10px;"><a href="javascript:void(0);" onclick="ms_display_more_info( this );">[ + more information]</a></span>
                            <div class="ms_more_info">
                                <p>Allows purchase the product exclusively, removing the product from the store</p>
                                <a href="javascript:void(0)" onclick="ms_hide_more_info( this );">[ + less information]</a>
                            </div>
							<br /><em style="color:#FF0000;">'.__('The exclusive sales are available only in the commercial version of the plugin', MS_TEXT_DOMAIN).'</em>
						</td>
					</tr>
					<tr>
					<tr>
						<td>
							'.__('Sell as a single:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="checkbox" name="ms_as_single" id="ms_as_price" CHECKED DISABLED /> <em style="color:#FF0000;">The commercial version of the plugin allows the sale of audio as a single, or only as part of a collection</em>
						</td>
					</tr>
					<tr>
						<td>
							'.__('Audio file for sale:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_file_path" class="file_path" id="ms_file_path" value="'.(($data && $data->file) ? esc_attr($data->file) : '').'" placeholder="'.__('File path/URL', MS_TEXT_DOMAIN).'" /> <input type="button" class="button_for_upload button" value="'.__('Upload a file', MS_TEXT_DOMAIN).'" />
						</td>
					</tr>
					<tr>
						<td>
							'.__('Audio file for demo:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_demo_file_path" id="ms_demo_file_path" class="file_path"  value="'.(($data && $data->demo) ? esc_attr($data->demo) : '').'" placeholder="'.esc_attr(__('File path/URL', MS_TEXT_DOMAIN)).'" /> <input type="button" class="button_for_upload button" value="'.esc_attr(__('Upload a file', MS_TEXT_DOMAIN)).'" /><br />
							<input type="checkbox" name="ms_protect" id="ms_protect" disabled />
							'.__('Protect the file', MS_TEXT_DOMAIN).'<em style="color:#FF0000;">'.__('The protection of audio files is only available for commercial version of plugin', MS_TEXT_DOMAIN).'</em><br><br>
							<em>'.__('For MIDI files, the protection option is not available, the audio file would be played completely.').'</em>
						</td>
					</tr>
					<tr>
						<td valign="top">
							'.__('Artist:', MS_TEXT_DOMAIN).'
						</td>
						<td><ul id="ms_artist_list">';

						if($artist_post_list){
							foreach($artist_post_list as $artist){
								echo '<li class="ms-property-container"><input type="hidden" name="ms_artist[]" value="'.esc_attr($artist->name).'" /><input type="button" onclick="ms_remove(this);" class="button" value="'.esc_attr($artist->name).' [x]"></li>';
							}

						}
						echo '</ul><div style="clear:both;"><select onchange="ms_select_element(this, \'ms_artist_list\', \'ms_artist\');"><option value="none">'.__('Select an Artist', MS_TEXT_DOMAIN).'</option>';
						if($artist_list){
							foreach($artist_list as $artist){
								echo '<option value="'.esc_attr($artist->name).'">'.music_store_strip_tags($artist->name,true).'</option>';
							}
						}
						echo '
								 </select>
								 <input type="text" id="new_artist" placeholder="'.esc_attr(__('Enter a new artist', MS_TEXT_DOMAIN)).'">
								 <input type="button" value="'.esc_attr(__('Add artist', MS_TEXT_DOMAIN)).'" class="button" onclick="ms_add_element(\'new_artist\', \'ms_artist_list\', \'ms_artist_new\');"/><br />
								 <span class="ms-comment">'.__('Select an Artist from the list or enter new one', MS_TEXT_DOMAIN).'</span>
							</div>
						</td>
					</tr>
					<tr>
						<td valign="top" style="white-space:nowrap;">
							'.__('Album including the song:', MS_TEXT_DOMAIN).'
						</td>
						<td style="width:100%;"><ul id="ms_album_list">';
						if($album_post_list){
							foreach($album_post_list as $album){
								echo '<li class="ms-property-container"><input type="hidden" name="ms_album[]" value="'.esc_attr($album->name).'" /><input type="button" onclick="ms_remove(this);" class="button" value="'.esc_attr($album->name).' [x]"></li>';
							}

						}
						echo '</ul><div style="clear:both;"><select onchange="ms_select_element(this, \'ms_album_list\', \'ms_album\');"><option value="none">'.__('Select an Album', MS_TEXT_DOMAIN).'</option>';

						if($album_list){
							foreach($album_list as $album){
								echo '<option value="'.esc_attr($album->name).'">'.music_store_strip_tags($album->name,true).'</option>';
							}
						}
						echo '
								 </select>
								 <input type="text" id="new_album" placeholder="'.esc_attr(__('Enter a new album', MS_TEXT_DOMAIN)).'">
								 <input type="button" value="'.esc_attr(__('Add album', MS_TEXT_DOMAIN)).'" class="button" onclick="ms_add_element(\'new_album\', \'ms_album_list\', \'ms_album_new\');" /><br />
								 <span class="ms-comment">'.__('Select an Album from the list or enter new one', MS_TEXT_DOMAIN).'</span>
							</div>
						</td>
					</tr>
					<tr>
						<td>
							'.__('Cover:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_cover" class="file_path" id="ms_cover" value="'.(($data && $data->cover) ? esc_attr($data->cover) : '').'" placeholder="'.esc_attr(__('File path/URL', MS_TEXT_DOMAIN)).'" /> <input type="button" class="button_for_upload button" value="'.esc_attr(__('Upload a file', MS_TEXT_DOMAIN)).'" />
						</td>
					</tr>
					<tr>
						<td>
							'.__('Duration:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_time" id="ms_time" value="'.(($data && $data->time) ? esc_attr($data->time) : '').'" /> <span class="ms-comment">'.__('For example 00:00', MS_TEXT_DOMAIN).'</span>
						</td>
					</tr>
					<tr>
						<td>
							'.__('Publication Year:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_year" id="ms_year" value="'.(($data && $data->year) ? esc_attr($data->year) : '').'" /> <span class="ms-comment">'.__('For example 1999', MS_TEXT_DOMAIN).'</span>
						</td>
					</tr>
					<tr>
						<td>
							'.__('ISRC:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_isrc" id="ms_isrc" value="'.(($data && $data->isrc) ? esc_attr($data->isrc) : '').'" /> <span class="ms-comment">'.__('Format: CC-XXX-YY-NNNNN', MS_TEXT_DOMAIN).'</span>
						</td>
					</tr>
					<tr>
						<td>
							'.__('Additional information:', MS_TEXT_DOMAIN).'
						</td>
						<td>
							<input type="text" name="ms_info" id="ms_info" value="'.(($data && $data->info) ? esc_attr($data->info) : '').'" placeholder="'.esc_attr(__('Page URL', MS_TEXT_DOMAIN)).'" /> <span class="ms-comment">'.__('Different webpage with additional information', MS_TEXT_DOMAIN).'</span>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<p style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
								To get commercial version of Music Store, <a href="http://musicstore.dwbooster.com" target="_blank">CLICK HERE</a><br />
								For reporting an issue or to request a customization, <a href="http://musicstore.dwbooster.com/contact-us" target="_blank">CLICK HERE</a>
							</p>
						</td>
					</tr>
				</table>
			';
		} // End print_metabox

        public static function print_discount_metabox(){
			global $music_store_settings;

            $currency = $music_store_settings[ 'ms_paypal_currency' ];
?>
            <em style="color:#FF0000;"><?php _e('The discounts are only available for commercial version of plugin'); ?></em>
            <h4><?php _e('Scheduled Discounts', MS_TEXT_DOMAIN);?></h4>
            <table class="form-table ms_discount_table" style="border:1px dotted #dfdfdf;">
                <tr>
                    <td style="font-weight:bold;"><?php _e('New price in '.$currency, MS_TEXT_DOMAIN); ?></td>
                    <td style="font-weight:bold;"><?php _e('Valid from dd/mm/yyyy', MS_TEXT_DOMAIN); ?></td>
                    <td style="font-weight:bold;"><?php _e('Valid to dd/mm/yyyy', MS_TEXT_DOMAIN); ?></td>
                    <td style="font-weight:bold;"><?php _e('Promotional text', MS_TEXT_DOMAIN); ?></td>
                    <td style="font-weight:bold;"><?php _e('Status', MS_TEXT_DOMAIN); ?></td>
                    <td></td>
                </tr>
            </table>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('New price (*)', MS_TEXT_DOMAIN); ?></th>
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
                <tr><td colspan="2"><input type="button" class="button" value="<?php print esc_attr(__('Add/Update Discount')); ?>" DISABLED /></td></tr>
            </table>
<?php
        } // End print_discount_metabox

		/*
		* Save the song data
		*
		* @access public
		* @return void
		*/
		public static function save_data(){
			global $wpdb, $post, $ms_errors;

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

			if ( !wp_verify_nonce( $_POST['ms_song_box_content_nonce'], plugin_basename( __FILE__ ) ) )
			return;
			if ( 'page' == $_POST['post_type'] ) {
				if ( !current_user_can( 'edit_page', $post_id ) )
				return;
			} else {
				if ( !current_user_can( 'edit_post', $post_id ) )
				return;
			}

			$file_path = esc_url_raw(trim(stripcslashes($_POST['ms_file_path'])));
			$demo_file_path = esc_url_raw(trim(stripcslashes($_POST['ms_demo_file_path'])));
			$cover = esc_url_raw(trim(stripcslashes($_POST['ms_cover'])));

			if( !empty( $file_path ) && !music_store_mime_type_accepted( $file_path ) ){
				music_store_setError( __( 'Invalid file type for selling.', MS_TEXT_DOMAIN ) );
				$file_path = '';
			}

			if( !empty( $demo_file_path ) && !music_store_mime_type_accepted( $demo_file_path ) ){
				music_store_setError( __( 'Invalid file type for demo.', MS_TEXT_DOMAIN ) );
				$demo_file_path = '';
			}

			if( !empty( $cover ) && !music_store_mime_type_accepted( $cover ) ){
				music_store_setError( __( 'Invalid file type for cover.', MS_TEXT_DOMAIN ) );
				$cover = '';
			}

			$_SESSION[ 'ms_errors' ] = $ms_errors;

			$id = $post->ID;
			$data = array(
						'time'  	=> strip_tags(html_entity_decode(stripcslashes($_POST['ms_time']))),
						'file'  	=> $file_path,
						'demo'  	=> $demo_file_path,
						'protect' 	=> (isset($_POST['ms_protect'])) ? 1 : 0,
						'as_single' => 1,
						'info' 		=> esc_url_raw(stripcslashes($_POST['ms_info'])),
						'cover' 	=> $cover,
						'price' 	=> @floatval($_POST['ms_price']),
						'year'      => @intval($_POST['ms_year']),
						'isrc'      => $_POST['ms_isrc']
					);
			$format = array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s');
			$table = $wpdb->prefix.MSDB_POST_DATA;
			if(0 < $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE id=$id;") ){
				// Set an update query
				$wpdb->update(
					$table,
					$data,
					array('id'=>$id),
					$format,
					array('%d')
				);

			}else{
				// Set an insert query
				$data['id'] = $id;
				$wpdb->insert(
					$table,
					$data,
					$format
				);

			}

			// Clear the artist and album lists and then set the new ones
			wp_set_object_terms($id, null, 'ms_artist');
			wp_set_object_terms($id, null, 'ms_album');

			// Set the artists list
			if(isset($_POST['ms_artist'])){
				if(is_array( $_POST['ms_artist'] ))
				{
					$term_order = 0;
					foreach( $_POST['ms_artist'] as $artist )
					{
						$term_taxonomy_id = wp_set_object_terms($id, stripcslashes($artist), 'ms_artist', true);
						$wpdb->update(
							$wpdb->term_relationships,
							array(
								'term_order' => $term_order
							),
							array(
								'term_taxonomy_id' => $term_taxonomy_id[0],
								'object_id'		   => $id
							),
							array( '%d' ),
							array( '%d', '%d' )
						);
						$term_order++;
					}
				}
				else
					wp_set_object_terms($id, stripcslashes($_POST['ms_artist']), 'ms_artist', true);
			}

			if(isset($_POST['ms_artist_new'])){
				if(is_array($_POST['ms_artist_new']))
					$_POST['ms_artist_new'] = array_map('stripcslashes',$_POST['ms_artist_new']);
				else $_POST['ms_artist_new'] = stripcslashes($_POST['ms_artist_new']);
				wp_set_object_terms($id, $_POST['ms_artist_new'], 'ms_artist', true);
			}

			// Set the album list
			if(isset($_POST['ms_album'])){
				$term_order = 0;
				foreach( $_POST['ms_album'] as $album )
				{
					$term_taxonomy_id = wp_set_object_terms($id, stripcslashes($album), 'ms_album', true);
					$wpdb->update(
						$wpdb->term_relationships,
						array(
							'term_order' => $term_order
						),
						array(
							'term_taxonomy_id' => $term_taxonomy_id[0],
							'object_id'		   => $id
						),
						array( '%d' ),
						array( '%d', '%d' )
					);
					$term_order++;
				}
			}

			if(isset($_POST['ms_album_new'])){
				if(is_array($_POST['ms_album_new']))
					$_POST['ms_album_new'] = array_map('stripcslashes',$_POST['ms_album_new']);
				else $_POST['ms_album_new'] = stripcslashes($_POST['ms_album_new']);
				wp_set_object_terms($id, $_POST['ms_album_new'], 'ms_album', true);
			}

		} // End save_data

		/*
		* Create the list of properties to display of songs
		* @param array
		* @return array
		*/
		public static function columns($columns){
			return array(
				'cb'	 => '<input type="checkbox" />',
				'id'	 => __( 'Song Id', MS_TEXT_DOMAIN),
				'title'	 => __( 'Song Name', MS_TEXT_DOMAIN),
				'artist' => __( 'Artists', MS_TEXT_DOMAIN),
				'album'  => __( 'Albums', MS_TEXT_DOMAIN),
				'genre'  => __( 'Genres', MS_TEXT_DOMAIN),
				'plays'  => __( 'Plays', MS_TEXT_DOMAIN),
				'purchases' => __('Purchases', MS_TEXT_DOMAIN),
				'date'	 => __( 'Date', MS_TEXT_DOMAIN)
		   );
		} // End columns

		/*
		* Extrat the songs data for song list
		*/
		public static function columns_data($column){
			global $post;
			$obj = new MSSong($post->ID);

			switch ($column){
				case "artist":
					echo music_store_extract_attr_as_str($obj->artist, 'name', ', ');
				break;
				case "id":
					echo $post->ID;
				break;
				case "album":
					echo music_store_extract_attr_as_str($obj->album, 'name', ', ');
				break;
				case "genre":
					echo music_store_extract_attr_as_str($obj->genre, 'name', ', ');
				break;
				case "plays":
					echo $obj->plays;
				break;
				case "purchases":
					echo $obj->purchases;
				break;
			} // End switch
		} // End columns_data

	}// End MSSong class
} // Class exists check

?>