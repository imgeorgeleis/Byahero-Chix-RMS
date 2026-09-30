<?php
/** Recipe costing service. @package ByaheroChixRMS */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class BC_RMS_Costing_Service {
    public static function ingredient_cost_for_quantity($ingredient_id,$quantity,$unit_id){
        global $wpdb; $ingredients=BC_RMS_DB::table('ingredients');$units=BC_RMS_DB::table('units');
        $ingredient=$wpdb->get_row($wpdb->prepare("SELECT i.*,bu.unit_type base_type,bu.factor_to_base base_factor FROM $ingredients i JOIN $units bu ON bu.id=i.base_unit_id WHERE i.id=%d",$ingredient_id));
        $unit=$wpdb->get_row($wpdb->prepare("SELECT * FROM $units WHERE id=%d",$unit_id));
        if(!$ingredient||!$unit||$ingredient->base_type!==$unit->unit_type) return null;
        $base_cost=BC_RMS_DB::preferred_cost($ingredient_id); if(null===$base_cost) return null;
        $base_qty=((float)$quantity*(float)$unit->factor_to_base)/(float)$ingredient->base_factor;
        return $base_qty*$base_cost;
    }
    public static function recipe($recipe_id){
        global $wpdb;$recipes=BC_RMS_DB::table('recipes');$items=BC_RMS_DB::table('recipe_items');
        $recipe=$wpdb->get_row($wpdb->prepare("SELECT * FROM $recipes WHERE id=%d",$recipe_id)); if(!$recipe)return null;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $items WHERE recipe_id=%d ORDER BY sort_order,id",$recipe_id));
        $subtotal=0.0;$missing=0;foreach($rows as $row){$cost=self::ingredient_cost_for_quantity($row->ingredient_id,$row->quantity,$row->unit_id);if(null===$cost){$missing++;continue;}$subtotal+=$cost;}
        $waste=$subtotal*((float)$recipe->waste_percent/100);$total=$subtotal+$waste;$servings=max(0.0001,(float)$recipe->servings);
        return ['ingredient_cost'=>$subtotal,'waste_cost'=>$waste,'total_cost'=>$total,'cost_per_serving'=>$total/$servings,'missing_costs'=>$missing,'item_count'=>count($rows)];
    }
}
