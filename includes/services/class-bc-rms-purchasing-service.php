<?php
/**
 * Purchasing / purchase-order service.
 * @package ByaheroChixRMS
 */
if(!defined('ABSPATH')) exit;
class BC_RMS_Purchasing_Service {
    public static function next_po_number(){
        global $wpdb;$t=BC_RMS_DB::table('purchase_orders');$prefix='PO-'.current_time('Ymd').'-';
        $last=$wpdb->get_var($wpdb->prepare("SELECT po_number FROM $t WHERE po_number LIKE %s ORDER BY po_number DESC LIMIT 1",$wpdb->esc_like($prefix).'%'));
        $n=1;if($last&&preg_match('/^'.preg_quote($prefix,'/').'([0-9]+)$/',$last,$m))$n=(int)$m[1]+1;
        do{$num=$prefix.str_pad((string)$n++,4,'0',STR_PAD_LEFT);$exists=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE po_number=%s",$num));}while($exists);
        return $num;
    }
    public static function create($data){
        global $wpdb;$pot=BC_RMS_DB::table('purchase_orders');$pit=BC_RMS_DB::table('purchase_order_items');
        $supplier=absint($data['supplier_id']??0);
        $ingredients=(array)($data['ingredient_id']??[]);$units=(array)($data['purchase_unit_id']??[]);
        $qtys=(array)($data['ordered_quantity']??[]);$prices=(array)($data['unit_price']??[]);
        $lines=[];$subtotal=0;
        foreach($ingredients as $k=>$ingredient){
            $ingredient=absint($ingredient);$unit=absint($units[$k]??0);$qty=(float)($qtys[$k]??0);$price=max(0,(float)($prices[$k]??0));
            if(!$ingredient&&!$unit&&!$qty)continue;
            if(!$ingredient||!$unit||$qty<=0)return new WP_Error('invalid_po','Every purchase-order line needs an ingredient, purchase unit, and positive quantity.');
            $base=BC_RMS_Inventory_Service::to_ingredient_base($ingredient,$qty,$unit);
            if(null===$base)return new WP_Error('invalid_unit','A purchase unit is not compatible with its ingredient base unit.');
            $total=$qty*$price;$subtotal+=$total;$lines[]=['ingredient_id'=>$ingredient,'purchase_unit_id'=>$unit,'ordered_quantity'=>$qty,'unit_price'=>$price,'line_total'=>$total];
        }
        if(!$supplier||!$lines)return new WP_Error('invalid_po','Supplier and at least one valid purchase-order line are required.');
        $now=BC_RMS_DB::now();$num=self::next_po_number();
        $wpdb->query('START TRANSACTION');
        try{
            if(!$wpdb->insert($pot,['uuid'=>BC_RMS_DB::uuid(),'po_number'=>$num,'supplier_id'=>$supplier,'status'=>'draft','order_date'=>sanitize_text_field($data['order_date']??current_time('Y-m-d')),'expected_date'=>sanitize_text_field($data['expected_date']??'')?:null,'supplier_reference'=>sanitize_text_field($data['supplier_reference']??''),'notes'=>sanitize_textarea_field($data['notes']??''),'subtotal'=>$subtotal,'user_id'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now]))throw new Exception($wpdb->last_error?:'Could not create purchase order.');
            $po=(int)$wpdb->insert_id;
            foreach($lines as $line)if(!$wpdb->insert($pit,['uuid'=>BC_RMS_DB::uuid(),'purchase_order_id'=>$po]+$line+['received_quantity'=>0,'created_at'=>$now,'updated_at'=>$now]))throw new Exception($wpdb->last_error?:'Could not create purchase-order line.');
            $wpdb->query('COMMIT');return $po;
        }catch(Exception $e){$wpdb->query('ROLLBACK');return new WP_Error('po_failed',$e->getMessage());}
    }

    public static function set_status($id,$status){
        global $wpdb;$t=BC_RMS_DB::table('purchase_orders');
        if(!in_array($status,['draft','ordered','cancelled'],true))return new WP_Error('invalid_status','Invalid purchase-order status.');
        $current=$wpdb->get_var($wpdb->prepare("SELECT status FROM $t WHERE id=%d",$id));
        if(!$current)return new WP_Error('missing_po','Purchase order not found.');
        if(in_array($current,['received','part_received'],true))return new WP_Error('locked_po','Received purchase orders cannot be changed to this status.');
        $wpdb->update($t,['status'=>$status,'updated_at'=>BC_RMS_DB::now()],['id'=>$id]);return true;
    }
    public static function receive($id,$data){
        global $wpdb;$pot=BC_RMS_DB::table('purchase_orders');$pit=BC_RMS_DB::table('purchase_order_items');
        $po=$wpdb->get_row($wpdb->prepare("SELECT * FROM $pot WHERE id=%d",$id));
        if(!$po||$po->status==='cancelled')return new WP_Error('invalid_po','Purchase order cannot be received.');
        $items=$wpdb->get_results($wpdb->prepare("SELECT * FROM $pit WHERE purchase_order_id=%d ORDER BY id",$id));
        if(!$items)return new WP_Error('missing_item','Purchase-order items not found.');
        $receive=(array)($data['receive_quantity']??[]);$received_any=false;
        foreach($items as $item){
            $qty=(float)($receive[$item->id]??0);if($qty<=0)continue;
            $remaining=max(0,(float)$item->ordered_quantity-(float)$item->received_quantity);
            if($qty>$remaining+0.0000001)return new WP_Error('invalid_qty','A receive quantity exceeds the remaining ordered quantity.');
            $price=$qty*(float)$item->unit_price;
            $result=BC_RMS_Inventory_Service::receive(['ingredient_id'=>$item->ingredient_id,'quantity'=>$qty,'unit_id'=>$item->purchase_unit_id,'purchase_price'=>$price,'supplier_id'=>$po->supplier_id,'reference_no'=>$po->po_number,'receipt_date'=>sanitize_text_field($data['receipt_date']??current_time('Y-m-d')),'notes'=>'Received against '.$po->po_number]);
            if(is_wp_error($result))return $result;
            $wpdb->update($pit,['received_quantity'=>(float)$item->received_quantity+$qty,'updated_at'=>BC_RMS_DB::now()],['id'=>$item->id]);$received_any=true;
        }
        if(!$received_any)return new WP_Error('nothing_received','Enter a receive quantity for at least one line.');
        $remaining=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pit WHERE purchase_order_id=%d AND received_quantity+0.0000001 < ordered_quantity",$id));
        $received_lines=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pit WHERE purchase_order_id=%d AND received_quantity>0",$id));
        $status=$remaining===0?'received':($received_lines>0?'part_received':'ordered');
        $wpdb->update($pot,['status'=>$status,'updated_at'=>BC_RMS_DB::now()],['id'=>$id]);return true;
    }

}
