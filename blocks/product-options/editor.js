(function (blocks, element, components, blockEditor, serverSideRender, i18n) {
    'use strict';
    var el = element.createElement;
    var Fragment = element.Fragment;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ColorPalette = components.ColorPalette;
    var RangeControl = components.RangeControl;
    var SSR = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('northstar/product-options', {
        edit: function (props) {
            var a = props.attributes;
            var blockProps = useBlockProps({
                className: 'nspo-editor-block',
                style: { position: 'relative', minHeight: '24px' }
            });
            var set = function (values) { props.setAttributes(values); };

            return el(Fragment, {},
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
                            onChange: function (value) { set({ layout: value }); }
                        }),
                        el(TextControl, {
                            label: __('Add to Cart text', 'northstar-product-options'),
                            placeholder: __('Add to Cart', 'northstar-product-options'),
                            value: a.buttonText || '',
                            onChange: function (value) { set({ buttonText: value }); }
                        }),
                        el('div', { style: { marginTop: '16px' } },
                            el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, __('Add to Cart background', 'northstar-product-options')),
                            el(ColorPalette, {
                                value: a.buttonBg || '',
                                clearable: true,
                                onChange: function (value) { set({ buttonBg: value || '' }); }
                            })
                        )
                    ),
                    el(PanelBody, { title: __('Compact row border', 'northstar-product-options'), initialOpen: true },
                        el('p', { style: { marginTop: 0, color: '#646970' } }, __('These settings apply to Compact horizontal rows.', 'northstar-product-options')),
                        el('div', { style: { marginBottom: '12px' } },
                            el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, __('Border colour', 'northstar-product-options')),
                            el(ColorPalette, {
                                value: a.compactBorderColor || '#000000',
                                clearable: false,
                                onChange: function (value) { set({ compactBorderColor: value || '#000000' }); }
                            })
                        ),
                        el(TextControl, {
                            label: __('Border colour value', 'northstar-product-options'),
                            help: __('Accepts HEX, rgb(...) or rgba(...).', 'northstar-product-options'),
                            value: a.compactBorderColor || '#000000',
                            onChange: function (value) { set({ compactBorderColor: value || '#000000' }); }
                        }),
                        el(RangeControl, {
                            label: __('Border width (px)', 'northstar-product-options'),
                            value: a.compactBorderWidth === undefined ? 1 : a.compactBorderWidth,
                            min: 0,
                            max: 10,
                            step: 1,
                            onChange: function (value) { set({ compactBorderWidth: value }); }
                        }),
                        el(RangeControl, {
                            label: __('Corner radius (px)', 'northstar-product-options'),
                            value: a.compactBorderRadius === undefined ? 9 : a.compactBorderRadius,
                            min: 0,
                            max: 50,
                            step: 1,
                            onChange: function (value) { set({ compactBorderRadius: value }); }
                        })
                    ),
                    el(PanelBody, { title: __('Product source', 'northstar-product-options'), initialOpen: false },
                        el(TextControl, {
                            label: __('Optional product ID', 'northstar-product-options'),
                            help: __('Leave as 0 to use the product currently being viewed in a Single Product template.', 'northstar-product-options'),
                            type: 'number', min: 0,
                            value: a.productId || 0,
                            onChange: function (value) { set({ productId: parseInt(value || 0, 10) }); }
                        })
                    )
                ),
                el('div', blockProps,
                    el('div', { style: { pointerEvents: 'none' } },
                        el(SSR, {
                            key: JSON.stringify(props.attributes),
                            block: 'northstar/product-options',
                            attributes: props.attributes
                        })
                    )
                )
            );
        },
        save: function () { return null; }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender, window.wp.i18n);
