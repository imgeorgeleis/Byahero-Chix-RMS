<?php
/**
 * Product/menu costing helpers.
 *
 * @package ByaheroChixRMS
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BC_RMS_Product_Service {
    public static function packaging_cost( $product_id ) {
        global $wpdb;
        $links=BC_RMS_DB::table('product_packaging');
        $pack=BC_RMS_DB::table('packaging');
        return (float)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(pp.quantity*p.unit_cost),0) FROM $links pp INNER JOIN $pack p ON p.id=pp.packaging_id WHERE pp.product_id=%d AND p.active=1",
            $product_id
        ));
    }

    public static function summary( $product_id ) {
        global $wpdb;
        $products=BC_RMS_DB::table('products');
        $product=$wpdb->get_row($wpdb->prepare("SELECT * FROM $products WHERE id=%d",$product_id));
        if(!$product) return null;

        $recipe_cost=null;$missing=0;
        if($product->recipe_id){
            $recipe=BC_RMS_Costing_Service::recipe((int)$product->recipe_id);
            if($recipe){$recipe_cost=(float)$recipe['cost_per_serving'];$missing=(int)$recipe['missing_costs'];}
        }
        $packaging_cost=self::packaging_cost($product_id);
        $base_cost=null!==$recipe_cost?$recipe_cost+$packaging_cost:null;
        $price=(float)$product->selling_price;
        $food_cost_percent=(null!==$base_cost&&$price>0)?($base_cost/$price)*100:null;
        $gross_profit=null!==$base_cost?$price-$base_cost:null;
        $gross_margin_percent=(null!==$gross_profit&&$price>0)?($gross_profit/$price)*100:null;
        $target=max(0.01,(float)BC_RMS_DB::setting('target_food_cost','35'));
        $suggested=null!==$base_cost?$base_cost/($target/100):null;
        return [
            'recipe_cost'=>$recipe_cost,'packaging_cost'=>$packaging_cost,'base_cost'=>$base_cost,
            'selling_price'=>$price,'food_cost_percent'=>$food_cost_percent,'gross_profit'=>$gross_profit,
            'gross_margin_percent'=>$gross_margin_percent,'suggested_price'=>$suggested,'missing_costs'=>$missing
        ];
    }
}
