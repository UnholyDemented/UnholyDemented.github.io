<?php
	if( !defined( 'MS_H_URL' ) ) { echo 'Direct access not allowed.';  exit; }
    error_reporting( E_ERROR | E_PARSE );

    if (!function_exists('ms_mime_content_type')) {
		function ms_mime_content_type($filename) {
			$idx = strtolower(end( explode( '.', $filename )) );
			$mimet = array(	'ai' =>'application/postscript',
				'3gp' =>'audio/3gpp',
				'flv' =>'video/x-flv',
				'aif' =>'audio/x-aiff',
				'aifc' =>'audio/x-aiff',
				'aiff' =>'audio/x-aiff',
				'asc' =>'text/plain',
				'atom' =>'application/atom+xml',
				'avi' =>'video/x-msvideo',
				'bcpio' =>'application/x-bcpio',
				'bmp' =>'image/bmp',
				'cdf' =>'application/x-netcdf',
				'cgm' =>'image/cgm',
				'cpio' =>'application/x-cpio',
				'cpt' =>'application/mac-compactpro',
				'crl' =>'application/x-pkcs7-crl',
				'crt' =>'application/x-x509-ca-cert',
				'csh' =>'application/x-csh',
				'css' =>'text/css',
				'dcr' =>'application/x-director',
				'dir' =>'application/x-director',
				'djv' =>'image/vnd.djvu',
				'djvu' =>'image/vnd.djvu',
				'doc' =>'application/msword',
				'dtd' =>'application/xml-dtd',
				'dvi' =>'application/x-dvi',
				'dxr' =>'application/x-director',
				'eps' =>'application/postscript',
				'etx' =>'text/x-setext',
				'ez' =>'application/andrew-inset',
				'gif' =>'image/gif',
				'gram' =>'application/srgs',
				'grxml' =>'application/srgs+xml',
				'gtar' =>'application/x-gtar',
				'hdf' =>'application/x-hdf',
				'hqx' =>'application/mac-binhex40',
				'html' =>'text/html',
				'html' =>'text/html',
				'ice' =>'x-conference/x-cooltalk',
				'ico' =>'image/x-icon',
				'ics' =>'text/calendar',
				'ief' =>'image/ief',
				'ifb' =>'text/calendar',
				'iges' =>'model/iges',
				'igs' =>'model/iges',
				'jpe' =>'image/jpeg',
				'jpeg' =>'image/jpeg',
				'jpg' =>'image/jpeg',
				'js' =>'application/x-javascript',
				'kar' =>'audio/midi',
				'latex' =>'application/x-latex',
				'm3u' =>'audio/x-mpegurl',
				'man' =>'application/x-troff-man',
				'mathml' =>'application/mathml+xml',
				'me' =>'application/x-troff-me',
				'mesh' =>'model/mesh',
				'm4a' =>'audio/x-m4a',
				'mid' =>'audio/midi',
				'midi' =>'audio/midi',
				'mif' =>'application/vnd.mif',
				'mov' =>'video/quicktime',
				'movie' =>'video/x-sgi-movie',
				'mp2' =>'audio/mpeg',
				'mp3' =>'audio/mpeg',
				'mp4' =>'video/mp4',
				'm4v' =>'video/x-m4v',
				'mpe' =>'video/mpeg',
				'mpeg' =>'video/mpeg',
				'mpg' =>'video/mpeg',
				'mpga' =>'audio/mpeg',
				'ms' =>'application/x-troff-ms',
				'msh' =>'model/mesh',
				'mxu m4u' =>'video/vnd.mpegurl',
				'nc' =>'application/x-netcdf',
				'oda' =>'application/oda',
				'ogg' =>'application/ogg',
				'pbm' =>'image/x-portable-bitmap',
				'pdb' =>'chemical/x-pdb',
				'pdf' =>'application/pdf',
				'pgm' =>'image/x-portable-graymap',
				'pgn' =>'application/x-chess-pgn',
				'php' =>'application/x-httpd-php',
				'php4' =>'application/x-httpd-php',
				'php3' =>'application/x-httpd-php',
				'phtml' =>'application/x-httpd-php',
				'phps' =>'application/x-httpd-php-source',
				'png' =>'image/png',
				'pnm' =>'image/x-portable-anymap',
				'ppm' =>'image/x-portable-pixmap',
				'ppt' =>'application/vnd.ms-powerpoint',
				'ps' =>'application/postscript',
				'qt' =>'video/quicktime',
				'ra' =>'audio/x-pn-realaudio',
				'ram' =>'audio/x-pn-realaudio',
				'ras' =>'image/x-cmu-raster',
				'rdf' =>'application/rdf+xml',
				'rgb' =>'image/x-rgb',
				'rm' =>'application/vnd.rn-realmedia',
				'roff' =>'application/x-troff',
				'rtf' =>'text/rtf',
				'rtx' =>'text/richtext',
				'sgm' =>'text/sgml',
				'sgml' =>'text/sgml',
				'sh' =>'application/x-sh',
				'shar' =>'application/x-shar',
				'shtml' =>'text/html',
				'silo' =>'model/mesh',
				'sit' =>'application/x-stuffit',
				'skd' =>'application/x-koan',
				'skm' =>'application/x-koan',
				'skp' =>'application/x-koan',
				'skt' =>'application/x-koan',
				'smi' =>'application/smil',
				'smil' =>'application/smil',
				'snd' =>'audio/basic',
				'spl' =>'application/x-futuresplash',
				'src' =>'application/x-wais-source',
				'sv4cpio' =>'application/x-sv4cpio',
				'sv4crc' =>'application/x-sv4crc',
				'svg' =>'image/svg+xml',
				'swf' =>'application/x-shockwave-flash',
				't' =>'application/x-troff',
				'tar' =>'application/x-tar',
				'tcl' =>'application/x-tcl',
				'tex' =>'application/x-tex',
				'texi' =>'application/x-texinfo',
				'texinfo' =>'application/x-texinfo',
				'tgz' =>'application/x-tar',
				'tif' =>'image/tiff',
				'tiff' =>'image/tiff',
				'tr' =>'application/x-troff',
				'tsv' =>'text/tab-separated-values',
				'txt' =>'text/plain',
				'ustar' =>'application/x-ustar',
				'vcd' =>'application/x-cdlink',
				'vrml' =>'model/vrml',
				'vxml' =>'application/voicexml+xml',
				'wav' =>'audio/x-wav',
				'wbmp' =>'image/vnd.wap.wbmp',
				'wbxml' =>'application/vnd.wap.wbxml',
				'wml' =>'text/vnd.wap.wml',
				'wmlc' =>'application/vnd.wap.wmlc',
				'wmlc' =>'application/vnd.wap.wmlc',
				'wmls' =>'text/vnd.wap.wmlscript',
				'wmlsc' =>'application/vnd.wap.wmlscriptc',
				'wmlsc' =>'application/vnd.wap.wmlscriptc',
				'wrl' =>'model/vrml',
				'xbm' =>'image/x-xbitmap',
				'xht' =>'application/xhtml+xml',
				'xhtml' =>'application/xhtml+xml',
				'xls' =>'application/vnd.ms-excel',
				'xml xsl' =>'application/xml',
				'xpm' =>'image/x-xpixmap',
				'xslt' =>'application/xslt+xml',
				'xul' =>'application/vnd.mozilla.xul+xml',
				'xwd' =>'image/x-xwindowdump',
				'xyz' =>'chemical/x-xyz',
				'zip' =>'application/zip'
			);

			if (isset( $mimet[$idx] )) {
				return $mimet[$idx];
			} else {
				return 'application/octet-stream';
			}
		}
	}

	function ms_include_the_timeout()
	{
		if( !isset( $_REQUEST[ 'timeout' ] ) )
		{
			music_store_setError(
				'<div id="music_store_error_mssg"></div>
				<script>
					var timeout_text = "'.__( 'The store is processing the purchase. You will be redirected in', MS_TEXT_DOMAIN ).'";
				</script>'
			);
		}
		else
		{
			music_store_setError( 'There is no product associated with the entered data' );
		}
	}

	function ms_check_download_permissions(){
		global $music_store_settings;
		if( get_transient( 'ms_penalized_ip_'.ms_getIP() ) !== false )
		{
			_e( 'Please, try again in 3 minutes.', MS_TEXT_DOMAIN );
			exit;
		}
		delete_transient( 'ms_penalized_ip_'.ms_getIP() );
		global $wpdb;

		// If not session, create it
		if( session_id() == "" ) session_start();

		// Check if download for free or the user is an admin
		if(	!empty( $_SESSION[ 'download_for_free' ] ) || current_user_can( 'manage_options' ) ) return true;

		// and check the existence of a parameter with the purchase_id
		if( empty( $_REQUEST[ 'purchase_id' ] ) ){
			music_store_setError( 'The purchase id is required' );
			return false;
		}

		if( $music_store_settings[ 'ms_safe_download' ] ){

			if( session_id() == "" ) session_start();
			if( !empty( $_REQUEST[ 'ms_user_email' ] ) ) $_SESSION[ 'ms_user_email' ] =  sanitize_email($_REQUEST[ 'ms_user_email' ]);

			// Check if the user has typed the email used to purchase the product
			if( empty( $_SESSION[ 'ms_user_email' ] ) ){
				music_store_setError( "Please, go to the download page, and enter the email address used in products purchasing" );
				return false;
			}
			$data = $wpdb->get_row( $wpdb->prepare( 'SELECT CASE WHEN checking_date IS NULL THEN DATEDIFF(NOW(), date) ELSE DATEDIFF(NOW(), checking_date) END AS days, downloads, id FROM '.$wpdb->prefix.MSDB_PURCHASE.' WHERE purchase_id=%s AND email=%s ORDER BY checking_date DESC, date DESC', array( $_REQUEST[ 'purchase_id' ], $_SESSION[ 'ms_user_email' ] ) ) );
		}else{
			$data = $wpdb->get_row( $wpdb->prepare( 'SELECT CASE WHEN checking_date IS NULL THEN DATEDIFF(NOW(), date) ELSE DATEDIFF(NOW(), checking_date) END AS days, downloads, id FROM '.$wpdb->prefix.MSDB_PURCHASE.' WHERE purchase_id=%s ORDER BY checking_date DESC, date DESC', array( $_REQUEST[ 'purchase_id' ] ) ) );
		}

		if( is_null( $data ) ){
			if( get_transient( 'ms_suspect_ip_'.ms_getIP() ) !== false )
			{
				delete_transient( 'ms_suspect_ip_'.ms_getIP() );
				set_transient( 'ms_penalized_ip_'.ms_getIP(), true,  180 );
				_e( 'The purchase ID is incorrect, please, try again in 3 minutes.', MS_TEXT_DOMAIN );
				exit;
			}
			set_transient( 'ms_suspect_ip_'.ms_getIP(), true,  180 );
			ms_include_the_timeout();
			return false;
		}elseif( $music_store_settings[ 'ms_old_download_link' ] < $data->days ){
			delete_transient( 'ms_suspect_ip_'.ms_getIP() );
			music_store_setError( 'The download link has expired, please contact to the vendor' );
			return false;
		}elseif( $music_store_settings[ 'ms_downloads_number' ] > 0 &&  $music_store_settings[ 'ms_downloads_number' ] <= $data->downloads ){
			delete_transient( 'ms_suspect_ip_'.ms_getIP() );
			music_store_setError( 'The number of downloads has reached its limit, please contact to the vendor' );
			return false;
		}
		delete_transient( 'ms_suspect_ip_'.ms_getIP() );
		if( isset( $_REQUEST[ 'f' ] ) && !isset( $_SESSION[ 'cpms_donwloads' ] ) )
		{
            $_SESSION[ 'cpms_donwloads' ] = true;
            $wpdb->query( $wpdb->prepare( 'UPDATE '.$wpdb->prefix.MSDB_PURCHASE.' SET downloads=downloads+1 WHERE id=%d', $data->id ) );
        }

		return true;
	} // End ms_check_download_permissions

	function ms_copy_download_links($file){
		$parts  = pathinfo($file);
		$new_file_name = utf8_decode( rawurldecode( $parts[ 'basename' ] ) ).'_'.md5($file).( ( !empty($parts[ 'extension' ]) ) ? '.'.$parts[ 'extension' ] : '' );
        $dest = MS_DOWNLOAD.'/'.$new_file_name;
		$rand = rand(1000, 1000000);
		if(file_exists($dest)) return $new_file_name;

        if( ( $path = music_store_is_local( $file ) ) !== false ){
			if( music_store_copy( $path, $dest) ) return $new_file_name;
		}else{
			$response = wp_remote_get($file, array( 'timeout' => MS_REMOTE_TIMEOUT, 'stream' => true, 'filename' => $dest ) );
			if( !is_wp_error( $response ) && $response['response']['code'] == 200 ) return $new_file_name;
		}
        return $file;
	}

	function ms_remove_download_links(){
		global $music_store_settings;

        $now = time();
		$dif = $music_store_settings[ 'ms_old_download_link' ]*86400;
		$d = @dir(MS_DOWNLOAD);
		while (false !== ($entry = $d->read())) {
            // The music-store-icon.png file allow to know that htaccess file is supported, so it should not be deleted
			if($entry != '.' && $entry != '..' && $entry != 'music-store-icon.png' && $entry != '.htaccess' ){
				$file_name = MS_DOWNLOAD.'/'.$entry;
				$date = filemtime($file_name);
				if($now-$date >= $dif){ // Delete file
					@unlink($file_name);
				}
			}
		}
		$d->close();
	} // End ms_remove_download_links

    function ms_song_title($song_obj){
		if(isset($song_obj->post_title)) return $song_obj->post_title;
		return pathinfo($song_obj->file, PATHINFO_FILENAME);
	}

	function ms_generate_downloads(){
		global $wpdb, $download_links_str;
		ms_remove_download_links();

		if( ms_check_download_permissions() ){
			$purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix.MSDB_PURCHASE." WHERE purchase_id=%s", $_GET['purchase_id']));
			$download_links_str = '';

			if($purchase){ // Exists the purchase

				$id = $purchase->product_id;

				$_post = get_post($id);
				if(is_null($_post)){
					$download_links_str = __( 'The product is no longer available in our Music Store', MS_TEXT_DOMAIN );
					return;
				}
				if( $_post->post_type == 'ms_song' ) $obj = new MSSong($id);
				else{
					$download_links_str = __( 'The product is not valid', MS_TEXT_DOMAIN );
					return;
				}

				$urls = array();
				$songObj = new stdClass();
				if(isset($obj->file)){
					$songObj->title = ms_song_title($obj);
					$songObj->link  = str_replace( ' ', '%20', wp_kses_decode_entities($obj->file) );
					$urls[] = $songObj;
				}

				foreach($urls as $url){
					$download_link = ms_copy_download_links($url->link);
					if( $download_link !== $url->link ) $download_link = MS_H_URL.'?ms-action=f-download'.( ( isset( $_SESSION[ 'ms_user_email' ] ) ) ? '&ms_user_email='.$_SESSION[ 'ms_user_email' ] : '' ).'&f='.$download_link.( ( !empty( $_REQUEST[ 'purchase_id' ] ) ) ?  '&purchase_id='.$_REQUEST[ 'purchase_id' ] : '' );
					$download_links_str .= '<div> <a href="'.esc_url($download_link).'">'.music_store_strip_tags($url->title).'</a></div>';
				}

				if(empty($download_links_str)){
					$download_links_str = __('The list of purchased products is empty', MS_TEXT_DOMAIN);
				}
			} // End purchase checking
			else
			{
				ms_include_the_timeout();
			}
		}
	}

	function ms_browser_output( $file, $name )
	{
		try
		{
			header( 'Content-Type: '.ms_mime_content_type( basename( $file ) ) );
			header( 'Content-Disposition: attachment; filename="'.html_entity_decode( $name, ENT_QUOTES ).'"' );

			$file = wp_kses_decode_entities( $file );
			@ob_get_clean();

			$h = fopen( $file, 'r');
			if( $h )
			{
				while(!feof($h)) {
					$buffer = fread($h, 1024*6);
					print $buffer;
				}
				fclose($h);
			}
			else
			{
				print 'The file cannot be opened';
			}
			header ("Connection: close");
		}
		catch( Exception $err )
		{
			@unlink( MS_DOWNLOAD.'/.htaccess');
			header( 'location:'.esc_url_raw(MS_URL.'/ms-downloads/'.basename( $file )) );
		}
		exit;
	} // End ms_browser_output

	function ms_download_file(){
		global $wpdb, $ms_errors;
		if( isset( $_REQUEST[ 'f' ] ) ) $_REQUEST[ 'f' ] = stripslashes($_REQUEST['f']);
		if( isset( $_REQUEST[ 'f' ] ) && ms_check_download_permissions() ){
            $_REQUEST[ 'f' ] = utf8_decode( $_REQUEST[ 'f' ] );
			$file = MS_DOWNLOAD.'/'.basename( $_REQUEST[ 'f' ] );
			$file_name = basename( $_REQUEST[ 'f' ] );
			$pos = strrpos( $file_name, '_' );
			if( $pos !== false ) $file_name = substr( $file_name, 0, $pos );
			ms_browser_output( $file, $file_name );

		}else{
			$dlurl = $GLOBALS['music_store']->_ms_create_pages( 'ms-download-page', 'Download Page' );
			$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ).'ms-action=download'.( ( !empty( $_REQUEST[ 'purchase_id' ] ) ) ? '&purchase_id='.$_REQUEST[ 'purchase_id' ] : '' );
			header( 'location: '.esc_url_raw($dlurl) );
		}
	} // End ms_download_file
?>