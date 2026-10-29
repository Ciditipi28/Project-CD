<?php
function masters(): array {
    return [
      'users'=>['title'=>'Team accounts','table'=>'users','pk'=>'user_id','fields'=>['full_name'=>['text','Full name',100],'username'=>['text','Username',50],'role_id'=>['select','Role','roles','role_id','role_name'],'password'=>['password','Password (leave blank to keep)',255],'is_active'=>['check','Active account']]],
      'categories'=>['title'=>'Menu categories','table'=>'menu_categories','pk'=>'category_id','fields'=>['name'=>['text','Category name',50],'is_active'=>['check','Active category']]],
      'catalog'=>['title'=>'Dish & product catalog','table'=>'menu_items','pk'=>'menu_item_id','fields'=>['name'=>['text','Product name',100],'category_id'=>['select','Category','menu_categories','category_id','name'],'item_type'=>['enum','Product type',['dish'=>'Dish','rice'=>'Rice','drink'=>'Drink','combo'=>'Combo']],'standard_price'=>['money','Standard price'],'includes_unli_rice'=>['check','Includes unli-rice for dine-in'],'refill_menu_item_id'=>['optional','Refill rice product','menu_items','menu_item_id','name'],'is_active'=>['check','Active product']]],
      'units'=>['title'=>'Measurement units','table'=>'units','pk'=>'unit_id','fields'=>['unit_name'=>['text','Unit name',30],'symbol'=>['text','Symbol',10]]],
      'ingredients'=>['title'=>'Ingredient catalog','table'=>'inventory_items','pk'=>'inventory_item_id','fields'=>['name'=>['text','Ingredient name',100],'unit_id'=>['select','Base unit','units','unit_id','unit_name'],'reorder_level'=>['quantity','Low-stock threshold'],'is_active'=>['check','Active ingredient']]],
      'suppliers'=>['title'=>'Suppliers','table'=>'suppliers','pk'=>'supplier_id','fields'=>['name'=>['text','Supplier name',100],'contact_number'=>['optionaltext','Contact number',30],'address'=>['optionaltext','Address',255],'is_active'=>['check','Active supplier']]],
      'expense-categories'=>['title'=>'Expense categories','table'=>'expense_categories','pk'=>'expense_category_id','fields'=>['name'=>['text','Category name',50],'is_active'=>['check','Active category']]],
    ];
}
function saveMaster(string $module,array $p): void {
    need(['Owner']);$spec=masters()[$module]??null;if(!$spec)throw new RuntimeException('Unknown module.');$id=integer($p['id']??0,0);
    tx(function()use($module,$p,$spec,$id){
        $old=$id?one("SELECT * FROM {$spec['table']} WHERE {$spec['pk']}=?",[$id]):null;if($id&&!$old)throw new RuntimeException('Record not found.');$data=[];
        foreach($spec['fields']as $name=>$f){$v=$p[$name]??'';
            switch($f[0]){
                case 'text':$data[$name]=textval($v,$f[2]);break;
                case 'optionaltext':$data[$name]=$v===''?null:textval($v,$f[2]);break;
                case 'check':$data[$name]=isset($p[$name])?1:0;break;
                case 'money':$data[$name]=dec(cents($v));break;
                case 'quantity':$data[$name]=dec(fixed($v),3);break;
                case 'enum':$data[$name]=choice($v,array_keys($f[2]));break;
                case 'select':case 'optional':
                    $data[$name]=$f[0]==='optional'&&$v===''?null:integer($v);
                    if($data[$name]!==null&&!val("SELECT COUNT(*) FROM {$f[2]} WHERE {$f[3]}=?",[$data[$name]]))throw new RuntimeException('Choose an existing '.$f[1].'.');break;
                case 'password':if($v!==''||!$id){if(strlen($v)<10||strlen($v)>72)throw new InvalidArgumentException('Passwords must be 10 to 72 characters.');$data['password_hash']=password_hash($v,PASSWORD_DEFAULT);}break;
            }
        }
        if($module==='users'){
            if((int)$data['role_id']===1&&(!$old||(int)$old['role_id']!==1))throw new RuntimeException('This family workspace has one Owner / Admin. Choose Cashier or Kitchen Staff for another account.');
            $data['username']=strtolower($data['username']);if(!preg_match('/^[a-z0-9_.-]{3,50}$/D',$data['username']))throw new InvalidArgumentException('Username: 3–50 letters, digits, dots, underscores or hyphens.');
            if($id===(int)need()['user_id']&&(!$data['is_active']||(int)$data['role_id']!==1))throw new RuntimeException('You cannot deactivate or demote your own account.');
            if($old&&(int)$old['role_id']===1&&(!$data['is_active']||(int)$data['role_id']!==1)&&(int)val('SELECT COUNT(*) FROM users WHERE role_id=1 AND is_active=1')<=1)throw new RuntimeException('Keep at least one active owner.');
            if($id && openSession(false) && (int)openSession(false)['user_id']===$id && (!$data['is_active']||(int)$data['role_id']===3))throw new RuntimeException('Close this user’s cash session first.');
        }
        if($module==='catalog'){
            if($old&&$old['item_type']!==$data['item_type'] && (val('SELECT COUNT(*) FROM daily_menu_items WHERE menu_item_id=?',[$id])||val('SELECT COUNT(*) FROM combo_components WHERE combo_menu_item_id=? OR component_menu_item_id=?',[$id,$id])))throw new RuntimeException('Product type cannot change after it has been used. Create a new product instead.');
            if($data['includes_unli_rice']){
                if(!in_array($data['item_type'],['dish','combo'])||!val("SELECT COUNT(*) FROM menu_items WHERE menu_item_id=? AND item_type='rice' AND is_active=1",[$data['refill_menu_item_id']]))throw new RuntimeException('An unli meal must be a dish or combo with an active rice product.');
            }else $data['refill_menu_item_id']=null;
        }
        if($module==='ingredients'&&$old&&(int)$old['unit_id']!==(int)$data['unit_id']&&val('SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id=?',[$id]))throw new RuntimeException('Base unit cannot change after stock movements exist.');
        $rid=$id;if($id)change($spec['table'],$spec['pk'],$id,$data);else $rid=insert($spec['table'],$data);
        audit($id?'record_updated':'record_created',$spec['table'],$rid,$old,$data);
        if($module==='users'&&$id===(int)need()['user_id']&&isset($data['password_hash']))$_SESSION['auth_stamp']=hash('sha256',$data['password_hash']);
    });
}
function deleteMaster(string $module,int $id): void {
    need(['Owner']);$s=masters()[$module]??null;if(!$s)throw new RuntimeException('Unknown module.');
    tx(function()use($s,$module,$id){$old=one("SELECT * FROM {$s['table']} WHERE {$s['pk']}=?",[$id]);if(!$old)throw new RuntimeException('Record not found.');if($module==='users')throw new RuntimeException('Deactivate accounts instead of deleting them.');q("DELETE FROM {$s['table']} WHERE {$s['pk']}=?",[$id]);audit('unused_record_deleted',$s['table'],$id,$old);});
}
