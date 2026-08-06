(function ($) {
    'use strict';

    function decodeVariation($card) {
        var raw = $card.attr('data-variation');
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    }

    function selectCard($root, $card) {
        var variation = decodeVariation($card);
        if (!variation || !variation.purchasable) return;

        $root.find('.nspo__card').removeClass('is-selected').attr('aria-checked', 'false');
        $card.addClass('is-selected').attr('aria-checked', 'true');
        $root.find('.nspo__variation-id').val(variation.id);
        $root.find('.nspo__final-price').html(variation.price_html);

        var $regular = $root.find('.nspo__final-regular');
        if (variation.on_sale && variation.regular_price_html) {
            $regular.html(variation.regular_price_html).prop('hidden', false);
        } else {
            $regular.empty().prop('hidden', true);
        }

        $root.find('.nspo__add-to-cart').prop('disabled', false);
        $root.find('.nspo__message').empty().removeClass('is-error is-success');
        $root.trigger('nspo_variation_selected', [variation]);
        $(document.body).trigger('found_variation', [variation]);
    }

    $(document).on('click', '.nspo__card:not(.is-disabled)', function () {
        selectCard($(this).closest('.nspo'), $(this));
    });

    $(document).on('keydown', '.nspo__card', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        var $cards = $(this).closest('.nspo__cards').find('.nspo__card:not(.is-disabled)');
        var index = $cards.index(this);
        var next = event.key === 'ArrowRight' ? index + 1 : index - 1;
        if (next >= $cards.length) next = 0;
        if (next < 0) next = $cards.length - 1;
        $cards.eq(next).focus().trigger('click');
    });

    $(document).on('click', '.nspo__add-to-cart', function () {
        var $button = $(this);
        var $root = $button.closest('.nspo');
        if ($button.prop('disabled') || $button.hasClass('is-loading')) return;

        var originalText = $button.text();
        $button.addClass('is-loading').prop('disabled', true).text(NSPO_DATA.addingText);
        $root.find('.nspo__message').empty().removeClass('is-error is-success');

        $.ajax({
            url: NSPO_DATA.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'nspo_add_to_cart',
                nonce: NSPO_DATA.nonce,
                product_id: $root.data('product-id'),
                variation_id: $root.find('.nspo__variation-id').val()
            }
        }).done(function (response) {
            if (!response || !response.success) {
                var message = response && response.data && response.data.message ? response.data.message : NSPO_DATA.errorText;
                $root.find('.nspo__message').addClass('is-error').text(message);
                return;
            }

            var data = response.data || {};
            if (data.fragments) {
                $.each(data.fragments, function (selector, html) {
                    $(selector).replaceWith(html);
                });
            }

            $button.addClass('added');
            $(document.body).trigger('added_to_cart', [data.fragments || {}, data.cart_hash || '', $button]);
            $(document.body).trigger('nspo_added_to_cart', [data, $root]);
        }).fail(function () {
            $root.find('.nspo__message').addClass('is-error').text(NSPO_DATA.errorText);
        }).always(function () {
            $button.removeClass('is-loading').prop('disabled', false).text(originalText);
        });
    });
})(jQuery);
