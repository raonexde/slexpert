<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_portal_user();
$user=portal_user();$errors=[];
if(request_is_post()){
    verify_csrf();$name=trim((string)($_POST['name']??''));$phone=trim((string)($_POST['phone']??''));$language=($_POST['language']??'de')==='en'?'en':'de';$newPassword=(string)($_POST['new_password']??'');$confirm=(string)($_POST['password_confirm']??'');
    if($name===''||strlen($name)>150)$errors[]=t('Bitte geben Sie Ihren Namen ein.','Please enter your name.');
    if($newPassword!==''&&strlen($newPassword)<8)$errors[]=t('Das neue Passwort muss mindestens 8 Zeichen enthalten.','The new password must contain at least 8 characters.');
    if($newPassword!==''&&!hash_equals($newPassword,$confirm))$errors[]=t('Die neuen Passwörter stimmen nicht überein.','The new passwords do not match.');
    if(!$errors){
        if($newPassword!=='')db()->prepare('UPDATE portal_users SET name=?,phone=?,language=?,password_hash=? WHERE id=?')->execute([$name,substr($phone,0,80),$language,password_hash($newPassword,PASSWORD_DEFAULT),(int)$user['id']]);
        else db()->prepare('UPDATE portal_users SET name=?,phone=?,language=? WHERE id=?')->execute([$name,substr($phone,0,80),$language,(int)$user['id']]);
        $_SESSION['portal_user']['name']=$name;$_SESSION['portal_user']['phone']=$phone;$_SESSION['portal_user']['language']=$language;$_SESSION['lang']=$language;
        flash('success',t('Ihr Profil wurde gespeichert.','Your profile has been saved.'));redirect('account/profile.php?lang='.$language);
    }
}
$pageTitle=t('Profil','Profile');$bodyClass='account-page';$extraStyles=['css/account.css'];require dirname(__DIR__).'/includes/public-header.php';$accountPage='profile';require __DIR__.'/_nav.php';
?>
<main class="account-main narrow"><section class="account-heading"><div><p><?= e(t('KONTO','ACCOUNT')) ?></p><h1><?= e(t('Profil & Sicherheit','Profile & security')) ?></h1><span><?= e(t('Kontaktdaten, Sprache und Passwort verwalten.','Manage contact details, language and password.')) ?></span></div></section><?php foreach($errors as $error):?><div class="account-alert error"><?= e($error) ?></div><?php endforeach;?>
<section class="account-card account-form-card"><header><div><h2><?= e(t('Kontodaten','Account details')) ?></h2><p><?= e($user['email']) ?></p></div></header><form method="post"><?= csrf_field() ?><div class="account-form-grid"><label><?= e(t('Name','Name')) ?><input name="name" required maxlength="150" value="<?= e($user['name']) ?>"></label><label><?= e(t('Telefon / WhatsApp','Phone / WhatsApp')) ?><input name="phone" maxlength="80" value="<?= e($user['phone']) ?>"></label><label><?= e(t('Sprache','Language')) ?><select name="language"><option value="de"<?= selected('de',$user['language']) ?>>Deutsch</option><option value="en"<?= selected('en',$user['language']) ?>>English</option></select></label><span></span><label><?= e(t('Neues Passwort','New password')) ?><input name="new_password" type="password" minlength="8" autocomplete="new-password"><small><?= e(t('Leer lassen, um es nicht zu ändern.','Leave blank to keep it unchanged.')) ?></small></label><label><?= e(t('Neues Passwort wiederholen','Repeat new password')) ?><input name="password_confirm" type="password" minlength="8" autocomplete="new-password"></label></div><button class="account-primary" type="submit"><?= e(t('Profil speichern','Save profile')) ?></button></form></section></main>
<?php require dirname(__DIR__).'/includes/public-footer.php';?>
