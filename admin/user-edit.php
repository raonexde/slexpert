<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_super_admin();

$id=max(0,(int)($_GET['id']??$_POST['id']??0));
$managedUser=['id'=>0,'name'=>'','email'=>'','role'=>'editor','is_super_admin'=>0,'active'=>1,'last_login_at'=>null];
if($id){$stmt=db()->prepare('SELECT id,name,email,role,is_super_admin,active,last_login_at,created_at FROM admin_users WHERE id=?');$stmt->execute([$id]);$loaded=$stmt->fetch();if(!$loaded){flash('error','Der Benutzer wurde nicht gefunden.');redirect('admin/users.php');}$managedUser=$loaded;}
$permissions=$id?admin_permissions_for_user($id):[];
$modules=admin_permission_modules();

if(request_is_post()){
    verify_csrf();
    $managedUser['name']=substr(trim((string)($_POST['name']??'')),0,120);
    $managedUser['email']=strtolower(substr(trim((string)($_POST['email']??'')),0,190));
    $managedUser['active']=isset($_POST['active'])?1:0;
    $managedUser['is_super_admin']=isset($_POST['is_super_admin'])?1:0;
    $managedUser['role']=$managedUser['is_super_admin']?'admin':'editor';
    $password=(string)($_POST['new_password']??'');
    $errors=[];
    if($managedUser['name']==='')$errors[]='Bitte einen Namen eingeben.';
    if(!filter_var($managedUser['email'],FILTER_VALIDATE_EMAIL))$errors[]='Bitte eine gültige E-Mail-Adresse eingeben.';
    if(!$id&&strlen($password)<10)$errors[]='Für einen neuen Benutzer ist ein Passwort mit mindestens 10 Zeichen erforderlich.';
    if($password!==''&&strlen($password)<10)$errors[]='Das neue Passwort muss mindestens 10 Zeichen enthalten.';
    $duplicate=db()->prepare('SELECT COUNT(*) FROM admin_users WHERE email=? AND id<>?');$duplicate->execute([$managedUser['email'],$id]);if((int)$duplicate->fetchColumn()>0)$errors[]='Diese E-Mail-Adresse wird bereits verwendet.';
    if($id===(int)admin_user()['id']&&(!(int)$managedUser['active']||!(int)$managedUser['is_super_admin']))$errors[]='Sie können den eigenen Hauptadministrator-Zugang nicht deaktivieren oder herabstufen.';
    if($id&&((int)$managedUser['active']===0||(int)$managedUser['is_super_admin']===0)){
        $wasSuperStmt=db()->prepare('SELECT is_super_admin,active FROM admin_users WHERE id=?');$wasSuperStmt->execute([$id]);$wasSuper=$wasSuperStmt->fetch();
        if($wasSuper&&(int)$wasSuper['is_super_admin']===1&&(int)$wasSuper['active']===1){$activeSupers=(int)db()->query('SELECT COUNT(*) FROM admin_users WHERE active=1 AND is_super_admin=1')->fetchColumn();if($activeSupers<=1)$errors[]='Mindestens ein aktiver Hauptadministrator muss erhalten bleiben.';}
    }
    $submitted=(array)($_POST['permissions']??[]);$permissions=[];
    foreach($modules as $key=>$module){$view=isset($submitted[$key]['view']);$manage=isset($submitted[$key]['manage']);$permissions[$key]=['view'=>$view||$manage,'manage'=>$manage];}
    if($errors){flash('error',implode(' ',$errors));}
    else{
        $pdo=db();
        try{$pdo->beginTransaction();
            if($id){
                if($password!==''){$save=$pdo->prepare('UPDATE admin_users SET name=?,email=?,role=?,is_super_admin=?,active=?,password_hash=? WHERE id=?');$save->execute([$managedUser['name'],$managedUser['email'],$managedUser['role'],$managedUser['is_super_admin'],$managedUser['active'],password_hash($password,PASSWORD_DEFAULT),$id]);}
                else{$save=$pdo->prepare('UPDATE admin_users SET name=?,email=?,role=?,is_super_admin=?,active=? WHERE id=?');$save->execute([$managedUser['name'],$managedUser['email'],$managedUser['role'],$managedUser['is_super_admin'],$managedUser['active'],$id]);}
            }else{$save=$pdo->prepare('INSERT INTO admin_users (name,email,password_hash,role,is_super_admin,active) VALUES (?,?,?,?,?,?)');$save->execute([$managedUser['name'],$managedUser['email'],password_hash($password,PASSWORD_DEFAULT),$managedUser['role'],$managedUser['is_super_admin'],$managedUser['active']]);$id=(int)$pdo->lastInsertId();}
            $pdo->prepare('DELETE FROM admin_user_permissions WHERE admin_user_id=?')->execute([$id]);
            if(!(int)$managedUser['is_super_admin']){$permissionSave=$pdo->prepare('INSERT INTO admin_user_permissions (admin_user_id,module_key,can_view,can_manage) VALUES (?,?,?,?)');foreach($permissions as $key=>$permission){if(!$permission['view']&&!$permission['manage'])continue;$permissionSave->execute([$id,$key,$permission['view']?1:0,$permission['manage']?1:0]);}}
            $pdo->commit();flash('success','Benutzer und Bereichsrechte wurden gespeichert.');redirect('admin/user-edit.php?id='.$id);
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();flash('error','Speichern fehlgeschlagen: '.$exception->getMessage());}
    }
}

$adminPage='users';$adminTitle=$id?'Benutzer bearbeiten':'Neuer Benutzer';require __DIR__.'/_header.php';
?>
<div class="admin-content narrow"><a class="back-admin" href="users.php">← Benutzerverwaltung</a><div class="page-heading compact"><div><p>SICHERHEIT & ZUGRIFF</p><h1><?= $id?'Benutzer bearbeiten':'Neuen Benutzer anlegen' ?></h1><span>Hauptadministratoren haben Vollzugriff; Bereichsbenutzer erhalten nur ausgewählte Rechte.</span></div></div>
<form class="editor-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
<section class="admin-card form-card"><div class="card-head"><div><h2>Zugangsdaten</h2><p>Name, Login, Status und Administratortyp</p></div></div><div class="form-grid"><label>Name<input name="name" required maxlength="120" value="<?= e($managedUser['name']) ?>"></label><label>E-Mail / Login<input name="email" type="email" required maxlength="190" value="<?= e($managedUser['email']) ?>"></label><label>Neues Passwort<input name="new_password" type="password" minlength="10" autocomplete="new-password"<?= $id?'':' required' ?>><small><?= $id?'Leer lassen, um das bisherige Passwort zu behalten.':'Mindestens 10 Zeichen.' ?></small></label><div class="account-toggle-list"><label class="check"><input type="checkbox" name="active" value="1"<?= $managedUser['active']?' checked':'' ?>> Zugang aktiv</label><label class="check"><input type="checkbox" name="is_super_admin" value="1" data-super-admin-toggle<?= $managedUser['is_super_admin']?' checked':'' ?>> Hauptadministrator mit Vollzugriff</label></div></div></section>
<section class="admin-card permission-card"><div class="card-head"><div><h2>Bereichsrechte</h2><p>„Bearbeiten“ schließt das Ansehen automatisch ein. Hauptadministratoren benötigen keine Einzelrechte.</p></div></div><div class="permission-table" data-permission-table><div class="permission-head"><span>Verwaltungsbereich</span><span>Ansehen</span><span>Bearbeiten</span></div><?php foreach($modules as $key=>$module):$permission=$permissions[$key]??['view'=>false,'manage'=>false];?><div class="permission-row"><div><strong><?= e($module['label']) ?></strong><small><?= e($module['description']) ?></small></div><label><input type="checkbox" name="permissions[<?= e($key) ?>][view]" value="1"<?= $permission['view']?' checked':'' ?>><span>Ansehen</span></label><label><input type="checkbox" name="permissions[<?= e($key) ?>][manage]" value="1"<?= $permission['manage']?' checked':'' ?>><span>Bearbeiten</span></label></div><?php endforeach;?></div></section>
<div class="form-actions"><a href="users.php">Abbrechen</a><button class="primary-button" type="submit">Benutzer speichern</button></div></form></div>
<script>(function(){var master=document.querySelector('[data-super-admin-toggle]'),table=document.querySelector('[data-permission-table]');function update(){if(!master||!table)return;table.classList.toggle('permission-disabled',master.checked);table.querySelectorAll('input').forEach(function(input){input.disabled=master.checked;});}if(master){master.addEventListener('change',update);update();}document.querySelectorAll('.permission-row').forEach(function(row){var boxes=row.querySelectorAll('input');if(boxes.length===2)boxes[1].addEventListener('change',function(){if(this.checked)boxes[0].checked=true;});});})();</script>
<?php require __DIR__.'/_footer.php'; ?>
