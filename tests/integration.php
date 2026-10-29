<?php
// Run only against a NEW disposable database whose name begins with ce_test_.
if(PHP_SAPI!=='cli')exit;
if(!str_starts_with(getenv('CE_DB_NAME')?:'','ce_test_')){fwrite(STDERR,"Use a fresh CE_DB_NAME beginning with ce_test_. This test creates sample records.\n");exit(1);}
require __DIR__.'/../app/core.php';require __DIR__.'/../app/install.php';require __DIR__.'/../app/services.php';require __DIR__.'/../app/masters.php';require __DIR__.'/../app/actions.php';require __DIR__.'/../app/reports.php';
ini_set('display_errors','1');
$tests=0;
function check(bool $ok,string $name): void {global $tests;if(!$ok)throw new RuntimeException('FAILED: '.$name);$tests++;echo "PASS $name\n";}
function rejects(callable $fn,string $name): void {try{$fn();}catch(Throwable $e){check(true,$name.' ['.$e->getMessage().']');return;}throw new RuntimeException('FAILED: '.$name.' did not reject');}
function asUser(int $id): void {$GLOBALS['test_user']=one('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE user_id=?',[$id]);}
function remaining(int $catalog): int {foreach(menu(today())as $m)if((int)$m['menu_item_id']===$catalog)return $m['remaining'];throw new RuntimeException('Missing product');}
try{
installSchema();check((int)val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')===20,'exactly 20 tables');
if(val('SELECT COUNT(*) FROM users'))throw new RuntimeException('Choose a fresh test database.');
$owner=insert('users',['role_id'=>1,'full_name'=>'Christian Pascua','username'=>'owner','password_hash'=>password_hash('TestingOnly!123',PASSWORD_DEFAULT)]);asUser($owner);seedStarter($owner);
$cashier=insert('users',['role_id'=>2,'full_name'=>'Counter Cashier','username'=>'cashier','password_hash'=>password_hash('TestingOnly!123',PASSWORD_DEFAULT)]);
$staff=insert('users',['role_id'=>3,'full_name'=>'Kitchen Staff','username'=>'staff','password_hash'=>password_hash('TestingOnly!123',PASSWORD_DEFAULT)]);
check(password_verify('TestingOnly!123',need()['password_hash']),'password hashing');
check((int)val('SELECT COUNT(*) FROM menu_items')===12,'starter catalog');
rejects(fn()=>checkout([['id'=>1,'qty'=>1]],'dine_in','50',token()),'sale requires own open drawer');
act('session_open',['opening_float'=>'500.00']);$session=(int)openSession()['cash_session_id'];
rejects(fn()=>act('session_open',['opening_float'=>'0']),'one open cash drawer');
$startRice=remaining(1);$startChicken=remaining(3);$startTea=remaining(9);$stock=stockBalance(1);
$key=token();$sale=checkout([['id'=>12,'qty'=>2]],'dine_in','500',$key);
check((string)val('SELECT total_amount FROM sales WHERE sale_id=?',[$sale])==='210.00','combo total uses server prices');
check((string)val('SELECT change_given FROM payments WHERE sale_id=?',[$sale])==='290.00','cash change is exact');
check(remaining(1)===$startRice-2&&remaining(3)===$startChicken-2&&remaining(9)===$startTea-2,'combo deducts each component');
check(stockBalance(1)===$stock,'checkout does not double-deduct raw ingredients');
check((int)val('SELECT COUNT(*) FROM sale_items WHERE sale_id=?',[$sale])===2,'separate unli entitlements for two meals');
check(checkout([['id'=>12,'qty'=>2]],'dine_in','500',$key)===$sale&&(int)val('SELECT COUNT(*) FROM sales')===1,'duplicate checkout is idempotent');
$line=(int)val('SELECT MIN(sale_item_id) FROM sale_items WHERE sale_id=?',[$sale]);$refillKey=token();asUser($staff);refill($line,$refillKey);refill($line,$refillKey);
check(remaining(1)===$startRice-3,'duplicate refill only deducts once');
check((string)val('SELECT SUM(total_amount) FROM sales')==='210.00','refill has no revenue');
act('visit_close',['id'=>$line]);rejects(fn()=>refill($line,token()),'closed visits cannot refill');
rejects(fn()=>checkout([['id'=>1,'qty'=>1]],'dine_in','20',token()),'staff cannot sell');
rejects(fn()=>saveMaster('categories',['name'=>'Forbidden']),'staff cannot edit owner catalog');
stockMove(['inventory_item_id'=>1,'movement_type'=>'kitchen_issue','quantity'=>'3.000','reason'=>'Morning rice','operation_token'=>token()]);check(stockBalance(1)===$stock-3000,'ingredient issue reduces raw rice');
rejects(fn()=>stockMove(['inventory_item_id'=>1,'movement_type'=>'kitchen_issue','quantity'=>'9999','reason'=>'Too much','operation_token'=>token()]),'negative stock rejected');
rejects(fn()=>stockMove(['inventory_item_id'=>1,'movement_type'=>'adjustment','quantity'=>'1','reason'=>'Forbidden','operation_token'=>token()]),'staff cannot adjust stock');
$qty=(int)val('SELECT prepared_qty FROM daily_menu_items WHERE menu_item_id=2');saveDaily(['menu_item_id'=>2,'prepared_qty'=>$qty+5,'wasted_qty'=>0,'selling_price'=>'0.01','is_enabled'=>'1','reason'=>'Second batch']);check((string)val('SELECT selling_price FROM daily_menu_items WHERE menu_item_id=2')==='65.00','staff cannot override prices');
rejects(fn()=>saveDaily(['menu_item_id'=>1,'prepared_qty'=>1,'wasted_qty'=>0,'is_enabled'=>1,'reason'=>'Bad count']),'prepared count cannot drop below served');
$receiptToken=token();$target=act('receipt_create',['supplier_id'=>1,'payment_source'=>'drawer','operation_token'=>$receiptToken]);$rid=(int)substr($target,strrpos($target,'=')+1);
check(act('receipt_create',['supplier_id'=>1,'payment_source'=>'drawer','operation_token'=>$receiptToken])===$target,'duplicate receipt draft is idempotent');
rejects(fn()=>postReceipt($rid),'empty receipt cannot post');
act('receipt_line',['id'=>$rid,'inventory_item_id'=>1,'quantity'=>'2.500','unit_cost'=>'50.00']);$before=stockBalance(1);postReceipt($rid);postReceipt($rid);check(stockBalance(1)===$before+2500,'receipt posting updates stock once');
rejects(fn()=>act('receipt_line',['id'=>$rid,'inventory_item_id'=>1,'quantity'=>'1','unit_cost'=>'1']),'posted receipt cannot be edited');
rejects(fn()=>act('receipt_delete',['id'=>$rid]),'posted receipt cannot be deleted');
asUser($owner);$expenseKey=token();$expense=['expense_category_id'=>3,'amount'=>'20.00','description'=>'Transport fare','payment_source'=>'drawer','operation_token'=>$expenseKey];act('expense_add',$expense);act('expense_add',$expense);
check((int)val('SELECT COUNT(*) FROM expenses')===1,'duplicate expense is idempotent');
$totals=sessionTotals($session);check($totals['expected']===56500,'cash reconciliation: 500 + 210 - 125 - 20');
$before=(int)val('SELECT COUNT(*) FROM sales');$rice=remaining(1);
rejects(fn()=>checkout([['id'=>2,'qty'=>1]],'dine_in','1',token()),'insufficient cash rejected');
check((int)val('SELECT COUNT(*) FROM sales')===$before&&remaining(1)===$rice,'failed checkout leaves no partial sale');
rejects(fn()=>checkout([['id'=>12,'qty'=>24],['id'=>3,'qty'=>2]],'dine_in','9999',token()),'aggregate component stock prevents overselling');
$takeout=checkout([['id'=>12,'qty'=>1]],'takeout','105',token());check((int)val('SELECT unli_rice_granted FROM sale_items WHERE sale_id=?',[$takeout])===0,'takeout has no refill entitlement');
// Genuine CRUD and constraints.
saveMaster('categories',['name'=>'Temporary test','is_active'=>1]);$cat=(int)val("SELECT category_id FROM menu_categories WHERE name='Temporary test'");saveMaster('categories',['id'=>$cat,'name'=>'Renamed test','is_active'=>1]);check(val('SELECT name FROM menu_categories WHERE category_id=?',[$cat])==='Renamed test','catalog update persists');deleteMaster('categories',$cat);check(!(bool)val('SELECT COUNT(*) FROM menu_categories WHERE category_id=?',[$cat]),'unused catalog delete works');
rejects(fn()=>deleteMaster('categories',1),'foreign key protects used category');
rejects(fn()=>q('INSERT INTO menu_categories(name) VALUES (?)',['Ulam']),'unique category constraint');
rejects(fn()=>saveMaster('ingredients',['id'=>1,'name'=>'Uncooked rice','unit_id'=>2,'reorder_level'=>'5','is_active'=>1]),'cannot change used stock unit');
foreach(array_keys(reportTypes())as $type){[$h,$r]=reportData($type,today(),today());check(count($h)>0&&count($r)>0,'report '.$type.' returns database records');}
rejects(fn()=>reportData('sales','2026-12-31','2026-01-01'),'reversed report dates rejected');
act('session_close',['id'=>$session,'counted_cash'=>'670.00']);check(openSession(false)===null,'drawer closes');
rejects(fn()=>checkout([['id'=>1,'qty'=>1]],'dine_in','15',token()),'closed drawer blocks checkout');
rejects(fn()=>act('expense_add',['expense_category_id'=>1,'amount'=>'10','description'=>'No drawer','payment_source'=>'drawer','operation_token'=>token()]),'closed drawer blocks expenses');
asUser($cashier);act('session_open',['opening_float'=>'200']);rejects(fn()=>reportData('sales',today(),today()),'cashier cannot access owner reports');
$cashSale=checkout([['id'=>2,'qty'=>1],['id'=>1,'qty'=>1]],'dine_in','100',token());check((string)val('SELECT total_amount FROM sales WHERE sale_id=?',[$cashSale])==='80.00','cashier checkout works');
asUser($owner);rejects(fn()=>checkout([['id'=>1,'qty'=>1]],'dine_in','15',token()),'owner cannot use another cashier’s session');
check((int)val('SELECT COUNT(*) FROM audit_logs')>15,'sensitive writes create audit events');
echo "\nALL $tests INTEGRATION CHECKS PASSED\n";
}catch(Throwable $e){fwrite(STDERR,(string)$e."\n");exit(1);}
