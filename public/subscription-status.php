<?php
add_shortcode('shutterpress_user_subscription', function () {
    if (!is_user_logged_in()) {
        return '<div class="shutterpress-message error">
            <p>You must be logged in to view your subscription.</p>
        </div>';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table = $wpdb->prefix . 'shutterpress_user_quotas';
    $plans_table = $wpdb->prefix . 'shutterpress_subscription_plans';

    // Get current active subscription
    $active_quota = $wpdb->get_row($wpdb->prepare("
        SELECT q.*, p.plan_name, p.billing_cycle, p.is_unlimited, p.price
        FROM $table q
        LEFT JOIN $plans_table p ON q.plan_id = p.id
       WHERE q.user_id = %d AND q.status = 'active' AND (q.quota_renewal_date IS NULL OR q.quota_renewal_date >= CURDATE())
        ORDER BY q.created_at DESC LIMIT 1
    ", $user_id));

    // Get all subscription history
    $subscription_history = $wpdb->get_results($wpdb->prepare("
        SELECT q.*, p.plan_name, p.billing_cycle, p.is_unlimited, p.price
        FROM $table q
        LEFT JOIN $plans_table p ON q.plan_id = p.id
        WHERE q.user_id = %d
        ORDER BY q.created_at DESC
    ", $user_id));

    ob_start();
    ?>
    <div class="shutterpress-subscription-wrapper">
        
        <?php if ($active_quota): ?>
            <!-- Current Active Subscription -->
            <div class="shutterpress-current-subscription">
                <div class="subscription-header">
                    <h3 class="subscription-title">Your Active Subscription</h3>
                    <span class="subscription-badge active">Active</span>
                </div>
                
                <div class="subscription-card">
                    <div class="subscription-main-info">
                        <div class="plan-info">
                            <h4 class="plan-name"><?php echo esc_html($active_quota->plan_name); ?></h4>
                            <p class="plan-type">
                                <?php echo $active_quota->is_unlimited ? 'Unlimited Downloads' : 'Quota-based Plan'; ?>
                            </p>
                            <?php if ($active_quota->price && $active_quota->billing_cycle): ?>
                                <p class="plan-price">
                                    <?php echo wc_price($active_quota->price); ?> / <?php echo esc_html($active_quota->billing_cycle); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="subscription-stats">
                        <div class="stats-grid">
                            <?php if (!$active_quota->is_unlimited): ?>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo esc_html($active_quota->quota_total); ?></div>
                                    <div class="stat-label">Total Downloads</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo esc_html($active_quota->quota_used); ?></div>
                                    <div class="stat-label">Used</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo max(0, $active_quota->quota_total - $active_quota->quota_used); ?></div>
                                    <div class="stat-label">Remaining</div>
                                </div>
                            <?php else: ?>
                                <div class="stat-item">
                                    <div class="stat-value">∞</div>
                                    <div class="stat-label">Unlimited</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo esc_html($active_quota->quota_used); ?></div>
                                    <div class="stat-label">Downloaded</div>
                                </div>
                            <?php endif; ?>
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php echo $active_quota->quota_renewal_date ? esc_html(date('M j, Y', strtotime($active_quota->quota_renewal_date))) : 'N/A'; ?>
                                </div>
                                <div class="stat-label">Renewal Date</div>
                            </div>
                        </div>
                        
                        <?php if (!$active_quota->is_unlimited): ?>
                            <!-- Progress Bar -->
                            <div class="quota-progress">
                                <?php 
                                $percentage = $active_quota->quota_total > 0 ? min(100, ($active_quota->quota_used / $active_quota->quota_total) * 100) : 0;
                                $progress_class = $percentage >= 90 ? 'critical' : ($percentage >= 70 ? 'warning' : 'normal');
                                ?>
                                <div class="progress-bar <?php echo $progress_class; ?>">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <p class="progress-text"><?php echo round($percentage, 1); ?>% used</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- No Active Subscription -->
            <div class="shutterpress-no-subscription">
                <div class="no-subscription-card">
                    <div class="icon">
                        <span class="dashicons dashicons-portfolio"></span>
                    </div>
                    <h3>No Active Subscription</h3>
                    <p>You don't currently have an active subscription. Browse our plans to get started.</p>
                    <?php 
                    $plans_page = shutterpress_find_shortcode_page('shutterpress_plans');
                    if ($plans_page): 
                    ?>
                        <a href="<?php echo esc_url(get_permalink($plans_page)); ?>" class="browse-plans-btn">
                            Browse Plans
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Subscription History -->
        <?php if (!empty($subscription_history)): ?>
            <div class="shutterpress-subscription-history">
                <h3 class="history-title">Subscription History</h3>
                
                <div class="history-list">
                    <?php foreach ($subscription_history as $subscription): 
                        $status_class = strtolower($subscription->status);
                        $status_icon = [
                            'active' => 'dashicons-yes-alt',
                            'expired' => 'dashicons-clock',
                            'cancelled' => 'dashicons-dismiss',
                            'pending' => 'dashicons-hourglass'
                        ];
                    ?>
                        <div class="history-item <?php echo $status_class; ?>">
                            <div class="history-icon">
                                <span class="dashicons <?php echo $status_icon[$subscription->status] ?? 'dashicons-marker'; ?>"></span>
                            </div>
                            
                            <div class="history-content">
                                <div class="history-main">
                                    <h4 class="history-plan-name"><?php echo esc_html($subscription->plan_name ?: 'Unknown Plan'); ?></h4>
                                    <span class="history-status <?php echo $status_class; ?>">
                                        <?php echo esc_html(ucfirst($subscription->status)); ?>
                                    </span>
                                </div>
                                
                                <div class="history-details">
                                    <div class="history-detail">
                                        <strong>Type:</strong> 
                                        <?php echo $subscription->is_unlimited ? 'Unlimited' : $subscription->quota_total . ' downloads'; ?>
                                    </div>
                                    
                                    <?php if ($subscription->price && $subscription->billing_cycle): ?>
                                        <div class="history-detail">
                                            <strong>Price:</strong> 
                                            <?php echo wc_price($subscription->price) . ' / ' . esc_html($subscription->billing_cycle); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="history-detail">
                                        <strong>Started:</strong> 
                                        <?php echo esc_html(date('M j, Y', strtotime($subscription->created_at))); ?>
                                    </div>
                                    
                                    <?php if ($subscription->quota_renewal_date): ?>
                                        <div class="history-detail">
                                            <strong>
                                                <?php echo $subscription->status === 'active' ? 'Renews:' : 'Expired:'; ?>
                                            </strong> 
                                            <?php echo esc_html(date('M j, Y', strtotime($subscription->quota_renewal_date))); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($subscription->status === 'cancelled' && $subscription->cancel_reason): ?>
                                        <div class="history-detail">
                                            <strong>Reason:</strong> 
                                            <?php echo esc_html($subscription->cancel_reason); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="history-usage">
                                    <?php if (!$subscription->is_unlimited): ?>
                                        <span class="usage-text">
                                            <?php echo esc_html($subscription->quota_used); ?> of <?php echo esc_html($subscription->quota_total); ?> downloads used
                                        </span>
                                    <?php else: ?>
                                        <span class="usage-text">
                                            <?php echo esc_html($subscription->quota_used); ?> downloads used
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .shutterpress-subscription-wrapper {
        max-width: 800px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .shutterpress-current-subscription {
        margin-bottom: 40px;
    }

    .subscription-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .subscription-title {
        font-size: 24px;
        font-weight: 600;
        margin: 0;
        color: #1a1a1a;
    }

    .subscription-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .subscription-badge.active {
        background: #d4edda;
        color: #155724;
    }

    .subscription-card {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .subscription-main-info {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .plan-info h4 {
        font-size: 20px;
        font-weight: 600;
        margin: 0 0 8px 0;
        color: #1a1a1a;
    }

    .plan-type {
        color: #6c757d;
        margin: 0 0 4px 0;
        font-size: 14px;
    }

    .plan-price {
        color: #007cba;
        font-weight: 600;
        margin: 0;
        font-size: 16px;
    }

    .subscription-stats {
        border-top: 1px solid #f1f3f4;
        padding-top: 24px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1;
    }

    .stat-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }

    .quota-progress {
        margin-top: 20px;
    }

    .progress-bar {
        height: 8px;
        background: #f1f3f4;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 8px;
    }

    .progress-fill {
        height: 100%;
        transition: width 0.3s ease;
        border-radius: 4px;
    }

    .progress-bar.normal .progress-fill { background: #28a745; }
    .progress-bar.warning .progress-fill { background: #ffc107; }
    .progress-bar.critical .progress-fill { background: #dc3545; }

    .progress-text {
        font-size: 12px;
        color: #6c757d;
        margin: 0;
        text-align: center;
    }

    /* No Subscription */
    .shutterpress-no-subscription {
        margin-bottom: 40px;
    }

    .no-subscription-card {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        padding: 40px 24px;
        text-align: center;
    }

    .no-subscription-card .icon {
        font-size: 48px;
        color: #6c757d;
        margin-bottom: 16px;
    }

    .no-subscription-card h3 {
        font-size: 20px;
        margin: 0 0 12px 0;
        color: #1a1a1a;
    }

    .no-subscription-card p {
        color: #6c757d;
        margin: 0 0 20px 0;
    }

    .browse-plans-btn {
        background: #007cba;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        display: inline-block;
        transition: all 0.2s ease;
    }

    .browse-plans-btn:hover {
        background: #005a87;
        transform: translateY(-1px);
        text-decoration: none;
        color: white;
    }

    /* History */
    .shutterpress-subscription-history {
        margin-top: 40px;
    }

    .history-title {
        font-size: 20px;
        font-weight: 600;
        margin: 0 0 20px 0;
        color: #1a1a1a;
    }

    .history-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .history-item {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        gap: 16px;
        transition: all 0.2s ease;
    }

    .history-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .history-icon {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .history-item.active .history-icon {
        background: #d4edda;
        color: #155724;
    }

    .history-item.expired .history-icon {
        background: #fff3cd;
        color: #856404;
    }

    .history-item.cancelled .history-icon {
        background: #f8d7da;
        color: #721c24;
    }

    .history-item.pending .history-icon {
        background: #cce5f7;
        color: #004085;
    }

    .history-content {
        flex: 1;
    }

    .history-main {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .history-plan-name {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        color: #1a1a1a;
    }

    .history-status {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .history-status.active { background: #d4edda; color: #155724; }
    .history-status.expired { background: #fff3cd; color: #856404; }
    .history-status.cancelled { background: #f8d7da; color: #721c24; }
    .history-status.pending { background: #cce5f7; color: #004085; }

    .history-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }

    .history-detail {
        font-size: 13px;
        color: #6c757d;
    }

    .history-detail strong {
        color: #1a1a1a;
    }

    .history-usage {
        font-size: 12px;
        color: #6c757d;
        font-style: italic;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .subscription-main-info,
        .history-main {
            flex-direction: column;
            align-items: flex-start;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .history-details {
            grid-template-columns: 1fr;
        }

        .history-item {
            flex-direction: column;
            text-align: center;
        }

        .history-icon {
            align-self: center;
        }
    }
    </style>
    <?php
    return ob_get_clean();
});
?>