jQuery(document).ready(function($) {
    if (typeof amvFrontend === 'undefined') {
        return;
    }
    
    var config = amvFrontend.config || {};
    var strings = amvFrontend.strings || {};
    var isAdmin = amvFrontend.isAdmin || false;
    var styles = amvFrontend.styles || {};
    var trackNonce = amvFrontend.trackNonce || '';
    var trackingEnabled = amvFrontend.trackingEnabled !== undefined ? amvFrontend.trackingEnabled : '1';
    var debugEnabled = amvFrontend.debugEnabled !== undefined ? amvFrontend.debugEnabled : '1';
    
    // Track usage function
    function trackUsage(status, stepsCompleted) {
        // Check if tracking is enabled
        if (trackingEnabled !== '1') {
            console.log('AMV Tracking: Disabled');
            return;
        }
        
        // Get userId dynamically in case it wasn't initialized yet
        var currentUserId = getUserId();
        if (!currentUserId || !trackNonce) {
            console.log('AMV Tracking: Missing userId or nonce', {userId: currentUserId, trackNonce: trackNonce});
            return;
        }
        
        var formState = getFormState();
        
        $.ajax({
            url: amvFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'amv_track_usage',
                user_id: currentUserId,
                status: status,
                steps_completed: stepsCompleted,
                form_state: JSON.stringify(formState),
                nonce: trackNonce
            },
            success: function(response) {
                // Silently track, no user feedback needed
                console.log('AMV Tracking: Success', {status: status, stepsCompleted: stepsCompleted});
            },
            error: function(xhr, status, error) {
                // Log error for debugging but don't interrupt user experience
                console.log('AMV Tracking: Error', {status: status, error: error, response: xhr.responseText});
            }
        });
    }
    
    // Apply custom styles
    function applyCustomStyles() {
        // Only apply custom styles if style_preset is 'custom'
        var stylePreset = (styles && styles.style_preset) ? styles.style_preset : 'custom';
        if (stylePreset !== 'custom') {
            // Preset styles are handled by CSS files, skip JavaScript application
            return;
        }
        
        if (!styles || Object.keys(styles).length === 0) {
            // Apply defaults if no styles
            $('.amv-form-container').css({
                'width': '100%',
                'padding': '0'
            });
            return;
        }
        
        // Container styles
        var containerWidth = ((styles.container_width !== undefined) ? styles.container_width : 100) + ((styles.container_width_unit !== undefined) ? styles.container_width_unit : '%');
        var containerPadding = ((styles.container_padding !== undefined) ? styles.container_padding : 0) + 'px';
        
        $('.amv-form-container').css({
            'width': containerWidth,
            'max-width': styles.container_width_unit === 'px' ? containerWidth : 'none',
            'padding': containerPadding
        });
        
        // Step styles
        var stepBorderEnabled = styles.step_border_enabled !== '0';
        var stepBorderColor = (styles.step_border_color !== undefined) ? styles.step_border_color : '#e0e0e0';
        var stepBorderWidth = ((styles.step_border_width !== undefined) ? styles.step_border_width : 2) + 'px';
        var stepBorderRadius = ((styles.step_border_radius !== undefined) ? styles.step_border_radius : 8) + 'px';
        var stepBgColor = (styles.step_bg_color !== undefined) ? styles.step_bg_color : '#ffffff';
        var stepTextColor = (styles.step_text_color !== undefined) ? styles.step_text_color : '#000000';
        var stepPadding = ((styles.step_padding !== undefined) ? styles.step_padding : 0) + 'px';
        
        // Option styles
        var optionBorderEnabled = styles.option_border_enabled !== '0';
        var optionBorderColor = (styles.option_border_color !== undefined) ? styles.option_border_color : '#e0e0e0';
        var optionBorderWidth = ((styles.option_border_width !== undefined) ? styles.option_border_width : 1) + 'px';
        var optionBorderRadius = ((styles.option_border_radius !== undefined) ? styles.option_border_radius : 8) + 'px';
        var optionBgColor = (styles.option_bg_color !== undefined) ? styles.option_bg_color : '#ffffff';
        var optionHoverBgColor = (styles.option_hover_bg_color !== undefined) ? styles.option_hover_bg_color : '#f5f5f5';
        var optionTextColor = (styles.option_text_color !== undefined) ? styles.option_text_color : '#000000';
        var optionPadding = ((styles.option_padding !== undefined) ? styles.option_padding : 0) + 'px';
        
        // Image size
        var imageSize = (styles.image_size || 300) + 'px';
        
        // Apply step styles
        $('.amv-step').css({
            'border': stepBorderEnabled ? stepBorderWidth + ' solid ' + stepBorderColor : 'none',
            'border-radius': stepBorderRadius,
            'background-color': stepBgColor,
            'color': stepTextColor,
            'padding': stepPadding
        });
        
        $('.amv-step-title').css({
            'color': stepTextColor
        });
        
        // Apply option styles
        $('.amv-option').css({
            'border': optionBorderEnabled ? optionBorderWidth + ' solid ' + optionBorderColor : 'none',
            'border-radius': optionBorderRadius,
            'background-color': optionBgColor,
            'color': optionTextColor,
            'padding': optionPadding
        });
        
        // Apply hover styles
        var hoverStyle = '<style id="amv-custom-hover-styles">' +
            '.amv-option:hover { background-color: ' + optionHoverBgColor + ' !important; }' +
            '</style>';
        if ($('#amv-custom-hover-styles').length === 0) {
            $('head').append(hoverStyle);
        } else {
            $('#amv-custom-hover-styles').html('.amv-option:hover { background-color: ' + optionHoverBgColor + ' !important; }');
        }
        
        // Apply image size (with mobile constraint)
        function applyImageSize() {
            var isMobile = window.innerWidth <= 767;
            $('.amv-option-image img').css({
                'max-width': isMobile ? '100%' : imageSize,
                'max-height': isMobile ? 'none' : imageSize,
                'width': isMobile ? '100%' : 'auto',
                'height': 'auto'
            });
        }
        applyImageSize();
        
        // Reapply on window resize
        $(window).on('resize', function() {
            applyImageSize();
        });
    }
    
    // Cookie name constants
    var COOKIE_USER_ID = 'amv_user_id';
    var COOKIE_FORM_STATE = 'amv_form_state';
    var COOKIE_EXPIRY_DAYS = 365; // Cookie expires in 1 year
    
    // Cookie helper functions
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/';
    }
    
    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1, c.length);
            }
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length, c.length));
            }
        }
        return null;
    }
    
    // Generate unique user ID
    function generateUserId() {
        return 'amv_' + Date.now() + '_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    }
    
    // Get or create user ID
    function getUserId() {
        var userId = getCookie(COOKIE_USER_ID);
        if (!userId) {
            userId = generateUserId();
            setCookie(COOKIE_USER_ID, userId, COOKIE_EXPIRY_DAYS);
        }
        return userId;
    }
    
    // Save form state to cookie
    function saveFormStateToCookie() {
        var state = getFormState();
        var stateJson = JSON.stringify(state);
        setCookie(COOKIE_FORM_STATE, stateJson, COOKIE_EXPIRY_DAYS);
    }
    
    // Get form state from cookie
    function getFormStateFromCookie() {
        var stateJson = getCookie(COOKIE_FORM_STATE);
        if (stateJson) {
            try {
                return JSON.parse(stateJson);
            } catch (e) {
                console.error('Error parsing cookie state:', e);
                return null;
            }
        }
        return null;
    }
    
    // Initialize user ID on page load
    var userId = getUserId();
    
    // Handle selected state for browsers without :has() support
    function updateSelectedState() {
        $('.amv-option').removeClass('amv-option-selected');
        $('.amv-radio-input:checked').closest('.amv-option').addClass('amv-option-selected');
    }
    
    // Mark options with images for CSS fallback
    $('.amv-option').each(function() {
        if ($(this).find('.amv-option-image img').length) {
            $(this).addClass('amv-has-image');
        }
    });
    
    // Set data attribute for option count to enable adaptive grid
    $('.amv-step-options').each(function() {
        var count = $(this).find('.amv-option').length;
        // Cap at 5 for desktop, but set the actual count
        $(this).attr('data-options-count', Math.min(count, 10));
    });
    
    // Function to slugify text for URL (convert to URL-safe format)
    function slugify(text) {
        return text.toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')           // Replace spaces with -
            .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
            .replace(/\-\-+/g, '-')         // Replace multiple - with single -
            .replace(/^-+/, '')             // Trim - from start
            .replace(/-+$/, '');             // Trim - from end
    }
    
    // Function to get current form state with names
    // Only includes visible steps (not hidden ones) to ensure URL reflects current path
    function getFormState() {
        var state = {};
        $('.amv-step').each(function() {
            var $step = $(this);
            // Only include steps that are visible (not hidden)
            if ($step.hasClass('amv-step-hidden')) {
                return; // Skip hidden steps
            }
            var stepTitle = $step.find('.amv-step-title').text().trim();
            var $selected = $step.find('.amv-radio-input:checked');
            if ($selected.length && stepTitle) {
                var optionLabel = $selected.closest('.amv-option').find('.amv-option-label').text().trim();
                if (optionLabel) {
                    state[slugify(stepTitle)] = slugify(optionLabel);
                }
            }
        });
        return state;
    }
    
    // Function to update URL with current form state (readable format with names)
    function updateURL() {
        var state = getFormState();
        var params = new URLSearchParams();
        
        // Add each step selection as a readable query parameter using names
        $.each(state, function(stepName, optionName) {
            params.append(stepName, optionName);
        });
        
        var queryString = params.toString();
        var newURL = window.location.pathname;
        if (queryString) {
            newURL += '?' + queryString;
        }
        
        // Only update if URL actually changed
        if (window.location.search !== '?' + queryString && window.location.pathname + window.location.search !== newURL) {
            window.history.pushState({state: state}, '', newURL);
        }
        
        // Always save to cookie when URL updates
        saveFormStateToCookie();
        
        // Update debug info if admin and debug enabled
        if (isAdmin && debugEnabled === '1') {
            updateDebugInfo();
        }
    }
    
    // Function to restore form state from a state object (used by both URL and cookie restore)
    function restoreFromState(stateObject, skipURLUpdate) {
        if (!stateObject || Object.keys(stateObject).length === 0) {
            return;
        }
        
        var stepsToRestore = [];
        
        // Collect all steps that need to be restored, in order
        $('.amv-step').each(function() {
            var $step = $(this);
            var stepTitle = $step.find('.amv-step-title').text().trim();
            var stepSlug = slugify(stepTitle);
            var optionSlug = stateObject[stepSlug];
            
            if (optionSlug) {
                stepsToRestore.push({
                    $step: $step,
                    stepSlug: stepSlug,
                    optionSlug: optionSlug
                });
            }
        });
        
        if (stepsToRestore.length > 0) {
            // Temporarily disable URL updates during restore
            var originalUpdateURL = updateURL;
            if (skipURLUpdate) {
                updateURL = function() {}; // No-op during restore
            }
            
            // Restore steps sequentially, one at a time
            function restoreStep(index) {
                if (index >= stepsToRestore.length) {
                    // All steps restored, restore updateURL function
                    if (skipURLUpdate) {
                        updateURL = originalUpdateURL;
                        // Update URL after restore completes (but don't save to cookie again to avoid loop)
                        var tempSaveCookie = saveFormStateToCookie;
                        saveFormStateToCookie = function() {}; // Temporarily disable cookie save
                        updateURL();
                        saveFormStateToCookie = tempSaveCookie; // Restore cookie save function
                    }
                    // Trigger scroll after all restorations complete
                    setTimeout(scrollToLastStep, 300);
                    return;
                }
                
                var stepData = stepsToRestore[index];
                var $step = stepData.$step;
                var optionSlug = stepData.optionSlug;
                
                // Check if step is visible (if not, we need to wait)
                if ($step.hasClass('amv-step-hidden')) {
                    // Step not visible yet, wait a bit and try again
                    setTimeout(function() {
                        restoreStep(index);
                    }, 100);
                    return;
                }
                
                // Find option by matching label (slugified)
                var found = false;
                var $options = $step.find('.amv-option');
                $options.each(function() {
                    var $option = $(this);
                    var optionLabel = $option.find('.amv-option-label').text().trim();
                    var optionLabelSlug = slugify(optionLabel);
                    
                    if (optionLabelSlug === optionSlug) {
                        var $radio = $option.find('.amv-radio-input');
                        if ($radio.length && !$radio.is(':checked')) {
                            $radio.prop('checked', true);
                            // Trigger change to update UI and show next steps
                            $radio.trigger('change');
                            found = true;
                            return false; // Break the loop
                        }
                    }
                });
                
                // Move to next step after a delay to let change event process
                setTimeout(function() {
                    restoreStep(index + 1);
                }, 200);
            }
            
            // Start restoring from first step
            restoreStep(0);
        }
    }
    
    // Function to restore form state from URL (without triggering URL updates)
    function restoreFromURL(skipURLUpdate) {
        var urlParams = new URLSearchParams(window.location.search);
        var stateObject = {};
        
        // Build state object from URL parameters
        $('.amv-step').each(function() {
            var $step = $(this);
            var stepTitle = $step.find('.amv-step-title').text().trim();
            var stepSlug = slugify(stepTitle);
            var optionSlug = urlParams.get(stepSlug);
            
            if (optionSlug) {
                stateObject[stepSlug] = optionSlug;
            }
        });
        
        // Restore from state object
        if (Object.keys(stateObject).length > 0) {
            restoreFromState(stateObject, skipURLUpdate);
        }
    }
    
    // Function to scroll to last step or recommendation after URL restore
    function scrollToLastStep() {
        // Wait a bit for all change events to process
        setTimeout(function() {
            // Check if recommendations are visible
            var $recommendationResult = $('#amv-recommendation-result');
            if ($recommendationResult.is(':visible') && $recommendationResult.html().trim() !== '') {
                // Scroll to recommendations
                $('html, body').animate({
                    scrollTop: $recommendationResult.offset().top - 100
                }, 300);
                return;
            }
            
            // Otherwise, scroll to the last visible/active step
            var $lastActiveStep = $('.amv-step-active').last();
            if ($lastActiveStep.length) {
                $('html, body').animate({
                    scrollTop: $lastActiveStep.offset().top - 100
                }, 300);
            } else {
                // Fallback: scroll to first step if no active step found
                var $firstStep = $('.amv-step').first();
                if ($firstStep.length) {
                    $('html, body').animate({
                        scrollTop: $firstStep.offset().top - 100
                    }, 300);
                }
            }
        }, 500); // Delay to ensure all change events have processed
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        restoreFromURL(true); // Skip URL update since browser already changed it
        setTimeout(scrollToLastStep, 500); // Scroll after restore
    });
    
    // Apply custom styles on page load
    applyCustomStyles();
    
    // Track initial page load
    trackUsage('started', 0);
    
    // Track abandonment when user leaves page
    $(window).on('beforeunload', function() {
        // Check if tracking is enabled
        if (trackingEnabled !== '1') {
            return;
        }
        
        var formState = getFormState();
        var hasProgress = Object.keys(formState).length > 0;
        var isCompleted = $('#amv-recommendation-result').is(':visible');
        
        if (hasProgress && !isCompleted) {
            // User has made progress but hasn't completed - mark as abandoned
            var stepsCompleted = Object.keys(formState).length;
            var currentUserId = getUserId();
            // Use navigator.sendBeacon for reliable tracking on page unload
            if (navigator.sendBeacon && currentUserId && trackNonce) {
                var formData = new FormData();
                formData.append('action', 'amv_track_usage');
                formData.append('user_id', currentUserId);
                formData.append('status', 'abandoned');
                formData.append('steps_completed', stepsCompleted);
                formData.append('form_state', JSON.stringify(formState));
                formData.append('nonce', trackNonce);
                navigator.sendBeacon(amvFrontend.ajaxUrl, formData);
            }
        }
    });
    
    // Initial selected state
    updateSelectedState();
    
    // Try to restore from URL on page load (skip URL update to avoid duplicate)
    // Wait for DOM to be fully ready
    $(document).ready(function() {
        // Small delay to ensure all elements are rendered
        setTimeout(function() {
            var urlParams = new URLSearchParams(window.location.search);
            var hasURLState = false;
            $('.amv-step').each(function() {
                var $step = $(this);
                var stepTitle = $step.find('.amv-step-title').text().trim();
                var stepSlug = slugify(stepTitle);
                if (urlParams.has(stepSlug)) {
                    hasURLState = true;
                }
            });
            
            if (hasURLState) {
                // Restore from URL (priority)
                restoreFromURL(true);
            } else {
                // If no URL state, try to restore from cookie
                var cookieState = getFormStateFromCookie();
                if (cookieState && Object.keys(cookieState).length > 0) {
                    restoreFromState(cookieState, true);
                }
            }
        }, 100);
    });
    
    $('.amv-radio-input').on('change', function() {
        var $step = $(this).closest('.amv-step');
        var targetStepId = $(this).data('target-step');
        var recommendationsData = $(this).data('recommendations') || '';
        var isLast = $step.data('is-last') === 1;
        
        // Reset/hide recommendations when any step is changed
        $('#amv-recommendation-result').hide().empty();
        
        // Update selected state
        updateSelectedState();
        
        // Hide all steps after current and uncheck their radios to prevent stale state
        $step.nextAll('.amv-step').each(function() {
            var $nextStep = $(this);
            $nextStep.removeClass('amv-step-active').addClass('amv-step-hidden');
            // Uncheck radios in hidden steps to ensure clean state
            $nextStep.find('.amv-radio-input').prop('checked', false);
        });
        
        // Update URL immediately after hiding steps to reflect current state
        updateURL();
        
        // Track progress
        var currentStepIndex = $step.index() + 1;
        trackUsage('in_progress', currentStepIndex);
        
        // Parse recommendations
        var selectedRecommendations = [];
        if (recommendationsData) {
            selectedRecommendations = recommendationsData.split(',').filter(function(id) {
                return id && id.trim() !== '';
            });
        }
        
        // Check if target is recommendation
        if (targetStepId === 'RECOMMENDATION') {
            showRecommendation(selectedRecommendations);
            // Update URL again after showing recommendations
            updateURL();
            // Track completion
            var totalSteps = $('.amv-step').length;
            trackUsage('completed', totalSteps);
            return;
        }
        
        // Determine which step to show next
        var $nextStep = null;
        
        if (targetStepId) {
            // Jump to target step
            $nextStep = $('.amv-step[data-step-id="' + targetStepId + '"]');
        } else if (!isLast) {
            // No target specified, go to next sequential step
            $nextStep = $step.next('.amv-step');
        }
        
        if ($nextStep && $nextStep.length) {
            // Show the target/next step (even if it's the last one)
            $nextStep.removeClass('amv-step-hidden').addClass('amv-step-active');
            
            // Update URL with current state (including newly shown step)
            updateURL();
            
            // Scroll to next step
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: $nextStep.offset().top - 50
                }, 300);
            }, 50);
        } else {
            // No valid next step found - show recommendation
            // This handles: last step with no target, or invalid target step
            showRecommendation(selectedRecommendations);
            
            // Update URL with final state
            updateURL();
            
            // Track completion
            var totalSteps = $('.amv-step').length;
            trackUsage('completed', totalSteps);
        }
    });
    
    function showRecommendation(selectedRecIds) {
        if (!config.recommendations || Object.keys(config.recommendations).length === 0) {
            return;
        }
        
        // Show selected recommendations, or all if none selected
        var recommendationsToShow = selectedRecIds && selectedRecIds.length > 0 
            ? selectedRecIds 
            : Object.keys(config.recommendations);
        
        var html = '';
        var itemIndex = 0;
        
        $.each(recommendationsToShow, function(index, recId) {
            var rec = config.recommendations[recId];
            if (!rec) return;
            
            var currentIndex = itemIndex++;
            var recHtml = '<div class="amv-recommendation-item" data-rec-index="' + currentIndex + '">';
            if (rec.title) {
                recHtml += '<h3 class="amv-rec-title">' + $('<div>').text(rec.title).html() + '</h3>';
            }
            
            // Always show content/description if it exists
            if (rec.content) {
                recHtml += '<div class="amv-rec-description">' + rec.content + '</div>';
            }
            
            // Handle bundle content items (content_ids array)
            var contentIds = rec.content_ids || [];
            if (contentIds.length > 0) {
                recHtml += '<div class="amv-rec-content-items" data-rec-index="' + currentIndex + '">';
                recHtml += '<div class="amv-rec-content-loading">' + (strings.loading || 'Loading...') + '</div>';
                recHtml += '</div>';
            }
            
            recHtml += '</div>';
            html += recHtml;
        });
        
        if (html) {
            // Add header above recommendations
            var headerHtml = '<h2 class="amv-recommendations-header">' + (strings.recommendations_header || 'Sinun valintoihin sopivat ratkaisut') + '</h2>';
            // Add reset button
            var resetButtonHtml = '<div class="amv-reset-wrapper"><button type="button" class="amv-reset-button" id="amv-reset-form">' + (strings.reset_form || 'Reset Form') + '</button></div>';
            $('#amv-recommendation-result').html(headerHtml + html + resetButtonHtml).show();
            
            // Load post content for bundle items
            $.each(recommendationsToShow, function(index, recId) {
                var rec = config.recommendations[recId];
                if (!rec || !rec.content_ids || rec.content_ids.length === 0) {
                    return;
                }
                
                var $item = $('#amv-recommendation-result .amv-recommendation-item').eq(index);
                var $contentContainer = $item.find('.amv-rec-content-items');
                var loadedCount = 0;
                var totalCount = rec.content_ids.length;
                
                // Load each content item
                $.each(rec.content_ids, function(contentIndex, postId) {
                    $.ajax({
                        url: amvFrontend.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'amv_get_post_content',
                            post_id: postId,
                            nonce: amvFrontend.nonce
                        },
                        success: function(response) {
                            loadedCount++;
                            if (response.success && response.data) {
                                $contentContainer.find('.amv-rec-content-loading').remove();
                                $contentContainer.append(response.data);
                            }
                            if (loadedCount === totalCount) {
                                $contentContainer.find('.amv-rec-content-loading').remove();
                            }
                        },
                        error: function() {
                            loadedCount++;
                            if (loadedCount === totalCount) {
                                $contentContainer.find('.amv-rec-content-loading').remove();
                            }
                        }
                    });
                });
            });
            
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: $('#amv-recommendation-result').offset().top - 50
                }, 300);
            }, 50);
        }
    }
    
    // Function to update debug info (admin only)
    function updateDebugInfo() {
        if (!isAdmin || debugEnabled !== '1') {
            return;
        }
        
        var userId = getCookie(COOKIE_USER_ID) || 'Not set';
        var formState = getFormStateFromCookie() || {};
        var currentState = getFormState();
        var styleOptions = styles || {};
        
        var debugHtml = '<div class="amv-debug-row"><strong>User ID:</strong> <code>' + $('<div>').text(userId).html() + '</code></div>';
        debugHtml += '<div class="amv-debug-row"><strong>Cookie Form State:</strong> <pre>' + JSON.stringify(formState, null, 2) + '</pre></div>';
        debugHtml += '<div class="amv-debug-row"><strong>Current Form State:</strong> <pre>' + JSON.stringify(currentState, null, 2) + '</pre></div>';
        debugHtml += '<div class="amv-debug-row"><strong>Style Options:</strong> <pre>' + JSON.stringify(styleOptions, null, 2) + '</pre></div>';
        debugHtml += '<div class="amv-debug-row"><strong>URL:</strong> <code>' + window.location.href + '</code></div>';
        
        $('#amv-debug-content').html(debugHtml);
        $('#amv-debug-info').show();
    }
    
    // Function to reset the form
    function resetForm() {
        // Uncheck all radio buttons
        $('.amv-radio-input').prop('checked', false);
        
        // Hide all steps except the first one
        $('.amv-step').removeClass('amv-step-active').addClass('amv-step-hidden');
        $('.amv-step').first().removeClass('amv-step-hidden').addClass('amv-step-active');
        
        // Hide and clear recommendations
        $('#amv-recommendation-result').hide().empty();
        
        // Clear cookies
        setCookie(COOKIE_USER_ID, '', -1);
        setCookie(COOKIE_FORM_STATE, '', -1);
        
        // Generate new user ID
        userId = getUserId();
        
        // Update selected state
        updateSelectedState();
        
        // Clear URL
        window.history.pushState({}, '', window.location.pathname);
        
        // Update debug info if admin
        if (isAdmin) {
            updateDebugInfo();
        }
        
        // Scroll to top
        $('html, body').animate({
            scrollTop: 0
        }, 300);
    }
    
    // Handle reset button click
    $(document).on('click', '#amv-reset-form', function() {
        resetForm();
    });
    
    // Update debug info on page load and when form state changes
    if (isAdmin && debugEnabled === '1') {
        updateDebugInfo();
    }
});

