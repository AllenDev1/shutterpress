<?php 
add_shortcode('shutterpress_download_history', 'shutterpress_render_download_history');

function shutterpress_render_download_history() {
    if (!is_user_logged_in()) {
        return '<div class="shutterpress-message error">
            <p>You must be logged in to view your download history.</p>
        </div>';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table = $wpdb->prefix . 'shutterpress_download_logs';

    // Pagination setup
    $current_page = max(1, intval($_GET['download_page'] ?? 1));
    $per_page = 10; // Show 20 downloads per page
    $offset = ($current_page - 1) * $per_page;

    // Get total count for pagination
    $total_downloads = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));

    $total_pages = ceil($total_downloads / $per_page);

    // Get downloads for current page
    $logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table WHERE user_id = %d ORDER BY download_time DESC LIMIT %d OFFSET %d
    ", $user_id, $per_page, $offset));

    ob_start();
    ?>
    <div class="shutterpress-download-history-wrapper">
        <div class="download-history-header">
            <h3 class="download-history-title">Your Download History</h3>
            <?php if ($total_downloads > 0): ?>
                <div class="download-stats">
                    <span class="total-downloads"><?php echo number_format($total_downloads); ?> total downloads</span>
                    <?php if ($total_pages > 1): ?>
                        <span class="page-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="download-history-list">
                <?php 
                $current_date = '';
                foreach ($logs as $log): 
                    $product = wc_get_product($log->product_id);
                    $download_date = date('Y-m-d', strtotime($log->download_time));
                    $download_time = date('g:i A', strtotime($log->download_time));
                    $display_date = date('M j, Y', strtotime($log->download_time));
                    
                    // Show date separator
                    if ($current_date !== $download_date):
                        if ($current_date !== '') echo '</div>'; // Close previous date group
                        $current_date = $download_date;
                        
                        // Determine date label
                        $today = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        
                        if ($download_date === $today) {
                            $date_label = 'Today';
                        } elseif ($download_date === $yesterday) {
                            $date_label = 'Yesterday';
                        } else {
                            $date_label = $display_date;
                        }
                ?>
                        <div class="download-date-group">
                            <div class="date-separator">
                                <span class="date-label"><?php echo $date_label; ?></span>
                            </div>
                <?php endif; ?>

                    <div class="download-item <?php echo strtolower($log->download_type); ?>">
                        <div class="download-icon">
                            <?php 
                            $icon_class = 'dashicons-download';
                            $icon_color = '#6c757d';
                            
                            switch(strtolower($log->download_type)) {
                                case 'free':
                                    $icon_class = 'dashicons-download';
                                    $icon_color = '#28a745';
                                    break;
                                case 'subscription':
                                    $icon_class = 'dashicons-star-filled';
                                    $icon_color = '#007cba';
                                    break;
                                case 'premium':
                                    $icon_class = 'dashicons-cart';
                                    $icon_color = '#dc3545';
                                    break;
                            }
                            ?>
                            <span class="dashicons <?php echo $icon_class; ?>" style="color: <?php echo $icon_color; ?>;"></span>
                        </div>
                        
                        <div class="download-content">
                            <div class="download-main">
                                <h4 class="product-name">
                                    <?php if ($product): ?>
                                        <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                                            <?php echo esc_html($product->get_name()); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="deleted-product">Product no longer available</span>
                                    <?php endif; ?>
                                </h4>
                                
                                <div class="download-meta">
                                    <span class="download-type <?php echo strtolower($log->download_type); ?>">
                                        <?php echo esc_html(ucfirst($log->download_type)); ?>
                                    </span>
                                    <span class="download-time"><?php echo esc_html($download_time); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($product): ?>
                                <div class="download-actions">
                                    <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>" class="view-product-btn">
                                        <span class="dashicons dashicons-visibility"></span>
                                        View Product
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endforeach; ?>
                </div> <!-- Close last date group -->
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="download-pagination">
                        <div class="pagination-info">
                            <span>
                                Showing <?php echo number_format(($current_page - 1) * $per_page + 1); ?>-<?php echo number_format(min($current_page * $per_page, $total_downloads)); ?> 
                                of <?php echo number_format($total_downloads); ?> downloads
                            </span>
                        </div>
                        
                        <div class="pagination-nav">
                            <?php
                            $base_url = remove_query_arg('download_page');
                            $base_url = add_query_arg('download_page', '%%page%%', $base_url);
                            
                            // Previous button
                            if ($current_page > 1): ?>
                                <a href="<?php echo str_replace('%%page%%', $current_page - 1, $base_url); ?>" class="pagination-btn prev">
                                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <!-- Page numbers -->
                            <div class="page-numbers">
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                // Show first page if not in range
                                if ($start_page > 1): ?>
                                    <a href="<?php echo str_replace('%%page%%', 1, $base_url); ?>" class="page-num">1</a>
                                    <?php if ($start_page > 2): ?>
                                        <span class="page-dots">...</span>
                                    <?php endif; ?>
                                <?php endif;
                                
                                // Show page range
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $current_page): ?>
                                        <span class="page-num current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo str_replace('%%page%%', $i, $base_url); ?>" class="page-num"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor;
                                
                                // Show last page if not in range
                                if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span class="page-dots">...</span>
                                    <?php endif; ?>
                                    <a href="<?php echo str_replace('%%page%%', $total_pages, $base_url); ?>" class="page-num"><?php echo $total_pages; ?></a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Next button -->
                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo str_replace('%%page%%', $current_page + 1, $base_url); ?>" class="pagination-btn next">
                                    Next
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="no-downloads">
                <div class="no-downloads-card">
                    <div class="icon">
                        <span class="dashicons dashicons-download"></span>
                    </div>
                    <h3>No Downloads Yet</h3>
                    <p>You haven't downloaded anything yet. Start exploring our collection!</p>
                    <a href="<?php echo esc_url(home_url('/shop')); ?>" class="browse-products-btn">
                        Browse Products
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .shutterpress-download-history-wrapper {
        max-width: 800px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .download-history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e1e5e9;
    }

    .download-history-title {
        font-size: 24px;
        font-weight: 600;
        margin: 0;
        color: #1a1a1a;
    }

    .download-stats {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .total-downloads, .page-info {
        background: #f8f9fa;
        color: #6c757d;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .page-info {
        background: #e3f2fd;
        color: #1976d2;
    }

    .download-history-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .download-date-group {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .date-separator {
        display: flex;
        align-items: center;
        margin: 20px 0 15px 0;
    }

    .date-separator:first-child {
        margin-top: 0;
    }

    .date-label {
        background: #f8f9fa;
        color: #495057;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        position: relative;
    }

    .date-label::before {
        content: '';
        position: absolute;
        left: -50px;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 1px;
        background: #e1e5e9;
    }

    .date-label::after {
        content: '';
        position: absolute;
        right: -50px;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 1px;
        background: #e1e5e9;
    }

    .download-item {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        gap: 16px;
        transition: all 0.2s ease;
        position: relative;
    }

    .download-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transform: translateY(-1px);
    }

    .download-icon {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        background: #f8f9fa;
    }

    .download-item.free .download-icon {
        background: rgba(40, 167, 69, 0.1);
    }

    .download-item.subscription .download-icon {
        background: rgba(0, 124, 186, 0.1);
    }

    .download-item.premium .download-icon {
        background: rgba(220, 53, 69, 0.1);
    }

    .download-content {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .download-main {
        flex: 1;
    }

    .product-name {
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 8px 0;
        line-height: 1.4;
    }

    .product-name a {
        color: #1a1a1a;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .product-name a:hover {
        color: #007cba;
    }

    .deleted-product {
        color: #6c757d;
        font-style: italic;
    }

    .download-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .download-type {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .download-type.free {
        background: rgba(40, 167, 69, 0.1);
        color: #155724;
    }

    .download-type.subscription {
        background: rgba(0, 124, 186, 0.1);
        color: #004085;
    }

    .download-type.premium {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
    }

    .download-time {
        color: #6c757d;
        font-size: 13px;
    }

    .download-actions {
        flex-shrink: 0;
        margin-left: 16px;
    }

    .view-product-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        background: #f8f9fa;
        color: #495057;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .view-product-btn:hover {
        background: #007cba;
        color: white;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .load-more-notice {
        text-align: center;
        margin-top: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .load-more-notice p {
        margin: 0;
        color: #6c757d;
        font-size: 14px;
    }

    /* Pagination Styles */
    .download-pagination {
        margin-top: 40px;
        padding-top: 30px;
        border-top: 1px solid #e1e5e9;
    }

    .pagination-info {
        text-align: center;
        margin-bottom: 20px;
    }

    .pagination-info span {
        color: #6c757d;
        font-size: 14px;
    }

    .pagination-nav {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pagination-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: #f8f9fa;
        color: #495057;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        min-width: 100px;
        justify-content: center;
    }

    .pagination-btn:hover {
        background: #007cba;
        color: white;
        border-color: #007cba;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .pagination-btn.prev {
        margin-right: 8px;
    }

    .pagination-btn.next {
        margin-left: 8px;
    }

    .page-numbers {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .page-num {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        color: #495057;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
        background: #fff;
    }

    .page-num:hover {
        background: #007cba;
        color: white;
        border-color: #007cba;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .page-num.current {
        background: #007cba;
        color: white;
        border-color: #007cba;
        cursor: default;
    }

    .page-dots {
        padding: 0 8px;
        color: #6c757d;
        font-weight: bold;
    }

    /* No Downloads State */
    .no-downloads {
        margin-top: 40px;
    }

    .no-downloads-card {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        padding: 60px 24px;
        text-align: center;
    }

    .no-downloads-card .icon {
        font-size: 48px;
        color: #6c757d;
        margin-bottom: 20px;
    }

    .no-downloads-card h3 {
        font-size: 20px;
        margin: 0 0 12px 0;
        color: #1a1a1a;
    }

    .no-downloads-card p {
        color: #6c757d;
        margin: 0 0 24px 0;
        font-size: 15px;
    }

    .browse-products-btn {
        background: #007cba;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        display: inline-block;
        transition: all 0.2s ease;
    }

    .browse-products-btn:hover {
        background: #005a87;
        transform: translateY(-1px);
        text-decoration: none;
        color: white;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .download-history-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .download-stats {
            width: 100%;
            justify-content: space-between;
        }

        .download-item {
            flex-direction: column;
            text-align: center;
        }

        .download-content {
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .download-actions {
            margin-left: 0;
        }

        .date-label::before,
        .date-label::after {
            display: none;
        }

        .download-meta {
            justify-content: center;
        }

        /* Pagination responsive */
        .pagination-nav {
            flex-direction: column;
            gap: 12px;
        }

        .page-numbers {
            order: -1;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pagination-btn {
            min-width: 120px;
        }
    }

    @media (max-width: 480px) {
        .shutterpress-download-history-wrapper {
            padding: 0 10px;
        }

        .download-item {
            padding: 16px;
        }

        .download-history-title {
            font-size: 20px;
        }

        .no-downloads-card {
            padding: 40px 16px;
        }

        /* Pagination mobile */
        .pagination-info {
            font-size: 12px;
        }

        .page-num {
            width: 36px;
            height: 36px;
            font-size: 13px;
        }

        .pagination-btn {
            padding: 8px 12px;
            font-size: 13px;
            min-width: 100px;
        }

        .download-stats {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}
?>