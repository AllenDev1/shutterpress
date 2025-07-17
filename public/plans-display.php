<?php
add_shortcode('shutterpress_plans', 'shutterpress_render_frontend_plans');

function shutterpress_render_frontend_plans()
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    $plans = $wpdb->get_results("SELECT * FROM $table ORDER BY price ASC");
    if (!$plans) {
        return '<div class="shutterpress-no-plans"><p>No plans available at the moment.</p></div>';
    }

    ob_start();
    ?>
   
    
    <div class="shutterpress-plans-container">
        <?php 
        $plan_count = count($plans);
        $middle_plan = ceil($plan_count / 2) - 1;
        
        foreach ($plans as $index => $plan): 
            if (!$plan->woocommerce_product_id) continue;
            
            $checkout_url = wc_get_checkout_url() . '?add-to-cart=' . $plan->woocommerce_product_id;
            $is_popular = ($index === $middle_plan && $plan_count > 1); // Mark middle plan as popular
        ?>
            <div class="shutterpress-plan-card">
                <?php if ($is_popular): ?>
                    <div class="shutterpress-popular-badge">Popular</div>
                <?php endif; ?>
                
                <div class="shutterpress-plan-header">
                    <h3 class="shutterpress-plan-name"><?php echo esc_html($plan->plan_name); ?></h3>
                    <div class="shutterpress-plan-price"><?php echo wc_price($plan->price); ?></div>
                    <p class="shutterpress-plan-cycle">per <?php echo esc_html($plan->billing_cycle); ?></p>
                </div>
                
                <div class="shutterpress-plan-features">
                    <div class="shutterpress-plan-feature">
                        <strong>Downloads:</strong>
                        <div class="shutterpress-plan-quota">
                            <?php echo $plan->is_unlimited ? 'Unlimited' : esc_html($plan->quota); ?>
                        </div>
                    </div>
                    
                    <?php if ($plan->is_unlimited): ?>
                        <div class="shutterpress-plan-feature">
                            ✓ Unlimited downloads
                        </div>
                        <div class="shutterpress-plan-feature">
                            ✓ Premium support
                        </div>
                    <?php else: ?>
                        <div class="shutterpress-plan-feature">
                            ✓ <?php echo esc_html($plan->quota); ?> downloads per <?php echo esc_html($plan->billing_cycle); ?>
                        </div>
                        <div class="shutterpress-plan-feature">
                            ✓ Standard support
                        </div>
                    <?php endif; ?>
                </div>
                
                <a class="shutterpress-plan-button" href="<?php echo esc_url($checkout_url); ?>">
                    Choose This Plan
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    
    return ob_get_clean();
}