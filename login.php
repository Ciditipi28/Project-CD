<?php
require __DIR__.'/app/core.php';require __DIR__.'/app/auth_ui.php';
$error='';$setup=false;
try{if(user())go('index.php');$setup=(int)val('SELECT COUNT(*) FROM users')===0;if(!val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='owner_guard'")){$setup=true;$error='This database belongs to an earlier version. Configure a new family database and use Import family records.';}}catch(Throwable $e){$setup=true;}
if($_SERVER['REQUEST_METHOD']==='POST'&&!$setup)try{
    guard();$name=strtolower(trim((string)($_POST['username']??'')));
    // File-based rate limit also survives a new browser session; storage is denied by Apache.
    $path=__DIR__.'/storage/login-'.hash('sha256',($_SERVER['REMOTE_ADDR']??'').':'.$name).'.json';
    $f=fopen($path,'c+');if(!$f)throw new RuntimeException('Login storage is not writable. Check the storage folder permissions.');flock($f,LOCK_EX);
    try{$r=json_decode(stream_get_contents($f),true)?:['tries'=>0,'until'=>0];if($r['until']<time())$r=['tries'=>0,'until'=>time()+300];
        if($r['tries']>=5)throw new RuntimeException('Too many sign-in attempts. Please wait five minutes.');
        $u=one('SELECT * FROM users WHERE username=? AND is_active=1',[$name]);
        $valid=password_verify((string)($_POST['password']??''),$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if(!$u||!$valid){$r['tries']++;rewind($f);ftruncate($f,0);fwrite($f,json_encode($r));throw new RuntimeException('Username or password is incorrect.');}
        rewind($f);ftruncate($f,0);fwrite($f,json_encode(['tries'=>0,'until'=>0]));
        session_regenerate_id(true);$_SESSION['uid']=$u['user_id'];$_SESSION['auth_stamp']=hash('sha256',$u['password_hash']);$_SESSION['csrf']=token();$_SESSION['last_activity']=time();
    }finally{flock($f,LOCK_UN);fclose($f);}go('index.php');
}catch(Throwable $e){$error=safeError($e);}
authStart('Welcome home.','Run your family’s carinderia with ease.',$error,'YOUR FAMILY. YOUR KITCHEN. YOUR WORKSPACE.');
if($setup){echo '<div class="notice">Create your one Owner / Admin account to get started.</div><a class="auth-back" href="setup.php">Set up the family workspace →</a><a class="auth-back" href="import-family.php">Bring records from an earlier CarinderEase version</a>';}
else{authForm();authField('Username','username','text','autocomplete="username" maxlength="50"',$_POST['username']??'');authField('Password','password','password','autocomplete="current-password"');authSubmit('Sign in to workspace →');echo '<p class="register-prompt">Need a staff account or password reset? Ask your Owner / Admin.</p><div class="auth-assurance">One family business. One Owner / Admin.<br>Your menu, counter, kitchen and daily numbers in one place.</div>';}
authEnd();
