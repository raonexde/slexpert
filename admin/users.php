<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_super_admin();

if (request_is_post()) {
    verify_csrf();
    $userId = max(0, (int)($_POST['user_id'] ?? 0));
    $stmt = db()->prepare('SELECT id,name,active,is_super_admin FROM admin_users WHERE id=?');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();
    if (!$target) {
        flash('error', 'Der Benutzer wurde nicht gefunden.');
    } elseif ($userId === (int)admin_user()['id']) {
        flash('error', 'Den eigenen Zugang können Sie hier nicht deaktivieren.');
    } else {
        $newActive = (int)$target['active'] === 1 ? 0 : 1;
        if (!$newActive && (int)$target['is_super_admin'] === 1) {
            $remaining = (int)db()->query('SELECT COUNT(*) FROM admin_users WHERE active=1 AND is_super_admin=1')->fetchColumn();
            if ($remaining <= 1) {
                flash('error', 'Mindestens ein aktiver Hauptadministrator muss erhalten bleiben.');
                redirect('admin/users.php');
            }
        }
        db()->prepare('UPDATE admin_users SET active=? WHERE id=?')->execute([$newActive,$userId]);
        flash('success', $newActive ? 'Der Benutzer wurde aktiviert.' : 'Der Benutzer wurde deaktiviert.');
    }
    redirect('admin/users.php');
}

$users = db()->query("SELECT u.*,
    (SELECT COUNT(*) FROM admin_user_permissions p WHERE p.admin_user_id=u.id AND (p.can_view=1 OR p.can_manage=1)) AS permission_count
    FROM admin_users u ORDER BY u.is_super_admin DESC,u.active DESC,u.name")->fetchAll();
$adminPage='users';$adminTitle='Benutzerverwaltung';require __DIR__.'/_header.php';
?>
<div class="admin-content">
    <div class="page-heading compact"><div><p>SICHERHEIT & ZUGRIFF</p><h1>Benutzerverwaltung</h1><span>Admin-Zugänge erstellen und Rechte je Verwaltungsbereich vergeben.</span></div><a class="primary-button" href="user-edit.php">＋ Neuer Benutzer</a></div>
    <section class="admin-card">
        <div class="card-head"><div><h2>Admin-Benutzer</h2><p><?= count($users) ?> Zugänge · die Rechteverwaltung ist nur für Hauptadministratoren sichtbar</p></div></div>
        <div class="admin-table-wrap"><table class="admin-table user-admin-table"><thead><tr><th>Benutzer</th><th>Rolle</th><th>Bereiche</th><th>Letzter Login</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
        <?php foreach($users as $managedUser): ?><tr>
            <td><strong><?= e($managedUser['name']) ?></strong><small><?= e($managedUser['email']) ?></small></td>
            <td><?php if((int)$managedUser['is_super_admin']===1): ?><span class="role-badge super">Hauptadministrator</span><?php else: ?><span class="role-badge">Bereichsbenutzer</span><?php endif; ?></td>
            <td><?= (int)$managedUser['is_super_admin']===1 ? 'Alle Bereiche' : (int)$managedUser['permission_count'].' von '.count(admin_permission_modules()) ?><small><?= (int)$managedUser['is_super_admin']===1?'Uneingeschränkter Zugriff':'Individuelle Rechte' ?></small></td>
            <td><?= $managedUser['last_login_at']?e(date('d.m.Y H:i',strtotime($managedUser['last_login_at']))):'Noch nie' ?></td>
            <td><span class="status <?= $managedUser['active']?'confirmed':'archived' ?>"><?= $managedUser['active']?'Aktiv':'Inaktiv' ?></span></td>
            <td><div class="user-row-actions"><a href="user-edit.php?id=<?= (int)$managedUser['id'] ?>">Bearbeiten</a><?php if((int)$managedUser['id']!==(int)admin_user()['id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>"><button type="submit"><?= $managedUser['active']?'Deaktivieren':'Aktivieren' ?></button></form><?php endif; ?></div></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
<?php require __DIR__.'/_footer.php'; ?>
