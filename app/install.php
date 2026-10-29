<?php
function installSchema(): void {
    $c=require __DIR__.'/../config/database.php';
    $p=db(true);$p->exec('CREATE DATABASE IF NOT EXISTS `'.$c['name'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $count=(int)val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()');
    if($count===0){$sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../database/schema.sql'));foreach(explode(';',$sql)as $statement)if(trim($statement)!=='')db()->exec($statement);}
    elseif($count!==20)throw new RuntimeException('Database is not empty and does not contain the complete 20-table schema. Use a fresh empty database or inspect a previous interrupted installation.');
}
function seedStarter(int $owner): void {
    foreach(['Ulam','Rice','Drinks','Combos']as $name)insert('menu_categories',['name'=>$name]);
    foreach([['Kilogram','kg'],['Liter','L'],['Piece','pc']]as $unit)insert('units',['unit_name'=>$unit[0],'symbol'=>$unit[1]]);
    foreach(['Rent','LPG','Transport','Utilities','Other operating costs']as $name)insert('expense_categories',['name'=>$name]);
    insert('suppliers',['name'=>'Public market vendor']);insert('suppliers',['name'=>'Local grocery']);
    $products=[['Steamed Rice',2,'rice','15.00'],['Pork Adobo',1,'dish','65.00'],['Chicken Afritada',1,'dish','65.00'],['Pork Sinigang',1,'dish','75.00'],['Monggo',1,'dish','35.00'],['Pinakbet',1,'dish','40.00'],['Fried Tilapia',1,'dish','70.00'],['Bicol Express',1,'dish','65.00'],['Iced Tea',3,'drink','20.00'],['Bottled Water',3,'drink','15.00'],['Adobo Sulit Meal',4,'combo','95.00'],['Afritada Unli Meal',4,'combo','105.00']];
    foreach($products as $i=>$p){$id=insert('menu_items',['name'=>$p[0],'category_id'=>$p[1],'item_type'=>$p[2],'standard_price'=>$p[3],'includes_unli_rice'=>$i===11?1:0,'refill_menu_item_id'=>$i===11?1:null]);insert('daily_menu_items',['menu_item_id'=>$id,'menu_date'=>today(),'selling_price'=>$p[3],'prepared_qty'=>$p[2]==='combo'?null:($p[2]==='rice'?80:25),'wasted_qty'=>0,'is_enabled'=>1,'updated_by'=>$owner]);}
    foreach([[11,2,1],[11,1,1],[11,9,1],[12,3,1],[12,1,1],[12,9,1]]as $p)insert('combo_components',['combo_menu_item_id'=>$p[0],'component_menu_item_id'=>$p[1],'quantity'=>$p[2]]);
    foreach([['Uncooked rice',1,'5.000','25.000'],['Pork',1,'2.000','10.000'],['Chicken',1,'2.000','8.000'],['Cooking oil',2,'1.000','5.000'],['Mung beans',1,'1.000','4.000'],['Eggs',3,'12.000','60.000']]as $p){$id=insert('inventory_items',['name'=>$p[0],'unit_id'=>$p[1],'reorder_level'=>$p[2]]);insert('stock_movements',['inventory_item_id'=>$id,'movement_type'=>'opening_balance','quantity_change'=>$p[3],'reason'=>'Sample starting stock for demonstration','operation_token'=>token(),'recorded_by'=>$owner,'recorded_at'=>now()]);}
}
