(function($){
	var media = wp.media, contentcards_view;
	var base = {
		state: [],

	};
	var contentcards_view = _.extend( {}, base, {
		action: 'content_cards_shortcode',
        setLoader: function() {
			this.setContent(
				'<div class="content-cards-loading-info"><div class="content-cards-loading-info-image"><img src="'+contentcards.loading_image+'"></div><h1>'+contentcards.texts.loading_image_heading+'</h1><p>'+contentcards.texts.loading_image_text+'</p></div>'
		    );
        },
		template: media.template( 'editor-contentcards' ),
		initialize: function() {
			var self = this;

			wp.ajax.post( this.action, {
				post_id: media.view.settings.post.id,
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
					{
						type: 'listbox',
						name: 'target',
						label: contentcards.texts.target_label,
						onselect: function(e) {
						},
						'values': [
							{
								text: contentcards.texts.target_text_global, value: 'default'
							},
							{
								text: contentcards.texts.target_text_yes, value: '_blank'
							},
							{
								text: contentcards.texts.target_text_no, value: '_self'
							}
						],
						onPostRender: function() {
							// Select the second item by default
							this.value('default');
						}
					}
                ],
                onsubmit: function(e){
                	var s = '[contentcards url="' + e.data.url + '"' + ( e.data.target  ? ' target="_blank"' : '' )+']'
				    tinyMCE.activeEditor.insertContent( s );
                }
            } );
		}
	} );
	wp.mce.views.register( 'contentcards', contentcards_view ); 
}(jQuery));