<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
ini_set('display_errors', '0');
error_reporting(E_ALL);
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode','1');ini_set('session.gc_maxlifetime','1800');
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store');
}
function db(bool $serverOnly=false): PDO {
    static $pdo;
    if (!$serverOnly && $pdo) return $pdo;
    $c=require __DIR__.'/../config/database.php';
    if (!preg_match('/^[A-Za-z0-9_]+$/D',$c['name'])) throw new RuntimeException('Invalid database name in configuration.');
    $dsn="mysql:host={$c['host']};port={$c['port']};charset=utf8mb4".($serverOnly?'':";dbname={$c['name']}");
    $p=new PDO($dsn,$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $p->exec("SET time_zone = '+08:00'");
    $p->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY'");
    if (!$serverOnly) $pdo=$p;
    return $p;
}
function q(string $sql,array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function rows(string $sql,array $args=[]): array {return q($sql,$args)->fetchAll();}
function one(string $sql,array $args=[]): ?array {return q($sql,$args)->fetch() ?: null;}
function val(string $sql,array $args=[]): mixed {return q($sql,$args)->fetchColumn();}
function insert(string $table,array $data): int {
    $cols=array_keys($data); q('INSERT INTO `'.$table.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_values($data));
    return (int)db()->lastInsertId();
}
function change(string $table,string $pk,int $id,array $data): void {q('UPDATE `'.$table.'` SET '.implode(',',array_map(fn($k)=>'`'.$k.'`=?',array_keys($data))).' WHERE `'.$pk.'`=?',[...array_values($data),$id]);}
function tx(callable $fn): mixed {
    // Serialize short writes for a single counter, including retry and cash-close checks.
    $lock='ce_'.substr(hash('sha256',(string)val('SELECT DATABASE()')),0,40);
    if ((int)val('SELECT GET_LOCK(?, 8)',[$lock])!==1) throw new RuntimeException('The counter is busy. Please try again.');
    try {db()->beginTransaction(); $v=$fn(); db()->commit(); return $v;}
    catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    finally{q('SELECT RELEASE_LOCK(?)',[$lock]);}
}
function h(mixed $s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function now(): string {return date('Y-m-d H:i:s');}
function today(): string {return date('Y-m-d');}
function token(): string {return bin2hex(random_bytes(24));}
function cents(mixed $s): int {return fixed($s,2);}
function fixed(mixed $s,int $scale=3): int {
    $s=trim((string)$s);
    if(!preg_match('/^\d{1,7}(?:\.\d{1,'.$scale.'})?$/D',$s))throw new InvalidArgumentException('Enter a valid positive number with up to '.$scale.' decimal places.');
    $parts=explode('.',$s);return (int)$parts[0]*(10**$scale)+(int)str_pad($parts[1]??'',$scale,'0');
}
function dec(int $n,int $scale=2): string {$sign=$n<0?'-':'';$n=abs($n);return $sign.intdiv($n,10**$scale).'.'.str_pad((string)($n%(10**$scale)),$scale,'0',STR_PAD_LEFT);}
function money(mixed $v): string {return '₱'.number_format((float)$v,2);}
function integer(mixed $v,int $min=1,int $max=100000): int {if(filter_var($v,FILTER_VALIDATE_INT)===false || (int)$v<$min || (int)$v>$max)throw new InvalidArgumentException("Enter a whole number between $min and $max.");return (int)$v;}
function textval(mixed $v,int $max=255): string {$v=trim((string)$v);if($v===''||mb_strlen($v)>$max)throw new InvalidArgumentException("This field is required and must be at most $max characters.");return $v;}
function dateval(mixed $d): string {$d=(string)$d;$v=DateTime::createFromFormat('!Y-m-d',$d);if(!$v||$v->format('Y-m-d')!==$d)throw new InvalidArgumentException('Choose a valid date.');return $d;}
function choice(mixed $v,array $allowed): string {if(!in_array($v,$allowed,true))throw new InvalidArgumentException('Choose a valid option.');return (string)$v;}
function operation(mixed $v): string {if(!preg_match('/^[a-f0-9]{32,64}$/D',(string)$v))throw new InvalidArgumentException('Invalid request identifier. Reload the page.');return (string)$v;}
function user(): ?array {
    if(PHP_SAPI==='cli' && isset($GLOBALS['test_user']))return $GLOBALS['test_user'];
    static $resolved=false, $cached=null;
    if($resolved)return $cached;
    $resolved=true;
    if(empty($_SESSION['uid']))return null;
    if(time()-(int)($_SESSION['last_activity']??time())>1800){unset($_SESSION['uid'],$_SESSION['auth_stamp']);return null;}$_SESSION['last_activity']=time();
    $u=one('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE user_id=? AND is_active=1',[$_SESSION['uid']]);
    if(!$u || ($_SESSION['auth_stamp']??'')!==hash('sha256',$u['password_hash'])){unset($_SESSION['uid']);return null;}return $cached=$u;
}
function need(array $roles=['Owner','Cashier','Staff']): array {$u=user();if(!$u||!in_array($u['role_name'],$roles,true))throw new RuntimeException('You do not have permission for this action.');return $u;}
function owner(): bool {return user()['role_name']==='Owner';}
function csrf(): string {return $_SESSION['csrf']??=token();}
function guard(): void {if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('Your form expired. Reload the page and try again.');}
function flash(string $text,string $type='success'): void {$_SESSION['flash']=['text'=>$text,'type'=>$type];}
function go(string $url): never {header('Location: '.$url);exit;}
function audit(string $action,string $entity,int $id,mixed $old=null,mixed $new=null,?string $reason=null): void {
    foreach(['old','new'] as $key) if(is_array($$key)){unset(${$key}['password_hash']);}
    insert('audit_logs',['user_id'=>need()['user_id'],'action'=>$action,'entity_name'=>$entity,'record_id'=>$id,'old_values'=>$old===null?null:json_encode($old),'new_values'=>$new===null?null:json_encode($new),'reason'=>$reason,'created_at'=>now()]);
}
function safeError(Throwable $e): string {
    if($e instanceof PDOException){error_log((string)$e);return $e->getCode()==='23000'?'This record already exists or is linked to another record. Check your entries.':'The database could not complete this operation. No partial transaction was saved.';}
    return $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong. Please try again.';
}
