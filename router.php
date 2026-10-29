<?php
// Optional local PHP development server. XAMPP uses the included .htaccess files.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/';
$decoded=rawurldecode($path);
if(preg_match('~(?:^|/)(?:app|config|database|storage|tests|docs)(?:/|$)|\.\.|\.(?:md|sql|log|json)$~i',$decoded)){http_response_code(403);exit('Forbidden');}
if($path==='/'||$path==='/index.php'){require __DIR__.'/index.php';return true;}
return false;
