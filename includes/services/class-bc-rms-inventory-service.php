<?php
/**
 * Inventory ledger service.
 *
 * Stock-on-hand is derived from immutable movement rows. Positive deltas add
 * stock; negative deltas consume stock. Corrections are new movements rather
 * than edits to historical movements.
 *
 * @package ByaheroChixRMS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BC_RMS_Inventory_Service {
    public static function to_ingredient_base($ingredient_id,$quantity,$unit_id) {
        global $wpdb;
        $it=BC_RMS_DB::table('ingredients');$ut=BC_RMS_DB::table('units');
        $i=$wpdb->get_row($wpdb->prepare("SELECT i.base_unit_id,bu.unit_type base_type,bu.factor_to_base base_factor FROM $it i JOIN $ut bu ON bu.id=i.base_unit_id WHERE i.id=%d",$ingredient_id));
        $u=$wpdb->get_row($wpdb->prepare("SELECT unit_type,factor_to_base FROM $ut WHERE id=%d",$unit_id));
        if(!$i||!$u||$i->base_type!==$u->unit_type||(float)$i->base_factor==0) return null;
        return ((float)$quantity*(float)$u->factor_to_base)/(float)$i->base_factor;
    }

    public static function stock($ingredient_id) {
        global $wpdb;$mt=BC_RMS_DB::table('inventory_movements');
        return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity_delta),0) FROM $mt WHERE ingredient_id=%d",$ingredient_id));
    }

    public static function add_movement($ingredient_id,$type,$delta,$args=[]) {
        global $wpdb;$mt=BC_RMS_DB::table('inventory_movements');
        if(!$ingredient_id || abs((float)$delta)<0.0000001) return false;
        return (bool)$wpdb->insert($mt,[
            'uuid'=>BC_RMS_DB::uuid(),'ingredient_id'=>(int)$ingredient_id,
            'movement_type'=>sanitize_key($type),'quantity_delta'=>(float)$delta,
            'unit_cost'=>isset($args['unit_cost'])?(float)$args['unit_cost']:null,
            'reference_type'=>isset($args['reference_type'])?sanitize_key($args['reference_type']):null,
            'reference_id'=>isset($args['reference_id'])?(int)$args['reference_id']:null,
            'reference_code'=>isset($args['reference_code'])?sanitize_text_field($args['reference_code']):null,
            'notes'=>isset($args['notes'])?sanitize_text_field($args['notes']):null,
            'user_id'=>get_current_user_id(),'created_at'=>BC_RMS_DB::now()
        ]);
    }

    public static function packaging_stock($packaging_id) {
        global $wpdb;$mt=BC_RMS_DB::table('packaging_movements');
        return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity_delta),0) FROM $mt WHERE packaging_id=%d",$packaging_id));
    }

    public static function add_packaging_movement($packaging_id,$type,$delta,$args=[]) {
        global $wpdb;$mt=BC_RMS_DB::table('packaging_movements');
        if(!$packaging_id || abs((float)$delta)<0.0000001) return false;
        return (bool)$wpdb->insert($mt,['uuid'=>BC_RMS_DB::uuid(),'packaging_id'=>(int)$packaging_id,'movement_type'=>sanitize_key($type),'quantity_delta'=>(float)$delta,'unit_cost'=>isset($args['unit_cost'])?(float)$args['unit_cost']:null,'reference_type'=>isset($args['reference_type'])?sanitize_key($args['reference_type']):null,'reference_id'=>isset($args['reference_id'])?(int)$args['reference_id']:null,'reference_code'=>isset($args['reference_code'])?sanitize_text_field($args['reference_code']):null,'notes'=>isset($args['notes'])?sanitize_text_field($args['notes']):null,'user_id'=>get_current_user_id(),'created_at'=>BC_RMS_DB::now()]);
    }

    public static function product_packaging_requirements($product_id,$quantity=1) {
        global $wpdb;$ppt=BC_RMS_DB::table('product_packaging');$pt=BC_RMS_DB::table('packaging');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT pp.packaging_id,pp.quantity,p.name,p.track_inventory,p.unit_cost FROM $ppt pp JOIN $pt p ON p.id=pp.packaging_id WHERE pp.product_id=%d AND p.active=1",$product_id));
        $out=[];foreach($rows as $r){if(!$r->track_inventory)continue;$out[]=['packaging_id'=>(int)$r->packaging_id,'name'=>$r->name,'required'=>(float)$r->quantity*max(1,(float)$quantity),'unit_cost'=>(float)$r->unit_cost];}return $out;
    }

    public static function product_requirements($product_id,$quantity=1) {
        global $wpdb;
        $pt=BC_RMS_DB::table('products');$rt=BC_RMS_DB::table('recipes');
        $rit=BC_RMS_DB::table('recipe_items');$it=BC_RMS_DB::table('ingredients');$ut=BC_RMS_DB::table('units');
        $p=$wpdb->get_row($wpdb->prepare("SELECT recipe_id FROM $pt WHERE id=%d",$product_id));
        if(!$p||!$p->recipe_id) return [];
        $recipe=$wpdb->get_row($wpdb->prepare("SELECT servings FROM $rt WHERE id=%d",$p->recipe_id));
        if(!$recipe) return [];
        $servings=max(0.0001,(float)$recipe->servings);$req=[];
        $rows=$wpdb->get_results($wpdb->prepare("SELECT ri.*,i.name,i.track_inventory,u.symbol FROM $rit ri JOIN $it i ON i.id=ri.ingredient_id JOIN $ut u ON u.id=i.base_unit_id WHERE ri.recipe_id=%d",$p->recipe_id));
        foreach($rows as $ri){
            if(!$ri->track_inventory) continue;
            $base=self::to_ingredient_base((int)$ri->ingredient_id,(float)$ri->quantity,(int)$ri->unit_id);
            if(null===$base) continue;
            $need=($base/$servings)*max(1,(float)$quantity);
            if(!isset($req[$ri->ingredient_id])) $req[$ri->ingredient_id]=['ingredient_id'=>(int)$ri->ingredient_id,'name'=>$ri->name,'symbol'=>$ri->symbol,'required'=>0];
            $req[$ri->ingredient_id]['required']+=$need;
        }
        return array_values($req);
    }

    public static function product_availability($product_id) {
        $req=self::product_requirements($product_id,1);
        if(!$req) return ['available'=>true,'max_quantity'=>null,'shortages'=>[]];
        $max=null;$short=[];
        foreach($req as $r){
            $stock=self::stock($r['ingredient_id']);
            $possible=$r['required']>0?(int)floor(($stock+0.0000001)/$r['required']):PHP_INT_MAX;
            $max=$max===null?$possible:min($max,$possible);
            if($stock+0.0000001<$r['required']) $short[]=$r+['stock'=>$stock];
        }
        foreach(self::product_packaging_requirements($product_id,1) as $r){
            $stock=self::packaging_stock($r['packaging_id']);
            $possible=$r['required']>0?(int)floor(($stock+0.0000001)/$r['required']):PHP_INT_MAX;
            $max=$max===null?$possible:min($max,$possible);
            if($stock+0.0000001<$r['required'])$short[]=['name'=>$r['name'],'required'=>$r['required'],'stock'=>$stock,'symbol'=>'pc'];
        }
        return ['available'=>empty($short),'max_quantity'=>$max===null?null:max(0,(int)$max),'shortages'=>$short];
    }

    public static function validate_cart_stock($items) {
        $totals=[];$meta=[];
        foreach((array)$items as $raw){
            $pid=absint($raw['product_id']??0);$qty=max(1,(float)($raw['quantity']??1));
            foreach(self::product_requirements($pid,$qty) as $r){
                $iid=$r['ingredient_id'];
                if(!isset($totals[$iid])){$totals[$iid]=0;$meta[$iid]=$r;}
                $totals[$iid]+=$r['required'];
            }
        }
        $short=[];
        foreach($totals as $iid=>$required){
            $stock=self::stock($iid);
            if($stock+0.0000001<$required) $short[]=$meta[$iid]+['required'=>$required,'stock'=>$stock];
        }
        $ptot=[];$pmeta=[];
        foreach((array)$items as $raw){
            $pid=absint($raw['product_id']??0);$qty=max(1,(float)($raw['quantity']??1));
            foreach(self::product_packaging_requirements($pid,$qty) as $r){$id=$r['packaging_id'];if(!isset($ptot[$id])){$ptot[$id]=0;$pmeta[$id]=$r;}$ptot[$id]+=$r['required'];}
        }
        foreach($ptot as $id=>$required){$stock=self::packaging_stock($id);if($stock+0.0000001<$required)$short[]=['name'=>$pmeta[$id]['name'],'required'=>$required,'stock'=>$stock,'symbol'=>'pc'];}
        if($short){
            $parts=[];foreach($short as $r)$parts[]=sprintf('%s: need %s %s, available %s %s',$r['name'],number_format($r['required'],4),$r['symbol'],number_format($r['stock'],4),$r['symbol']);
            return new WP_Error('insufficient_stock','Insufficient inventory — '.implode('; ',$parts));
        }
        return true;
    }

    public static function consume_order($order_id) {
        global $wpdb;
        $mt=BC_RMS_DB::table('inventory_movements');
        if((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mt WHERE reference_type='order' AND reference_id=%d AND movement_type='sale'",$order_id))) return true;

        $ot=BC_RMS_DB::table('orders');$oit=BC_RMS_DB::table('order_items');$pt=BC_RMS_DB::table('products');
        $rt=BC_RMS_DB::table('recipes');$rit=BC_RMS_DB::table('recipe_items');$it=BC_RMS_DB::table('ingredients');
        $o=$wpdb->get_row($wpdb->prepare("SELECT order_number FROM $ot WHERE id=%d",$order_id));if(!$o)return false;
        $items=$wpdb->get_results($wpdb->prepare("SELECT oi.product_id,oi.quantity,p.recipe_id FROM $oit oi JOIN $pt p ON p.id=oi.product_id WHERE oi.order_id=%d",$order_id));
        foreach($items as $line){
            if(!$line->recipe_id) continue;
            $recipe=$wpdb->get_row($wpdb->prepare("SELECT servings FROM $rt WHERE id=%d",$line->recipe_id));if(!$recipe)continue;
            $servings=max(0.0001,(float)$recipe->servings);
            $ris=$wpdb->get_results($wpdb->prepare("SELECT ri.*,i.track_inventory FROM $rit ri JOIN $it i ON i.id=ri.ingredient_id WHERE ri.recipe_id=%d",$line->recipe_id));
            foreach($ris as $ri){
                if(!$ri->track_inventory) continue;
                $base=self::to_ingredient_base((int)$ri->ingredient_id,(float)$ri->quantity,(int)$ri->unit_id);
                if(null===$base) throw new Exception('Inventory unit conversion failed for ingredient ID '.(int)$ri->ingredient_id.'.');
                $used=($base/$servings)*(float)$line->quantity;
                if($used>0 && !self::add_movement($ri->ingredient_id,'sale',-$used,[
                    'reference_type'=>'order','reference_id'=>$order_id,'reference_code'=>$o->order_number,
                    'notes'=>'Automatic recipe consumption'
                ])) throw new Exception('Could not write inventory sale movement.');
            }
        }
        $items=$wpdb->get_results($wpdb->prepare("SELECT product_id,quantity FROM $oit WHERE order_id=%d",$order_id));
        foreach($items as $line)foreach(self::product_packaging_requirements($line->product_id,$line->quantity) as $r){
            if(!self::add_packaging_movement($r['packaging_id'],'sale',-$r['required'],['unit_cost'=>$r['unit_cost'],'reference_type'=>'order','reference_id'=>$order_id,'reference_code'=>$o->order_number,'notes'=>'Automatic product packaging consumption'])) throw new Exception('Could not write packaging sale movement.');
        }
        return true;
    }

    public static function reverse_order($order_id) {
        global $wpdb;$mt=BC_RMS_DB::table('inventory_movements');$ot=BC_RMS_DB::table('orders');
        if((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mt WHERE reference_type='order_void' AND reference_id=%d",$order_id))) return true;
        $o=$wpdb->get_row($wpdb->prepare("SELECT order_number FROM $ot WHERE id=%d",$order_id));if(!$o)return false;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT ingredient_id,quantity_delta,unit_cost FROM $mt WHERE reference_type='order' AND reference_id=%d AND movement_type='sale'",$order_id));
        foreach($rows as $r){
            if(!self::add_movement($r->ingredient_id,'void_reversal',abs((float)$r->quantity_delta),[
                'unit_cost'=>$r->unit_cost,'reference_type'=>'order_void','reference_id'=>$order_id,
                'reference_code'=>$o->order_number,'notes'=>'Automatic inventory reversal for voided order'
            ])) throw new Exception('Could not reverse inventory movement.');
        }
        $pmt=BC_RMS_DB::table('packaging_movements');
        if(!(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pmt WHERE reference_type='order_void' AND reference_id=%d",$order_id))){
            $prows=$wpdb->get_results($wpdb->prepare("SELECT packaging_id,quantity_delta,unit_cost FROM $pmt WHERE reference_type='order' AND reference_id=%d AND movement_type='sale'",$order_id));
            foreach($prows as $r)if(!self::add_packaging_movement($r->packaging_id,'void_reversal',abs((float)$r->quantity_delta),['unit_cost'=>$r->unit_cost,'reference_type'=>'order_void','reference_id'=>$order_id,'reference_code'=>$o->order_number,'notes'=>'Automatic packaging reversal for voided order']))throw new Exception('Could not reverse packaging movement.');
        }
        return true;
    }

    public static function receive($data) {
        global $wpdb;$rt=BC_RMS_DB::table('inventory_receipts');$rit=BC_RMS_DB::table('inventory_receipt_items');
        $ingredient_id=absint($data['ingredient_id']??0);$qty=(float)($data['quantity']??0);$unit_id=absint($data['unit_id']??0);$price=max(0,(float)($data['purchase_price']??0));
        $base=self::to_ingredient_base($ingredient_id,$qty,$unit_id);
        if(!$ingredient_id||$qty<=0||!$unit_id||null===$base||$base<=0) return new WP_Error('invalid_receipt','Ingredient, compatible unit, and positive quantity are required.');
        $unit_cost=$price/$base;$now=BC_RMS_DB::now();$date=sanitize_text_field($data['receipt_date']??current_time('Y-m-d'));
        $num='RCV-'.current_time('Ymd-His').'-'.wp_rand(100,999);
        $wpdb->query('START TRANSACTION');
        try{
            if(!$wpdb->insert($rt,['uuid'=>BC_RMS_DB::uuid(),'receipt_number'=>$num,'supplier_id'=>absint($data['supplier_id']??0)?:null,'reference_no'=>sanitize_text_field($data['reference_no']??''),'receipt_date'=>$date,'notes'=>sanitize_textarea_field($data['notes']??''),'user_id'=>get_current_user_id(),'created_at'=>$now])) throw new Exception($wpdb->last_error?:'Could not create receipt.');
            $rid=(int)$wpdb->insert_id;
            if(!$wpdb->insert($rit,['uuid'=>BC_RMS_DB::uuid(),'receipt_id'=>$rid,'ingredient_id'=>$ingredient_id,'quantity_base'=>$base,'unit_cost_base'=>$unit_cost,'purchase_quantity'=>$qty,'purchase_unit_id'=>$unit_id,'purchase_price'=>$price,'created_at'=>$now])) throw new Exception($wpdb->last_error?:'Could not create receipt item.');
            if(!self::add_movement($ingredient_id,'receipt',$base,['unit_cost'=>$unit_cost,'reference_type'=>'receipt','reference_id'=>$rid,'reference_code'=>$num,'notes'=>sanitize_text_field($data['reference_no']??'')])) throw new Exception('Could not write receipt movement.');
            $wpdb->query('COMMIT');return $rid;
        }catch(Exception $e){$wpdb->query('ROLLBACK');return new WP_Error('receipt_failed',$e->getMessage());}
    }

    public static function adjust_packaging($data) {
        $id=absint($data['packaging_id']??0);$qty=abs((float)($data['quantity']??0));$kind=sanitize_key($data['movement_type']??'receipt');
        if(!in_array($kind,['receipt','adjustment_in','adjustment_out','waste'],true))$kind='receipt';
        if(!$id||$qty<=0)return new WP_Error('invalid_packaging_adjustment','Packaging and positive quantity are required.');
        $delta=in_array($kind,['receipt','adjustment_in'],true)?$qty:-$qty;
        if(!self::add_packaging_movement($id,$kind,$delta,['reference_type'=>'manual','reference_code'=>'PKG-'.current_time('Ymd-His'),'notes'=>sanitize_text_field($data['notes']??'')]))return new WP_Error('packaging_adjustment_failed','Could not save packaging movement.');
        return true;
    }

    public static function adjust($data) {
        $iid=absint($data['ingredient_id']??0);$qty=abs((float)($data['quantity']??0));$uid=absint($data['unit_id']??0);
        $kind=sanitize_key($data['movement_type']??'adjustment_in');
        if(!in_array($kind,['adjustment_in','adjustment_out','waste'],true)) $kind='adjustment_in';
        $base=self::to_ingredient_base($iid,$qty,$uid);
        if(!$iid||$qty<=0||!$uid||null===$base) return new WP_Error('invalid_adjustment','Ingredient, compatible unit, and positive quantity are required.');
        $delta=$kind==='adjustment_in'?$base:-$base;
        if(!self::add_movement($iid,$kind,$delta,['reference_type'=>'manual','reference_code'=>'MAN-'.current_time('Ymd-His'),'notes'=>sanitize_text_field($data['notes']??'')])) return new WP_Error('adjustment_failed','Could not save inventory movement.');
        return true;
    }
}
