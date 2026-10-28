<?php
/**
 * Front-end RMS application shell.
 *
 * @package ByaheroChixRMS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BC_RMS_Frontend {
    public static function init(){
        add_shortcode('byahero_chix_rms',[__CLASS__,'shortcode']);
        add_action('wp_enqueue_scripts',[__CLASS__,'assets']);
    }
    public static function assets(){
        if(!is_singular()) return;
        global $post;
        if(!$post || !has_shortcode($post->post_content,'byahero_chix_rms')) return;
        wp_enqueue_style('bc-rms-app',BC_RMS_URL.'assets/css/frontend.css',[],BC_RMS_VERSION);
        wp_enqueue_script('bc-rms-pos',BC_RMS_URL.'assets/js/pos.js',[],BC_RMS_VERSION,true);
        if(current_user_can('bc_view_orders')){
            wp_enqueue_script('bc-rms-kitchen',BC_RMS_URL.'assets/js/kitchen.js',[],BC_RMS_VERSION,true);
            wp_localize_script('bc-rms-kitchen','BCRMS_KITCHEN',['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bc_rms_kitchen')]);
        }
        wp_localize_script('bc-rms-pos','BCRMS_POS',[
            'ajaxUrl'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('bc_rms_pos'),
            'catalog'=>BC_RMS_POS_Service::catalog(),
            'currency'=>'₱',
            'frontend'=>true
        ]);
    }
    public static function shortcode(){
        if(!is_user_logged_in()) return '<div class="bc-rms-login"><h2>Byahero Chix RMS</h2><p>Please log in to access the restaurant system.</p>'.wp_login_form(['echo'=>false,'redirect'=>get_permalink()]).'</div>';
        if(!current_user_can('bc_view_rms') && !current_user_can('bc_use_pos')) return '<p>You do not have permission to access the RMS.</p>';
        $u=wp_get_current_user();$catalog=BC_RMS_POS_Service::catalog();
        ob_start(); ?>
        <div class="bc-rms-app">
          <header class="bc-app-header"><div><strong>BYAHERO CHIX</strong><span>Restaurant Management System</span></div><div><?php echo esc_html($u->display_name); ?></div></header>
          <div class="bc-app-body">
            <nav class="bc-app-nav">
              <?php if(current_user_can('bc_view_rms')):?><button class="bc-app-tab" data-view="dashboard">Dashboard</button><?php endif;?>
              <?php if(current_user_can('bc_use_pos')):?><button class="bc-app-tab active" data-view="pos">POS</button><?php endif;?>
              <?php if(current_user_can('bc_view_orders')):?><a href="<?php echo esc_url(admin_url('admin.php?page=bc-rms-orders')); ?>">Orders</a><?php endif;?>
              <?php if(current_user_can('bc_view_orders')):?><button class="bc-app-tab" data-view="kitchen">Kitchen</button><?php endif;?>
              <?php if(current_user_can('bc_view_inventory')):?><a href="<?php echo esc_url(admin_url('admin.php?page=bc-rms-inventory')); ?>">Inventory</a><?php endif;?>
              <?php if(current_user_can('bc_manage_inventory')):?><a href="<?php echo esc_url(admin_url('admin.php?page=bc-rms-purchase-orders')); ?>">Purchasing</a><?php endif;?>
            </nav>
            <main class="bc-app-main">
              <section id="bc-view-dashboard" class="bc-app-view" hidden><h1>Dashboard</h1><div class="bc-dashboard-cards"><article><small>System</small><strong>Ready</strong><span>Operational dashboard foundation</span></article><article><small>POS</small><strong>Online</strong><span>Paid orders commit inventory</span></article><article><small>Version</small><strong>0.13.6</strong><span>Front-end RMS shell</span></article></div></section>
              <?php if(current_user_can('bc_view_orders')):?><section id="bc-view-kitchen" class="bc-app-view" hidden><h1>Kitchen Display</h1><p>Paid orders only · Queued → Preparing → Ready → Served / Picked Up</p><button type="button" id="bc-kitchen-refresh">Refresh Orders</button> <button type="button" id="bc-kitchen-sound" aria-pressed="false">🔇 Enable Sound</button> <span id="bc-kitchen-auto-status" aria-live="polite">Auto-refresh: every 5 seconds</span><div id="bc-kitchen-message" role="status"></div><div id="bc-kitchen-board" class="bc-kitchen-board"></div></section><?php endif;?>
              <section id="bc-view-pos" class="bc-app-view">
                <div class="bc-pos bc-pos-frontend">
                  <div class="bc-pos-layout"><section class="bc-pos-menu"><div class="bc-pos-toolbar"><input id="bc-pos-barcode" type="text" inputmode="none" autocomplete="off" placeholder="Scan product barcode..." aria-label="Scan product barcode"><input id="bc-pos-search" type="search" placeholder="Search menu..."><select id="bc-pos-category"><option value="">All Categories</option><?php $cats=[];foreach($catalog as $x)if(!empty($x['category_name']))$cats[$x['category_name']]=1;foreach(array_keys($cats) as $c)echo '<option>'.esc_html($c).'</option>';?></select></div><div id="bc-pos-products" class="bc-pos-products"></div></section>
                    <aside class="bc-pos-cart"><div class="bc-order-heading"><div><small>ORDER</small><h2>Current Order</h2></div><div class="bc-pos-order-type"><button type="button" data-type="dine_in" class="active">Dine-in</button><button type="button" data-type="takeout">Takeout</button></div></div><div id="bc-pos-cart-items"></div>
                      <div class="bc-pos-totals"><p><span>Subtotal</span><strong id="bc-pos-subtotal">₱0.00</strong></p><p><span>Discount</span><input id="bc-pos-discount" type="number" min="0" step="0.01" value="0" readonly></p><p><label>Promo / Voucher Code <input id="bc-pos-promo" placeholder="e.g. WELCOME10"></label><button type="button" id="bc-pos-apply-promo">Apply</button><button type="button" id="bc-pos-clear-promo">Clear</button></p><div class="bc-statutory-panel" style="border:1px solid #d3c7b7;border-radius:10px;padding:12px;margin:10px 0"><strong>Senior / PWD Discount</strong><p><label>Type <select id="bc-pos-statutory-type"><option value="none">None</option><option value="senior">Senior Citizen</option><option value="pwd">PWD</option></select></label></p><p><label>Customer Full Name <input id="bc-pos-statutory-name" type="text" maxlength="200"></label></p><p><label>ID Reference <input id="bc-pos-statutory-id" type="text" maxlength="120"></label></p><p><label>Eligible portion (₱) <input id="bc-pos-statutory-eligible" type="number" step="0.01" min="0" value="0"></label></p><p><button id="bc-pos-apply-statutory" type="button">Apply Statutory Discount</button> <button id="bc-pos-clear-statutory" type="button">Clear</button></p><small>Apply only to the eligible customer portion. VAT relief depends on Settings → VAT Registered. Voucher stacking is disabled.</small><div id="bc-pos-statutory-summary"></div></div><p class="total"><span>TOTAL</span><strong id="bc-pos-total">₱0.00</strong></p></div>
                      <div class="bc-payment-panel"><label>Payment Method<select id="bc-pos-payment"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="card">Card</option><option value="other">Other</option></select></label><label>Cash Tendered<input id="bc-pos-tendered" type="number" min="0" step="0.01" inputmode="decimal" placeholder="₱0.00"></label><div class="bc-change-box"><span>CHANGE</span><strong id="bc-pos-change">₱0.00</strong></div><div class="bc-quick-cash"><button type="button" data-cash="exact">Exact</button><button type="button" data-cash="100">₱100</button><button type="button" data-cash="200">₱200</button><button type="button" data-cash="500">₱500</button><button type="button" data-cash="1000">₱1,000</button></div></div>
                      <div class="bc-pos-actions"><button id="bc-pos-hold" type="button">Hold</button><button id="bc-pos-held" type="button">Held Orders</button></div><button id="bc-pos-checkout" class="bc-pay-button" type="button">PAY ORDER</button><div id="bc-pos-message"></div>
                    </aside>
                  </div>
                  <div id="bc-pos-modal" class="bc-pos-modal" hidden><div class="bc-pos-modal-card"><button id="bc-pos-modal-close" type="button">×</button><div id="bc-pos-modal-body"></div></div></div>
                </div>
              </section>
            </main>
          </div>
        </div>
        <script>document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.bc-app-tab').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.bc-app-tab').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('.bc-app-view').forEach(v=>v.hidden=v.id!=='bc-view-'+b.dataset.view)}))});</script>
        <?php return ob_get_clean();
    }
}
