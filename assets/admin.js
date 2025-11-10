jQuery(document).ready(function($) {
    // Test schema generation
    $('#gsc-test-schema').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var productId = $('#gsc-test-product-id').val();
        
        if (!productId || productId < 1) {
            $('#gsc-test-results').html(
                '<div class="notice notice-error"><p><strong>❌ Error:</strong> Please enter a valid product ID.</p></div>'
            );
            return;
        }
        
        // Show loading state
        button.prop('disabled', true);
        $('#gsc-loading').show();
        $('#gsc-test-results').html('');
        
        $.ajax({
            url: gscAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'gsc_test_schema',
                nonce: gscAjax.nonce,
                product_id: productId
            },
            timeout: 10000, // 10 second timeout
            success: function(response) {
                button.prop('disabled', false);
                $('#gsc-loading').hide();
                
                if (response.success) {
                    var schema = response.data.schema;
                    var json = response.data.json;
                    var hasOffers = schema.offers ? '✅' : '❌';
                    var hasReview = schema.review ? '✅' : '❌';
                    var hasRating = schema.aggregateRating ? '✅' : '❌';
                    
                    $('#gsc-test-results').html(
                        '<div class="notice notice-success">' +
                        '<p><strong>✅ Schema Generation Test Passed!</strong></p>' +
                        '<p><strong>Product:</strong> ' + (schema.name || 'N/A') + '</p>' +
                        '<p><strong>Schema Components:</strong> Offers: ' + hasOffers + ' | Review: ' + hasReview + ' | Rating: ' + hasRating + '</p>' +
                        '</div>' +
                        '<h3>Generated Schema (JSON-LD):</h3>' +
                        '<div class="gsc-schema-preview"><pre>' + json + '</pre></div>' +
                        '<div class="notice notice-info">' +
                        '<p><strong>🔍 Next Steps:</strong></p>' +
                        '<ul>' +
                        '<li>Copy the JSON above and test it with <a href="https://search.google.com/test/rich-results" target="_blank">Google\'s Rich Results Test</a></li>' +
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
                button.prop('disabled', false);
                $('#gsc-loading').hide();
                
                var errorMsg = error;
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. The product might not exist or the server is slow.';
                }
                
                $('#gsc-test-results').html(
                    '<div class="notice notice-error">' +
                    '<p><strong>❌ AJAX request failed:</strong> ' + errorMsg + '</p>' +
                    '<p>Please check:</p>' +
                    '<ul>' +
                    '<li>The product ID exists in your database</li>' +
                    '<li>Your WordPress installation is working correctly</li>' +
                    '<li>There are no JavaScript console errors</li>' +
                    '</ul>' +
                    '</div>'
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
    
    // Complete site optimization
    $('#gsc-optimize-site').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        
        // Confirm before running optimization
        if (!confirm('This will optimize your entire site. This may take a few minutes. Continue?')) {
            return;
        }
        
        button.text('🔄 Optimizing Site...').prop('disabled', true);
        
        $.ajax({
            url: gsc_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gsc_optimize_site',
                nonce: gsc_admin_ajax.nonce
            },
            timeout: 300000, // 5 minutes timeout
            success: function(response) {
                if (response.success) {
                    var results = response.data;
                    var html = '<div class="notice notice-success">';
                    html += '<h4>🎉 Site Optimization Completed!</h4>';
                    html += '<ul>';
                    html += '<li><strong>Meta Tags:</strong> Optimized ' + results.meta_tags.optimized_products + '/' + results.meta_tags.total_products + ' products</li>';
                    html += '<li><strong>Content:</strong> Enhanced ' + results.content.enhanced_products + '/' + results.content.total_products + ' products</li>';
                    html += '<li><strong>Schema:</strong> ' + results.schema.valid_schema + '/' + results.schema.total_checked + ' products have valid schema</li>';
                    html += '<li><strong>Performance:</strong> Cache cleared and optimizations applied</li>';
                    html += '</ul>';
                    html += '<p><strong>Next Steps:</strong></p>';
                    html += '<ul>';
                    html += '<li>🔍 Check your pages in <a href="https://search.google.com/test/rich-results" target="_blank">Google Rich Results Test</a></li>';
                    html += '<li>📊 Monitor improvements in <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a></li>';
                    html += '<li>⚡ Test page speed with <a href="https://pagespeed.web.dev/" target="_blank">PageSpeed Insights</a></li>';
                    html += '</ul>';
                    html += '</div>';
                    
                    $('#gsc-optimization-results').html(html);
                } else {
                    $('#gsc-optimization-results').html(
                        '<div class="notice notice-error"><p><strong>❌ Optimization failed:</strong> ' + response.data + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $('#gsc-optimization-results').html(
                    '<div class="notice notice-error"><p><strong>❌ Optimization failed:</strong> ' + error + '</p></div>'
                );
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Template preview functionality
    $('input[name*="template"]').on('keyup', function() {
        var template = $(this).val();
        var preview = template
            .replace('{product_name}', 'Example Product Name')
            .replace('{site_name}', 'Your Site Name')
            .replace('{price}', '99.00')
            .replace('{currency}', 'EUR');
        
        var previewId = $(this).attr('name').replace('gsc_schema_fix_options[', '').replace(']', '') + '_preview';
        
        if ($('#' + previewId).length === 0) {
            $(this).after('<div id="' + previewId + '" class="template-preview" style="margin-top: 5px; padding: 10px; background: #f0f0f1; border-radius: 3px; font-style: italic; color: #666;"></div>');
        }
        
        $('#' + previewId).html('<strong>Preview:</strong> ' + preview);
    });
    
    // Progress tracking for optimization
    var optimizationSteps = [
        'Analyzing current site structure...',
        'Optimizing meta tags and titles...',
        'Enhancing product content...',
        'Implementing schema markup...',
        'Applying performance optimizations...',
        'Validating improvements...',
        'Finalizing optimization...'
    ];
    
    function showOptimizationProgress() {
        var progressHtml = '<div class="optimization-progress" style="margin: 20px 0;">';
        progressHtml += '<div class="progress-bar" style="width: 100%; background: #f0f0f1; border-radius: 10px; overflow: hidden;">';
        progressHtml += '<div class="progress-fill" style="width: 0%; height: 20px; background: linear-gradient(90deg, #28a745, #20c997); transition: width 2s ease;"></div>';
        progressHtml += '</div>';
        progressHtml += '<div class="progress-steps" style="margin-top: 10px;"></div>';
        progressHtml += '</div>';
        
        return progressHtml;
    }
    
    // Warn before leaving if changes not saved
    $(window).on('beforeunload', function(e) {
        if (formChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
});