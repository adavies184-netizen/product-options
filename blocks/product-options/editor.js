(function (blocks, element, components, blockEditor, serverSideRender, i18n) {
    'use strict';
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ColorPalette = components.ColorPalette;
    var SSR = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('northstar/product-options', {
        edit: function (props) {
            var a = props.attributes;
            return el(element.Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Northstar options', 'northstar-product-options'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Option layout', 'northstar-product-options'),
                            value: a.layout || 'columns',
                            options: [
                                { label: __('Three columns', 'northstar-product-options'), value: 'columns' },
                                { label: __('Horizontal rows with images', 'northstar-product-options'), value: 'horizontal' },
                                { label: __('Compact horizontal rows', 'northstar-product-options'), value: 'horizontal-compact' }
                            ],
                            onChange: function (value) { props.setAttributes({ layout: value }); }
                        }),
                        el(TextControl, {
                            label: __('Add to Cart text', 'northstar-product-options'),
                            placeholder: __('Add to Cart', 'northstar-product-options'),
                            value: a.buttonText || '',
                            onChange: function (value) { props.setAttributes({ buttonText: value }); }
                        }),
                        el('div', { style: { marginTop: '16px' } },
                            el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, __('Add to Cart background', 'northstar-product-options')),
                            el(ColorPalette, {
                                value: a.buttonBg || '',
                                clearable: true,
                                onChange: function (value) { props.setAttributes({ buttonBg: value || '' }); }
                            })
                        )
                    ),
                    el(PanelBody, { title: __('Product source', 'northstar-product-options'), initialOpen: false },
                        el(TextControl, {
                            label: __('Optional product ID', 'northstar-product-options'),
                            help: __('Leave as 0 to use the product currently being viewed in a Single Product template.', 'northstar-product-options'),
                            type: 'number',
                            min: 0,
                            value: a.productId || 0,
                            onChange: function (value) { props.setAttributes({ productId: parseInt(value || 0, 10) }); }
                        })
                    )
                ),
                el(SSR, { block: 'northstar/product-options', attributes: props.attributes })
            );
        },
        save: function () { return null; }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender, window.wp.i18n);
