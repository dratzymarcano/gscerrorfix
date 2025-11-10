jQuery(document).ready(function($) {
    // Test schema generation
    $('#gsc-test-schema').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        
        button.text('🔄 Testing Schema...').prop('disabled', true);
        
        $.ajax({
            url: gsc_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gsc_test_schema',
                nonce: gsc_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var schema = response.data;
                    var hasOffers = schema.offers ? '✅' : '❌';
                    var hasReview = schema.review ? '✅' : '❌';
                    var hasRating = schema.aggregateRating ? '✅' : '❌';
                    
                    $('#gsc-test-results').html(
                        '<div class="notice notice-success">' +
                        '<p><strong>✅ Schema Generation Test Passed!</strong></p>' +
                        '<p>Offers: ' + hasOffers + ' | Review: ' + hasReview + ' | Rating: ' + hasRating + '</p>' +
                        '</div>' +
                        '<h4>Generated Schema (JSON-LD):</h4>' +
                        '<div class="gsc-schema-fix-preview">' + JSON.stringify(response.data, null, 2) + '</div>' +
                        '<div class="notice notice-info">' +
                        '<p><strong>Next Steps:</strong></p>' +
                        '<ul>' +
                        '<li>Test your pages with <a href="https://search.google.com/test/rich-results" target="_blank">Google\'s Rich Results Test</a></li>' +
                        '<li>Check your pages in <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a></li>' +
                        '<li>Allow 2-4 weeks for Google to re-crawl and update your search results</li>' +
                        '</ul>' +
                        '</div>'
                    );
                } else {
                    $('#gsc-test-results').html(
                        '<div class="notice notice-error"><p><strong>❌ Schema test failed:</strong> ' + response.data + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $('#gsc-test-results').html(
                    '<div class="notice notice-error"><p><strong>❌ AJAX request failed:</strong> ' + error + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Form validation
    $('form').on('submit', function(e) {
        var ratingValue = $('input[name="gsc_schema_fix_options[default_rating_value]"]').val();
        var ratingCount = $('input[name="gsc_schema_fix_options[default_rating_count]"]').val();
        
        if (ratingValue < 1 || ratingValue > 5) {
            alert('Rating value must be between 1 and 5');
            e.preventDefault();
            return false;
        }
        
        if (ratingCount < 1) {
            alert('Rating count must be at least 1');
            e.preventDefault();
            return false;
        }
        
        // Show success message after save
        if ($('.notice-success').length === 0) {
            $(this).after('<div class="notice notice-success is-dismissible"><p><strong>Settings saved!</strong> Your schema markup settings have been updated.</p></div>');
        }
    });
    
    // Tooltip functionality
    $('.description').each(function() {
        var $this = $(this);
        var text = $this.text();
        
        if (text.includes('fixes') || text.includes('error')) {
            $this.css({
                'background': '#fff3cd',
                'border': '1px solid #ffeaa7',
                'padding': '5px 8px',
                'border-radius': '3px',
                'font-weight': '500'
            });
        }
    });
    
    // Auto-save notification
    var formChanged = false;
    $('input, select').on('change', function() {
        formChanged = true;
        if ($('.auto-save-notice').length === 0) {
            $('.submit').prepend('<div class="auto-save-notice notice notice-warning inline"><p>💾 Remember to save your changes!</p></div>');
        }
    });
    
    // Remove auto-save notice on form submit
    $('form').on('submit', function() {
        $('.auto-save-notice').remove();
        formChanged = false;
    });
    
    // Warn before leaving if changes not saved
    $(window).on('beforeunload', function(e) {
        if (formChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
});