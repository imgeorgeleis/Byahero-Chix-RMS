<?php
if (!defined('ABSPATH'))
    exit;
class BC_RMS_Admin
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'save']);
        add_action('admin_init', [__CLASS__, 'delete']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }
    public static function assets($h)
    {
        if (strpos($h, 'bc-rms') !== false)
            wp_enqueue_style('bc-rms', BC_RMS_URL . 'assets/css/admin.css', [], BC_RMS_VERSION);
    }
    public static function menu()
    {
        add_menu_page('Byahero Chix RMS', 'Byahero Chix RMS', 'bc_view_rms', 'bc-rms', [__CLASS__, 'dashboard'], 'dashicons-store', 26);
        add_submenu_page('bc-rms', 'Ingredients', 'Ingredients', 'bc_manage_ingredients', 'bc-rms-ingredients', [__CLASS__, 'ingredients']);
        add_submenu_page('bc-rms', 'Categories', 'Categories', 'bc_manage_ingredients', 'bc-rms-categories', [__CLASS__, 'categories']);
        add_submenu_page('bc-rms', 'Suppliers', 'Suppliers', 'bc_manage_suppliers', 'bc-rms-suppliers', [__CLASS__, 'suppliers']);
        add_submenu_page('bc-rms', 'Supplier Pricing', 'Supplier Pricing', 'bc_manage_suppliers', 'bc-rms-pricing', [__CLASS__, 'pricing']);
        add_submenu_page('bc-rms', 'Recipes', 'Recipes', 'bc_manage_recipes', 'bc-rms-recipes', [__CLASS__, 'recipes']);
        add_submenu_page('bc-rms', 'Units', 'Units', 'bc_manage_units', 'bc-rms-units', [__CLASS__, 'units']);
        add_submenu_page('bc-rms', 'Settings', 'Settings', 'bc_manage_settings', 'bc-rms-settings', [__CLASS__, 'settings']);
    }
    private static function nonce()
    {
        wp_nonce_field('bc_rms_save');
    }
    private static function go($p, $args = ['saved' => 1])
    {
        wp_safe_redirect(add_query_arg(array_merge(['page' => $p], $args), admin_url('admin.php')));
        exit;
    }
    private static function delete_url($page, $type, $id)
    {
        return wp_nonce_url(add_query_arg(['page'=>$page,'bc_rms_delete'=>$type,'id'=>absint($id)], admin_url('admin.php')), 'bc_rms_delete_'.$type.'_'.$id);
    }
    public static function delete()
    {
        if (empty($_GET['bc_rms_delete']) || empty($_GET['id'])) return;
        global $wpdb;
        $type = sanitize_key($_GET['bc_rms_delete']);
        $id = absint($_GET['id']);
        $map = [
            'ingredient'=>['ingredients','bc_manage_ingredients','bc-rms-ingredients'],
            'category'=>['ingredient_categories','bc_manage_ingredients','bc-rms-categories'],
            'supplier'=>['suppliers','bc_manage_suppliers','bc-rms-suppliers'],
            'pricing'=>['supplier_items','bc_manage_suppliers','bc-rms-pricing'],
            'unit'=>['units','bc_manage_units','bc-rms-units'],
            'recipe'=>['recipes','bc_manage_recipes','bc-rms-recipes'],
            'recipe_item'=>['recipe_items','bc_manage_recipes','bc-rms-recipes'],
        ];
        if (!isset($map[$type]) || !current_user_can($map[$type][1])) wp_die('Invalid request.');
        check_admin_referer('bc_rms_delete_'.$type.'_'.$id);
        $blocked = '';
        if ($type === 'ingredient') {
            $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('supplier_items').' WHERE ingredient_id=%d',$id));
            if($count) $blocked='This ingredient is used by Supplier Pricing. Delete those pricing records first, or set the ingredient to Inactive.';
        } elseif ($type === 'category') {
            $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('ingredients').' WHERE category_id=%d',$id));
            if($count) $blocked='This category is assigned to one or more ingredients. Reassign them first, or set the category to Inactive.';
        } elseif ($type === 'supplier') {
            $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('supplier_items').' WHERE supplier_id=%d',$id));
            if($count) $blocked='This supplier has Supplier Pricing records. Delete those pricing records first, or set the supplier to Inactive.';
        } elseif ($type === 'recipe') {
            $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('recipe_items').' WHERE recipe_id=%d',$id));
            if($count) $wpdb->delete(BC_RMS_DB::table('recipe_items'),['recipe_id'=>$id],['%d']);
        } elseif ($type === 'unit') {
            $a=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('ingredients').' WHERE base_unit_id=%d',$id));
            $b=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('supplier_items').' WHERE purchase_unit_id=%d',$id));
            $c=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('unit_conversions').' WHERE from_unit_id=%d OR to_unit_id=%d',$id,$id));
            if($a+$b+$c) $blocked='This unit is already referenced by other records. Set it to Inactive instead.';
        }
        if ($blocked) self::go($map[$type][2], ['bc_error'=>rawurlencode($blocked)]);
        $wpdb->delete(BC_RMS_DB::table($map[$type][0]), ['id'=>$id], ['%d']);
        self::go($map[$type][2], ['deleted'=>1]);
    }
    public static function save()
    {
        if (empty($_POST['bc_rms_action']))
            return;
        if (!current_user_can('bc_view_rms') || !check_admin_referer('bc_rms_save'))
            wp_die('Invalid request.');
        global $wpdb;
        $a = sanitize_key($_POST['bc_rms_action']);
        $n = BC_RMS_DB::now();
        if ($a === 'ingredient') {
            $t = BC_RMS_DB::table('ingredients');
            $id = absint($_POST['id'] ?? 0);
            $d = ['name' => sanitize_text_field($_POST['name']), 'category_id' => absint($_POST['category_id']) ?: null, 'description' => sanitize_textarea_field($_POST['description'] ?? ''), 'base_unit_id' => absint($_POST['base_unit_id']), 'minimum_stock' => (float) $_POST['minimum_stock'], 'reorder_level' => (float) $_POST['reorder_level'], 'track_inventory' => isset($_POST['track_inventory']) ? 1 : 0, 'active' => isset($_POST['active']) ? 1 : 0, 'updated_at' => $n];
            if ($id)
                $wpdb->update($t, $d, ['id' => $id]);
            else {
                $d += ['uuid' => BC_RMS_DB::uuid(), 'created_at' => $n];
                $wpdb->insert($t, $d);
            }
            self::go('bc-rms-ingredients');
        }
        if ($a === 'category') {
            $t = BC_RMS_DB::table('ingredient_categories');
            $id = absint($_POST['id'] ?? 0);
            $d = ['name' => sanitize_text_field($_POST['name']), 'description' => sanitize_textarea_field($_POST['description'] ?? ''), 'active' => isset($_POST['active']) ? 1 : 0, 'updated_at' => $n];
            if ($id)
                $wpdb->update($t, $d, ['id' => $id]);
            else {
                $d += ['uuid' => BC_RMS_DB::uuid(), 'created_at' => $n];
                $wpdb->insert($t, $d);
            }
            self::go('bc-rms-categories');
        }
        if ($a === 'supplier') {
            $t = BC_RMS_DB::table('suppliers');
            $id = absint($_POST['id'] ?? 0);
            $d = ['name' => sanitize_text_field($_POST['name']), 'contact_person' => sanitize_text_field($_POST['contact_person']), 'phone' => sanitize_text_field($_POST['phone']), 'email' => sanitize_email($_POST['email']), 'address' => sanitize_textarea_field($_POST['address']), 'lead_time_days' => absint($_POST['lead_time_days']), 'payment_terms' => sanitize_text_field($_POST['payment_terms']), 'notes' => sanitize_textarea_field($_POST['notes']), 'active' => isset($_POST['active']) ? 1 : 0, 'updated_at' => $n];
            if ($id)
                $wpdb->update($t, $d, ['id' => $id]);
            else {
                $d += ['uuid' => BC_RMS_DB::uuid(), 'created_at' => $n];
                $wpdb->insert($t, $d);
            }
            self::go('bc-rms-suppliers');
        }
        if ($a === 'pricing') {
            $t = BC_RMS_DB::table('supplier_items');
            $id = absint($_POST['id'] ?? 0);
            $iid = absint($_POST['ingredient_id']);
            if (isset($_POST['preferred']))
                $wpdb->update($t, ['preferred' => 0], ['ingredient_id' => $iid]);
            $d = ['supplier_id' => absint($_POST['supplier_id']), 'ingredient_id' => $iid, 'purchase_unit_id' => absint($_POST['purchase_unit_id']), 'purchase_qty' => (float) $_POST['purchase_qty'], 'purchase_price' => (float) $_POST['purchase_price'], 'minimum_order' => (float) $_POST['minimum_order'], 'lead_time_days' => absint($_POST['lead_time_days']), 'preferred' => isset($_POST['preferred']) ? 1 : 0, 'active' => isset($_POST['active']) ? 1 : 0, 'updated_at' => $n];
            if ($id)
                $wpdb->update($t, $d, ['id' => $id]);
            else {
                $d += ['uuid' => BC_RMS_DB::uuid(), 'created_at' => $n];
                $wpdb->insert($t, $d);
            }
            self::go('bc-rms-pricing');
        }
        if ($a === 'unit') {
            $t = BC_RMS_DB::table('units');
            $id = absint($_POST['id'] ?? 0);
            $d = ['name' => sanitize_text_field($_POST['name']), 'symbol' => sanitize_text_field($_POST['symbol']), 'unit_type' => sanitize_key($_POST['unit_type']), 'factor_to_base' => (float) $_POST['factor_to_base'], 'is_base' => isset($_POST['is_base']) ? 1 : 0, 'active' => isset($_POST['active']) ? 1 : 0, 'updated_at' => $n];
            if ($id)
                $wpdb->update($t, $d, ['id' => $id]);
            else {
                $d += ['uuid' => BC_RMS_DB::uuid(), 'created_at' => $n];
                $wpdb->insert($t, $d);
            }
            self::go('bc-rms-units');
        }
        if ($a === 'recipe') {
            if (!current_user_can('bc_manage_recipes')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('recipes');$id=absint($_POST['id']??0);
            $d=['name'=>sanitize_text_field($_POST['name']??''),'description'=>sanitize_textarea_field($_POST['description']??''),'yield_qty'=>(float)($_POST['yield_qty']??1),'yield_unit_id'=>absint($_POST['yield_unit_id']??0)?:null,'servings'=>max(0.0001,(float)($_POST['servings']??1)),'waste_percent'=>max(0,(float)($_POST['waste_percent']??0)),'active'=>isset($_POST['active'])?1:0,'updated_at'=>$n];
            if(!$d['name'])wp_die('Recipe name is required.');
            if($id)$wpdb->update($t,$d,['id'=>$id]);else{$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);$id=(int)$wpdb->insert_id;}
            self::go('bc-rms-recipes',['saved'=>1,'edit'=>$id]);
        }
        if ($a === 'recipe_item') {
            if (!current_user_can('bc_manage_recipes')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('recipe_items');$id=absint($_POST['id']??0);$recipe_id=absint($_POST['recipe_id']??0);
            $d=['recipe_id'=>$recipe_id,'ingredient_id'=>absint($_POST['ingredient_id']??0),'quantity'=>(float)($_POST['quantity']??0),'unit_id'=>absint($_POST['unit_id']??0),'notes'=>sanitize_text_field($_POST['notes']??''),'sort_order'=>absint($_POST['sort_order']??0),'updated_at'=>$n];
            if(!$recipe_id||!$d['ingredient_id']||$d['quantity']<=0||!$d['unit_id'])wp_die('Recipe, ingredient, quantity, and unit are required.');
            if($id)$wpdb->update($t,$d,['id'=>$id]);else{$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);}
            self::go('bc-rms-recipes',['saved'=>1,'edit'=>$recipe_id]);
        }
        if ($a === 'settings') {
            $t = BC_RMS_DB::table('settings');
            foreach (['business_name', 'currency', 'currency_symbol', 'timezone', 'target_food_cost', 'target_margin'] as $k) {
                $v = sanitize_text_field($_POST[$k]);
                $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE setting_key=%s", $k));
                if ($id)
                    $wpdb->update($t, ['setting_value' => $v, 'updated_at' => $n], ['id' => $id]);
                else
                    $wpdb->insert($t, ['setting_key' => $k, 'setting_value' => $v, 'created_at' => $n, 'updated_at' => $n]);
            }
            self::go('bc-rms-settings');
        }
    }
    private static function f($l, $n, $v = '', $type = 'text', $step = '')
    {
        echo '<p><label><b>' . esc_html($l) . '</b><br><input class="regular-text" type="' . esc_attr($type) . '" name="' . esc_attr($n) . '" value="' . esc_attr($v) . '" ' . ($step ? 'step="' . esc_attr($step) . '"' : '') . '></label></p>';
    }
    private static function c($l, $n, $v = true)
    {
        echo '<p><label><input type="checkbox" name="' . esc_attr($n) . '" value="1" ' . checked($v, true, false) . '> ' . esc_html($l) . '</label></p>';
    }
    private static function sel($l, $n, $rows, $v = 0)
    {
        echo '<p><label><b>' . esc_html($l) . '</b><br><select name="' . esc_attr($n) . '" required><option value="">— Select —</option>';
        foreach ($rows as $r)
            echo '<option value="' . $r->id . '" ' . selected($v, $r->id, false) . '>' . esc_html($r->name . (isset($r->symbol) ? ' (' . $r->symbol . ')' : '')) . '</option>';
        echo '</select></label></p>';
    }
    private static function form($action, $id = 0)
    {
        echo '<form method="post" class="bc-card">';
        self::nonce();
        echo '<input type="hidden" name="bc_rms_action" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '">';
    }
    private static function end()
    {
        submit_button();
        echo '</form>';
    }
    private static function saved()
    {
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Saved successfully.</p></div>';
        if (isset($_GET['deleted'])) echo '<div class="notice notice-success"><p>Record permanently deleted.</p></div>';
        if (isset($_GET['bc_error'])) echo '<div class="notice notice-error"><p>'.esc_html(wp_unslash($_GET['bc_error'])).'</p></div>';
    }
    public static function dashboard()
    {
        global $wpdb;
        echo '<div class="wrap bc-wrap"><h1>Byahero Chix RMS <small>v0.3.0</small></h1><div class="bc-stats">';
        foreach (['Ingredients' => 'ingredients', 'Suppliers' => 'suppliers', 'Categories' => 'ingredient_categories', 'Units' => 'units'] as $l => $t) {
            $c = $wpdb->get_var('SELECT COUNT(*) FROM ' . BC_RMS_DB::table($t) . ' WHERE active=1');
            echo '<div class="bc-stat"><strong>' . $c . '</strong><span>' . $l . '</span></div>';
        }
        echo '</div><div class="bc-card"><h2>Recipe & Costing Build</h2><p>Master Data plus Recipe Builder and live ingredient costing based on preferred supplier pricing.</p></div></div>';
    }
    public static function ingredients()
    {
        self::saved();
        global $wpdb;
        $it = BC_RMS_DB::table('ingredients');
        $ct = BC_RMS_DB::table('ingredient_categories');
        $ut = BC_RMS_DB::table('units');
        $id = absint($_GET['edit'] ?? 0);
        $r = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $it WHERE id=%d", $id)) : null;
        $cats = $wpdb->get_results("SELECT * FROM $ct WHERE active=1 ORDER BY name");
        $units = $wpdb->get_results("SELECT * FROM $ut WHERE active=1 ORDER BY unit_type,name");
        echo '<div class="wrap bc-wrap"><h1>Ingredients</h1><div class="bc-grid"><div>';
        self::form('ingredient', $id);
        self::f('Name', 'name', $r->name ?? '');
        self::sel('Category', 'category_id', $cats, $r->category_id ?? 0);
        self::sel('Base Unit', 'base_unit_id', $units, $r->base_unit_id ?? 0);
        self::f('Minimum Stock', 'minimum_stock', $r->minimum_stock ?? 0, 'number', '0.0001');
        self::f('Reorder Level', 'reorder_level', $r->reorder_level ?? 0, 'number', '0.0001');
        self::c('Track Inventory', 'track_inventory', !$r || $r->track_inventory);
        self::c('Active', 'active', !$r || $r->active);
        self::end();
        echo '</div><div class="bc-card"><table class="widefat striped"><tr><th>Name</th><th>Category</th><th>Unit</th><th>Preferred Cost</th><th></th></tr>';
        foreach ($wpdb->get_results("SELECT i.*,c.name category,u.symbol FROM $it i LEFT JOIN $ct c ON c.id=i.category_id JOIN $ut u ON u.id=i.base_unit_id ORDER BY i.name") as $x) {
            $cost = BC_RMS_DB::preferred_cost($x->id);
            echo '<tr><td>' . $x->name . '</td><td>' . $x->category . '</td><td>' . $x->symbol . '</td><td>' . ($cost === null ? '—' : '₱' . number_format($cost, 4) . '/' . $x->symbol) . '</td><td><a href="?page=bc-rms-ingredients&edit=' . $x->id . '">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Permanently delete this ingredient? This cannot be undone.\')" href="' . esc_url(self::delete_url('bc-rms-ingredients','ingredient',$x->id)) . '">Delete</a></td></tr>';
        }
        echo '</table></div></div></div>';
    }
    public static function categories()
    {
        self::saved();
        global $wpdb;
        $t = BC_RMS_DB::table('ingredient_categories');
        $id = absint($_GET['edit'] ?? 0);
        $r = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id)) : null;
        echo '<div class="wrap bc-wrap"><h1>Categories</h1><div class="bc-grid"><div>';
        self::form('category', $id);
        self::f('Name', 'name', $r->name ?? '');
        self::c('Active', 'active', !$r || $r->active);
        self::end();
        echo '</div><div class="bc-card"><table class="widefat striped">';
        foreach ($wpdb->get_results("SELECT * FROM $t ORDER BY name") as $x)
            echo '<tr><td>' . $x->name . '</td><td><a href="?page=bc-rms-categories&edit=' . $x->id . '">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Permanently delete this category? This cannot be undone.\')" href="' . esc_url(self::delete_url('bc-rms-categories','category',$x->id)) . '">Delete</a></td></tr>';
        echo '</table></div></div></div>';
    }
    public static function suppliers()
    {
        self::saved();
        global $wpdb;
        $t = BC_RMS_DB::table('suppliers');
        $id = absint($_GET['edit'] ?? 0);
        $r = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id)) : null;
        echo '<div class="wrap bc-wrap"><h1>Suppliers</h1><div class="bc-grid"><div>';
        self::form('supplier', $id);
        foreach ([['Name', 'name'], ['Contact Person', 'contact_person'], ['Phone', 'phone'], ['Email', 'email'], ['Payment Terms', 'payment_terms']] as $f)
            self::f($f[0], $f[1], $r->{$f[1]} ?? '');
        self::f('Lead Time (days)', 'lead_time_days', $r->lead_time_days ?? 0, 'number', '1');
        self::c('Active', 'active', !$r || $r->active);
        self::end();
        echo '</div><div class="bc-card"><table class="widefat striped"><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th></th></tr>';
        foreach ($wpdb->get_results("SELECT * FROM $t ORDER BY name") as $x)
            echo '<tr><td>' . $x->name . '</td><td>' . $x->contact_person . '</td><td>' . $x->phone . '</td><td><a href="?page=bc-rms-suppliers&edit=' . $x->id . '">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Permanently delete this supplier? This cannot be undone.\')" href="' . esc_url(self::delete_url('bc-rms-suppliers','supplier',$x->id)) . '">Delete</a></td></tr>';
        echo '</table></div></div></div>';
    }
    public static function pricing()
    {
        self::saved();
        global $wpdb;
        $t = BC_RMS_DB::table('supplier_items');
        $i = BC_RMS_DB::table('ingredients');
        $s = BC_RMS_DB::table('suppliers');
        $u = BC_RMS_DB::table('units');
        $id = absint($_GET['edit'] ?? 0);
        $r = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id)) : null;
        echo '<div class="wrap bc-wrap"><h1>Supplier Pricing</h1><div class="bc-grid"><div>';
        self::form('pricing', $id);
        self::sel('Ingredient', 'ingredient_id', $wpdb->get_results("SELECT id,name FROM $i WHERE active=1 ORDER BY name"), $r->ingredient_id ?? 0);
        self::sel('Supplier', 'supplier_id', $wpdb->get_results("SELECT id,name FROM $s WHERE active=1 ORDER BY name"), $r->supplier_id ?? 0);
        self::sel('Purchase Unit', 'purchase_unit_id', $wpdb->get_results("SELECT id,name,symbol FROM $u WHERE active=1 ORDER BY name"), $r->purchase_unit_id ?? 0);
        self::f('Purchase Qty', 'purchase_qty', $r->purchase_qty ?? 1, 'number', '0.0001');
        self::f('Purchase Price', 'purchase_price', $r->purchase_price ?? 0, 'number', '0.01');
        self::f('Minimum Order', 'minimum_order', $r->minimum_order ?? 0, 'number', '0.0001');
        self::f('Lead Time', 'lead_time_days', $r->lead_time_days ?? 0, 'number', '1');
        self::c('Preferred Supplier', 'preferred', $r && $r->preferred);
        self::c('Active', 'active', !$r || $r->active);
        self::end();
        echo '</div><div class="bc-card"><table class="widefat striped"><tr><th>Ingredient</th><th>Supplier</th><th>Purchase</th><th>Price</th><th>Preferred</th><th></th></tr>';
        foreach ($wpdb->get_results("SELECT p.*,i.name ingredient,s.name supplier,u.symbol FROM $t p JOIN $i i ON i.id=p.ingredient_id JOIN $s s ON s.id=p.supplier_id JOIN $u u ON u.id=p.purchase_unit_id ORDER BY i.name,p.preferred DESC") as $x)
            echo '<tr><td>' . $x->ingredient . '</td><td>' . $x->supplier . '</td><td>' . $x->purchase_qty . ' ' . $x->symbol . '</td><td>₱' . number_format($x->purchase_price, 2) . '</td><td>' . ($x->preferred ? '✓' : '') . '</td><td><a href="?page=bc-rms-pricing&edit=' . $x->id . '">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Permanently delete this pricing record? This cannot be undone.\')" href="' . esc_url(self::delete_url('bc-rms-pricing','pricing',$x->id)) . '">Delete</a></td></tr>';
        echo '</table></div></div></div>';
    }
    public static function units()
    {
        self::saved();
        global $wpdb;
        $t = BC_RMS_DB::table('units');
        $id = absint($_GET['edit'] ?? 0);
        $r = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id)) : null;
        echo '<div class="wrap bc-wrap"><h1>Units</h1><div class="bc-grid"><div>';
        self::form('unit', $id);
        self::f('Name', 'name', $r->name ?? '');
        self::f('Symbol', 'symbol', $r->symbol ?? '');
        self::f('Unit Type', 'unit_type', $r->unit_type ?? 'count');
        self::f('Factor to Base', 'factor_to_base', $r->factor_to_base ?? 1, 'number', '0.00000001');
        self::c('Base Unit', 'is_base', $r && $r->is_base);
        self::c('Active', 'active', !$r || $r->active);
        self::end();
        echo '</div><div class="bc-card"><table class="widefat striped"><tr><th>Name</th><th>Symbol</th><th>Type</th><th>Factor</th><th></th></tr>';
        foreach ($wpdb->get_results("SELECT * FROM $t ORDER BY unit_type,name") as $x)
            echo '<tr><td>' . $x->name . '</td><td>' . $x->symbol . '</td><td>' . $x->unit_type . '</td><td>' . $x->factor_to_base . '</td><td><a href="?page=bc-rms-units&edit=' . $x->id . '">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Permanently delete this unit? This cannot be undone.\')" href="' . esc_url(self::delete_url('bc-rms-units','unit',$x->id)) . '">Delete</a></td></tr>';
        echo '</table></div></div></div>';
    }
    public static function recipes()
    {
        self::saved(); global $wpdb;
        $rt=BC_RMS_DB::table('recipes');$rit=BC_RMS_DB::table('recipe_items');$it=BC_RMS_DB::table('ingredients');$ut=BC_RMS_DB::table('units');
        $id=absint($_GET['edit']??0);$item_id=absint($_GET['edit_item']??0);
        $r=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM $rt WHERE id=%d",$id)):null;
        $item=$item_id?$wpdb->get_row($wpdb->prepare("SELECT * FROM $rit WHERE id=%d",$item_id)):null;
        $units=$wpdb->get_results("SELECT * FROM $ut WHERE active=1 ORDER BY unit_type,name");
        echo '<div class="wrap bc-wrap"><h1>Recipes</h1><div class="bc-grid"><div>';
        self::form('recipe',$id);self::f('Recipe Name','name',$r->name??'');self::f('Yield Quantity','yield_qty',$r->yield_qty??1,'number','0.0001');self::sel('Yield Unit','yield_unit_id',$units,$r->yield_unit_id??0);self::f('Servings','servings',$r->servings??1,'number','0.0001');self::f('Waste %','waste_percent',$r->waste_percent??0,'number','0.01');self::c('Active','active',!$r||$r->active);self::end();
        if($id){
            echo '<div class="bc-card"><h2>Add Recipe Ingredient</h2><form method="post">';self::nonce();echo '<input type="hidden" name="bc_rms_action" value="recipe_item"><input type="hidden" name="id" value="'.absint($item_id).'"><input type="hidden" name="recipe_id" value="'.absint($id).'">';
            self::sel('Ingredient','ingredient_id',$wpdb->get_results("SELECT id,name FROM $it WHERE active=1 ORDER BY name"),$item->ingredient_id??0);self::f('Quantity','quantity',$item->quantity??1,'number','0.0001');self::sel('Unit','unit_id',$units,$item->unit_id??0);self::f('Notes','notes',$item->notes??'');self::f('Sort Order','sort_order',$item->sort_order??0,'number','1');submit_button($item_id?'Update Ingredient':'Add Ingredient');echo '</form></div>';
        }
        echo '</div><div>';
        if($id){$cost=BC_RMS_Costing_Service::recipe($id);echo '<div class="bc-card"><h2>Live Cost Summary</h2><div class="bc-cost-grid"><div><span>Ingredients</span><strong>₱'.number_format($cost['ingredient_cost'],2).'</strong></div><div><span>Waste</span><strong>₱'.number_format($cost['waste_cost'],2).'</strong></div><div><span>Total Recipe</span><strong>₱'.number_format($cost['total_cost'],2).'</strong></div><div><span>Per Serving</span><strong>₱'.number_format($cost['cost_per_serving'],2).'</strong></div></div>';if($cost['missing_costs'])echo '<p class="bc-warning">'.$cost['missing_costs'].' ingredient(s) have no usable preferred supplier cost and are excluded from the total.</p>';echo '</div>';
            echo '<div class="bc-card"><h2>Recipe Ingredients</h2><table class="widefat striped"><tr><th>Ingredient</th><th>Quantity</th><th>Cost</th><th></th></tr>';
            $rows=$wpdb->get_results($wpdb->prepare("SELECT ri.*,i.name ingredient,u.symbol FROM $rit ri JOIN $it i ON i.id=ri.ingredient_id JOIN $ut u ON u.id=ri.unit_id WHERE ri.recipe_id=%d ORDER BY ri.sort_order,ri.id",$id));foreach($rows as $x){$c=BC_RMS_Costing_Service::ingredient_cost_for_quantity($x->ingredient_id,$x->quantity,$x->unit_id);echo '<tr><td>'.esc_html($x->ingredient).'</td><td>'.esc_html($x->quantity).' '.esc_html($x->symbol).'</td><td>'.(null===$c?'Missing price':'₱'.number_format($c,2)).'</td><td><a href="?page=bc-rms-recipes&edit='.$id.'&edit_item='.$x->id.'">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Delete this recipe ingredient?\')" href="'.esc_url(self::delete_url('bc-rms-recipes','recipe_item',$x->id)).'">Delete</a></td></tr>';}echo '</table></div>';
        }
        echo '<div class="bc-card"><h2>Recipe Master</h2><table class="widefat striped"><tr><th>Recipe</th><th>Servings</th><th>Total Cost</th><th>Cost/Serving</th><th></th></tr>';
        foreach($wpdb->get_results("SELECT * FROM $rt ORDER BY active DESC,name") as $x){$c=BC_RMS_Costing_Service::recipe($x->id);echo '<tr><td>'.esc_html($x->name).'</td><td>'.esc_html($x->servings).'</td><td>₱'.number_format($c['total_cost'],2).'</td><td>₱'.number_format($c['cost_per_serving'],2).'</td><td><a href="?page=bc-rms-recipes&edit='.$x->id.'">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Delete this recipe and all its recipe ingredients?\')" href="'.esc_url(self::delete_url('bc-rms-recipes','recipe',$x->id)).'">Delete</a></td></tr>';}echo '</table></div></div></div></div>';
    }
    public static function settings()
    {
        self::saved();
        global $wpdb;
        $t = BC_RMS_DB::table('settings');
        $get = fn($k) => $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $t WHERE setting_key=%s", $k));
        echo '<div class="wrap bc-wrap"><h1>Settings</h1>';
        self::form('settings');
        foreach ([['Business Name', 'business_name'], ['Currency', 'currency'], ['Currency Symbol', 'currency_symbol'], ['Timezone', 'timezone'], ['Target Food Cost %', 'target_food_cost'], ['Target Margin %', 'target_margin']] as $f)
            self::f($f[0], $f[1], $get($f[1]));
        self::end();
        echo '</div>';
    }
}