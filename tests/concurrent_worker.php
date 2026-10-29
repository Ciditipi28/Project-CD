<?php
if(PHP_SAPI!=='cli'||!str_starts_with(getenv('CE_DB_NAME')?:'','ce_test_'))exit(1);
require __DIR__.'/../app/services.php';
$GLOBALS['test_user']=one("SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE username='cashier'");
try{$id=checkout([['id'=>1,'qty'=>1]],'dine_in','15',$argv[1]);echo json_encode(['ok'=>true,'id'=>$id]);}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
