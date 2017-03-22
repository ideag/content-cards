jQuery(function($){
  // Uploading files
  var tiny_options_frame;

  jQuery('.button_upload').click(function( event ){
    event.preventDefault();
    // If the media frame already exists, reopen it.
    if ( tiny_options_frame ) {
      tiny_options_frame.open();
      return;
    }

    var button = jQuery( this );
    // Create the media frame.
    tiny_options_frame = wp.media.frames.tiny_options_frame = wp.media({
      title: button.data( 'uploader_title' ),
      button: {
        text: button.data( 'uploader_button_text' ),
      },
      multiple: false  // Set to true to allow multiple files to be selected
    });

    // When an image is selected, run a callback.
    tiny_options_frame.on( 'select', function() {
      // We set multiple to false so only get one image from the uploader
      attachment = tiny_options_frame.state().get('selection').first().toJSON();
      jQuery( '#'+button.data('target') ).val( attachment.url );
      // Do something with attachment.id and/or attachment.url here
    });

    // Finally, open the modal
    tiny_options_frame.open();
  });
});
