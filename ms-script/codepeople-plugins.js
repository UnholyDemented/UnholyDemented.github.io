jQuery( function($){
	
	//------------------------ PRIVATE FUNCTIONS ------------------------
	
	/**
	 * Get the screen width
	 */ 
	function _getWidth()
	{
		var myWidth = 0;
		if( typeof( window.innerWidth ) == 'number' ) {
			//Non-IE
			myWidth = window.innerWidth;
		} else if( document.documentElement && document.documentElement.clientWidth ) {
			//IE 6+ in 'standards compliant mode'
			myWidth = document.documentElement.clientWidth;
		} else if( document.body && document.body.clientWidth ) {
			//IE 4 compatible
			myWidth = document.body.clientWidth;
		}
		
		 if( typeof window.devicePixelRatio != 'undefined' && window.devicePixelRatio ) myWidth = myWidth/window.devicePixelRatio;    
		return ( typeof screen != 'undefined' ) ? Math.min( screen.width, myWidth ) : myWidth;
	};
	
	/**
	 * Increase the product's popularity
	 */
	function _increasePopularity( e )
	{
		var id = e.attr( 'data-product' );
		if( typeof id != 'undefined' )
		{	
			var url = ( ms_global && ms_global['hurl'] ) ? ms_global['hurl'] : '/';
			$.post( url, { 'ms-action': 'popularity', 'id' : id });
		}	
	};
	
	/**
	 * Play next player
	 */
	function _playNext( playerNumber )
	{
		if( playerNumber+1 < player_counter )
		{
			var toPlay = playerNumber+1;
			if( players[ toPlay ] instanceof jQuery && players[ toPlay ].is( 'a' ) ) players[ toPlay ].click();
			else players[ toPlay ].play();
		}	
	};
	//------------------------ PUBLIC FUNCTIONS ------------------------
	
	/**
	 * Countdown in the download page
	 */
	window['music_store_counting'] = function()
    {
        var loc = document.location.href;
        document.getElementById( "music_store_error_mssg" ).innerHTML = timeout_text+' '+timeout_counter;
        if( timeout_counter == 0 )
        {
            document.location = loc+( ( loc.indexOf( '?' ) == -1 ) ? '?' : '&' )+'timeout=1';    
        }
        else
        {
            timeout_counter--;
            setTimeout( music_store_counting, 1000 );
        }    
    };
	
	//------------------------ MAIN CODE ------------------------
	var min_screen_width = 640,
	
		// Players
		loadMidiClass = false,
		players = [],
		player_counter = 0,
		s = $('.ms-player.single audio'),
		m = $('.ms-player.multiple audio'),
		c = {
				iPadUseNativeControls: false,
				iPhoneUseNativeControls: false,
				success: function( media, dom ){
					media.addEventListener( 'timeupdate', function( e ){
						if( !isNaN( this.currentTime ) && !isNaN( this.duration ) && this.src.indexOf( 'ms-action=secure' ) != -1 )
						{
							if( this.duration - this.currentTime < 4 )
							{
								this.setVolume( this.volume - this.volume / 3 );
							}
							else
							{
								if( typeof this[ 'bkVolume' ] == 'undefined' ) this[ 'bkVolume' ] = this.volume;
								this.setVolume( this.bkVolume );
							}	
							
						}
					});
					media.addEventListener( 'volumechange', function( e ){
						if( !isNaN( this.currentTime ) && !isNaN( this.duration ) && this.src.indexOf( 'ms-action=secure' ) != -1 )
						{
							if( ( this.duration - this.currentTime > 4 ) && this.currentTime )  this[ 'bkVolume' ] = this.volume;
						}
					});
					
					media.addEventListener( 'ended', function( e ){
						if( 
							ms_global && ms_global[ 'play_all' ]*1
						)
						{
							var playerNumber = $(this).attr('playerNumber')*1;
							_playNext( playerNumber );
						}
					});
				}
			};
	
	s.each(function(){
		var e 	= $(this),
			src = e.find( 'source' ).attr( 'src' );
			
		if( /\.mid$/i.test( src ) )
		{
			var replacement = $( '<a href="#" data-href="'+src+'" class="midiPlayer midiPlay" data-product="'+e.attr( 'data-product' )+'"><span></span></a>' );
			e.replaceWith( replacement );
			e = replacement;
			e.closest( '.ms-player' ).css( 'background', 'transparent' );
			players[ player_counter ] = e;
			loadMidiClass = true;
		}	
		else
		{
			c['audioVolume'] = 'vertical';
			players[ player_counter ] = new MediaElementPlayer(e, c);
		}	
		e.attr('playerNumber', player_counter);
		player_counter++;
	});
	
	
	m.each(function(){
		var e = $(this),
			src = e.find( 'source' ).attr( 'src' );
		
		if( /\.mid$/i.test( src ) )
		{
			var replacement = $( '<a href="#" data-href="'+src+'" class="midiPlayer midiPlay" data-product="'+e.attr( 'data-product' )+'"><span></span></a>' );
			e.replaceWith( replacement );
			e = replacement;
			players[ player_counter ] = e;
			loadMidiClass = true;
		}	
		else
		{
			c['features'] = ['playpause'];
			players[ player_counter ] = new MediaElementPlayer(e, c);
		}	
		e.attr('playerNumber', player_counter);
		player_counter++;
	});
	
	if( loadMidiClass )
	{
		$( 'body' ).append( '<script type="text/javascript" src="//www.midijs.net/lib/midi.js"></script>' );
		var MIDIjs_counter = 10,
			checkMIDIjsObj = setInterval( 
				function()
				{ 
					MIDIjs_counter--;
					if( MIDIjs_counter < 0 ) clearInterval( checkMIDIjsObj );
					else if( typeof MIDIjs != 'undefined' ) 
					{
						clearInterval( checkMIDIjsObj );
						MIDIjs.player_callback = function( evt ){  
							if( evt.time == 0 )
							{
								// Play next
								var e = $( '.midiStop' ),
									playerNumber = e.attr('playerNumber')*1;
								
								e.click();	
								_playNext( playerNumber );
							}	
						};
						
						$( document ).on( 
							'click', 
							'.midiPlay,.midiStop',
							function()
							{
								try
								{
									var e = $( this );
									if( e.hasClass( 'midiPlay' ) )
									{	
										MIDIjs.play( e.attr( 'data-href' ) );
										$( '.midiStop' ).click();
										_increasePopularity( e );
										e.attr( 'class', 'midiPlayer midiStop' );
									}
									else
									{
										e.attr( 'class', 'midiPlayer midiPlay' );
										MIDIjs.stop();
									}	
								}catch( err ){}
								
								return false;
							}
						);
					}	
				},
				1000
			);
	}	
	
	// Increase popularity
	$( 'audio' ).bind( 'play', function(){ 
		_increasePopularity( $( this ) );
	} );
	
	// Free downloads
	$( '.ms-download-link' ).click( function( evt ){ 
		var e = $( evt.target );
		if( typeof e.attr( 'data-id' ) != 'undefined' )
		{	
			evt.preventDefault();
			$.ajax(
				ms_global[ 'hurl' ],
				{
					data: { 'id': e.attr( 'data-id' ), 'ms-action': 'registerfreedownload' }
				}
			).done( 
				( function( _url ){
					return function(){ document.location.href = _url; };
				} )( $( evt.target ).attr( 'href' ) )
			);
		}	
	});
	
	
	// Check the download links
	timeout_counter = 10;
    if( $( '[id="music_store_error_mssg"]' ).length ) 
    {
        music_store_counting();
    }

	// Browser's resize of Mobile orientation
	$( window ).bind( 'orientationchange resize', function(){
		setTimeout( 
			(function( minWidth, getWidth )
			{
				return function()
				{ 
					$( '.music-store-item' ).each( function(){
						var e = $( this ),
							c = e.find( '.collection-cover,.song-cover' );
						
						if( getWidth < minWidth )
						{	
							if( c.length ) c.css( { 'height': 'auto' } );
							e.css( {'width': '100%', 'height': 'auto'} );
						}	
						else
						{	
							if( c.length ) c.css( { 'height': '' } );
							e.css( {'width': e.attr( 'data-width' ), 'height': '' } );
						}	
					} );
					if( getWidth >= minWidth && typeof ms_correct_heights != 'undefined' ) 
						ms_correct_heights();
				}
			})( min_screen_width, _getWidth() ), 
			100 
		);
	} );
	$( window ).on( 'load', function(){ $( window ).trigger( 'resize' ); } );	
});