<?php
/**
 * POS catalog and order transaction service.
 *
 * Transaction rows snapshot names, prices, and costs so historical orders do
 * not change when menu configuration is edited later.
 *
 * @package ByaheroChixRMS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BC_RMS_POS_Service {
    public static function catalog() {
        global $wpdb;
        $pt=BC_RMS_DB::table('products');$ct=BC_RMS_DB::table('product_categories');
        $vt=BC_RMS_DB::table('product_variants');$pgt=BC_RMS_DB::table('product_modifier_groups');
        $gt=BC_RMS_DB::table('modifier_groups');$mt=BC_RMS_DB::table('modifiers');

        $products=$wpdb->get_results("SELECT p.*,c.name category_name FROM $pt p LEFT JOIN $ct c ON c.id=p.category_id WHERE p.active=1 AND p.pos_enabled=1 ORDER BY c.sort_order,p.sort_order,p.name",ARRAY_A);
        foreach($products as &$p){
            $p['base_cost']=(float)(BC_RMS_Product_Service::summary((int)$p['id'])['base_cost']??0);
            $p['variants']=$wpdb->get_results($wpdb->prepare("SELECT id,name,sku,price_adjustment,cost_adjustment,default_variant FROM $vt WHERE product_id=%d AND active=1 ORDER BY sort_order,name",$p['id']),ARRAY_A);
            $groups=$wpdb->get_results($wpdb->prepare("SELECT g.* FROM $pgt pg JOIN $gt g ON g.id=pg.group_id WHERE pg.product_id=%d AND g.active=1 ORDER BY g.sort_order,g.name",$p['id']),ARRAY_A);
            foreach($groups as &$g){
                $g['modifiers']=$wpdb->get_results($wpdb->prepare("SELECT id,name,price_adjustment,cost_adjustment FROM $mt WHERE group_id=%d AND active=1 ORDER BY sort_order,name",$g['id']),ARRAY_A);
            }
            $p['modifier_groups']=$groups;
        }
        return $products;
    }

    public static function create_order($payload) {
        global $wpdb;
        $items=$payload['items']??[];
        if(!is_array($items)||!$items) return new WP_Error('empty_cart','Cart is empty.');

        $pt=BC_RMS_DB::table('products');$vt=BC_RMS_DB::table('product_variants');
        $mt=BC_RMS_DB::table('modifiers');$pgt=BC_RMS_DB::table('product_modifier_groups');
        $gt=BC_RMS_DB::table('modifier_groups');$ot=BC_RMS_DB::table('orders');
        $oit=BC_RMS_DB::table('order_items');$omt=BC_RMS_DB::table('order_item_modifiers');
        $now=current_time('mysql');$subtotal=0;$normalized=[];

        foreach($items as $raw){
            $pid=absint($raw['product_id']??0);$qty=max(1,absint($raw['quantity']??1));
            $product=$wpdb->get_row($wpdb->prepare("SELECT * FROM $pt WHERE id=%d AND active=1 AND pos_enabled=1",$pid));
            if(!$product) return new WP_Error('invalid_product','A cart product is unavailable.');

            $price=(float)$product->selling_price;$cost=(float)(BC_RMS_Product_Service::summary($pid)['base_cost']??0);
            $variant=null;$vid=absint($raw['variant_id']??0);
            if($vid){
                $variant=$wpdb->get_row($wpdb->prepare("SELECT * FROM $vt WHERE id=%d AND product_id=%d AND active=1",$vid,$pid));
                if(!$variant) return new WP_Error('invalid_variant','A selected variant is unavailable.');
                $price+=(float)$variant->price_adjustment;$cost+=(float)$variant->cost_adjustment;
            }

            $mods=[];$selected=array_values(array_unique(array_map('absint',(array)($raw['modifier_ids']??[]))));
            $allowed_groups=$wpdb->get_results($wpdb->prepare("SELECT g.* FROM $pgt pg JOIN $gt g ON g.id=pg.group_id WHERE pg.product_id=%d AND g.active=1",$pid));
            foreach($allowed_groups as $group){
                $available=$wpdb->get_col($wpdb->prepare("SELECT id FROM $mt WHERE group_id=%d AND active=1",$group->id));
                $chosen=array_values(array_intersect($selected,array_map('intval',$available)));
                $count=count($chosen);$min=max((int)$group->min_select,$group->required_group?1:0);$max=(int)$group->max_select;
                if($count<$min || ($max>0&&$count>$max) || ($group->selection_type==='single'&&$count>1))
                    return new WP_Error('modifier_rules','Modifier selection rules were not satisfied for '.$group->name.'.');
                foreach($chosen as $mid){
                    $m=$wpdb->get_row($wpdb->prepare("SELECT * FROM $mt WHERE id=%d",$mid));
                    $price+=(float)$m->price_adjustment;$cost+=(float)$m->cost_adjustment;$mods[]=$m;
                }
            }
            $line=$price*$qty;$subtotal+=$line;
            $normalized[]=['product'=>$product,'variant'=>$variant,'qty'=>$qty,'unit_price'=>$price,'unit_cost'=>$cost,'line_total'=>$line,'mods'=>$mods,'notes'=>sanitize_text_field($raw['notes']??'')];
        }

        $discount=max(0,(float)($payload['discount_total']??0));$discount=min($discount,$subtotal);
        $tax=0;$total=max(0,$subtotal-$discount+$tax);
        $method=in_array($payload['payment_method']??'cash',['cash','gcash','card','other'],true)?$payload['payment_method']:'cash';
        $tender=max(0,(float)($payload['amount_tendered']??0));
        if($method==='cash' && $tender<$total) return new WP_Error('insufficient_cash','Amount tendered is less than the order total.');
        if($method!=='cash') $tender=$total;
        $change=max(0,$tender-$total);

        $wpdb->query('START TRANSACTION');
        try{
            $order_number='BC-'.current_time('Ymd').'-'.str_pad((string)(1+(int)$wpdb->get_var("SELECT COUNT(*) FROM $ot WHERE DATE(created_at)=CURDATE()")),4,'0',STR_PAD_LEFT);
            $ok=$wpdb->insert($ot,['uuid'=>BC_RMS_DB::uuid(),'order_number'=>$order_number,'status'=>'completed','order_type'=>in_array($payload['order_type']??'dine_in',['dine_in','takeout'],true)?$payload['order_type']:'dine_in','subtotal'=>$subtotal,'discount_total'=>$discount,'tax_total'=>$tax,'total'=>$total,'payment_method'=>$method,'amount_tendered'=>$tender,'change_due'=>$change,'cashier_user_id'=>get_current_user_id(),'notes'=>sanitize_textarea_field($payload['notes']??''),'completed_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
            if(!$ok) throw new Exception($wpdb->last_error?:'Could not create order.');
            $oid=(int)$wpdb->insert_id;
            foreach($normalized as $x){
                $p=$x['product'];$v=$x['variant'];
                if(!$wpdb->insert($oit,['uuid'=>BC_RMS_DB::uuid(),'order_id'=>$oid,'product_id'=>$p->id,'variant_id'=>$v?$v->id:null,'product_name'=>$p->name,'variant_name'=>$v?$v->name:null,'sku'=>$v&&$v->sku?$v->sku:$p->sku,'quantity'=>$x['qty'],'unit_price'=>$x['unit_price'],'unit_cost'=>$x['unit_cost'],'line_total'=>$x['line_total'],'notes'=>$x['notes'],'created_at'=>$now])) throw new Exception($wpdb->last_error?:'Could not create order item.');
                $oi=(int)$wpdb->insert_id;
                foreach($x['mods'] as $m) if(!$wpdb->insert($omt,['uuid'=>BC_RMS_DB::uuid(),'order_item_id'=>$oi,'modifier_id'=>$m->id,'modifier_name'=>$m->name,'price_adjustment'=>$m->price_adjustment,'cost_adjustment'=>$m->cost_adjustment,'created_at'=>$now])) throw new Exception($wpdb->last_error?:'Could not save modifier.');
            }
            $wpdb->query('COMMIT');
            return ['id'=>$oid,'order_number'=>$order_number,'total'=>$total,'change'=>$change];
        }catch(Exception $e){
            $wpdb->query('ROLLBACK');
            return new WP_Error('order_failed',$e->getMessage());
        }
    }
}
