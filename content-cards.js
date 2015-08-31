(function($){
	var media = wp.media, contentcards_view;
	var base = {
		state: [],

	};
	var contentcards_view = _.extend( {}, base, {
		action: 'content_cards_shortcode',
        setLoader: function() {
			this.setContent(
				'<img src="'+contentcards.loading_image+'">'
		    );
        },
		template: media.template( 'editor-contentcards' ),
		initialize: function() {
			var self = this;

			wp.ajax.post( this.action, {
				post_ID: media.view.settings.post.id,
				type: this.shortcode.tag,
				shortcode: this.shortcode.string()
			} )
			.done( function( response ) {
				self.render(response);
			} )
			.fail( function( response ) {
				if ( self.url ) {
					self.removeMarkers();
				} else {
					self.setError( response.message || response.statusText, 'admin-media' );
				}
			} );
		},
		edit: function( data, update ) {
	    	var shortcode_data = wp.shortcode.next('contentcards', data);
		    var values = shortcode_data.shortcode.attrs.named;
		    tinyMCE.activeEditor.windowManager.open({
                title: contentcards.texts.link_dialog_title,
                body: [
                    {
                        type: 'textbox',
                        name: 'url',
                        label: contentcards.texts.link_label,
	                    value: values['url']
                    },
                ],
                onsubmit: function(e){
                	var s = '[contentcards';
				    for(var i in e.data){
					    if(e.data.hasOwnProperty(i) && i != 'innercontent'){
						    s += ' ' + i + '="' + e.data[i] + '"';
					    }
				    }
				    s += ']';
				    tinyMCE.activeEditor.insertContent( s );
                }
            } );
		}
	} );
	wp.mce.views.register( 'contentcards', contentcards_view ); 
}(jQuery));