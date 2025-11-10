jQuery(document).ready(function($) {
    // v4.0.6 - Load Analytics Dashboard
    function loadAnalytics() {
        $.ajax({
            url: gscAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'gsc_get_analytics',
                nonce: gscAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    displayAnalytics(data);
                } else {
                    $('#gsc-analytics-dashboard').html(
                        '<div class="notice notice-error"><p>Failed to load analytics</p></div>'
                    );
                }
            },
            error: function() {
                $('#gsc-analytics-dashboard').html(
                    '<div class="notice notice-error"><p>Error loading analytics</p></div>'
                );
            }
        });
    }
    
    function displayAnalytics(data) {
        var overview = data.overview;
        var errors = data.errors;
        var chart = data.daily_chart;
        
        var coverageClass = overview.coverage_percent >= 80 ? 'success' : 
                           (overview.coverage_percent >= 50 ? 'warning' : 'error');
        
        var trendIcon = errors.trend === 'improving' ? '📈 Improving' : 
                       (errors.trend === 'worsening' ? '📉 Worsening' : '➡️ Stable');
        
        var html = '<div class="gsc-analytics-grid">';
        
        // Overview Cards
        html += '<div class="gsc-stat-card">';
        html += '<h3>Total Products</h3>';
        html += '<div class="gsc-stat-value">' + overview.total_products + '</div>';
        html += '<small>Platform: ' + overview.platform + '</small>';
        html += '</div>';
        
        html += '<div class="gsc-stat-card">';
        html += '<h3>Schema Coverage</h3>';
        html += '<div class="gsc-stat-value gsc-score-' + coverageClass + '">' + overview.coverage_percent + '%</div>';
        html += '<small>' + overview.products_with_schema + ' products with schema</small>';
        html += '</div>';
        
        html += '<div class="gsc-stat-card">';
        html += '<h3>Active Errors</h3>';
        html += '<div class="gsc-stat-value' + (errors.current_count > 0 ? ' gsc-score-error' : '') + '">' + errors.current_count + '</div>';
        html += '<small>' + trendIcon + '</small>';
        html += '</div>';
        
        html += '<div class="gsc-stat-card">';
        html += '<h3>Total Generated</h3>';
        html += '<div class="gsc-stat-value">' + overview.total_generated.toLocaleString() + '</div>';
        html += '<small>Schema markups created</small>';
        html += '</div>';
        
        html += '</div>'; // End grid
        
        // Chart
        html += '<div class="gsc-chart-container">';
        html += '<h3>Daily Schema Generation (Last 7 Days)</h3>';
        html += '<div class="gsc-chart">';
        
        var maxValue = Math.max(...Object.values(chart), 1);
        for (var date in chart) {
            var count = chart[date];
            var height = maxValue > 0 ? (count / maxValue) * 100 : 0;
            var dateLabel = new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            
            html += '<div class="gsc-chart-bar">';
            html += '<div class="gsc-bar-fill" style="height: ' + height + '%;" title="' + count + ' products"></div>';
            html += '<div class="gsc-bar-label">' + dateLabel + '</div>';
            html += '<div class="gsc-bar-value">' + count + '</div>';
            html += '</div>';
        }
        
        html += '</div></div>';
        
        $('#gsc-analytics-dashboard').html(html);
    }
    
    // Load analytics on page load
    if ($('#gsc-analytics-dashboard').length) {
        loadAnalytics();
    }
    
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
                    var validation = response.data.validation;
                    var hasOffers = schema.offers ? '✅' : '❌';
                    var hasReview = schema.review ? '✅' : '❌';
                    var hasRating = schema.aggregateRating ? '✅' : '❌';
                    
                    // Build validation display
                    var validationHtml = '';
                    if (validation) {
                        var validationClass = validation.valid ? 'notice-success' : 'notice-error';
                        var validationIcon = validation.valid ? '✅' : '❌';
                        var scoreClass = validation.score >= 80 ? 'success' : (validation.score >= 60 ? 'warning' : 'error');
                        
                        validationHtml = '<div class="notice ' + validationClass + '">' +
                            '<p><strong>' + validationIcon + ' Schema Validation Results</strong></p>' +
                            '<p>Score: <strong class="gsc-score-' + scoreClass + '">' + validation.score + '/100</strong></p>';
                        
                        if (validation.errors && validation.errors.length > 0) {
                            validationHtml += '<p><strong>Errors:</strong></p><ul>';
                            validation.errors.forEach(function(error) {
                                validationHtml += '<li>❌ ' + error + '</li>';
                            });
                            validationHtml += '</ul>';
                        }
                        
                        if (validation.warnings && validation.warnings.length > 0) {
                            validationHtml += '<p><strong>Warnings:</strong></p><ul>';
                            validation.warnings.forEach(function(warning) {
                                validationHtml += '<li>⚠️ ' + warning + '</li>';
                            });
                            validationHtml += '</ul>';
                        }
                        
                        if (validation.valid && validation.warnings.length === 0) {
                            validationHtml += '<p>✨ Perfect! Your schema has no errors or warnings.</p>';
                        }
                        
                        validationHtml += '</div>';
                    }
                    
                    $('#gsc-test-results').html(
                        '<div class="notice notice-success">' +
                        '<p><strong>✅ Schema Generation Test Passed!</strong></p>' +
                        '<p><strong>Product:</strong> ' + (schema.name || 'N/A') + '</p>' +
                        '<p><strong>Schema Components:</strong> Offers: ' + hasOffers + ' | Review: ' + hasReview + ' | Rating: ' + hasRating + '</p>' +
                        '</div>' +
                        validationHtml +
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
    
    // v4.0.2.1 - Handle toggle switches
    $('#gsc-settings-form input[type="checkbox"]').on('change', function() {
        var checkbox = $(this);
        var formData = {
            action: 'gsc_save_settings',
            nonce: gscAjax.nonce
        };
        
        // Get all checkbox values
        $('#gsc-settings-form input[type="checkbox"]').each(function() {
            formData[$(this).attr('name')] = $(this).is(':checked') ? 1 : 0;
        });
        
        // Show global saving indicator
        $('#gsc-global-status').removeClass('gsc-status-success gsc-status-error').addClass('gsc-status-saving').show();
        $('#gsc-global-status .gsc-status-text').text('Saving changes...');
        $('#gsc-global-status .dashicons').removeClass('dashicons-saved dashicons-no').addClass('dashicons-update');
        
        $.ajax({
            url: gscAjax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success status
                    $('#gsc-global-status').removeClass('gsc-status-saving').addClass('gsc-status-success');
                    $('#gsc-global-status .gsc-status-text').text('✓ All changes saved');
                    $('#gsc-global-status .dashicons').removeClass('dashicons-update').addClass('dashicons-saved');
                    
                    // Auto-hide after 5 seconds
                    setTimeout(function() {
                        $('#gsc-global-status').fadeOut();
                    }, 5000);
                    
                    // Reload analytics if error scanning toggle changed
                    if (formData.enable_error_scanning !== undefined || formData.enable_auto_fix !== undefined) {
                        if ($('#gsc-analytics-dashboard').length) {
                            loadAnalytics();
                        }
                    }
                } else {
                    $('#gsc-global-status').removeClass('gsc-status-saving').addClass('gsc-status-error');
                    $('#gsc-global-status .gsc-status-text').text('✗ Error saving: ' + response.data);
                    $('#gsc-global-status .dashicons').removeClass('dashicons-update').addClass('dashicons-no');
                }
            },
            error: function() {
                $('#gsc-global-status').removeClass('gsc-status-saving').addClass('gsc-status-error');
                $('#gsc-global-status .gsc-status-text').text('✗ Failed to save settings');
                $('#gsc-global-status .dashicons').removeClass('dashicons-update').addClass('dashicons-no');
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
    
    // v4.0.5 - GSC Error Scanning
    $('#gsc-scan-errors').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        
        // Show loading state
        button.prop('disabled', true);
        $('#gsc-scan-loading').show();
        $('#gsc-error-results').html('');
        $('#gsc-fix-errors').hide();
        
        $.ajax({
            url: gscAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'gsc_scan_errors',
                nonce: gscAjax.nonce
            },
            success: function(response) {
                button.prop('disabled', false);
                $('#gsc-scan-loading').hide();
                
                if (response.success) {
                    var data = response.data;
                    var errors = data.errors;
                    var totalProducts = data.total_products;
                    var totalErrors = data.total_errors;
                    
                    if (totalProducts === 0) {
                        $('#gsc-error-results').html(
                            '<div class="notice notice-success">' +
                            '<p><strong>✅ No Errors Found!</strong></p>' +
                            '<p>All your products have valid schema markup. Great job!</p>' +
                            '</div>'
                        );
                    } else {
                        // Show fix button
                        $('#gsc-fix-errors').show();
                        
                        var html = '<div class="notice notice-warning">' +
                            '<p><strong>⚠️ Found ' + totalErrors + ' errors in ' + totalProducts + ' products</strong></p>' +
                            '</div>';
                        
                        html += '<h3>Error Details:</h3>';
                        html += '<div class="gsc-error-list">';
                        
                        errors.forEach(function(item) {
                            html += '<div class="gsc-error-item" style="margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #f0ad4e; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                            html += '<h4 style="margin: 0 0 10px;">' + item.product_title + ' (ID: ' + item.product_id + ')</h4>';
                            html += '<p><a href="' + item.product_url + '" target="_blank">View Product →</a></p>';
                            html += '<ul style="margin: 10px 0;">';
                            
                            item.errors.forEach(function(error) {
                                var icon = error.severity === 'error' ? '❌' : '⚠️';
                                html += '<li>' + icon + ' <strong>' + error.field + ':</strong> ' + error.message + '</li>';
                            });
                            
                            html += '</ul>';
                            html += '</div>';
                        });
                        
                        html += '</div>';
                        
                        $('#gsc-error-results').html(html);
                    }
                } else {
                    $('#gsc-error-results').html(
                        '<div class="notice notice-error"><p><strong>❌ Scan failed:</strong> ' + response.data + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false);
                $('#gsc-scan-loading').hide();
                $('#gsc-error-results').html(
                    '<div class="notice notice-error"><p><strong>❌ Error:</strong> Unable to scan products. Please try again.</p></div>'
                );
            }
        });
    });
    
    // v4.0.5 - Auto-Fix Errors
    $('#gsc-fix-errors').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to automatically fix detected errors? This will modify product data.')) {
            return;
        }
        
        var button = $(this);
        
        // Show loading state
        button.prop('disabled', true);
        button.html('<span class="spinner is-active"></span> Fixing...');
        
        $.ajax({
            url: gscAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'gsc_fix_errors',
                nonce: gscAjax.nonce
            },
            success: function(response) {
                button.prop('disabled', false);
                button.html('<span class="dashicons dashicons-admin-tools"></span> Auto-Fix Errors');
                
                if (response.success) {
                    var fixed = response.data.fixed;
                    var remaining = response.data.remaining;
                    
                    var html = '<div class="notice notice-success">' +
                        '<p><strong>✅ Fixed ' + fixed + ' products!</strong></p>';
                    
                    if (remaining > 0) {
                        html += '<p>⚠️ ' + remaining + ' products still have errors that require manual fixing.</p>';
                    } else {
                        html += '<p>🎉 All errors have been fixed!</p>';
                        button.hide();
                    }
                    
                    html += '</div>';
                    
                    $('#gsc-error-results').prepend(html);
                    
                    // Reload analytics to show updated error count
                    if ($('#gsc-analytics-dashboard').length) {
                        loadAnalytics();
                    }
                    
                    // Show global success status
                    $('#gsc-global-status').removeClass('gsc-status-saving gsc-status-error').addClass('gsc-status-success').show();
                    $('#gsc-global-status .gsc-status-text').text('✓ Errors fixed successfully');
                    $('#gsc-global-status .dashicons').removeClass('dashicons-update dashicons-no').addClass('dashicons-saved');
                    
                    // Scroll to top
                    $('html, body').animate({
                        scrollTop: $('#gsc-error-results').offset().top - 100
                    }, 500);
                } else {
                    $('#gsc-error-results').prepend(
                        '<div class="notice notice-error"><p><strong>❌ Fix failed:</strong> ' + response.data + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false);
                button.html('<span class="dashicons dashicons-admin-tools"></span> Auto-Fix Errors');
                $('#gsc-error-results').prepend(
                    '<div class="notice notice-error"><p><strong>❌ Error:</strong> Unable to fix errors. Please try again.</p></div>'
                );
            }
        });
    });
});
