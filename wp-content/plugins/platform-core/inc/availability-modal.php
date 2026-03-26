<?php
/**
 * Update Availability Modal - Amelia Integration (FIXED)
 * Add this to your theme's functions.php or inc/availability-modal.php
 * 
 * This provides a clean modal interface that embeds Amelia's employee panel
 * specifically for the calendar/availability section
 */

// ============================================================
// ENQUEUE MODAL SCRIPTS & STYLES
// ============================================================
add_action('wp_enqueue_scripts', function() {
    // Only load on pages with the dashboard shortcode or specific pages
    if (is_user_logged_in() && platform_core_user_is_expert()) {
        
        // Get employee ID for the current user
        $user_id = get_current_user_id();
        $employee_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);
        
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                // Create modal HTML (only once)
                if (!$('#ted-availability-modal').length) {
                    $('body').append(`
                        <div id=\"ted-availability-modal\" class=\"ted-modal\" style=\"display:none;\">
                            <div class=\"ted-modal-overlay\"></div>
                            <div class=\"ted-modal-container\">
                                <div class=\"ted-modal-header\">
                                    <h2>Update Availability</h2>
                                    <button class=\"ted-modal-close\" aria-label=\"Close\">&times;</button>
                                </div>
                                <div class=\"ted-modal-body\">
                                    <div class=\"ted-modal-loading\">
                                        <div class=\"ted-spinner\"></div>
                                        <p>Loading calendar settings...</p>
                                    </div>
                                    <iframe id=\"ted-availability-iframe\" style=\"display:none;\" frameborder=\"0\"></iframe>
                                </div>
                                <div class=\"ted-modal-footer\">
                                    <button class=\"ted-btn ted-btn-outline ted-modal-close\">Close</button>
                                    <a href=\"" . esc_js(home_url('/expert-panel-internal#/appointments')) . "\" class=\"ted-btn ted-btn-dark\" target=\"_blank\">Open Full Panel</a>
                                </div>
                            </div>
                        </div>
                    `);
                }

                // Open modal handler
                $(document).on('click', '[data-open-availability]', function(e) {
                    e.preventDefault();
                    console.log('Opening availability modal...');
                    openAvailabilityModal();
                });

                function openAvailabilityModal() {
                    var modal = $('#ted-availability-modal');
                    var iframe = $('#ted-availability-iframe');
                    var loading = $('.ted-modal-loading');
                    
                    console.log('Modal opening...');
                    modal.fadeIn(200);
                    $('body').css('overflow', 'hidden');
                    
                    // Load iframe if not already loaded
                    if (!iframe.attr('src')) {
                        console.log('Loading Amelia panel...');
                        
                        // Load the Amelia employee panel directly
                        var panelUrl = '" . esc_js(home_url('/expert-panel-internal')) . "';
                        
                        // Set iframe source
                        iframe.attr('src', panelUrl + '#/appointments');
                        
                        // Hide loading after a delay (since iframe load event might not fire reliably)
                        setTimeout(function() {
                            loading.fadeOut(300, function() {
                                iframe.fadeIn(300);
                            });
                        }, 1500);
                        
                        // Also listen for actual load event
                        iframe.on('load', function() {
                            console.log('Iframe loaded');
                            loading.fadeOut(300);
                            iframe.fadeIn(300);
                        });
                        
                        // Error handler
                        iframe.on('error', function() {
                            console.error('Failed to load iframe');
                            loading.html('<p style=\"color: #ef4444;\">Failed to load calendar. <a href=\"' + panelUrl + '\" target=\"_blank\">Open in new tab</a></p>');
                        });
                    } else {
                        loading.hide();
                        iframe.show();
                    }
                }

                function closeAvailabilityModal() {
                    var modal = $('#ted-availability-modal');
                    modal.fadeOut(200);
                    $('body').css('overflow', '');
                }

                // Close handlers
                $(document).on('click', '.ted-modal-close, .ted-modal-overlay', function() {
                    closeAvailabilityModal();
                });

                // ESC key to close
                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape' && $('#ted-availability-modal').is(':visible')) {
                        closeAvailabilityModal();
                    }
                });
            });
        ");

        // Add modal CSS
        wp_add_inline_style('wp-block-library', "
            /* Availability Modal Styles */
            .ted-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 999999;
            }

            .ted-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
            }

            .ted-modal-container {
                position: relative;
                width: 90%;
                max-width: 1200px;
                max-height: 90vh;
                margin: 2rem auto;
                background: white;
                border-radius: 1rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .ted-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem 2rem;
                border-bottom: 1px solid #e5e7eb;
            }

            .ted-modal-header h2 {
                margin: 0;
                font-size: 1.5rem;
                font-weight: 600;
                color: #111827;
            }

            .ted-modal-close {
                background: none;
                border: none;
                font-size: 2rem;
                line-height: 1;
                color: #6b7280;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.375rem;
                transition: all 0.2s;
            }

            .ted-modal-close:hover {
                background: #f3f4f6;
                color: #111827;
            }

            .ted-modal-body {
                flex: 1;
                overflow: auto;
                position: relative;
                min-height: 500px;
                background: #f9fafb;
            }

            .ted-modal-loading {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
                color: #6b7280;
            }

            .ted-spinner {
                width: 40px;
                height: 40px;
                margin: 0 auto 1rem;
                border: 3px solid #e5e7eb;
                border-top-color: #2563eb;
                border-radius: 50%;
                animation: ted-spin 0.8s linear infinite;
            }

            @keyframes ted-spin {
                to { transform: rotate(360deg); }
            }

            #ted-availability-iframe {
                width: 100%;
                height: 100%;
                min-height: 600px;
                border: none;
                background: white;
            }

            .ted-modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
                padding: 1.25rem 2rem;
                border-top: 1px solid #e5e7eb;
                background: #f9fafb;
            }

            .ted-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
                font-weight: 500;
                border-radius: 0.5rem;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                border: none;
            }

            .ted-btn-outline {
                background: white;
                color: #374151;
                border: 1px solid #d1d5db;
            }

            .ted-btn-outline:hover {
                background: #f9fafb;
                border-color: #9ca3af;
            }

            .ted-btn-dark {
                background: #111827;
                color: white;
            }

            .ted-btn-dark:hover {
                background: #1f2937;
            }

            @media (max-width: 768px) {
                .ted-modal-container {
                    width: 95%;
                    margin: 1rem auto;
                    max-height: 95vh;
                }

                .ted-modal-header {
                    padding: 1rem 1.25rem;
                }

                .ted-modal-header h2 {
                    font-size: 1.25rem;
                }

                .ted-modal-footer {
                    padding: 1rem 1.25rem;
                    flex-direction: column;
                }

                .ted-btn {
                    width: 100%;
                    justify-content: center;
                }

                #ted-availability-iframe {
                    min-height: 500px;
                }
            }
        ");
    }
});