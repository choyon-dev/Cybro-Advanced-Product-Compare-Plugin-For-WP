(function($) {
    'use strict';

    const CybroCompare = {
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },

        bindEvents: function() {
            // Add to compare button click
            $(document).on('click', '.add-to-compare', this.addToCompare.bind(this));
            
            // Remove from compare button click
            $(document).on('click', '.remove-from-compare', this.removeFromCompare.bind(this));
            
            // Update compare table when products change
            $(document).on('cybro_compare_updated', this.updateCompareTable.bind(this));
        },

        initTooltips: function() {
            // Initialize tooltips if needed
            $('.cybro-compare-button').tooltip();
        },

        addToCompare: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const productId = $button.data('product-id');

            if (!productId) return;

            $.ajax({
                url: cybroCompare.ajaxurl,
                type: 'POST',
                data: {
                    action: 'add_to_compare',
                    product_id: productId,
                    nonce: cybroCompare.nonce
                },
                beforeSend: function() {
                    $button.addClass('loading');
                },
                success: function(response) {
                    if (response.success) {
                        $button
                            .removeClass('add-to-compare')
                            .addClass('remove-from-compare')
                            .find('.compare-button-text')
                            .text(cybroCompare.i18n.product_added);
                        
                        $button.find('.dashicons')
                            .removeClass('dashicons-update')
                            .addClass('dashicons-yes');
                        
                        $(document).trigger('cybro_compare_updated');
                    } else {
                        this.showError(response.data.message);
                    }
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        },

        removeFromCompare: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const productId = $button.data('product-id');

            if (!productId) return;

            $.ajax({
                url: cybroCompare.ajaxurl,
                type: 'POST',
                data: {
                    action: 'remove_from_compare',
                    product_id: productId,
                    nonce: cybroCompare.nonce
                },
                beforeSend: function() {
                    $button.addClass('loading');
                },
                success: function(response) {
                    if (response.success) {
                        // If button is in product list
                        if ($button.hasClass('cybro-compare-button')) {
                            $button
                                .removeClass('remove-from-compare')
                                .addClass('add-to-compare')
                                .find('.compare-button-text')
                                .text(cybroCompare.i18n.product_removed);
                            
                            $button.find('.dashicons')
                                .removeClass('dashicons-yes')
                                .addClass('dashicons-update');
                        } else {
                            // If button is in compare table
                            $button.closest('td').fadeOut(function() {
                                $(document).trigger('cybro_compare_updated');
                            });
                        }
                    } else {
                        this.showError(response.data.message);
                    }
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        },

        updateCompareTable: function() {
            const $table = $('.cybro-compare-table-wrapper');
            if (!$table.length) return;

            $.ajax({
                url: cybroCompare.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_compare_table',
                    nonce: cybroCompare.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $table.replaceWith(response.data.html);
                    }
                }
            });
        },

        showError: function(message) {
            const $errorDiv = $('<div>')
                .addClass('cybro-compare-error')
                .text(message)
                .css({
                    'position': 'fixed',
                    'top': '20px',
                    'right': '20px',
                    'background-color': '#f44336',
                    'color': 'white',
                    'padding': '15px',
                    'border-radius': '4px',
                    'z-index': '9999'
                });

            $('body').append($errorDiv);

            // Remove the error message after 3 seconds
            setTimeout(function() {
                $errorDiv.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        CybroCompare.init();
    });

})(jQuery);
