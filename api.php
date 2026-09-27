<?php
/* SLOW DM — мини-сервер базы данных. Положить рядом с index.html. */
header('Content-Type: application/json; charset=utf-8');
const ADMIN_TOKEN = 'slowdm-admin-2026';   /* ОБЯЗАТЕЛЬНО смените на свой и поменяйте в index.html */
const DB_FILE = __DIR__.'/slowdm-db.json';

$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : null;
$token = isset($_GET['token']) ? (string)$_GET['token'] : (is_array($input) && isset($input['token']) ? (string)$input['token'] : '');
$isAdmin = is_string($token) && $token !== '' && hash_equals(ADMIN_TOKEN, $token);

function db_load(){
  if(!is_file(DB_FILE)) return array('users'=>array(),'news'=>null,'promos'=>null,'rules'=>null,'settings'=>null,'seclog'=>array());
  $d = json_decode((string)file_get_contents(DB_FILE), true);
  return is_array($d) ? $d : array();
}
function db_save($d){ file_put_contents(DB_FILE, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX); }

if(isset($_GET['ping'])){ echo json_encode(array('ok'=>true,'server'=>'slowdm-php','time'=>date('c'))); exit; }

$db = db_load();

if(isset($_GET['reset'])){
  if(!$isAdmin){ http_response_code(403); echo json_encode(array('error'=>'forbidden')); exit; }
  db_save(array('users'=>array(),'news'=>null,'promos'=>null,'rules'=>null,'settings'=>null,'seclog'=>array(),'_updated'=>date('c')));
  echo json_encode(array('ok'=>true)); exit;
}

if($method==='POST'||$method==='PUT'){
  if(!$isAdmin){ http_response_code(403); echo json_encode(array('error'=>'forbidden')); exit; }
  $key = is_array($input)&&isset($input['key']) ? (string)$input['key'] : '';
  $allowed = array('users','news','promos','rules','settings','seclog');
  if(!in_array($key,$allowed,true)){ http_response_code(400); echo json_encode(array('error'=>'bad key')); exit; }
  $val = is_array($input)&&array_key_exists('value',$input) ? $input['value'] : null;
  if($key==='users'&&is_array($val)){
    $old = array();
    if(isset($db['users'])&&is_array($db['users'])){ foreach($db['users'] as $ou){ if(isset($ou['id'])) $old[$ou['id']]=$ou; } }
    foreach($val as &$nu){
      if(isset($nu['id'])&&isset($old[$nu['id']])){
        if(!isset($nu['passPlain'])&&isset($old[$nu['id']]['passPlain'])) $nu['passPlain']=$old[$nu['id']]['passPlain'];
      }
    }
    unset($nu);
  }
  $db[$key]=$val;
  $db['_updated']=date('c');
  db_save($db);
  echo json_encode(array('ok'=>true,'updated'=>$db['_updated'])); exit;
}

/* GET — снимок базы (пароли passPlain отдаются только с токеном) */
$users = array();
if(isset($db['users'])&&is_array($db['users'])){
  foreach($db['users'] as $u){ if(!$isAdmin){ unset($u['passPlain']); } $users[]=$u; }
}
echo json_encode(array(
 'ok'=>true,'mode'=>'slowdm-php',
 'updated'=>isset($db['_updated'])?$db['_updated']:null,
 'users'=>$users,
 'news'=>isset($db['news'])?$db['news']:null,
 'promos'=>isset($db['promos'])?$db['promos']:null,
 'rules'=>isset($db['rules'])?$db['rules']:null,
 'settings'=>isset($db['settings'])?$db['settings']:null,
 'seclog'=>isset($db['seclog'])?$db['seclog']:array()
), JSON_UNESCAPED_UNICODE);