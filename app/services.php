<?php
require_once __DIR__.'/core.php';
function menu(string $date): array {
    $r=rows('SELECT d.*,m.name,m.item_type,m.includes_unli_rice,m.refill_menu_item_id,m.is_active,c.name category,COALESCE(s.served,0) served FROM daily_menu_items d JOIN menu_items m ON m.menu_item_id=d.menu_item_id JOIN menu_categories c ON c.category_id=m.category_id LEFT JOIN (SELECT daily_menu_item_id,SUM(quantity) served FROM sale_item_components GROUP BY daily_menu_item_id) s ON s.daily_menu_item_id=d.daily_menu_item_id WHERE d.menu_date=? ORDER BY c.category_id,m.name',[$date]);
    $by=[];foreach($r as $x)$by[$x['menu_item_id']]=$x;
    $defs=rows('SELECT * FROM combo_components ORDER BY combo_component_id');
    foreach($r as &$x){
        $remaining=(int)$x['prepared_qty']-(int)$x['wasted_qty']-(int)$x['served'];
        if($x['item_type']==='combo'){
            $parts=array_values(array_filter($defs,fn($p)=>(int)$p['combo_menu_item_id']===(int)$x['menu_item_id']));
            $remaining=$parts?PHP_INT_MAX:0;
            foreach($parts as $part){$p=$by[$part['component_menu_item_id']]??null;$a=$p&&$p['is_enabled']&&$p['is_active']?(int)$p['prepared_qty']-(int)$p['wasted_qty']-(int)$p['served']:0;$remaining=min($remaining,intdiv(max(0,$a),(int)$part['quantity']));}
        }
        if($x['includes_unli_rice'] && $x['item_type']!=='combo'){
            $rice=$by[$x['refill_menu_item_id']]??null;
            $remaining=min($remaining,$rice&&$rice['is_enabled']&&$rice['is_active']?(int)$rice['prepared_qty']-(int)$rice['wasted_qty']-(int)$rice['served']:0);
        }
        $x['remaining']=max(0,$remaining);$x['available']=$x['is_enabled']&&$x['is_active']&&$remaining>0;
    }unset($x);return $r;
}
function openSession(bool $mine=true): ?array {
    $s=one("SELECT c.*,u.full_name FROM cash_sessions c JOIN users u ON u.user_id=c.user_id WHERE status='open'");
    if($mine && $s && (int)$s['user_id']!==(int)need()['user_id'])return null;return $s;
}
function sessionTotals(int $id): array {
    $s=one('SELECT * FROM cash_sessions WHERE cash_session_id=?',[$id]);if(!$s)throw new RuntimeException('Cash session not found.');
    $sales=cents(val('SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN sales s ON s.sale_id=p.sale_id WHERE s.cash_session_id=?',[$id]));
    $purchases=cents(val("SELECT COALESCE(SUM(ROUND(i.quantity*i.unit_cost,2)),0) FROM stock_receipts r JOIN stock_receipt_items i ON i.stock_receipt_id=r.stock_receipt_id WHERE r.status='posted' AND r.cash_session_id=?",[$id]));
    $expenses=cents(val('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE cash_session_id=?',[$id]));
    return ['sales'=>$sales,'purchases'=>$purchases,'expenses'=>$expenses,'expected'=>cents($s['opening_float'])+$sales-$purchases-$expenses];
}
function checkout(array $cart,string $type,mixed $cash,string $key): int {
    $u=need(['Owner','Cashier']);operation($key);choice($type,['dine_in','takeout']);$tender=cents($cash);
    return tx(function()use($u,$cart,$type,$tender,$key){
        $old=one('SELECT s.*,c.user_id FROM sales s JOIN cash_sessions c ON c.cash_session_id=s.cash_session_id WHERE checkout_token=?',[$key]);
        if($old){if((int)$old['user_id']!==(int)$u['user_id'])throw new RuntimeException('Request belongs to another cashier.');return (int)$old['sale_id'];}
        $session=openSession();if(!$session)throw new RuntimeException('Open your cash session before taking orders.');
        if(!$cart||count($cart)>100)throw new InvalidArgumentException('Add at least one item, up to 100 cart lines.');
        $map=[];$catalog=[];foreach(menu(today())as $d){$map[(int)$d['daily_menu_item_id']]=$d;$catalog[(int)$d['menu_item_id']]=$d;}
        $wanted=[];foreach($cart as $line){$id=integer($line['id']??0);$qty=integer($line['qty']??0,1,100);$wanted[$id]=($wanted[$id]??0)+$qty;if($wanted[$id]>100)throw new InvalidArgumentException('Maximum 100 units of one product per sale.');}
        $lines=[];$usage=[];$total=0;
        foreach($wanted as $id=>$qty){
            $d=$map[$id]??null;if(!$d||!$d['available'])throw new RuntimeException('An item is no longer available. Refresh the menu.');
            $parts=[];
            if($d['item_type']==='combo'){
                foreach(rows('SELECT * FROM combo_components WHERE combo_menu_item_id=?',[$d['menu_item_id']])as $p){$c=$catalog[$p['component_menu_item_id']]??null;if(!$c||!$c['available']||$c['item_type']==='combo')throw new RuntimeException('A combo component is unavailable.');$parts[(int)$c['daily_menu_item_id']]=(int)$p['quantity'];}
                if(!$parts)throw new RuntimeException('This combo has no components.');
                if($d['includes_unli_rice'] && !isset($parts[(int)($catalog[$d['refill_menu_item_id']]['daily_menu_item_id']??0)]))throw new RuntimeException('Unli-rice combo must include its configured rice.');
            }else{
                $parts[$id]=1;
                if($d['includes_unli_rice']){$rice=$catalog[$d['refill_menu_item_id']]??null;if(!$rice||!$rice['available']||$rice['item_type']!=='rice')throw new RuntimeException('Rice is unavailable for this meal.');$parts[(int)$rice['daily_menu_item_id']]=1;}
            }
            foreach($parts as $pid=>$n)$usage[$pid]=($usage[$pid]??0)+$n*$qty;
            $eligible=(bool)$d['includes_unli_rice']&&$type==='dine_in';
            $copies=$eligible?$qty:1;
            for($i=0;$i<$copies;$i++)$lines[]=['d'=>$d,'qty'=>$eligible?1:$qty,'parts'=>$parts,'eligible'=>$eligible];
            $total+=cents($d['selling_price'])*$qty;
        }
        foreach($usage as $id=>$qty)if($qty>$map[$id]['remaining'])throw new RuntimeException('Not enough '.$map[$id]['name'].' for the whole order.');
        if($tender<$total)throw new RuntimeException('Cash received is less than the order total.');
        $id=insert('sales',['sale_number'=>'CE-'.date('ymd').'-'.strtoupper(substr($key,0,10)),'checkout_token'=>$key,'cash_session_id'=>$session['cash_session_id'],'sold_at'=>now(),'order_type'=>$type,'total_amount'=>dec($total)]);
        foreach($lines as $line){$d=$line['d'];$lid=insert('sale_items',['sale_id'=>$id,'daily_menu_item_id'=>$d['daily_menu_item_id'],'item_name_snapshot'=>$d['name'],'quantity'=>$line['qty'],'unit_price'=>$d['selling_price'],'unli_rice_granted'=>(int)$line['eligible'],'refill_menu_item_id'=>$line['eligible']?$d['refill_menu_item_id']:null]);
            foreach($line['parts']as $pid=>$n)insert('sale_item_components',['sale_item_id'=>$lid,'daily_menu_item_id'=>$pid,'quantity'=>$n*$line['qty'],'serving_type'=>'initial','operation_token'=>$key,'served_by'=>$u['user_id'],'served_at'=>now()]);
        }
        insert('payments',['sale_id'=>$id,'amount_paid'=>dec($total),'cash_tendered'=>dec($tender),'change_given'=>dec($tender-$total),'paid_at'=>now()]);audit('sale_completed','sales',$id,null,['total'=>dec($total)]);return $id;
    });
}
function refill(int $line,string $key): void {
    $u=need();operation($key);tx(function()use($line,$key,$u){
        if(val("SELECT COUNT(*) FROM sale_item_components WHERE operation_token=?",[$key]))return;
        $l=one('SELECT i.*,s.order_type,s.sold_at FROM sale_items i JOIN sales s ON s.sale_id=i.sale_id WHERE sale_item_id=?',[$line]);
        if(!$l||!$l['unli_rice_granted']||$l['refill_closed_at']||$l['order_type']!=='dine_in'||substr($l['sold_at'],0,10)!==today())throw new RuntimeException('This meal is not eligible for a refill.');
        $d=null;foreach(menu(today())as $m)if((int)$m['menu_item_id']===(int)$l['refill_menu_item_id'])$d=$m;
        if(!$d||!$d['available']||$d['item_type']!=='rice')throw new RuntimeException('No prepared rice is available.');
        insert('sale_item_components',['sale_item_id'=>$line,'daily_menu_item_id'=>$d['daily_menu_item_id'],'quantity'=>1,'serving_type'=>'refill','operation_token'=>$key,'served_by'=>$u['user_id'],'served_at'=>now()]);audit('rice_refill','sale_items',$line);
    });
}
function stockBalance(int $id): int {return (int)val('SELECT COALESCE(SUM(quantity_change),0)*1000 FROM stock_movements WHERE inventory_item_id=?',[$id]);}
function stockMove(array $p): void {
    $u=need();$id=integer($p['inventory_item_id']??0);$kind=choice($p['movement_type']??'',['opening_balance','kitchen_issue','wastage','adjustment']);$qty=fixed($p['quantity']??'');if(!$qty)throw new InvalidArgumentException('Quantity must be greater than zero.');
    if(in_array($kind,['opening_balance','adjustment']))need(['Owner']);
    $sign=in_array($kind,['kitchen_issue','wastage'])?-1:($kind==='adjustment'&&($p['direction']??'')==='out'?-1:1);$key=operation($p['operation_token']??'');$reason=textval($p['reason']??'');
    tx(function()use($u,$id,$kind,$qty,$sign,$key,$reason){
        if(val('SELECT COUNT(*) FROM stock_movements WHERE operation_token=?',[$key]))return;
        if(!val('SELECT COUNT(*) FROM inventory_items WHERE inventory_item_id=? AND is_active=1',[$id]))throw new RuntimeException('Select an active ingredient.');
        if($kind==='opening_balance' && val('SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id=?',[$id]))throw new RuntimeException('Opening balance can only be recorded before any movements.');
        if(stockBalance($id)+$qty*$sign<0)throw new RuntimeException('Insufficient ingredient stock.');
        $mid=insert('stock_movements',['inventory_item_id'=>$id,'movement_type'=>$kind,'quantity_change'=>dec($qty*$sign,3),'reason'=>$reason,'operation_token'=>$key,'recorded_by'=>$u['user_id'],'recorded_at'=>now()]);audit('stock_'.$kind,'stock_movements',$mid,null,['quantity'=>dec($qty*$sign,3)],$reason);
    });
}
function validatePaymentSource(array $p): array {
    $source=choice($p['payment_source']??'',['owner','drawer']);$session=null;
    if($source==='drawer'){$s=openSession(false);if(!$s)throw new RuntimeException('Open a drawer session before recording a drawer payment.');$session=$s['cash_session_id'];}
    return [$source,$session];
}
function postReceipt(int $id): void {
    $u=need(['Owner','Staff']);tx(function()use($id,$u){
        $r=one('SELECT * FROM stock_receipts WHERE stock_receipt_id=?',[$id]);if(!$r)throw new RuntimeException('Receipt not found.');if($r['status']==='posted')return;
        $lines=rows('SELECT l.*,i.is_active FROM stock_receipt_items l JOIN inventory_items i ON i.inventory_item_id=l.inventory_item_id WHERE stock_receipt_id=?',[$id]);if(!$lines)throw new RuntimeException('Add at least one receipt line.');
        [$source,$session]=validatePaymentSource($r);
        foreach($lines as $l){if(!$l['is_active'])throw new RuntimeException('A purchased ingredient is inactive.');insert('stock_movements',['inventory_item_id'=>$l['inventory_item_id'],'stock_receipt_item_id'=>$l['stock_receipt_item_id'],'movement_type'=>'purchase','quantity_change'=>$l['quantity'],'reason'=>'Stock receipt #'.$id,'operation_token'=>hash('sha256','receipt-'.$id.'-'.$l['stock_receipt_item_id']),'recorded_by'=>$u['user_id'],'recorded_at'=>now()]);}
        change('stock_receipts','stock_receipt_id',$id,['status'=>'posted','received_at'=>now(),'posted_at'=>now(),'cash_session_id'=>$session]);audit('receipt_posted','stock_receipts',$id);
    });
}
function saveDaily(array $p): void {
    $u=need(['Owner','Staff']);$id=integer($p['menu_item_id']??0);$reason=textval($p['reason']??'Daily preparation');
    tx(function()use($p,$u,$id,$reason){
        $m=one('SELECT * FROM menu_items WHERE menu_item_id=? AND is_active=1',[$id]);if(!$m)throw new RuntimeException('Choose an active menu product.');
        $old=one('SELECT * FROM daily_menu_items WHERE menu_date=? AND menu_item_id=?',[today(),$id]);
        $price=owner()?dec(cents($p['selling_price']??'')):($old['selling_price']??$m['standard_price']);
        $prepared=$m['item_type']==='combo'?null:integer($p['prepared_qty']??0,0);
        $waste=$m['item_type']==='combo'?0:integer($p['wasted_qty']??0,0);
        $served=$old?(int)val('SELECT COALESCE(SUM(quantity),0) FROM sale_item_components WHERE daily_menu_item_id=?',[$old['daily_menu_item_id']]):0;
        if($prepared!==null && $prepared<$served+$waste)throw new RuntimeException('Prepared portions cannot be less than served plus waste.');
        $data=['menu_item_id'=>$id,'menu_date'=>today(),'selling_price'=>$price,'prepared_qty'=>$prepared,'wasted_qty'=>$waste,'is_enabled'=>isset($p['is_enabled'])?1:0,'updated_by'=>$u['user_id']];
        if($old){$did=(int)$old['daily_menu_item_id'];change('daily_menu_items','daily_menu_item_id',$did,$data);}else $did=insert('daily_menu_items',$data);
        audit('daily_menu_saved','daily_menu_items',$did,$old,$data,$reason);
    });
}
