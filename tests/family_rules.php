<?php
if(PHP_SAPI!=='cli'||!str_starts_with(getenv('CE_DB_NAME')?:'','ce_test_'))exit(1);
require __DIR__.'/../app/core.php';require __DIR__.'/../app/services.php';require __DIR__.'/../app/masters.php';
$u=one('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.role_id=1');if(!$u)exit(1);$GLOBALS['test_user']=$u;$count=0;
function denied(callable $fn,string $label,string $part): void {global $count;try{$fn();}catch(Throwable $e){if(!str_contains($e->getMessage(),$part))throw $e;$count++;echo "PASS $label\n";return;}throw new RuntimeException('FAIL '.$label);}
try{
denied(fn()=>saveMaster('users',['full_name'=>'Second Owner','username'=>'secondowner','role_id'=>'1','password'=>'TestingOnly!123','is_active'=>'1']),'UI/service refuses second owner','one Owner');
denied(fn()=>insert('users',['role_id'=>1,'full_name'=>'Direct owner','username'=>'directowner','password_hash'=>password_hash('TestingOnly!123',PASSWORD_DEFAULT)]),'database refuses second owner','Duplicate');
denied(fn()=>saveMaster('users',['id'=>$u['user_id'],'full_name'=>$u['full_name'],'username'=>$u['username'],'role_id'=>'2','password'=>'','is_active'=>1]),'owner cannot demote self','cannot deactivate or demote');
saveMaster('users',['full_name'=>'Family helper','username'=>'helper','role_id'=>'3','password'=>'TestingOnly!123','is_active'=>1]);if(!val("SELECT COUNT(*) FROM users WHERE username='helper' AND role_id=3"))throw new RuntimeException('Staff creation failed');$count++;echo "PASS owner creates staff directly without OTP\nALL $count FAMILY ROLE CHECKS PASSED\n";
}catch(Throwable $e){fwrite(STDERR,(string)$e);exit(1);}
