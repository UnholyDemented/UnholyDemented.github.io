jQuery( 
	function( $ )
	{
		// Menu header
		$('.music-store-header').prepend( '<span class="header-handle"></span>' );
		$(document).on(
			'click', 
			'.music-store-header .header-handle', 
			function()
			{
				$('.music-store-filters').toggle(300);
			}
		);
		
		$(document)
		.on(
			'mouseover',
			'.collection-payment-buttons input[type="image"],.song-payment-buttons input[type="image"],.track-button input[type="image"]',
			function()
			{
				var me = $(this);
				if( !me.hasClass('rotate-in-hor'))
				{
					$(this).addClass('rotate-in-hor');
					setTimeout(
						function()
						{
							me.removeClass('rotate-in-hor');
						},
						1000
					);	
				}	
				
			}
		);
		
		// Replace the popularity texts with the stars 
		var popularity_top = 0;
		$( '.collection-popularity,.song-popularity' ).each(
			function()
			{
				var e = $( this ),
					p = parseInt( e.find( 'span' ).remove().end().text().replace( /\s/g, '' ) );
					
				e.text( '' ).attr( 'popularity', p );
				popularity_top = Math.max( popularity_top, p );
			}
		);
		
		$( '.collection-popularity,.song-popularity' ).each(
			function()
			{
			
				var e = $( this ),
					p = e.attr( 'popularity' ),
					str = '',
					active = 0;

				if( popularity_top > 0 )
				{
					active = Math.ceil( p / popularity_top * 100 / 20 );
				}
				
				for( var i = 0; i < active; i++ )
				{
					str += '<div class="star-active"></div>';
				}
				
				for( var i = 0, h = 5 - active; i < h; i++ )
				{
					str += '<div class="star-inactive"></div>';
				}
				e.html( str );
			}
		);
		
		// Set buttons classes
		$('.ms-shopping-cart-list .button,.ms-shopping-cart-list .button,.ms-shopping-cart-resume .button').addClass('bttn-stretch bttn-sm bttn-primary').removeClass('button').wrap('<span class="bttn-stretch bttn-sm bttn-primary" style="margin-top: -6px !important;"></span>');
		
		$('.ms-shopping-cart').next('.music-store-song,.music-store-collection').find('.left-column.single').css('padding-top','36px');
	} 	
);