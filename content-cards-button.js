( function() {
    tinymce.PluginManager.add( 'contentcards', function( editor, url ) {
        editor.addButton( 'contentcards_shortcode', {
            text: 'CC',
            icon: false,
            onclick: function() {
                editor.windowManager.open( {
                    title: 'Add Content Card',
                    body: [{
                        type: 'textbox',
                        name: 'uri',
                        label: 'Link URI'
                    },
                    {
                        type: 'checkbox',
                        name: 'target',
                        label: 'Target',
                        text: 'Open in a new tab',
                        checked:false
                    }],
                    onsubmit: function( e ) {
                        editor.insertContent( '[contentcards url="' + e.data.uri + '"' + ( e.data.target ? ' target="blank"' : '' )+'] ' );
                    }
                } );
            }
        } );
    } );
} )();