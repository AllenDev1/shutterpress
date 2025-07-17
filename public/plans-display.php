<?php
add_shortcode('shutterpress_plans', 'shutterpress_render_frontend_plans');

function shutterpress_render_frontend_plans()
{
    global $wpdb;
    $table = $wpdb->prefix . 'shutterpress_subscription_plans';

    $plans = $wpdb->get_results("SELECT * FROM $table ORDER BY billing_cycle, price ASC");
    if (!$plans) {
        return '<div class="shutterpress-no-plans"><p>No plans available at the moment.</p></div>';
    }

    // Group plans by billing cycle
    $grouped_plans = [];
    foreach ($plans as $plan) {
        if (!$plan->woocommerce_product_id)
            continue;
        $grouped_plans[$plan->billing_cycle][] = $plan;
    }

    ob_start();
    ?>


    <div class="shutterpress-plans-wrapper">
        <div class="shutterpress-plans-header">
            <h2 class="shutterpress-plans-title">Choose Your Plan</h2>
            <p class="shutterpress-plans-subtitle">Flexible subscription plans to meet your creative needs</p>
        </div>

        <div class="shutterpress-billing-tabs">
            <?php
            $tab_labels = [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'yearly' => 'Annual'
            ];

            $first_tab = true;
            foreach ($grouped_plans as $cycle => $cycle_plans):
                $is_best_value = ($cycle === 'yearly');
                ?>
                <button class="shutterpress-tab-button <?php echo $first_tab ? 'active' : ''; ?>"
                    data-tab="<?php echo esc_attr($cycle); ?>">
                    <?php echo esc_html($tab_labels[$cycle] ?? ucfirst($cycle)); ?>
                    <?php if ($is_best_value): ?>
                        <span class="shutterpress-best-value">Best Value</span>
                    <?php endif; ?>
                </button>
                <?php
                $first_tab = false;
            endforeach; ?>
        </div>

        <?php
        $first_content = true;
        foreach ($grouped_plans as $cycle => $cycle_plans):
            ?>
            <div class="shutterpress-tab-content <?php echo $first_content ? 'active' : ''; ?>"
                id="tab-<?php echo esc_attr($cycle); ?>">
                <div class="shutterpress-plans-container">
                    <?php
                    foreach ($cycle_plans as $index => $plan):
                        $checkout_url = wc_get_checkout_url() . '?add-to-cart=' . $plan->woocommerce_product_id;
                        $is_featured = ($index === 0 && count($cycle_plans) > 1);

                        // Calculate savings for yearly plans
                        $savings_text = '';
                        if ($cycle === 'yearly') {
                            $monthly_equivalent = $plan->price / 12;
                            $savings_text = 'Save ' . wc_price($plan->price * 0.2) . ' annually';
                        }
                        ?>
                        <div class="shutterpress-plan-card">
                            <?php if ($plan->is_unlimited): ?>
                                <div class="shutterpress-plan-badge">Most Popular</div>
                            <?php endif; ?>

                            <div class="shutterpress-plan-header">
                                <h3 class="shutterpress-plan-name"><?php echo esc_html($plan->plan_name); ?></h3>
                                <div class="shutterpress-plan-price">
                                    <?php echo wc_price($plan->price); ?>
                                </div>
                                <p class="shutterpress-plan-cycle">per <?php echo esc_html($plan->billing_cycle); ?></p>

                                <?php if ($savings_text): ?>
                                    <div class="shutterpress-plan-savings"><?php echo $savings_text; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="shutterpress-plan-features">
                                <div class="shutterpress-plan-quota">
                                    <?php if ($plan->is_unlimited): ?>
                                        <span class="quota-number">Unlimited</span>
                                        <span class="quota-text">Downloads</span>
                                    <?php else: ?>
                                        <span class="quota-number"><?php echo esc_html($plan->quota); ?></span>
                                        <span class="quota-text">downloads per <?php echo esc_html($plan->billing_cycle); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="shutterpress-plan-feature">
                                    <?php echo $plan->is_unlimited ? 'Access to entire library' : 'Curated content selection'; ?>
                                </div>

                                <div class="shutterpress-plan-feature">
                                    Standard license included
                                </div>

                                <div class="shutterpress-plan-feature">
                                    <?php echo $plan->is_unlimited ? 'Priority support' : 'Email support'; ?>
                                </div>

                                <?php if ($plan->is_unlimited): ?>
                                    <div class="shutterpress-plan-feature">
                                        Commercial usage rights
                                    </div>
                                <?php endif; ?>
                            </div>

                            <a class="shutterpress-plan-button <?php echo $is_featured ? '' : 'secondary'; ?>"
                                href="<?php echo esc_url($checkout_url); ?>">
                                <?php echo $is_featured ? 'Get Started' : 'Choose Plan'; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            $first_content = false;
        endforeach; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = document.querySelectorAll('.shutterpress-tab-button');
            const tabContents = document.querySelectorAll('.shutterpress-tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const targetTab = this.dataset.tab;

                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById('tab-' + targetTab).classList.add('active');
                });
            });
        });
    </script>
    <?php

    return ob_get_clean();
}