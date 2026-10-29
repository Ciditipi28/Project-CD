<?php
require __DIR__.'/app/core.php';require __DIR__.'/app/services.php';require __DIR__.'/app/masters.php';require __DIR__.'/app/actions.php';require __DIR__.'/app/ui.php';require __DIR__.'/app/views_sales.php';require __DIR__.'/app/views_manage.php';require __DIR__.'/app/reports.php';
try{if(!user())go('login.php');}catch(Throwable $e){go('login.php');}
$page=(string)($_GET['page']??'dashboard');if(!isset(pages()[$page])){http_response_code(404);$page='dashboard';$GLOBALS['page_error']='That page does not exist.';}
try{need(routeRoles($page));}catch(Throwable $e){http_response_code(403);$page='dashboard';$GLOBALS['page_error']='You do not have access to that page.';}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{guard();$target=act((string)($_POST['action']??''),$_POST);flash('Saved successfully.');go($target);}
    catch(Throwable $e){$GLOBALS['page_error']=safeError($e);http_response_code(422);}
}
if($page==='reports'&&($_GET['export']??'')==='csv'){
    try{$type=choice($_GET['type']??'sales',array_keys(reportTypes()));[$headers,$data]=reportData($type,dateval($_GET['from']??today()),dateval($_GET['to']??today()));header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="CarinderEase-'.$type.'-'.today().'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$headers);foreach($data as $row){$values=array_map(function($v){$s=(string)$v;return preg_match('/^[=+@\t\r-]/',$s)&&!is_numeric($s)?"'".$s:$s;},array_values($row));fputcsv($out,$values);}fclose($out);exit;}catch(Throwable $e){$GLOBALS['page_error']=safeError($e);}
}
layoutStart($page);
try{
    if(isset(masters()[$page]))masterView($page);
    else switch($page){
    case 'dashboard':dashboardView();break;case 'pos':posView();break;case 'daily-menu':dailyView();break;case 'combos':combosView();break;case 'sales':salesView();break;case 'sale':saleView();break;case 'refills':refillsView();break;case 'inventory':inventoryView();break;case 'movements':movementsView();break;case 'receiving':receivingView();break;case 'receipt':receiptView();break;case 'expenses':expensesView();break;case 'cash-session':cashView();break;case 'reports':reportsView();break;case 'audit':auditView();break;case 'profile':profileView();break;case 'settings':settingsView();break;
    }
}catch(Throwable $e){echo '<div class="notice error" role="alert">'.h(safeError($e)).'</div><a class="btn ghost" href="index.php">Return to overview</a>';error_log((string)$e);}
layoutEnd();
