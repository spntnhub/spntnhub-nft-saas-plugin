/**
 * NFT SaaS – Gutenberg Block (nft-saas/buy-button)
 * Editor: shows a placeholder. Frontend: renders via PHP render_callback.
 */
(function (blocks, element, blockEditor, components, i18n) {
    var el         = element.createElement;
    var __         = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var MediaUpload       = blockEditor.MediaUpload;
    var PanelBody         = components.PanelBody;
    var TextControl       = components.TextControl;
    var Button            = components.Button;

    blocks.registerBlockType('nft-saas/buy-button', {
        title:       __('NFT Buy Button', 'nft-saas'),
        description: __('Display a buy button for an NFT-listed image.', 'nft-saas'),
        icon:        'image',
        category:    'media',
        keywords:    [__('nft'), __('buy'), __('web3'), __('blockchain')],

        attributes: {
            mediaID:   { type: 'number',  default: 0    },
            mediaURL:  { type: 'string',  default: ''   },
            mediaTitle:{ type: 'string',  default: ''   },
            price:     { type: 'string',  default: '0.01' },
        },

        edit: function (props) {
            var attrs    = props.attributes;
            var mediaID  = attrs.mediaID;
            var mediaURL = attrs.mediaURL;

            return el(
                'div',
                null,

                // Sidebar controls
                el(InspectorControls, null,
                    el(PanelBody, { title: __('NFT Settings', 'nft-saas'), initialOpen: true },
                        el(TextControl, {
                            label:    __('Price (POL / ETH)', 'nft-saas'),
                            value:    attrs.price,
                            onChange: function (val) { props.setAttributes({ price: val }); },
                            help:     __('Sale price in native chain currency. Must match the value set in Media Library.', 'nft-saas'),
                        })
                    )
                ),

                // Block preview
                el('div', {
                        style: {
                            border:       '2px dashed #a0aec0',
                            borderRadius: '8px',
                            padding:      '20px',
                            textAlign:    'center',
                            background:   '#f7fafc',
                        }
                    },

                    mediaURL
                        ? el('img', { src: mediaURL, style: { maxWidth: '100%', maxHeight: '200px', marginBottom: '12px', borderRadius: '4px' } })
                        : null,

                    el('p', { style: { fontWeight: 700, margin: '0 0 6px' } },
                        mediaID
                            ? (attrs.mediaTitle || '(Image #' + mediaID + ')')
                            : __('No image selected', 'nft-saas')
                    ),

                    mediaID
                        ? el('p', { style: { color: '#718096', fontSize: '0.85em', margin: '0 0 12px' } },
                            __('Price: ', 'nft-saas') + attrs.price + ' POL/ETH'
                          )
                        : null,

                    el(MediaUpload, {
                        onSelect: function (media) {
                            props.setAttributes({
                                mediaID:    media.id,
                                mediaURL:   media.url,
                                mediaTitle: media.title,
                            });
                        },
                        allowedTypes: ['image'],
                        value:        mediaID,
                        render:       function (obj) {
                            return el(Button, {
                                    onClick:   obj.open,
                                    isPrimary: !mediaID,
                                    isSecondary: !!mediaID,
                                    style: { marginTop: '8px' },
                                },
                                mediaID
                                    ? __('Change Image', 'nft-saas')
                                    : __('Select Image', 'nft-saas')
                            );
                        },
                    })
                )
            );
        },

        // Rendering is done server-side via render_callback in PHP
        save: function () { return null; },
    });

}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
));
