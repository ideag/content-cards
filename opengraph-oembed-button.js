( function() {
    tinymce.PluginManager.add( 'opengraph', function( editor, url ) {
        editor.addButton( 'opengraph_shortcode', {
            text: 'OG',
            icon: false,
            onclick: function() {
                editor.windowManager.open( {
                    title: 'Add OpenGraph Link',
                    body: [{
                        type: 'textbox',
                        name: 'uri',
                        label: 'Link URI'
                    }],
                    onsubmit: function( e ) {
                        editor.insertContent( '[opengraph url="' + e.data.uri + '"] ' );
                    }
                } );
            }
        } );
    } );
} )();