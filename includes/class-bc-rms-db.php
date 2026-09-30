<?php
/** Database helpers. @package ByaheroChixRMS */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class BC_RMS_DB {
    public static function table( $name ) { global $wpdb; return $wpdb->prefix . 'bc_' . $name; }
    public static function now() { return current_time( 'mysql', true ); }
    public static function uuid() { return wp_generate_uuid4(); }
    public static function setting( $key, $default = '' ) {
        global $wpdb; $table=self::table('settings');
        $value=$wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key=%s",$key));
        return null === $value ? $default : $value;
    }
    public static function preferred_cost( $ingredient_id ) {
        global $wpdb; $si=self::table('supplier_items'); $u=self::table('units');
        $r=$wpdb->get_row($wpdb->prepare("SELECT s.*,u.factor_to_base FROM $si s JOIN $u u ON u.id=s.purchase_unit_id WHERE s.ingredient_id=%d AND s.active=1 ORDER BY s.preferred DESC,s.id DESC LIMIT 1",$ingredient_id));
        if(!$r || (float)$r->purchase_qty<=0 || (float)$r->factor_to_base<=0) return null;
        return (float)$r->purchase_price / ((float)$r->purchase_qty*(float)$r->factor_to_base);
    }
}
