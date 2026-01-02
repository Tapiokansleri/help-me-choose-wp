jQuery(document).ready(function($) {
    // Tab navigation functionality
    $('.amv-tab-link').on('click', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');
        
        // Remove active class from all tabs
        $('.amv-tab-link').removeClass('nav-tab-active');
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Hide all tab panels
        $('.amv-tab-panel').hide();
        // Show target tab panel
        $('#amv-tab-' + targetTab).show();
        
        // Save active tab to localStorage
        localStorage.setItem('amv_active_tab', targetTab);
    });
    
    // Check for tab parameter in URL
    var urlParams = new URLSearchParams(window.location.search);
    var urlTab = urlParams.get('tab');
    
    // Check if there's already an active tab (set by PHP based on whether steps are empty)
    var $activeTab = $('.amv-tab-link.nav-tab-active');
    var defaultTab = $activeTab.length ? $activeTab.data('tab') : null;
    
    // Restore active tab from URL parameter, localStorage, or use default from PHP
    var savedTab = urlTab || localStorage.getItem('amv_active_tab') || defaultTab;
    if (savedTab) {
        var $savedTabLink = $('.amv-tab-link[data-tab="' + savedTab + '"]');
        // Only restore if the tab exists (e.g., statistics tab might be hidden if tracking is disabled)
        if ($savedTabLink.length) {
            // Only trigger click if it's not already active (to avoid unnecessary re-rendering)
            if (!$savedTabLink.hasClass('nav-tab-active')) {
                $savedTabLink.trigger('click');
            }
        } else {
            // If saved tab doesn't exist, activate first tab
            $('.amv-tab-link').first().trigger('click');
        }
    } else {
        // If no saved tab and no default, activate first tab
        $('.amv-tab-link').first().trigger('click');
    }
    
    if (typeof amvAdmin === 'undefined') {
        return;
    }
    
    var stepCounter = amvAdmin.stepCounter || 1;
    var optionCounter = {};
    var strings = amvAdmin.strings || {};
    
    // Initialize option counters for existing steps
    $('.amv-step-config').each(function() {
        var stepId = $(this).data('step-id');
        var optionCount = $(this).find('.amv-option-config').length;
        optionCounter[stepId] = optionCount + 1;
    });
    
    // Update step numbers on page load to ensure sequential numbering
    updateStepNumbers();
    
    // Update target step selects when steps change
    function updateTargetStepSelects() {
        var steps = {};
        $('.amv-step-config').each(function() {
            var stepId = $(this).data('step-id');
            var stepTitle = $(this).find('input[name*="[title]"]').val() || stepId;
            steps[stepId] = stepTitle;
        });
        
        $('.amv-target-step-select').each(function() {
            var currentStepId = $(this).closest('.amv-step-config').data('step-id');
            var currentValue = $(this).val();
            var html = '<option value="">' + (strings.select_target_step || '-- Select Target Step --') + '</option>';
            $.each(steps, function(id, title) {
                if (id !== currentStepId) {
                    var selected = currentValue === id ? 'selected' : '';
                    html += '<option value="' + id + '" ' + selected + '>' + title + '</option>';
                }
            });
            var recSelected = currentValue === 'RECOMMENDATION' ? 'selected' : '';
            html += '<option value="RECOMMENDATION" ' + recSelected + '>' + strings.jump_to_recommendation + '</option>';
            $(this).html(html).val(currentValue);
        });
    }
    
    // Initialize target step selects on page load
    updateTargetStepSelects();
    
    // Initialize recommendation checkboxes on page load
    updateRecommendationCheckboxes();
    
    // Function to update step numbers sequentially
    function updateStepNumbers() {
        $('#amv-steps-list .amv-step-config').each(function(index) {
            var stepNum = index + 1;
            $(this).find('.step-number').text(stepNum);
        });
    }
    
    // Add step
    $('#amv-add-step').on('click', function() {
        var stepId = 'step_' + stepCounter++;
        var currentStepCount = $('#amv-steps-list .amv-step-config').length;
        var stepHtml = '<div class="amv-step-config amv-step-collapsed" data-step-id="' + stepId + '">' +
            '<div class="amv-step-header">' +
            '<h3>' +
            '<button type="button" class="amv-toggle-step" aria-expanded="false">' +
            '<span class="dashicons dashicons-arrow-right-alt2"></span>' +
            '</button>' +
            strings.step + ' <span class="step-number">' + (currentStepCount + 1) + '</span>' +
            '</h3>' +
            '<button type="button" class="button-link amv-remove-step">' + strings.remove + '</button>' +
            '</div>' +
            '<div class="amv-step-content" style="display: none;">' +
            '<table class="form-table">' +
            '<tr><th><label>' + strings.step_title + '</label></th>' +
            '<td><input type="text" name="steps[' + stepId + '][title]" value="" class="regular-text" placeholder="' + strings.enter_step_title + '"></td></tr>' +
            '<tr><th><label>' + strings.options + '</label></th>' +
            '<td><div class="amv-options-list"></div>' +
            '<button type="button" class="button amv-add-option">' + strings.add_option + '</button></td></tr>' +
            '</table></div></div>';
        $('#amv-steps-list').append(stepHtml);
        updateTargetStepSelects();
        updateStepNumbers();
        
        // Initialize toggle for new step
        initStepToggle($('#amv-steps-list .amv-step-config').last());
    });
    
    // Remove step
    $(document).on('click', '.amv-remove-step', function() {
        $(this).closest('.amv-step-config').remove();
        updateTargetStepSelects();
        updateStepNumbers();
    });
    
    // Add option
    $(document).on('click', '.amv-add-option', function() {
        var stepId = $(this).closest('.amv-step-config').data('step-id');
        if (!optionCounter[stepId]) optionCounter[stepId] = 1;
        var optId = 'opt_' + optionCounter[stepId]++;
        var recCheckboxes = getRecommendationCheckboxes(stepId, optId);
        var optHtml = '<div class="amv-option-config">' +
            '<div class="amv-option-image-section">' +
            '<div class="amv-option-image-preview" style="display:none;"></div>' +
            '<input type="hidden" name="steps[' + stepId + '][options][' + optId + '][image_id]" class="amv-option-image-id" value="">' +
            '<button type="button" class="button amv-upload-image-btn">' + strings.add_image + '</button>' +
            '<button type="button" class="button amv-remove-image-btn" style="display:none;">' + strings.remove_image + '</button>' +
            '</div>' +
            '<input type="text" name="steps[' + stepId + '][options][' + optId + '][label]" value="" placeholder="' + strings.option_label + '" class="regular-text">' +
            '<textarea name="steps[' + stepId + '][options][' + optId + '][description]" rows="2" class="large-text" placeholder="' + (strings.option_description || 'Option description (optional)') + '" style="margin-top: 5px;"></textarea>' +
            '<select name="steps[' + stepId + '][options][' + optId + '][target_step]" class="amv-target-step-select" style="margin-top: 5px;" required>' +
            '<option value="" selected>' + (strings.select_target_step || '-- Select Target Step --') + '</option>' +
            '<option value="RECOMMENDATION">' + strings.jump_to_recommendation + '</option></select>' +
            '<div class="amv-recommendations-selector">' +
            '<label class="amv-recommendations-label">' + strings.recommendations_label + '</label>' +
            '<div class="amv-recommendations-checkboxes">' + recCheckboxes + '</div>' +
            '</div>' +
            '<button type="button" class="button-link amv-remove-option">' + strings.remove + '</button>' +
            '</div>';
        $(this).siblings('.amv-options-list').append(optHtml);
        updateTargetStepSelects();
        
        // Hide recommendations selector for new option (default is sequential)
        var $newOption = $(this).siblings('.amv-options-list').find('.amv-option-config').last();
        $newOption.find('.amv-recommendations-selector').hide();
        
        // Initialize image upload for new option
        initImageUpload($newOption);
    });
    
    // Image upload functionality
    function initImageUpload($optionConfig) {
        var $uploadBtn = $optionConfig.find('.amv-upload-image-btn');
        var $removeBtn = $optionConfig.find('.amv-remove-image-btn');
        var $imageId = $optionConfig.find('.amv-option-image-id');
        var $imagePreview = $optionConfig.find('.amv-option-image-preview');
        
        $uploadBtn.off('click').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: strings.select_image,
                button: {
                    text: strings.use_image
                },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $imageId.val(attachment.id);
                $imagePreview.html('<img src="' + attachment.url + '" alt="" style="max-width: 100px; height: auto;">').show();
                $uploadBtn.hide();
                $removeBtn.show();
            });
            
            frame.open();
        });
        
        $removeBtn.off('click').on('click', function(e) {
            e.preventDefault();
            $imageId.val('');
            $imagePreview.hide().empty();
            $uploadBtn.show();
            $removeBtn.hide();
        });
    }
    
    // Initialize image upload for existing options
    $('.amv-option-config').each(function() {
        initImageUpload($(this));
    });
    
    function getRecommendationCheckboxes(stepId, optId) {
        var html = '';
        $('.amv-recommendation-config').each(function() {
            var recId = $(this).data('rec-id');
            var recTitle = $(this).find('input[name*="[title]"]').val() || recId;
            html += '<label class="amv-rec-checkbox-label">' +
                '<input type="checkbox" name="steps[' + stepId + '][options][' + optId + '][recommendations][]" value="' + recId + '"> ' +
                recTitle +
                '</label>';
        });
        if (!html) {
            html = '<span class="description">' + strings.no_recommendations + '</span>';
        }
        return html;
    }
    
    // Update recommendation checkboxes when recommendations change
    function updateRecommendationCheckboxes() {
        $('.amv-option-config').each(function() {
            var $config = $(this);
            var $labelInput = $config.find('input[name*="[label]"]');
            if ($labelInput.length) {
                var nameAttr = $labelInput.attr('name');
                var nameMatch = nameAttr.match(/steps\[([^\]]+)\]\[options\]\[([^\]]+)\]/);
                if (nameMatch && nameMatch.length >= 3) {
                    var stepId = nameMatch[1];
                    var optId = nameMatch[2];
                    var $checkboxes = $config.find('.amv-recommendations-checkboxes');
                    if ($checkboxes.length) {
                        // Preserve checked state
                        var checkedIds = [];
                        $checkboxes.find('input[type="checkbox"]:checked').each(function() {
                            checkedIds.push($(this).val());
                        });
                        
                        // Update checkboxes
                        $checkboxes.html(getRecommendationCheckboxes(stepId, optId));
                        
                        // Restore checked state
                        checkedIds.forEach(function(recId) {
                            $checkboxes.find('input[type="checkbox"][value="' + recId + '"]').prop('checked', true);
                        });
                    }
                }
            }
        });
    }
    
    // Update selects when step titles change
    $(document).on('input', 'input[name*="[title]"]', function() {
        updateTargetStepSelects();
    });
    
    // Show/hide recommendation checkboxes based on target step selection
    $(document).on('change', '.amv-target-step-select', function() {
        var $optionConfig = $(this).closest('.amv-option-config');
        var $recommendationsSelector = $optionConfig.find('.amv-recommendations-selector');
        
        if ($(this).val() === 'RECOMMENDATION') {
            $recommendationsSelector.slideDown(200);
        } else {
            $recommendationsSelector.slideUp(200);
        }
    });
    
    // Initialize visibility on page load
    $('.amv-target-step-select').each(function() {
        var $optionConfig = $(this).closest('.amv-option-config');
        var $recommendationsSelector = $optionConfig.find('.amv-recommendations-selector');
        
        if ($(this).val() === 'RECOMMENDATION') {
            $recommendationsSelector.show();
        } else {
            $recommendationsSelector.hide();
        }
    });
    
    // Remove option
    $(document).on('click', '.amv-remove-option', function() {
        $(this).closest('.amv-option-config').remove();
    });
    
    // Add recommendation
    $('#amv-add-recommendation').on('click', function() {
        var recId = 'rec_' + Date.now();
        var postTypesHtml = '<option value="">' + (strings.select_post_type || '-- Select Post Type --') + '</option>';
        if (amvAdmin.postTypes) {
            amvAdmin.postTypes.forEach(function(pt) {
                postTypesHtml += '<option value="' + pt.value + '">' + pt.label + '</option>';
            });
        }
        var recHtml = '<div class="amv-recommendation-config" data-rec-id="' + recId + '">' +
            '<div class="amv-rec-header">' +
            '<input type="text" name="recommendations[' + recId + '][title]" value="" placeholder="' + strings.recommendation_title + '" class="regular-text">' +
            '<button type="button" class="button-link amv-remove-recommendation">' + strings.remove + '</button>' +
            '</div>' +
            '<table class="form-table">' +
            '<tr><th><label>' + (strings.description || 'Description') + '</label></th>' +
            '<td><textarea name="recommendations[' + recId + '][content]" rows="4" class="large-text" placeholder="' + (strings.recommendation_description || 'Recommendation description (supports shortcodes)') + '"></textarea>' +
            '<p class="description">' + (strings.recommendation_description_help || 'Optional description text for this recommendation bundle. You can use shortcodes here.') + '</p></td></tr>' +
            '<tr><th><label>' + (strings.select_content_items || 'Select Content Items') + '</label></th>' +
            '<td><div class="amv-post-selector">' +
            '<select class="amv-post-type-select" style="margin-bottom: 10px;">' + postTypesHtml + '</select>' +
            '<input type="text" class="amv-post-search regular-text" placeholder="' + (strings.search_content || 'Search for pages, products, or posts...') + '" style="margin-bottom: 10px;">' +
            '<div class="amv-post-search-results" style="display:none;"></div></div>' +
            '<div class="amv-selected-content-list" style="margin-top: 15px;">' +
            '<p><strong>' + (strings.selected_items || 'Selected Items:') + '</strong></p>' +
            '<ul class="amv-content-items-list" style="list-style: none; padding: 0; margin: 10px 0;">' +
            '<li class="amv-no-items" style="padding: 8px; color: #666; font-style: italic;">' + (strings.no_items_selected || 'No items selected. Search and add items above.') + '</li>' +
            '</ul></div>' +
            '<p class="description">' + (strings.select_content_items_help || 'Search and select multiple pages, products, or posts to include in this recommendation bundle.') + '</p></td></tr>' +
            '</table></div>';
        $('#amv-recommendations-list').append(recHtml);
        initRecommendationConfig($('#amv-recommendations-list .amv-recommendation-config').last());
        updateRecommendationCheckboxes();
    });
    
    // Initialize recommendation config for bundles
    function initRecommendationConfig($config) {
        var $postTypeSelect = $config.find('.amv-post-type-select');
        var $postSearch = $config.find('.amv-post-search');
        var $itemsList = $config.find('.amv-content-items-list');
        
        // Handle post type selection
        $postTypeSelect.off('change').on('change', function() {
            var postType = $(this).val();
            $postSearch.data('post-type', postType);
            if (postType) {
                $postSearch.focus();
            }
        });
        
        // Post search with autocomplete
        var searchTimeout;
        $postSearch.off('input').on('input', function() {
            var $this = $(this);
            var searchTerm = $this.val();
            var postType = $this.data('post-type') || '';
            
            clearTimeout(searchTimeout);
            if (searchTerm.length < 2) {
                $config.find('.amv-post-search-results').hide().empty();
                return;
            }
            
            if (!postType) {
                alert(strings.select_post_type_first || 'Please select a post type first');
                $this.val('');
                return;
            }
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: amvAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'amv_search_posts',
                        search: searchTerm,
                        post_type: postType,
                        nonce: amvAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var html = '<ul class="amv-search-results-list" style="list-style: none; padding: 0; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto;">';
                            response.data.forEach(function(post) {
                                html += '<li data-id="' + post.id + '" data-type="' + postType + '" style="padding: 8px; cursor: pointer; border-bottom: 1px solid #eee;" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">' + post.title + '</li>';
                            });
                            html += '</ul>';
                            $config.find('.amv-post-search-results').html(html).show();
                        } else {
                            $config.find('.amv-post-search-results').html('<p style="padding: 10px;">' + strings.no_results + '</p>').show();
                        }
                    }
                });
            }, 300);
        });
        
        // Handle result selection - add to list
        $config.off('click', '.amv-search-results-list li').on('click', '.amv-search-results-list li', function() {
            var postId = $(this).data('id');
            var postTitle = $(this).text();
            var postType = $(this).data('type');
            var recId = $config.data('rec-id');
            
            // Check if already added
            if ($itemsList.find('li[data-post-id="' + postId + '"]').length > 0) {
                alert(strings.item_already_added || 'This item is already in the list');
                return;
            }
            
            // Get post type label
            var postTypeLabel = postType;
            if (amvAdmin.postTypes) {
                amvAdmin.postTypes.forEach(function(pt) {
                    if (pt.value === postType) {
                        postTypeLabel = pt.label;
                    }
                });
            }
            
            // Remove "no items" message if exists
            $itemsList.find('.amv-no-items').remove();
            
            // Add to list
            var itemHtml = '<li class="amv-content-item" data-post-id="' + postId + '" style="padding: 8px; margin: 5px 0; background: #f5f5f5; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">' +
                '<span>' + postTitle + ' <em>(' + postTypeLabel + ')</em></span>' +
                '<input type="hidden" name="recommendations[' + recId + '][content_ids][]" value="' + postId + '">' +
                '<button type="button" class="button-link amv-remove-content-item" style="color: #dc3232;">' + strings.remove + '</button>' +
                '</li>';
            $itemsList.append(itemHtml);
            
            // Clear search
            $postSearch.val('');
            $config.find('.amv-post-search-results').hide();
        });
        
        // Remove content item
        $config.off('click', '.amv-remove-content-item').on('click', '.amv-remove-content-item', function() {
            $(this).closest('li').remove();
            // Show "no items" message if list is empty
            if ($itemsList.find('li.amv-content-item').length === 0) {
                $itemsList.append('<li class="amv-no-items" style="padding: 8px; color: #666; font-style: italic;">' + (strings.no_items_selected || 'No items selected. Search and add items above.') + '</li>');
            }
        });
    }
    
    // Initialize existing recommendations
    $('.amv-recommendation-config').each(function() {
        initRecommendationConfig($(this));
    });
    
    // Step toggle functionality
    function initStepToggle($stepConfig) {
        var $toggle = $stepConfig.find('.amv-toggle-step');
        var $content = $stepConfig.find('.amv-step-content');
        var $header = $stepConfig.find('.amv-step-header');
        var $titleInput = $stepConfig.find('input[name*="[title]"]');
        
        // Update title preview when title changes
        $titleInput.off('input').on('input', function() {
            var title = $(this).val();
            var $preview = $header.find('.step-title-preview');
            if (title) {
                if ($preview.length) {
                    $preview.text(': ' + title);
                } else {
                    $header.find('h3').append('<span class="step-title-preview">: ' + title + '</span>');
                }
            } else {
                $preview.remove();
            }
        });
        
        // Toggle function
        function toggleStep() {
            var isExpanded = $toggle.attr('aria-expanded') === 'true';
            if (isExpanded) {
                $content.slideUp(200);
                $toggle.attr('aria-expanded', 'false');
                $toggle.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                $stepConfig.addClass('amv-step-collapsed');
            } else {
                $content.slideDown(200);
                $toggle.attr('aria-expanded', 'true');
                $toggle.find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                $stepConfig.removeClass('amv-step-collapsed');
            }
        }
        
        // Make entire header clickable (except remove button)
        $header.off('click').on('click', function(e) {
            if (!$(e.target).closest('.amv-remove-step').length && !$(e.target).closest('button').length) {
                toggleStep();
            }
        });
        
        // Also keep toggle button clickable
        $toggle.off('click').on('click', function(e) {
            e.stopPropagation();
            toggleStep();
        });
    }
    
    // Initialize toggles for existing steps
    $('.amv-step-config').each(function() {
        initStepToggle($(this));
    });
    
    // Expand all steps
    $('#amv-expand-all-steps').on('click', function() {
        $('.amv-step-config').each(function() {
            var $step = $(this);
            var $toggle = $step.find('.amv-toggle-step');
            var $content = $step.find('.amv-step-content');
            if ($toggle.attr('aria-expanded') === 'false') {
                $content.slideDown(200);
                $toggle.attr('aria-expanded', 'true');
                $toggle.find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                $step.removeClass('amv-step-collapsed');
            }
        });
    });
    
    // Collapse all steps
    $('#amv-collapse-all-steps').on('click', function() {
        $('.amv-step-config').each(function() {
            var $step = $(this);
            var $toggle = $step.find('.amv-toggle-step');
            var $content = $step.find('.amv-step-content');
            if ($toggle.attr('aria-expanded') === 'true') {
                $content.slideUp(200);
                $toggle.attr('aria-expanded', 'false');
                $toggle.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                $step.addClass('amv-step-collapsed');
            }
        });
    });
    
    // Remove recommendation
    $(document).on('click', '.amv-remove-recommendation', function() {
        $(this).closest('.amv-recommendation-config').remove();
        updateRecommendationCheckboxes();
    });
    
    // Create database table
    $(document).on('click', '#amv-create-table', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('Creating...');
        
        $.ajax({
            url: amvAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_create_table',
                nonce: amvAdmin.createTableNonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error creating table');
                    $button.prop('disabled', false).text('Create Table Now');
                }
            },
            error: function() {
                alert('Error creating table');
                $button.prop('disabled', false).text('Create Table Now');
            }
        });
    });
    
    // Reset database handler
    $(document).on('click', '#amv-reset-database', function() {
        if (!confirm('Are you sure you want to reset all tracking data? This action cannot be undone!')) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text('Resetting...');
        
        $.ajax({
            url: amvAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_reset_database',
                nonce: amvAdmin.resetDatabaseNonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Database reset successfully!');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error resetting database');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('Error resetting database');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Copy shortcode to clipboard
    $(document).on('click', '#amv-copy-shortcode', function() {
        var $input = $('#amv-shortcode-input');
        var $message = $('#amv-copy-message');
        
        // Select the text
        $input.select();
        $input[0].setSelectionRange(0, 99999); // For mobile devices
        
        try {
            // Copy to clipboard
            document.execCommand('copy');
            
            // Show success message
            $message.fadeIn(200);
            setTimeout(function() {
                $message.fadeOut(200);
            }, 2000);
            
            // Change button text temporarily
            var $button = $(this);
            var originalText = $button.text();
            $button.text('Copied!');
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        } catch (err) {
            // Fallback: try modern clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText('[auta_minua_valitsemaan]').then(function() {
                    $message.fadeIn(200);
                    setTimeout(function() {
                        $message.fadeOut(200);
                    }, 2000);
                    
                    var $button = $('#amv-copy-shortcode');
                    var originalText = $button.text();
                    $button.text('Copied!');
                    setTimeout(function() {
                        $button.text(originalText);
                    }, 2000);
                });
            } else {
                alert('Please manually copy the shortcode: [auta_minua_valitsemaan]');
            }
        }
    });
    
    // Export configuration
    $(document).on('click', '#amv-export-config', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text(strings.exporting || 'Exporting...');
        
        $.ajax({
            url: amvAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_export_config',
                nonce: amvAdmin.exportConfigNonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.json) {
                    // Create blob and download
                    var blob = new Blob([response.data.json], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'amv-config-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    // Show success message
                    alert(strings.export_success || 'Configuration exported successfully!');
                } else {
                    alert(strings.export_error || 'Failed to export configuration.');
                }
            },
            error: function() {
                alert(strings.export_error || 'Failed to export configuration.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Import configuration
    $(document).on('click', '#amv-import-config', function() {
        var $button = $(this);
        var $textarea = $('#amv-import-json');
        var $message = $('#amv-import-message');
        var jsonInput = $textarea.val().trim();
        
        if (!jsonInput) {
            $message.removeClass('notice-success').addClass('notice notice-error').html('<p>' + (strings.import_no_data || 'Please paste JSON configuration data.') + '</p>').show();
            return;
        }
        
        if (!confirm(strings.import_confirm || 'This will replace your current configuration. Are you sure?')) {
            return;
        }
        
        var originalText = $button.text();
        $button.prop('disabled', true).text(strings.importing || 'Importing...');
        $message.hide();
        
        $.ajax({
            url: amvAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_import_config',
                nonce: amvAdmin.importConfigNonce,
                json: jsonInput
            },
            success: function(response) {
                if (response.success) {
                    var message = response.data.message || (strings.import_success || 'Configuration imported successfully!');
                    if (response.data.steps_count !== undefined) {
                        message += '<br>' + (strings.steps_imported || 'Steps imported:') + ' ' + response.data.steps_count;
                    }
                    if (response.data.recommendations_count !== undefined) {
                        message += '<br>' + (strings.recommendations_imported || 'Recommendations imported:') + ' ' + response.data.recommendations_count;
                    }
                    $message.removeClass('notice-error').addClass('notice notice-success').html('<p>' + message + '</p>').show();
                    
                    // Reload page after 2 seconds to show updated configuration
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : (strings.import_error || 'Failed to import configuration.');
                    $message.removeClass('notice-success').addClass('notice notice-error').html('<p>' + errorMsg + '</p>').show();
                }
            },
            error: function(xhr) {
                var errorMsg = strings.import_error || 'Failed to import configuration.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                }
                $message.removeClass('notice-success').addClass('notice notice-error').html('<p>' + errorMsg + '</p>').show();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Generate starter pack
    $(document).on('click', '#amv-generate-starter-pack', function() {
        var $button = $(this);
        var originalText = $button.text();
        $button.prop('disabled', true).text(strings.generating_starter_pack || 'Generating...');
        
        $.ajax({
            url: amvAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_generate_starter_pack',
                nonce: amvAdmin.generateStarterPackNonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.json) {
                    // Create blob and download
                    var blob = new Blob([response.data.json], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'amv-starter-pack-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    // Show success message
                    alert(strings.starter_pack_success || 'Starter pack generated successfully!');
                } else {
                    alert(strings.starter_pack_error || 'Failed to generate starter pack.');
                }
            },
            error: function() {
                alert(strings.starter_pack_error || 'Failed to generate starter pack.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Wizard: Generate starter pack
    $(document).on('click', '#amv-wizard-generate-pack', function() {
        var $button = $(this);
        var $status = $('#amv-wizard-step1-status');
        var $step2 = $('.amv-wizard-step[data-step="2"]');
        var originalText = $button.text();
        $button.prop('disabled', true).text(strings.generating_starter_pack || 'Generating...');
        
        $.ajax({
            url: amvAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_generate_starter_pack',
                nonce: amvAdmin.generateStarterPackNonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.json) {
                    // Create blob and download
                    var blob = new Blob([response.data.json], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'amv-starter-pack-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    // Show success and activate step 2
                    $status.show();
                    $step2.css({
                        'border-color': '#0073aa',
                        'background': '#f0f7fc'
                    });
                    $step2.find('div[style*="background: #ddd"]').css({
                        'background': '#0073aa',
                        'color': '#fff'
                    });
                } else {
                    alert(strings.starter_pack_error || 'Failed to generate starter pack.');
                }
            },
            error: function() {
                alert(strings.starter_pack_error || 'Failed to generate starter pack.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Wizard: Import configuration
    $(document).on('click', '#amv-wizard-import-config', function() {
        var $button = $(this);
        var $textarea = $('#amv-wizard-import-json');
        var $message = $('#amv-wizard-import-message');
        var $step3 = $('.amv-wizard-step[data-step="3"]');
        var $step4 = $('.amv-wizard-step[data-step="4"]');
        var jsonInput = $textarea.val().trim();
        
        if (!jsonInput) {
            $message.removeClass('notice-success').addClass('notice notice-error').html('<p>' + (strings.import_no_data || 'Please paste JSON configuration data.') + '</p>').show();
            return;
        }
        
        if (!confirm(strings.import_confirm || 'This will replace your current configuration. Are you sure?')) {
            return;
        }
        
        var originalText = $button.text();
        $button.prop('disabled', true).text(strings.importing || 'Importing...');
        $message.hide();
        
        $.ajax({
            url: amvAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_import_config',
                nonce: amvAdmin.importConfigNonce,
                json: jsonInput
            },
            success: function(response) {
                if (response.success) {
                    var message = response.data.message || (strings.import_success || 'Configuration imported successfully!');
                    if (response.data.steps_count !== undefined) {
                        message += '<br>' + (strings.steps_imported || 'Steps imported:') + ' ' + response.data.steps_count;
                    }
                    if (response.data.recommendations_count !== undefined) {
                        message += '<br>' + (strings.recommendations_imported || 'Recommendations imported:') + ' ' + response.data.recommendations_count;
                    }
                    $message.removeClass('notice-error').addClass('notice notice-success').html('<p>' + message + '</p>').show();
                    
                    // Activate step 4
                    $step3.css({
                        'border-color': '#0073aa',
                        'background': '#f0f7fc'
                    });
                    $step3.find('div[style*="background: #ddd"]').css({
                        'background': '#0073aa',
                        'color': '#fff'
                    });
                    $step4.css({
                        'border-color': '#46b450',
                        'background': '#f0fcf0'
                    });
                    $step4.find('div[style*="background: #ddd"]').css({
                        'background': '#46b450',
                        'color': '#fff'
                    });
                    $('#amv-wizard-step4-success').show();
                    
                    // Reload page after 2 seconds to show updated configuration
                    setTimeout(function() {
                        window.location.href = window.location.pathname + '?page=auta-minua-valitsemaan&tab=wizard';
                    }, 2000);
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : (strings.import_error || 'Failed to import configuration.');
                    $message.removeClass('notice-success').addClass('notice notice-error').html('<p>' + errorMsg + '</p>').show();
                }
            },
            error: function(xhr) {
                var errorMsg = strings.import_error || 'Failed to import configuration.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                }
                $message.removeClass('notice-success').addClass('notice notice-error').html('<p>' + errorMsg + '</p>').show();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Style preset selector
    $('#amv-style-preset').on('change', function() {
        var preset = $(this).val();
        if (preset === 'custom') {
            $('#amv-custom-styles').slideDown();
        } else {
            $('#amv-custom-styles').slideUp();
        }
    });
});

