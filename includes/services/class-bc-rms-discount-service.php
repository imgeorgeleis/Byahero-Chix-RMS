<?php
/** Server-authoritative basic promotional discount calculator. Statutory discounts intentionally excluded. */
if(!defined('ABSPATH'))exit;
class BC_RMS_Discount_Service {
 public static function quote($subtotal,$code){
  global $wpdb;$subtotal=max(0,round((float)$subtotal,2));$code=strtoupper(trim(sanitize_text_field($code)));
  if(!$code)return ['code'=>'','type'=>'none','discount'=>0,'total'=>$subtotal];
  $t=BC_RMS_DB::table('discount_rules');$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE code=%s AND active=1",$code));
  if(!$r)return new WP_Error('invalid_promo','Voucher or promo code is invalid.');
  $today=current_time('Y-m-d');
  if(($r->start_date && $today<$r->start_date)||($r->end_date && $today>$r->end_date))return new WP_Error('expired_promo','Voucher or promo code is outside its validity period.');
  if($r->usage_limit && $r->usage_count >= $r->usage_limit)return new WP_Error('used_promo','Voucher or promo code has reached its usage limit.');
  if($subtotal<(float)$r->min_subtotal)return new WP_Error('promo_minimum','Minimum order amount is not met.');
  if($r->discount_type==='percent')$discount=round($subtotal*min(100,max(0,(float)$r->discount_value))/100,2);
  elseif($r->discount_type==='fixed')$discount=max(0,(float)$r->discount_value);
  else return new WP_Error('promo_type','Invalid promotion configuration.');
  if((float)$r->max_discount>0)$discount=min($discount,(float)$r->max_discount);
  $discount=min($subtotal,$discount);
  return ['id'=>(int)$r->id,'code'=>$r->code,'type'=>$r->discount_type,'discount'=>$discount,'total'=>round($subtotal-$discount,2)];
 }

 /** Statutory discount quote based on cashier-attested eligible portion. No stacking. */
 public static function statutory_quote($subtotal,$payload){
    $subtotal=round(max(0,(float)$subtotal),2);
    $type=sanitize_key($payload['statutory_type']??'none');
    if(!in_array($type,['none','senior','pwd'],true))return new WP_Error('discount_type','Invalid statutory discount type.');
    if($type==='none')return ['type'=>'none','customer_name'=>'','id_reference'=>'','eligible_amount'=>0,'vat_relief'=>0,'discount'=>0,'total'=>$subtotal];
    $name=sanitize_text_field($payload['statutory_customer_name']??'');$id=sanitize_text_field($payload['statutory_id_reference']??'');
    if(!$name||!$id)return new WP_Error('statutory_identity','Enter customer name and ID reference for statutory discount.');
    $eligible=round((float)($payload['statutory_eligible_amount']??0),2);
    if($eligible<=0||$eligible>$subtotal)return new WP_Error('statutory_eligible','Eligible customer portion must be positive and cannot exceed order subtotal.');
    $vat_registered=BC_RMS_DB::setting('vat_registered','no')==='yes';
    $vat_relief=$vat_registered?round($eligible*12/112,2):0;
    $discount=round(($eligible-$vat_relief)*.20,2);
    return ['type'=>$type,'customer_name'=>$name,'id_reference'=>$id,'eligible_amount'=>$eligible,'vat_relief'=>$vat_relief,'discount'=>$discount,'total'=>round($subtotal-$vat_relief-$discount,2),'vat_registered'=>$vat_registered];
 }
}
