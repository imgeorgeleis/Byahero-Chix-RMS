<?php
if (!defined('ABSPATH')) exit;

class BC_RMS_DB
{
    public static function table($n)
    {
        global $wpdb;
        return $wpdb->prefix . 'bc_' . $n;
    }
    public static function now()
    {
        return current_time('mysql', true);
    }
    public static function uuid()
    {
        return wp_generate_uuid4();
    }
    public static function preferred_cost($ingredient_id)
    {
        global $wpdb;
        $si = self::table('supplier_items');
        $u = self::table('units');
        $r = $wpdb->get_row($wpdb->prepare("SELECT s.*,u.factor_to_base FROM $si s JOIN $u u ON u.id=s.purchase_unit_id WHERE s.ingredient_id=%d AND s.active=1 ORDER BY s.preferred DESC,s.id DESC LIMIT 1", $ingredient_id));
        if (!$r || $r->purchase_qty <= 0 || $r->factor_to_base <= 0)
            return null;
        return (float) $r->purchase_price / ((float) $r->purchase_qty * (float) $r->factor_to_base);
    }
}
