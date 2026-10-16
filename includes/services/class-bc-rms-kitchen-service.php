<?php
/** Payment-gated, optimistic-concurrency protected fulfillment transitions. */
if(!defined('ABSPATH')) exit;
class BC_RMS_Kitchen_Service {
    public static function advance($id,$next){
        global $wpdb;
        $t=BC_RMS_DB::table('orders');$e=BC_RMS_DB::table('kitchen_events');
        $id=absint($id);$next=sanitize_key($next);
        if(!$id)return new WP_Error('bad_order','Invalid order.');
        $o=$wpdb->get_row($wpdb->prepare("SELECT status,payment_status,fulfillment_status,order_type FROM $t WHERE id=%d",$id));
        if(!$o || $o->payment_status!=='paid' || $o->status!=='completed')return new WP_Error('unpaid','Only paid, non-voided orders may advance in Kitchen.');
        $transitions=['queued'=>'preparing','preparing'=>'ready','ready'=>($o->order_type==='takeout'?'picked_up':'served')];
        $from=$o->fulfillment_status;
        if(!isset($transitions[$from]) || $transitions[$from]!==$next)return new WP_Error('invalid_transition','This kitchen transition is not allowed. Refresh the board.');
        $now=current_time('mysql');
        $wpdb->query('START TRANSACTION');
        $updated=$wpdb->query($wpdb->prepare("UPDATE $t SET fulfillment_status=%s,updated_at=%s WHERE id=%d AND status='completed' AND payment_status='paid' AND fulfillment_status=%s",$next,$now,$id,$from));
        if($updated!==1){$wpdb->query('ROLLBACK');return new WP_Error('stale_order','Order was updated elsewhere. Refresh the board.');}
        if(!$wpdb->insert($e,['order_id'=>$id,'from_status'=>$from,'to_status'=>$next,'user_id'=>get_current_user_id(),'created_at'=>$now],['%d','%s','%s','%d','%s'])){$wpdb->query('ROLLBACK');return new WP_Error('audit_failure','Could not record status audit event.');}
        $wpdb->query('COMMIT');return true;
    }
    public static function board(){
        global $wpdb;$t=BC_RMS_DB::table('orders');$it=BC_RMS_DB::table('order_items');$mod=BC_RMS_DB::table('order_item_modifiers');
        $orders=$wpdb->get_results("SELECT id,order_number,order_type,fulfillment_status,created_at FROM $t WHERE status='completed' AND payment_status='paid' AND fulfillment_status IN ('queued','preparing','ready') ORDER BY created_at ASC LIMIT 120",ARRAY_A);
        foreach($orders as &$o){
            $o['items']=$wpdb->get_results($wpdb->prepare("SELECT id,quantity,product_name,variant_name,notes FROM $it WHERE order_id=%d ORDER BY id",$o['id']),ARRAY_A);
            foreach($o['items'] as &$item)$item['modifiers']=$wpdb->get_col($wpdb->prepare("SELECT modifier_name FROM $mod WHERE order_item_id=%d ORDER BY id",$item['id']));
            unset($item);
        }
        unset($o);return $orders;
    }
}
