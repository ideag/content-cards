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
                        label: contentcards.texts.link_label
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
                    }],
                    onsubmit: function( e ) {
                        editor.insertContent( '[contentcards url="' + e.data.uri + '"' + ( e.data.target ? ' target="_blank"' : '' )+'] ' );
                    }
                } );
            }
        } );
    } );
} )();