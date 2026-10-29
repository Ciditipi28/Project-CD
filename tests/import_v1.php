<?php
if(PHP_SAPI!=='cli'||!str_starts_with(getenv('CE_DB_NAME')?:'','ce_test_'))exit(1);
require __DIR__.'/../app/core.php';require __DIR__.'/../app/family_import.php';$count=0;
function check(bool $ok,string $label): void {global $count;if(!$ok)throw new RuntimeException('FAIL '.$label);$count++;echo "PASS $label\n";}
try{$c=require __DIR__.'/../config/database.php';$name=$c['name'].'_source';db(true)->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$s=new PDO("mysql:host={$c['host']};port={$c['port']};dbname=$name;charset=utf8mb4",$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$s->exec("SET time_zone='+08:00'");$sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/fixtures/v1_schema.sql'));foreach(explode(';',$sql)as $stmt)if(trim($stmt))$s->exec($stmt);
$st=$s->prepare('INSERT INTO users(role_id,full_name,username,password_hash) VALUES (1,?,?,?)');$st->execute(['Legacy Owner','owner',password_hash('TestingOnly!123',PASSWORD_DEFAULT)]);
$s->exec("INSERT INTO cash_sessions(user_id,opened_at,opening_float,status) VALUES (1,NOW(),500,'open')");
try{importFamily($name,'owner','TestingOnly!123');throw new LogicException('Open drawer allowed');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'Close the source'),'open source drawer blocks import');}
$s->exec("UPDATE cash_sessions SET status='closed',closed_at=NOW(),counted_cash=580 WHERE cash_session_id=1");$st=$s->prepare("INSERT INTO sales(sale_number,checkout_token,cash_session_id,sold_at,order_type,total_amount) VALUES ('CE-LEGACY',?,1,NOW(),'takeout',80)");$st->execute([token()]);
try{importFamily($name,'owner','TestingOnly!123');throw new LogicException('Incomplete payment allowed');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'incomplete'),'incomplete source payments rejected');}
check((int)val('SELECT COUNT(*) FROM users')===0&&(int)val('SELECT COUNT(*) FROM sales')===0,'failed import rolls back all copied records');
$s->exec('INSERT INTO payments(sale_id,amount_paid,cash_tendered,change_given,paid_at) VALUES (1,80,100,20,NOW())');$result=importFamily($name,'owner','TestingOnly!123');check($result['owner']==='owner','v1 Owner retained');check((string)val('SELECT amount_paid FROM payments WHERE sale_id=1')==='80.00','v1 payment retained');check((int)$s->query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'v1 source remains intact');echo "ALL $count V1 COPY CHECKS PASSED\n";
}catch(Throwable $e){fwrite(STDERR,(string)$e);exit(1);}
