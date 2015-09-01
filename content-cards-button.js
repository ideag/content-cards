( function() {
    tinymce.PluginManager.add( 'contentcards', function( editor, url ) {
        editor.addButton( 'contentcards_shortcode', {
            text: 'CC',
            icon: false,
            onclick: function() {
                editor.windowManager.open( {
                    title: contentcards.texts.add_dialog_title,
                    body: [{
                        type: 'textbox',
                        name: 'uri',
                        label: contentcards.texts.link_label,
                    },
                    {
                        type: 'checkbox',
                        name: 'target',
                        label: contentcards.texts.target_label,
                        text: contentcards.texts.target_text,
                        checked:false
                    }],
                    onsubmit: function( e ) {
                        editor.insertContent( '[contentcards url="' + e.data.uri + '"' + ( e.data.target ? ' target="_blank"' : '' )+'] ' );
                    }
                } );
            }
        } );
    } );
} )();