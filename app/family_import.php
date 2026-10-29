<?php
require_once __DIR__.'/install.php';
function importFamily(string $sourceName,string $username,string $password): array {
 $c=require __DIR__.'/../config/database.php';
 if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$sourceName)||$sourceName===$c['name'])throw new RuntimeException('Choose the old source database. The configured family database must be a different, empty database.');
 $source=new PDO("mysql:host={$c['host']};port={$c['port']};dbname=$sourceName;charset=utf8mb4",$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $source->exec("SET time_zone = '+08:00'");
 $read=function(string $sql,array $args=[])use($source){$s=$source->prepare($sql);$s->execute($args);return $s;};
 $u=$read('SELECT * FROM users WHERE username=? AND role_id=1 AND is_active=1',[strtolower(trim($username))])->fetch();
 if(!$u||!password_verify($password,$u['password_hash']))throw new RuntimeException('Source Owner username or password is incorrect. Use the family business Owner, not the platform Admin.');
 $multi=array_key_exists('carinderia_id',$u);$cid=$multi?(int)$u['carinderia_id']:null;if($multi&&$cid<1)throw new RuntimeException('Select an Owner account with a business.');
 $open=$read("SELECT COUNT(*) FROM cash_sessions WHERE status='open'".($multi?' AND carinderia_id=?':''),$multi?[$cid]:[])->fetchColumn();if($open)throw new RuntimeException('Close the source business cash drawer before importing, then pause sales until the switch is complete.');
 $business=$multi?$read('SELECT name FROM carinderias WHERE carinderia_id=?',[$cid])->fetchColumn():'Original family workspace';
 installSchema();
 if(!val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='owner_guard'"))throw new RuntimeException('The destination is not a fresh family-edition database. Choose a new database name in config/database.php.');
 $tables=['users','menu_categories','menu_items','daily_menu_items','combo_components','cash_sessions','sales','sale_items','sale_item_components','payments','units','inventory_items','suppliers','stock_receipts','stock_receipt_items','stock_movements','expense_categories','expenses','audit_logs'];
 foreach($tables as $t)if(val('SELECT COUNT(*) FROM `'.$t.'`'))throw new RuntimeException('Destination must be empty. Import never merges or replaces existing family records.');
 $source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
 try{$summary=tx(function()use($tables,$read,$multi,$cid,$u,$business){
  foreach($tables as $t)if(val('SELECT COUNT(*) FROM `'.$t.'`'))throw new RuntimeException('Destination is no longer empty.');
  $counts=[];$riceLinks=[];$otherOwners=0;
  foreach($tables as $table){
   $columns=[];foreach(rows('SHOW COLUMNS FROM `'.$table.'`')as $col)if(!preg_match('/(?:VIRTUAL|STORED) GENERATED/',$col['Extra']))$columns[$col['Field']]=true;
   if($multi&&$table==='payments'){$records=$read('SELECT sale_id AS payment_id,sale_id,total_amount AS amount_paid,cash_tendered,change_given,paid_at FROM sales WHERE carinderia_id=?',[$cid])->fetchAll();}
   else{$records=$read('SELECT * FROM `'.$table.'`'.($multi?' WHERE carinderia_id=?':''),$multi?[$cid]:[])->fetchAll();}
   foreach($records as $record){$data=array_intersect_key($record,$columns);
    if($table==='users'){
     if((int)$data['user_id']===(int)$u['user_id']){$data['role_id']=1;$data['is_active']=1;}
     elseif((int)$data['role_id']===1){$data['role_id']=2;$data['is_active']=0;$otherOwners++;}
    }
    if($table==='menu_items'&&!empty($data['refill_menu_item_id'])){$riceLinks[$data['menu_item_id']]=$data['refill_menu_item_id'];$data['refill_menu_item_id']=null;}
    insert($table,$data);
   }
   $counts[$table]=count($records);
   if($table==='menu_items')foreach($riceLinks as $id=>$rice)change('menu_items','menu_item_id',(int)$id,['refill_menu_item_id'=>$rice]);
  }
  if(val('SELECT COUNT(*) FROM sales s LEFT JOIN payments p ON p.sale_id=s.sale_id WHERE p.payment_id IS NULL OR p.amount_paid<>s.total_amount'))throw new RuntimeException('Source sale/payment records are incomplete. No records were imported.');
  insert('audit_logs',['user_id'=>$u['user_id'],'action'=>'family_import','entity_name'=>'users','record_id'=>$u['user_id'],'new_values'=>json_encode(['business'=>$business,'counts'=>$counts,'other_owners_disabled'=>$otherOwners]),'reason'=>'Read-only copy from earlier CarinderEase database','created_at'=>now()]);
  return ['business'=>$business,'owner'=>$u['username'],'counts'=>$counts,'other_owners_disabled'=>$otherOwners];
 });$source->commit();return $summary;}catch(Throwable $e){if($source->inTransaction())$source->rollBack();throw $e;}
}
