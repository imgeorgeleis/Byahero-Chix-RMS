<?php
if (!defined('ABSPATH'))
    exit;
class BC_RMS_Admin
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'save']);
        add_action('wp_ajax_bc_rms_checkout', [__CLASS__, 'ajax_checkout']);
        add_action('wp_ajax_bc_rms_pos_catalog', [__CLASS__, 'ajax_pos_catalog']);
        add_action('wp_ajax_bc_rms_hold_order', [__CLASS__, 'ajax_hold_order']);
        add_action('wp_ajax_bc_rms_held_orders', [__CLASS__, 'ajax_held_orders']);
        add_action('wp_ajax_bc_rms_resume_order', [__CLASS__, 'ajax_resume_order']);
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
        add_submenu_page('bc-rms', 'Menu Categories', 'Menu Categories', 'bc_manage_products', 'bc-rms-product-categories', [__CLASS__, 'product_categories']);
        add_submenu_page('bc-rms', 'POS', 'POS', 'bc_use_pos', 'bc-rms-pos', [__CLASS__, 'pos']);
        add_submenu_page('bc-rms', 'Orders', 'Orders', 'bc_view_orders', 'bc-rms-orders', [__CLASS__, 'orders']);
        add_submenu_page('bc-rms', 'Inventory', 'Inventory', 'bc_view_inventory', 'bc-rms-inventory', [__CLASS__, 'inventory']);
        add_submenu_page('bc-rms', 'Receive Stock', 'Receive Stock', 'bc_manage_inventory', 'bc-rms-receive-stock', [__CLASS__, 'receive_stock']);
        add_submenu_page('bc-rms', 'Purchase Orders', 'Purchase Orders', 'bc_manage_inventory', 'bc-rms-purchase-orders', [__CLASS__, 'purchase_orders']);
        add_submenu_page('bc-rms', 'Stock Movements', 'Stock Movements', 'bc_view_inventory', 'bc-rms-stock-movements', [__CLASS__, 'stock_movements']);
        add_submenu_page('bc-rms', 'Packaging Stock', 'Packaging Stock', 'bc_view_inventory', 'bc-rms-packaging-stock', [__CLASS__, 'packaging_stock']);
        add_submenu_page('bc-rms', 'Products / Menu', 'Products / Menu', 'bc_manage_products', 'bc-rms-products', [__CLASS__, 'products']);
        add_submenu_page('bc-rms', 'Packaging', 'Packaging', 'bc_manage_packaging', 'bc-rms-packaging', [__CLASS__, 'packaging']);
        add_submenu_page('bc-rms', 'Variants', 'Variants', 'bc_manage_products', 'bc-rms-variants', [__CLASS__, 'variants']);
        add_submenu_page('bc-rms', 'Modifiers', 'Modifiers', 'bc_manage_modifiers', 'bc-rms-modifiers', [__CLASS__, 'modifiers']);
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
            'product_category'=>['product_categories','bc_manage_products','bc-rms-product-categories'],
            'product'=>['products','bc_manage_products','bc-rms-products'],
            'packaging'=>['packaging','bc_manage_packaging','bc-rms-packaging'],
            'variant'=>['product_variants','bc_manage_products','bc-rms-variants'],
            'modifier_group'=>['modifier_groups','bc_manage_modifiers','bc-rms-modifiers'],
            'modifier'=>['modifiers','bc_manage_modifiers','bc-rms-modifiers'],
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
        } elseif ($type === 'packaging') {
            $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('product_packaging').' WHERE packaging_id=%d',$id));
            if($count) $blocked='This packaging item is assigned to a product. Remove the assignment first.';
        } elseif ($type === 'modifier_group') {
            $m=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('modifiers').' WHERE group_id=%d',$id));
            $p=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('product_modifier_groups').' WHERE group_id=%d',$id));
            if($m+$p) $blocked='This modifier group has modifiers or product assignments. Remove them first.';
        } elseif ($type === 'product') {
            $v=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('product_variants').' WHERE product_id=%d',$id));
            $p=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('product_packaging').' WHERE product_id=%d',$id));
            $g=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('product_modifier_groups').' WHERE product_id=%d',$id));
            if($v+$p+$g) $blocked='This product has variants, packaging, or modifier assignments. Remove them first.';
        } elseif ($type === 'product_category') {
            $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('products').' WHERE category_id=%d',$id));
            if($count) $blocked='This menu category is assigned to one or more products. Reassign those products first.';
        } elseif ($type === 'recipe') {
            $products=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('products').' WHERE recipe_id=%d',$id));
            if($products) $blocked='This recipe is linked to one or more menu products. Unlink or reassign those products first.';
            $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.BC_RMS_DB::table('recipe_items').' WHERE recipe_id=%d',$id));
            if(!$blocked && $count) $wpdb->delete(BC_RMS_DB::table('recipe_items'),['recipe_id'=>$id],['%d']);
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
        if ($a === 'void_order') {
            if (!current_user_can('bc_view_orders')) wp_die('Not allowed.');
            $id=absint($_POST['id']??0);
            $reason=sanitize_text_field($_POST['reason']??'');
            if(!$id || !$reason) wp_die('A valid order and void reason are required.');
            $r=BC_RMS_POS_Service::void_order($id,$reason);
            if(is_wp_error($r)) wp_die(esc_html($r->get_error_message()));
            self::go('bc-rms-orders',['view'=>$id,'saved'=>1]);
        }
        if ($a === 'purchase_order_create') {
            if(!current_user_can('bc_manage_inventory')) wp_die('Not allowed.');
            $r=BC_RMS_Purchasing_Service::create($_POST);if(is_wp_error($r))wp_die(esc_html($r->get_error_message()));
            self::go('bc-rms-purchase-orders',['view'=>$r,'saved'=>1]);
        }
        if ($a === 'purchase_order_status') {
            if(!current_user_can('bc_manage_inventory')) wp_die('Not allowed.');
            $id=absint($_POST['id']??0);$r=BC_RMS_Purchasing_Service::set_status($id,sanitize_key($_POST['status']??''));
            if(is_wp_error($r))wp_die(esc_html($r->get_error_message()));self::go('bc-rms-purchase-orders',['view'=>$id,'saved'=>1]);
        }
        if ($a === 'purchase_order_receive') {
            if(!current_user_can('bc_manage_inventory')) wp_die('Not allowed.');
            $id=absint($_POST['id']??0);$r=BC_RMS_Purchasing_Service::receive($id,$_POST);
            if(is_wp_error($r))wp_die(esc_html($r->get_error_message()));self::go('bc-rms-purchase-orders',['view'=>$id,'saved'=>1]);
        }
        if ($a === 'inventory_receive') {
            if(!current_user_can('bc_manage_inventory')) wp_die('Not allowed.');
            $r=BC_RMS_Inventory_Service::receive($_POST);
            if(is_wp_error($r)) wp_die(esc_html($r->get_error_message()));
            self::go('bc-rms-receive-stock',['saved'=>1]);
        }
        if ($a === 'packaging_adjust') {
            if(!current_user_can('bc_manage_inventory')) wp_die('Not allowed.');
            $r=BC_RMS_Inventory_Service::adjust_packaging($_POST);
            if(is_wp_error($r)) wp_die(esc_html($r->get_error_message()));
            self::go('bc-rms-packaging-stock',['saved'=>1]);
        }
        if ($a === 'inventory_adjust') {
            if(!current_user_can('bc_manage_inventory')) wp_die('Not allowed.');
            $r=BC_RMS_Inventory_Service::adjust($_POST);
            if(is_wp_error($r)) wp_die(esc_html($r->get_error_message()));
            self::go('bc-rms-inventory',['saved'=>1]);
        }
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
        if ($a === 'packaging') {
            if (!current_user_can('bc_manage_packaging')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('packaging');$id=absint($_POST['id']??0);
            $d=['name'=>sanitize_text_field($_POST['name']??''),'unit_cost'=>max(0,(float)($_POST['unit_cost']??0)),'notes'=>sanitize_textarea_field($_POST['notes']??''),'active'=>isset($_POST['active'])?1:0,'updated_at'=>$n];
            if(!$d['name']) wp_die('Packaging name is required.');
            if($id)$wpdb->update($t,$d,['id'=>$id]);else{$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);}self::go('bc-rms-packaging');
        }
        if ($a === 'variant') {
            if (!current_user_can('bc_manage_products')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('product_variants');$id=absint($_POST['id']??0);$pid=absint($_POST['product_id']??0);
            if(isset($_POST['default_variant']))$wpdb->update($t,['default_variant'=>0],['product_id'=>$pid]);
            $d=['product_id'=>$pid,'name'=>sanitize_text_field($_POST['name']??''),'sku'=>sanitize_text_field($_POST['sku']??'')?:null,'price_adjustment'=>(float)($_POST['price_adjustment']??0),'cost_adjustment'=>(float)($_POST['cost_adjustment']??0),'default_variant'=>isset($_POST['default_variant'])?1:0,'active'=>isset($_POST['active'])?1:0,'sort_order'=>absint($_POST['sort_order']??0),'updated_at'=>$n];
            if(!$pid||!$d['name'])wp_die('Product and variant name are required.');
            if($id)$wpdb->update($t,$d,['id'=>$id]);else{$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);}self::go('bc-rms-variants');
        }
        if ($a === 'modifier_group') {
            if (!current_user_can('bc_manage_modifiers')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('modifier_groups');$id=absint($_POST['id']??0);$type=in_array($_POST['selection_type']??'multiple',['single','multiple'],true)?$_POST['selection_type']:'multiple';
            $d=['name'=>sanitize_text_field($_POST['name']??''),'selection_type'=>$type,'min_select'=>absint($_POST['min_select']??0),'max_select'=>absint($_POST['max_select']??0),'required_group'=>isset($_POST['required_group'])?1:0,'active'=>isset($_POST['active'])?1:0,'sort_order'=>absint($_POST['sort_order']??0),'updated_at'=>$n];
            if(!$d['name'])wp_die('Modifier group name is required.');
            if($id)$wpdb->update($t,$d,['id'=>$id]);else{$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);}self::go('bc-rms-modifiers');
        }
        if ($a === 'modifier') {
            if (!current_user_can('bc_manage_modifiers')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('modifiers');$id=absint($_POST['id']??0);
            $d=['group_id'=>absint($_POST['group_id']??0),'name'=>sanitize_text_field($_POST['name']??''),'price_adjustment'=>(float)($_POST['price_adjustment']??0),'cost_adjustment'=>(float)($_POST['cost_adjustment']??0),'active'=>isset($_POST['active'])?1:0,'sort_order'=>absint($_POST['sort_order']??0),'updated_at'=>$n];
            if(!$d['group_id']||!$d['name'])wp_die('Modifier group and modifier name are required.');
            if($id)$wpdb->update($t,$d,['id'=>$id]);else{$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);}self::go('bc-rms-modifiers');
        }
        if ($a === 'product_packaging') {
            if (!current_user_can('bc_manage_packaging')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('product_packaging');$pid=absint($_POST['product_id']??0);$pk=absint($_POST['packaging_id']??0);$qty=max(.0001,(float)($_POST['quantity']??1));
            if(!$pid||!$pk)wp_die('Product and packaging are required.');
            $eid=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE product_id=%d AND packaging_id=%d",$pid,$pk));
            if($eid)$wpdb->update($t,['quantity'=>$qty,'updated_at'=>$n],['id'=>$eid]);else$wpdb->insert($t,['uuid'=>BC_RMS_DB::uuid(),'product_id'=>$pid,'packaging_id'=>$pk,'quantity'=>$qty,'created_at'=>$n,'updated_at'=>$n]);
            self::go('bc-rms-products',['edit'=>$pid,'saved'=>1]);
        }
        if ($a === 'assign_modifier_group') {
            if (!current_user_can('bc_manage_modifiers')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('product_modifier_groups');$pid=absint($_POST['product_id']??0);$gid=absint($_POST['group_id']??0);
            if(!$pid||!$gid)wp_die('Product and modifier group are required.');
            if(!$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE product_id=%d AND group_id=%d",$pid,$gid)))$wpdb->insert($t,['uuid'=>BC_RMS_DB::uuid(),'product_id'=>$pid,'group_id'=>$gid,'created_at'=>$n]);
            self::go('bc-rms-products',['edit'=>$pid,'saved'=>1]);
        }
        if ($a === 'remove_product_packaging') {
            if (!current_user_can('bc_manage_packaging')) wp_die('Not allowed.');
            $id=absint($_POST['id']??0);$pid=absint($_POST['product_id']??0);
            if($id) $wpdb->delete(BC_RMS_DB::table('product_packaging'),['id'=>$id],['%d']);
            self::go('bc-rms-products',['edit'=>$pid,'saved'=>1]);
        }
        if ($a === 'remove_modifier_group') {
            if (!current_user_can('bc_manage_modifiers')) wp_die('Not allowed.');
            $id=absint($_POST['id']??0);$pid=absint($_POST['product_id']??0);
            if($id) $wpdb->delete(BC_RMS_DB::table('product_modifier_groups'),['id'=>$id],['%d']);
            self::go('bc-rms-products',['edit'=>$pid,'saved'=>1]);
        }
        if ($a === 'product_category') {
            if (!current_user_can('bc_manage_products')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('product_categories');$id=absint($_POST['id']??0);
            $d=[
                'name'=>sanitize_text_field($_POST['name']??''),
                'description'=>sanitize_textarea_field($_POST['description']??''),
                'sort_order'=>absint($_POST['sort_order']??0),
                'active'=>isset($_POST['active'])?1:0,
                'updated_at'=>$n
            ];
            if(!$d['name']) wp_die('Menu category name is required.');
            if($id) $wpdb->update($t,$d,['id'=>$id]);
            else {$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);}
            self::go('bc-rms-product-categories');
        }
        if ($a === 'product') {
            if (!current_user_can('bc_manage_products')) wp_die('Not allowed.');
            $t=BC_RMS_DB::table('products');$id=absint($_POST['id']??0);
            $sku=sanitize_text_field($_POST['sku']??'');
            $d=[
                'sku'=>$sku!==''?$sku:null,
                'name'=>sanitize_text_field($_POST['name']??''),
                'category_id'=>absint($_POST['category_id']??0)?:null,
                'recipe_id'=>absint($_POST['recipe_id']??0)?:null,
                'description'=>sanitize_textarea_field($_POST['description']??''),
                'selling_price'=>max(0,(float)($_POST['selling_price']??0)),
                'pos_enabled'=>isset($_POST['pos_enabled'])?1:0,
                'active'=>isset($_POST['active'])?1:0,
                'sort_order'=>absint($_POST['sort_order']??0),
                'updated_at'=>$n
            ];
            if(!$d['name']) wp_die('Product name is required.');
            if($sku!==''){
                $duplicate=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE sku=%s AND id<>%d",$sku,$id));
                if($duplicate) wp_die('SKU already exists. Please use a unique SKU.');
            }
            if($id) $wpdb->update($t,$d,['id'=>$id]);
            else {$d+=['uuid'=>BC_RMS_DB::uuid(),'created_at'=>$n];$wpdb->insert($t,$d);$id=(int)$wpdb->insert_id;}
            self::go('bc-rms-products',['saved'=>1,'edit'=>$id]);
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
    private static function sel_optional($l,$n,$rows,$v=0)
    {
        echo '<p><label><b>'.esc_html($l).'</b><br><select name="'.esc_attr($n).'"><option value="">— None —</option>';
        foreach($rows as $r) echo '<option value="'.absint($r->id).'" '.selected($v,$r->id,false).'>'.esc_html($r->name).'</option>';
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
        echo '<div class="wrap bc-wrap"><h1>Byahero Chix RMS <small>v0.5.0</small></h1><div class="bc-stats">';
        foreach (['Ingredients' => 'ingredients', 'Recipes' => 'recipes', 'Products' => 'products', 'Suppliers' => 'suppliers'] as $l => $t) {
            $c = $wpdb->get_var('SELECT COUNT(*) FROM ' . BC_RMS_DB::table($t) . ' WHERE active=1');
            echo '<div class="bc-stat"><strong>' . $c . '</strong><span>' . $l . '</span></div>';
        }
        echo '</div><div class="bc-card"><h2>Product Configuration & Costing Build</h2><p>Products now support packaging costs, variants, and modifier/add-on groups in preparation for the POS order engine.</p></div></div>';
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
    /**
     * Render menu/product categories.
     */
    public static function inventory()
    {
        if(!current_user_can('bc_view_inventory')) wp_die('Not allowed.');
        self::saved();global $wpdb;$it=BC_RMS_DB::table('ingredients');$ut=BC_RMS_DB::table('units');$mt=BC_RMS_DB::table('inventory_movements');
        $rows=$wpdb->get_results("SELECT i.id,i.name,i.minimum_stock,i.reorder_level,u.symbol,COALESCE(SUM(m.quantity_delta),0) stock FROM $it i JOIN $ut u ON u.id=i.base_unit_id LEFT JOIN $mt m ON m.ingredient_id=i.id WHERE i.active=1 AND i.track_inventory=1 GROUP BY i.id ORDER BY i.name");
        $units=$wpdb->get_results("SELECT id,name,symbol,unit_type FROM $ut WHERE active=1 ORDER BY unit_type,name");
        echo '<div class="wrap bc-wrap"><h1>Inventory</h1><div class="bc-card bc-wide"><table class="widefat striped"><tr><th>Ingredient</th><th>Stock on Hand</th><th>Reorder Level</th><th>Status</th></tr>';
        foreach($rows as $r){$low=(float)$r->stock<=(float)$r->reorder_level;echo '<tr><td><strong>'.esc_html($r->name).'</strong></td><td>'.number_format($r->stock,4).' '.esc_html($r->symbol).'</td><td>'.number_format($r->reorder_level,4).' '.esc_html($r->symbol).'</td><td>'.($low?'<strong>LOW STOCK</strong>':'OK').'</td></tr>';}
        echo '</table></div><div class="bc-grid"><div class="bc-card"><h2>Manual Adjustment / Wastage</h2><form method="post">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="inventory_adjust"><p><label><b>Ingredient</b><br><select name="ingredient_id" required><option value="">— Select —</option>';foreach($rows as $r)echo '<option value="'.absint($r->id).'">'.esc_html($r->name).'</option>';echo '</select></label></p><p><label><b>Movement</b><br><select name="movement_type"><option value="adjustment_in">Adjustment In (+)</option><option value="adjustment_out">Adjustment Out (-)</option><option value="waste">Waste / Spoilage (-)</option></select></label></p><p><label><b>Quantity</b><br><input type="number" min="0.0001" step="0.0001" name="quantity" required></label></p><p><label><b>Unit</b><br><select name="unit_id" required><option value="">— Select —</option>';foreach($units as $u)echo '<option value="'.absint($u->id).'">'.esc_html($u->name.' ('.$u->symbol.')').'</option>';echo '</select></label></p><p><label><b>Reason / Notes</b><br><input class="regular-text" name="notes" required></label></p>';submit_button('Save Movement');echo '</form></div><div class="bc-card"><h2>How stock works</h2><p>Stock is calculated from the inventory ledger. Receiving and positive adjustments add stock. Sales, wastage and negative adjustments subtract stock. Voiding a completed sale creates a reversal movement instead of deleting history.</p><p><a class="button" href="?page=bc-rms-receive-stock">Receive Stock</a> <a class="button" href="?page=bc-rms-stock-movements">View Ledger</a></p></div></div></div>';
    }

    public static function purchase_orders()
    {
        if(!current_user_can('bc_manage_inventory'))wp_die('Not allowed.');
        self::saved();global $wpdb;$pot=BC_RMS_DB::table('purchase_orders');$pit=BC_RMS_DB::table('purchase_order_items');$st=BC_RMS_DB::table('suppliers');$it=BC_RMS_DB::table('ingredients');$ut=BC_RMS_DB::table('units');
        $view=absint($_GET['view']??0);
        echo '<div class="wrap bc-wrap"><h1>Purchase Orders</h1>';
        if($view){
            $po=$wpdb->get_row($wpdb->prepare("SELECT po.*,s.name supplier FROM $pot po JOIN $st s ON s.id=po.supplier_id WHERE po.id=%d",$view));
            $item=$po?$wpdb->get_row($wpdb->prepare("SELECT pi.*,i.name ingredient,u.name unit_name,u.symbol FROM $pit pi JOIN $it i ON i.id=pi.ingredient_id JOIN $ut u ON u.id=pi.purchase_unit_id WHERE pi.purchase_order_id=%d ORDER BY pi.id LIMIT 1",$view)):null;
            if(!$po||!$item){echo '<div class="notice notice-error"><p>Purchase order not found.</p></div></div>';return;}
            $remaining=max(0,(float)$item->ordered_quantity-(float)$item->received_quantity);
            echo '<div class="bc-card"><h2>'.esc_html($po->po_number).'</h2><p><strong>'.esc_html(strtoupper(str_replace('_',' ',$po->status))).'</strong> · '.esc_html($po->supplier).' · Ordered '.esc_html($po->order_date).($po->expected_date?' · Expected '.esc_html($po->expected_date):'').'</p><table class="widefat striped"><tr><th>Ingredient</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Unit Price</th><th>Total</th></tr><tr><td>'.esc_html($item->ingredient).'</td><td>'.number_format($item->ordered_quantity,4).' '.esc_html($item->symbol).'</td><td>'.number_format($item->received_quantity,4).' '.esc_html($item->symbol).'</td><td>'.number_format($remaining,4).' '.esc_html($item->symbol).'</td><td>₱'.number_format($item->unit_price,2).'</td><td>₱'.number_format($item->line_total,2).'</td></tr></table>';
            if(in_array($po->status,['draft','ordered'],true)){echo '<hr><form method="post" style="display:inline-block;margin-right:10px">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="purchase_order_status"><input type="hidden" name="id" value="'.absint($po->id).'"><input type="hidden" name="status" value="'.($po->status==='draft'?'ordered':'cancelled').'">';submit_button($po->status==='draft'?'Mark as Ordered':'Cancel PO','secondary','submit',false);echo '</form>';}
            if($remaining>0 && $po->status!=='cancelled'){echo '<hr><h3>Receive Against PO</h3><form method="post">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="purchase_order_receive"><input type="hidden" name="id" value="'.absint($po->id).'"><p><label><b>Receipt Date</b><br><input type="date" name="receipt_date" value="'.esc_attr(current_time('Y-m-d')).'" required></label></p><p><label><b>Quantity to Receive</b><br><input type="number" name="receive_quantity" min="0.0001" max="'.esc_attr($remaining).'" step="0.0001" required> '.esc_html($item->symbol).'</label></p>';submit_button('Receive Stock');echo '</form>';}
            echo '</div><p><a href="?page=bc-rms-purchase-orders">← Back to Purchase Orders</a></p></div>';return;
        }
        $suppliers=$wpdb->get_results("SELECT id,name FROM $st WHERE active=1 ORDER BY name");$ingredients=$wpdb->get_results("SELECT id,name FROM $it WHERE active=1 AND track_inventory=1 ORDER BY name");$units=$wpdb->get_results("SELECT id,name,symbol FROM $ut WHERE active=1 ORDER BY unit_type,name");
        echo '<div class="bc-grid"><div class="bc-card"><h2>Create Purchase Order</h2><form method="post">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="purchase_order_create"><p><label><b>Supplier</b><br><select name="supplier_id" required><option value="">— Select —</option>';foreach($suppliers as $x)echo '<option value="'.absint($x->id).'">'.esc_html($x->name).'</option>';echo '</select></label></p><p><label><b>Order Date</b><br><input type="date" name="order_date" value="'.esc_attr(current_time('Y-m-d')).'" required></label></p><p><label><b>Expected Date</b><br><input type="date" name="expected_date"></label></p><p><label><b>Ingredient</b><br><select name="ingredient_id" required><option value="">— Select —</option>';foreach($ingredients as $x)echo '<option value="'.absint($x->id).'">'.esc_html($x->name).'</option>';echo '</select></label></p><p><label><b>Purchase Unit</b><br><select name="purchase_unit_id" required><option value="">— Select —</option>';foreach($units as $u)echo '<option value="'.absint($u->id).'">'.esc_html($u->name.' ('.$u->symbol.')').'</option>';echo '</select></label></p><p><label><b>Order Quantity</b><br><input type="number" name="ordered_quantity" min="0.0001" step="0.0001" required></label></p><p><label><b>Unit Price</b><br><input type="number" name="unit_price" min="0" step="0.01" required></label></p><p><label><b>Supplier Reference</b><br><input class="regular-text" name="supplier_reference"></label></p><p><label><b>Notes</b><br><textarea class="large-text" rows="3" name="notes"></textarea></label></p>';submit_button('Create Draft PO');echo '</form></div><div class="bc-card"><h2>Purchase Order History</h2><table class="widefat striped"><tr><th>PO</th><th>Supplier</th><th>Date</th><th>Status</th><th>Total</th></tr>';
        $rows=$wpdb->get_results("SELECT po.*,s.name supplier FROM $pot po JOIN $st s ON s.id=po.supplier_id ORDER BY po.id DESC LIMIT 200");foreach($rows as $x)echo '<tr><td><a href="?page=bc-rms-purchase-orders&view='.absint($x->id).'">'.esc_html($x->po_number).'</a></td><td>'.esc_html($x->supplier).'</td><td>'.esc_html($x->order_date).'</td><td>'.esc_html(strtoupper(str_replace('_',' ',$x->status))).'</td><td>₱'.number_format($x->subtotal,2).'</td></tr>';echo '</table></div></div></div>';
    }

    public static function receive_stock()
    {
        if(!current_user_can('bc_manage_inventory')) wp_die('Not allowed.');
        self::saved();global $wpdb;$it=BC_RMS_DB::table('ingredients');$ut=BC_RMS_DB::table('units');$st=BC_RMS_DB::table('suppliers');
        $ingredients=$wpdb->get_results("SELECT id,name FROM $it WHERE active=1 AND track_inventory=1 ORDER BY name");$units=$wpdb->get_results("SELECT id,name,symbol FROM $ut WHERE active=1 ORDER BY unit_type,name");$suppliers=$wpdb->get_results("SELECT id,name FROM $st WHERE active=1 ORDER BY name");
        echo '<div class="wrap bc-wrap"><h1>Receive Stock</h1><div class="bc-card" style="max-width:720px"><form method="post">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="inventory_receive"><p><label><b>Receipt Date</b><br><input type="date" name="receipt_date" value="'.esc_attr(current_time('Y-m-d')).'" required></label></p><p><label><b>Supplier</b><br><select name="supplier_id"><option value="">— Optional —</option>';foreach($suppliers as $x)echo '<option value="'.absint($x->id).'">'.esc_html($x->name).'</option>';echo '</select></label></p><p><label><b>Supplier Reference / Invoice</b><br><input class="regular-text" name="reference_no"></label></p><p><label><b>Ingredient</b><br><select name="ingredient_id" required><option value="">— Select —</option>';foreach($ingredients as $x)echo '<option value="'.absint($x->id).'">'.esc_html($x->name).'</option>';echo '</select></label></p><p><label><b>Quantity Received</b><br><input type="number" min="0.0001" step="0.0001" name="quantity" required></label></p><p><label><b>Purchase Unit</b><br><select name="unit_id" required><option value="">— Select —</option>';foreach($units as $u)echo '<option value="'.absint($u->id).'">'.esc_html($u->name.' ('.$u->symbol.')').'</option>';echo '</select></label></p><p><label><b>Total Purchase Price</b><br><input type="number" min="0" step="0.01" name="purchase_price" value="0"></label></p><p><label><b>Notes</b><br><textarea class="large-text" rows="3" name="notes"></textarea></label></p>';submit_button('Receive Into Inventory');echo '</form></div></div>';
    }

    public static function stock_movements()
    {
        if(!current_user_can('bc_view_inventory')) wp_die('Not allowed.');
        global $wpdb;$mt=BC_RMS_DB::table('inventory_movements');$it=BC_RMS_DB::table('ingredients');$ut=BC_RMS_DB::table('units');
        $rows=$wpdb->get_results("SELECT m.*,i.name ingredient,u.symbol FROM $mt m JOIN $it i ON i.id=m.ingredient_id JOIN $ut u ON u.id=i.base_unit_id ORDER BY m.id DESC LIMIT 500");
        echo '<div class="wrap bc-wrap"><h1>Stock Movements</h1><div class="bc-card bc-wide"><table class="widefat striped"><tr><th>Date</th><th>Ingredient</th><th>Type</th><th>Quantity</th><th>Reference</th><th>Notes</th><th>User</th></tr>';
        foreach($rows as $r){$u=get_userdata($r->user_id);echo '<tr><td>'.esc_html($r->created_at).'</td><td>'.esc_html($r->ingredient).'</td><td>'.esc_html(strtoupper(str_replace('_',' ',$r->movement_type))).'</td><td>'.($r->quantity_delta>0?'+':'').number_format($r->quantity_delta,4).' '.esc_html($r->symbol).'</td><td>'.esc_html($r->reference_code?:'—').'</td><td>'.esc_html($r->notes?:'—').'</td><td>'.esc_html($u?$u->display_name:'—').'</td></tr>';}
        echo '</table></div></div>';
    }

    public static function packaging_stock()
    {
        if(!current_user_can('bc_view_inventory')) wp_die('Not allowed.');
        self::saved();global $wpdb;$pt=BC_RMS_DB::table('packaging');$mt=BC_RMS_DB::table('packaging_movements');
        $rows=$wpdb->get_results("SELECT p.*,COALESCE(SUM(m.quantity_delta),0) stock FROM $pt p LEFT JOIN $mt m ON m.packaging_id=p.id WHERE p.active=1 GROUP BY p.id ORDER BY p.name");
        echo '<div class="wrap bc-wrap"><h1>Packaging Stock</h1><div class="bc-card bc-wide"><table class="widefat striped"><tr><th>Packaging</th><th>Stock</th><th>Reorder</th><th>Status</th></tr>';
        foreach($rows as $r){$tracked=property_exists($r,'track_inventory')?(bool)$r->track_inventory:true;$reorder=property_exists($r,'reorder_level')?(float)$r->reorder_level:0;$low=$tracked && (float)$r->stock<=$reorder;echo '<tr><td><strong>'.esc_html($r->name).'</strong></td><td>'.($tracked?number_format($r->stock,4).' pc':'Not tracked').'</td><td>'.number_format($reorder,4).' pc</td><td>'.(!$tracked?'—':($low?'<strong>LOW STOCK</strong>':'OK')).'</td></tr>';}
        echo '</table></div><div class="bc-card" style="max-width:650px"><h2>Packaging Stock Movement</h2><form method="post">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="packaging_adjust"><p><label><b>Packaging</b><br><select name="packaging_id" required><option value="">— Select —</option>';foreach($rows as $r){$tracked=property_exists($r,'track_inventory')?(bool)$r->track_inventory:true;if($tracked)echo '<option value="'.absint($r->id).'">'.esc_html($r->name).'</option>';}echo '</select></label></p><p><label><b>Movement</b><br><select name="movement_type"><option value="receipt">Receive Stock (+)</option><option value="adjustment_in">Adjustment In (+)</option><option value="adjustment_out">Adjustment Out (-)</option><option value="waste">Waste / Damaged (-)</option></select></label></p><p><label><b>Quantity (pieces)</b><br><input type="number" min="0.0001" step="0.0001" name="quantity" required></label></p><p><label><b>Notes / Reference</b><br><input class="regular-text" name="notes"></label></p>';submit_button('Save Packaging Movement');echo '</form></div><div class="bc-card bc-wide"><h2>Recent Packaging Movements</h2><table class="widefat striped"><tr><th>Date</th><th>Packaging</th><th>Type</th><th>Qty</th><th>Reference</th><th>Notes</th></tr>';
        $mov=$wpdb->get_results("SELECT m.*,p.name FROM $mt m JOIN $pt p ON p.id=m.packaging_id ORDER BY m.id DESC LIMIT 200");foreach($mov as $m)echo '<tr><td>'.esc_html($m->created_at).'</td><td>'.esc_html($m->name).'</td><td>'.esc_html(strtoupper(str_replace('_',' ',$m->movement_type))).'</td><td>'.($m->quantity_delta>0?'+':'').number_format($m->quantity_delta,4).'</td><td>'.esc_html($m->reference_code?:'—').'</td><td>'.esc_html($m->notes?:'—').'</td></tr>';echo '</table></div></div>';
    }

    public static function pos()
    {
        if(!current_user_can('bc_use_pos')) wp_die('Not allowed.');
        $catalog=BC_RMS_POS_Service::catalog();
        wp_enqueue_script('bc-rms-pos',BC_RMS_URL.'assets/js/pos.js',[],BC_RMS_VERSION,true);
        wp_localize_script('bc-rms-pos','BCRMS_POS',['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bc_rms_pos'),'catalog'=>$catalog,'currency'=>'₱']);
        echo '<div class="wrap bc-wrap bc-pos"><h1>Byahero Chix POS <small>v0.9.0</small></h1><div class="bc-pos-layout"><section><div class="bc-pos-toolbar"><input id="bc-pos-search" type="search" placeholder="Search menu..."><select id="bc-pos-category"><option value="">All Categories</option>';
        $cats=[];foreach($catalog as $x)if(!empty($x['category_name']))$cats[$x['category_name']]=1;foreach(array_keys($cats) as $c)echo '<option>'.esc_html($c).'</option>';
        echo '</select></div><div id="bc-pos-products" class="bc-pos-products"></div></section><aside class="bc-pos-cart"><h2>Current Order</h2><div class="bc-pos-order-type"><button type="button" data-type="dine_in" class="active">Dine-in</button><button type="button" data-type="takeout">Takeout</button></div><div id="bc-pos-cart-items"></div><div class="bc-pos-totals"><p><span>Subtotal</span><strong id="bc-pos-subtotal">₱0.00</strong></p><p><span>Discount</span><input id="bc-pos-discount" type="number" min="0" step="0.01" value="0"></p><p class="total"><span>Total</span><strong id="bc-pos-total">₱0.00</strong></p></div><label>Payment<select id="bc-pos-payment"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="card">Card</option><option value="other">Other</option></select></label><label>Amount Tendered<input id="bc-pos-tendered" type="number" min="0" step="0.01"></label><div class="bc-pos-actions"><button id="bc-pos-hold" class="button button-large" type="button">Hold Order</button><button id="bc-pos-held" class="button button-large" type="button">Held Orders</button></div><button id="bc-pos-checkout" class="button button-primary button-hero">Complete Order</button><div id="bc-pos-message"></div></aside></div><div id="bc-pos-modal" class="bc-pos-modal" hidden><div class="bc-pos-modal-card"><button id="bc-pos-modal-close" type="button">×</button><div id="bc-pos-modal-body"></div></div></div></div>';
    }

    public static function ajax_pos_catalog()
    {
        check_ajax_referer('bc_rms_pos','nonce');
        if(!current_user_can('bc_use_pos')) wp_send_json_error(['message'=>'Not allowed.'],403);
        wp_send_json_success(BC_RMS_POS_Service::catalog());
    }

    public static function ajax_checkout()
    {
        if(!current_user_can('bc_use_pos')) wp_send_json_error(['message'=>'Not allowed.'],403);
        check_ajax_referer('bc_rms_pos','nonce');
        $payload=json_decode(wp_unslash($_POST['order']??''),true);
        if(!is_array($payload)) wp_send_json_error(['message'=>'Invalid order data.'],400);
        $result=BC_RMS_POS_Service::create_order($payload);
        if(is_wp_error($result)) wp_send_json_error(['message'=>$result->get_error_message()],400);
        wp_send_json_success($result);
    }

    public static function ajax_hold_order()
    {
        if(!current_user_can('bc_use_pos')) wp_send_json_error(['message'=>'Not allowed.'],403);
        check_ajax_referer('bc_rms_pos','nonce');$p=json_decode(wp_unslash($_POST['order']??''),true);
        $r=BC_RMS_POS_Service::hold_order(is_array($p)?$p:[]);if(is_wp_error($r))wp_send_json_error(['message'=>$r->get_error_message()],400);wp_send_json_success($r);
    }
    public static function ajax_held_orders()
    {
        if(!current_user_can('bc_use_pos')) wp_send_json_error(['message'=>'Not allowed.'],403);
        check_ajax_referer('bc_rms_pos','nonce');wp_send_json_success(BC_RMS_POS_Service::held_orders());
    }
    public static function ajax_resume_order()
    {
        if(!current_user_can('bc_use_pos')) wp_send_json_error(['message'=>'Not allowed.'],403);
        check_ajax_referer('bc_rms_pos','nonce');$id=absint($_POST['id']??0);$p=BC_RMS_POS_Service::held_order_payload($id);
        if(!$p)wp_send_json_error(['message'=>'Held order not found.'],404);BC_RMS_POS_Service::delete_held($id);wp_send_json_success($p);
    }

    public static function orders()
    {
        if(!current_user_can('bc_view_orders')) wp_die('Not allowed.');
        global $wpdb;$ot=BC_RMS_DB::table('orders');$oit=BC_RMS_DB::table('order_items');
        $view=absint($_GET['view']??0);
        echo '<div class="wrap bc-wrap"><h1>Orders</h1>';
        if($view){
            $o=$wpdb->get_row($wpdb->prepare("SELECT * FROM $ot WHERE id=%d",$view));if(!$o){echo '<p>Order not found.</p></div>';return;}
            echo '<div class="bc-card bc-receipt"><h2>'.esc_html($o->order_number).'</h2><p><strong>'.esc_html(strtoupper($o->status)).'</strong> · '.esc_html(ucwords(str_replace('_',' ',$o->order_type))).' · '.esc_html($o->created_at).' · '.esc_html(strtoupper($o->payment_method)).'</p><button class="button bc-print-receipt" onclick="window.print()">Print Receipt</button><table class="widefat striped"><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>';
            $omt=BC_RMS_DB::table('order_item_modifiers');foreach($wpdb->get_results($wpdb->prepare("SELECT * FROM $oit WHERE order_id=%d ORDER BY id",$view)) as $i){$mods=$wpdb->get_col($wpdb->prepare("SELECT modifier_name FROM $omt WHERE order_item_id=%d ORDER BY id",$i->id));echo '<tr><td>'.esc_html($i->product_name.($i->variant_name?' — '.$i->variant_name:'')).($mods?'<small style="display:block">'.esc_html(implode(', ',$mods)).'</small>':'').($i->notes?'<small style="display:block">Note: '.esc_html($i->notes).'</small>':'').'</td><td>'.esc_html($i->quantity).'</td><td>₱'.number_format($i->unit_price,2).'</td><td>₱'.number_format($i->line_total,2).'</td></tr>';}
            echo '</table><div class="bc-receipt-totals"><p>Subtotal: ₱'.number_format($o->subtotal,2).'</p><p>Discount: ₱'.number_format($o->discount_total,2).'</p><p><strong>Total: ₱'.number_format($o->total,2).'</strong></p><p>Tendered: ₱'.number_format($o->amount_tendered,2).' · Change: ₱'.number_format($o->change_due,2).'</p></div>';if($o->status==='completed'){echo '<hr><form method="post" class="bc-no-print">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="void_order"><input type="hidden" name="id" value="'.absint($o->id).'"><label><strong>Void reason</strong><br><input type="text" name="reason" required style="width:320px"></label> ';submit_button('Void Order','secondary','submit',false);echo '</form>';}echo '</div><p class="bc-no-print"><a href="?page=bc-rms-orders">← Back to Orders</a></p>';
        }else{
            echo '<div class="bc-card bc-wide"><table class="widefat striped"><tr><th>Order</th><th>Date</th><th>Status</th><th>Type</th><th>Payment</th><th>Total</th><th>Cashier</th><th></th></tr>';
            foreach($wpdb->get_results("SELECT * FROM $ot ORDER BY id DESC LIMIT 200") as $o){$u=get_userdata($o->cashier_user_id);echo '<tr><td><strong>'.esc_html($o->order_number).'</strong></td><td>'.esc_html($o->created_at).'</td><td>'.esc_html(strtoupper($o->status)).'</td><td>'.esc_html(ucwords(str_replace('_',' ',$o->order_type))).'</td><td>'.esc_html(strtoupper($o->payment_method)).'</td><td>₱'.number_format($o->total,2).'</td><td>'.esc_html($u?$u->display_name:'—').'</td><td><a href="?page=bc-rms-orders&view='.$o->id.'">View</a></td></tr>';}echo '</table></div>';
        }echo '</div>';
    }

    public static function packaging()
    {
        self::saved();global $wpdb;$t=BC_RMS_DB::table('packaging');$id=absint($_GET['edit']??0);$r=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id)):null;
        echo '<div class="wrap bc-wrap"><h1>Packaging</h1><div class="bc-grid"><div>';self::form('packaging',$id);self::f('Name','name',$r->name??'');self::f('Unit Cost','unit_cost',$r->unit_cost??0,'number','0.0001');echo '<p><label><b>Notes</b><br><textarea class="large-text" rows="4" name="notes">'.esc_textarea($r->notes??'').'</textarea></label></p>';self::c('Active','active',!$r||$r->active);self::end();
        echo '</div><div class="bc-card"><table class="widefat striped"><tr><th>Packaging</th><th>Unit Cost</th><th>Status</th><th></th></tr>';foreach($wpdb->get_results("SELECT * FROM $t ORDER BY active DESC,name") as $x)echo '<tr><td>'.esc_html($x->name).'</td><td>₱'.number_format($x->unit_cost,4).'</td><td>'.($x->active?'Active':'Inactive').'</td><td><a href="?page=bc-rms-packaging&edit='.$x->id.'">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Delete this packaging item?\')" href="'.esc_url(self::delete_url('bc-rms-packaging','packaging',$x->id)).'">Delete</a></td></tr>';echo '</table></div></div></div>';
    }

    public static function variants()
    {
        self::saved();global $wpdb;$t=BC_RMS_DB::table('product_variants');$pt=BC_RMS_DB::table('products');$id=absint($_GET['edit']??0);$r=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id)):null;$products=$wpdb->get_results("SELECT id,name FROM $pt WHERE active=1 ORDER BY name");
        echo '<div class="wrap bc-wrap"><h1>Product Variants</h1><div class="bc-grid"><div>';self::form('variant',$id);self::sel_optional('Product','product_id',$products,$r->product_id??0);self::f('Variant Name','name',$r->name??'');self::f('Variant SKU','sku',$r->sku??'');self::f('Price Adjustment','price_adjustment',$r->price_adjustment??0,'number','0.01');self::f('Cost Adjustment','cost_adjustment',$r->cost_adjustment??0,'number','0.0001');self::f('Sort Order','sort_order',$r->sort_order??0,'number','1');self::c('Default Variant','default_variant',$r&&$r->default_variant);self::c('Active','active',!$r||$r->active);self::end();
        echo '</div><div class="bc-card bc-wide"><table class="widefat striped"><tr><th>Product</th><th>Variant</th><th>Price +/-</th><th>Cost +/-</th><th>Default</th><th></th></tr>';foreach($wpdb->get_results("SELECT v.*,p.name product FROM $t v JOIN $pt p ON p.id=v.product_id ORDER BY p.name,v.sort_order,v.name") as $x)echo '<tr><td>'.esc_html($x->product).'</td><td>'.esc_html($x->name).'</td><td>₱'.number_format($x->price_adjustment,2).'</td><td>₱'.number_format($x->cost_adjustment,4).'</td><td>'.($x->default_variant?'Yes':'').'</td><td><a href="?page=bc-rms-variants&edit='.$x->id.'">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Delete this variant?\')" href="'.esc_url(self::delete_url('bc-rms-variants','variant',$x->id)).'">Delete</a></td></tr>';echo '</table></div></div></div>';
    }

    public static function modifiers()
    {
        self::saved();global $wpdb;$gt=BC_RMS_DB::table('modifier_groups');$mt=BC_RMS_DB::table('modifiers');$gid=absint($_GET['edit_group']??0);$g=$gid?$wpdb->get_row($wpdb->prepare("SELECT * FROM $gt WHERE id=%d",$gid)):null;$mid=absint($_GET['edit_modifier']??0);$m=$mid?$wpdb->get_row($wpdb->prepare("SELECT * FROM $mt WHERE id=%d",$mid)):null;$groups=$wpdb->get_results("SELECT id,name FROM $gt WHERE active=1 ORDER BY sort_order,name");
        echo '<div class="wrap bc-wrap"><h1>Modifiers & Add-ons</h1><div class="bc-grid"><div>';self::form('modifier_group',$gid);self::f('Group Name','name',$g->name??'');echo '<p><label><b>Selection Type</b><br><select name="selection_type"><option value="multiple" '.selected($g->selection_type??'multiple','multiple',false).'>Multiple</option><option value="single" '.selected($g->selection_type??'','single',false).'>Single</option></select></label></p>';self::f('Minimum Select','min_select',$g->min_select??0,'number','1');self::f('Maximum Select (0 = no limit)','max_select',$g->max_select??0,'number','1');self::f('Sort Order','sort_order',$g->sort_order??0,'number','1');self::c('Required Group','required_group',$g&&$g->required_group);self::c('Active','active',!$g||$g->active);self::end();
        echo '<div class="bc-card">';self::form('modifier',$mid);self::sel_optional('Modifier Group','group_id',$groups,$m->group_id??0);self::f('Modifier Name','name',$m->name??'');self::f('Price Adjustment','price_adjustment',$m->price_adjustment??0,'number','0.01');self::f('Cost Adjustment','cost_adjustment',$m->cost_adjustment??0,'number','0.0001');self::f('Sort Order','sort_order',$m->sort_order??0,'number','1');self::c('Active','active',!$m||$m->active);self::end();echo '</div></div>';
        echo '<div class="bc-card bc-wide"><table class="widefat striped"><tr><th>Group</th><th>Type</th><th>Rules</th><th>Modifier</th><th>Price +/-</th><th>Cost +/-</th><th></th></tr>';$rows=$wpdb->get_results("SELECT g.id gid,g.name group_name,g.selection_type,g.min_select,g.max_select,m.id mid,m.name modifier,m.price_adjustment,m.cost_adjustment FROM $gt g LEFT JOIN $mt m ON m.group_id=g.id ORDER BY g.sort_order,g.name,m.sort_order,m.name");foreach($rows as $x)echo '<tr><td>'.esc_html($x->group_name).'</td><td>'.esc_html($x->selection_type).'</td><td>'.absint($x->min_select).'–'.($x->max_select?absint($x->max_select):'∞').'</td><td>'.esc_html($x->modifier?:'—').'</td><td>'.($x->mid?'₱'.number_format($x->price_adjustment,2):'—').'</td><td>'.($x->mid?'₱'.number_format($x->cost_adjustment,4):'—').'</td><td><a href="?page=bc-rms-modifiers&edit_group='.$x->gid.'">Edit Group</a>'.($x->mid?' | <a href="?page=bc-rms-modifiers&edit_modifier='.$x->mid.'">Edit Modifier</a>':'').'</td></tr>';echo '</table></div></div></div>';
    }

    public static function product_categories()
    {
        self::saved();
        global $wpdb;
        $t=BC_RMS_DB::table('product_categories');
        $id=absint($_GET['edit']??0);
        $r=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id)):null;

        echo '<div class="wrap bc-wrap"><h1>Menu Categories</h1><div class="bc-grid"><div>';
        self::form('product_category',$id);
        self::f('Name','name',$r->name??'');
        self::f('Sort Order','sort_order',$r->sort_order??0,'number','1');
        echo '<p><label><b>Description</b><br><textarea class="large-text" rows="4" name="description">'.esc_textarea($r->description??'').'</textarea></label></p>';
        self::c('Active','active',!$r||$r->active);
        self::end();

        echo '</div><div class="bc-card"><table class="widefat striped"><tr><th>Name</th><th>Sort</th><th>Status</th><th></th></tr>';
        foreach($wpdb->get_results("SELECT * FROM $t ORDER BY sort_order,name") as $x){
            echo '<tr><td>'.esc_html($x->name).'</td><td>'.absint($x->sort_order).'</td><td>'.($x->active?'Active':'Inactive').'</td><td><a href="?page=bc-rms-product-categories&edit='.$x->id.'">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Permanently delete this menu category?\')" href="'.esc_url(self::delete_url('bc-rms-product-categories','product_category',$x->id)).'">Delete</a></td></tr>';
        }
        echo '</table></div></div></div>';
    }

    /**
     * Render sellable menu products and recipe-to-product costing.
     */
    public static function products()
    {
        self::saved();
        global $wpdb;
        $t=BC_RMS_DB::table('products');
        $ct=BC_RMS_DB::table('product_categories');
        $rt=BC_RMS_DB::table('recipes');
        $id=absint($_GET['edit']??0);
        $r=$id?$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id)):null;
        $cats=$wpdb->get_results("SELECT id,name FROM $ct WHERE active=1 ORDER BY sort_order,name");
        $recipes=$wpdb->get_results("SELECT id,name FROM $rt WHERE active=1 ORDER BY name");

        echo '<div class="wrap bc-wrap"><h1>Products / Menu</h1><div class="bc-grid"><div>';
        self::form('product',$id);
        self::f('Product Name','name',$r->name??'');
        self::f('SKU','sku',$r->sku??'');
        self::sel_optional('Menu Category','category_id',$cats,$r->category_id??0);
        self::sel_optional('Recipe / Cost Basis','recipe_id',$recipes,$r->recipe_id??0);
        self::f('Selling Price','selling_price',$r->selling_price??0,'number','0.01');
        self::f('Sort Order','sort_order',$r->sort_order??0,'number','1');
        echo '<p><label><b>Description</b><br><textarea class="large-text" rows="4" name="description">'.esc_textarea($r->description??'').'</textarea></label></p>';
        self::c('Available in POS','pos_enabled',!$r||$r->pos_enabled);
        self::c('Active','active',!$r||$r->active);
        self::end();

        if($id){
            $summary=BC_RMS_Product_Service::summary($id);
            echo '<div class="bc-card"><h2>Product Costing</h2><div class="bc-cost-grid">';
            echo '<div><span>Recipe Cost / Serving</span><strong>'.(null===$summary['recipe_cost']?'—':'₱'.number_format($summary['recipe_cost'],2)).'</strong></div>';
            echo '<div><span>Packaging Cost</span><strong>₱'.number_format($summary['packaging_cost'],2).'</strong></div>';
            echo '<div><span>Total Base Cost</span><strong>'.(null===$summary['base_cost']?'—':'₱'.number_format($summary['base_cost'],2)).'</strong></div>';
            echo '<div><span>Selling Price</span><strong>₱'.number_format($summary['selling_price'],2).'</strong></div>';
            echo '<div><span>Food Cost %</span><strong>'.(null===$summary['food_cost_percent']?'—':number_format($summary['food_cost_percent'],1).'%').'</strong></div>';
            echo '<div><span>Gross Margin</span><strong>'.(null===$summary['gross_margin_percent']?'—':number_format($summary['gross_margin_percent'],1).'%').'</strong></div>';
            echo '<div><span>Gross Profit</span><strong>'.(null===$summary['gross_profit']?'—':'₱'.number_format($summary['gross_profit'],2)).'</strong></div>';
            echo '<div><span>Suggested Price @ '.esc_html(BC_RMS_DB::setting('target_food_cost','35')).'% Food Cost</span><strong>'.(null===$summary['suggested_price']?'—':'₱'.number_format($summary['suggested_price'],2)).'</strong></div>';
            echo '</div>';
            $packt=BC_RMS_DB::table('packaging');$ppt=BC_RMS_DB::table('product_packaging');$packs=$wpdb->get_results("SELECT id,name,unit_cost FROM $packt WHERE active=1 ORDER BY name");
            echo '<hr><h3>Packaging Assignment</h3><form method="post">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="product_packaging"><input type="hidden" name="product_id" value="'.absint($id).'"><select name="packaging_id" required><option value="">— Select Packaging —</option>';foreach($packs as $pk)echo '<option value="'.absint($pk->id).'">'.esc_html($pk->name).' — ₱'.number_format($pk->unit_cost,2).'</option>';echo '</select> <input type="number" step="0.0001" min="0.0001" name="quantity" value="1" style="width:90px"> ';submit_button('Add / Update','secondary','submit',false);echo '</form>';
            $assigned=$wpdb->get_results($wpdb->prepare("SELECT pp.*,p.name,p.unit_cost FROM $ppt pp JOIN $packt p ON p.id=pp.packaging_id WHERE pp.product_id=%d ORDER BY p.name",$id));if($assigned){echo '<table class="widefat striped"><tr><th>Packaging</th><th>Qty</th><th>Subtotal</th><th></th></tr>';foreach($assigned as $pk){echo '<tr><td>'.esc_html($pk->name).'</td><td>'.number_format($pk->quantity,4).'</td><td>₱'.number_format($pk->quantity*$pk->unit_cost,2).'</td><td><form method="post" style="margin:0">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="remove_product_packaging"><input type="hidden" name="product_id" value="'.absint($id).'"><input type="hidden" name="id" value="'.absint($pk->id).'">';submit_button('Remove','small','submit',false);echo '</form></td></tr>';}echo '</table>';}
            $mgt=BC_RMS_DB::table('modifier_groups');$pmgt=BC_RMS_DB::table('product_modifier_groups');$groups=$wpdb->get_results("SELECT id,name FROM $mgt WHERE active=1 ORDER BY sort_order,name");echo '<hr><h3>Modifier Group Assignment</h3><form method="post">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="assign_modifier_group"><input type="hidden" name="product_id" value="'.absint($id).'"><select name="group_id" required><option value="">— Select Group —</option>';foreach($groups as $g)echo '<option value="'.absint($g->id).'">'.esc_html($g->name).'</option>';echo '</select> ';submit_button('Assign','secondary','submit',false);echo '</form>';
            $assigned_groups=$wpdb->get_results($wpdb->prepare("SELECT pmg.id,g.name,g.selection_type,g.min_select,g.max_select FROM $pmgt pmg JOIN $mgt g ON g.id=pmg.group_id WHERE pmg.product_id=%d ORDER BY g.sort_order,g.name",$id));if($assigned_groups){echo '<table class="widefat striped"><tr><th>Assigned Group</th><th>Type</th><th>Rules</th><th></th></tr>';foreach($assigned_groups as $ag){echo '<tr><td>'.esc_html($ag->name).'</td><td>'.esc_html(ucfirst($ag->selection_type)).'</td><td>'.absint($ag->min_select).'–'.($ag->max_select?absint($ag->max_select):'∞').'</td><td><form method="post" style="margin:0">';wp_nonce_field('bc_rms_save');echo '<input type="hidden" name="bc_rms_action" value="remove_modifier_group"><input type="hidden" name="product_id" value="'.absint($id).'"><input type="hidden" name="id" value="'.absint($ag->id).'">';submit_button('Remove','small','submit',false);echo '</form></td></tr>';}echo '</table>';}
            if($summary['missing_costs']) echo '<p class="bc-warning">The linked recipe has '.absint($summary['missing_costs']).' ingredient(s) with missing supplier cost. Product costing is incomplete.</p>';
            if(!$r->recipe_id) echo '<p class="bc-warning">No recipe is linked. Cost and margin calculations are unavailable until a recipe is selected.</p>';
            echo '</div>';
        }

        echo '</div><div class="bc-card bc-wide"><table class="widefat striped"><tr><th>Product</th><th>Category</th><th>Recipe</th><th>Price</th><th>Cost</th><th>Food Cost</th><th>Margin</th><th>POS</th><th></th></tr>';
        $rows=$wpdb->get_results("SELECT p.*,c.name category,r.name recipe FROM $t p LEFT JOIN $ct c ON c.id=p.category_id LEFT JOIN $rt r ON r.id=p.recipe_id ORDER BY c.sort_order,p.sort_order,p.name");
        foreach($rows as $x){
            $m=BC_RMS_Product_Service::summary($x->id);
            echo '<tr><td><strong>'.esc_html($x->name).'</strong>'.($x->sku?'<br><small>'.esc_html($x->sku).'</small>':'').'</td>';
            echo '<td>'.esc_html($x->category?:'—').'</td><td>'.esc_html($x->recipe?:'—').'</td><td>₱'.number_format($x->selling_price,2).'</td>';
            echo '<td>'.(null===$m['recipe_cost']?'—':'₱'.number_format($m['recipe_cost'],2)).'</td>';
            echo '<td>'.(null===$m['food_cost_percent']?'—':number_format($m['food_cost_percent'],1).'%').'</td>';
            echo '<td>'.(null===$m['gross_margin_percent']?'—':number_format($m['gross_margin_percent'],1).'%').'</td>';
            echo '<td>'.($x->pos_enabled?'Yes':'No').'</td><td><a href="?page=bc-rms-products&edit='.$x->id.'">Edit</a> | <a class="bc-delete" onclick="return confirm(\'Permanently delete this product?\')" href="'.esc_url(self::delete_url('bc-rms-products','product',$x->id)).'">Delete</a></td></tr>';
        }
        echo '</table></div></div></div>';
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