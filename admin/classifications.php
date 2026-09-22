<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('classifications', request_is_post());

$allowedTypes = ['accommodation','sight','activity','restaurant','spice_garden','shop','service'];
$typeLabels = [
    'accommodation'=>'Unterkunft','sight'=>'Sehenswürdigkeit','activity'=>'Aktivitätszentrum',
    'restaurant'=>'Restaurant','spice_garden'=>'Gewürzgarten','shop'=>'Shop','service'=>'Zusatzleistung',
];
$editId = max(0,(int)($_GET['id']??0));
$classification = ['id'=>0,'item_type'=>'accommodation','code'=>'','name_de'=>'','name_en'=>'','active'=>1,'sort_order'=>0];
if ($editId) {
    $stmt=db()->prepare('SELECT * FROM catalog_classifications WHERE id=?');
    $stmt->execute([$editId]);
    $classification=$stmt->fetch()?:$classification;
}

if (request_is_post()) {
    verify_csrf();
    try {
        $id=max(0,(int)($_POST['id']??0));
        $itemType=in_array($_POST['item_type']??'', $allowedTypes, true)?$_POST['item_type']:'accommodation';
        $code=strtolower(trim((string)($_POST['code']??'')));
        $code=preg_replace('/[^a-z0-9_]+/','_',$code)??'';
        $code=trim(substr($code,0,80),'_');
        $nameDe=substr(trim((string)($_POST['name_de']??'')),0,160);
        $nameEn=substr(trim((string)($_POST['name_en']??'')),0,160);
        if ($code===''||$nameDe===''||$nameEn==='') throw new RuntimeException('Code sowie deutscher und englischer Name sind erforderlich.');
        $values=[$itemType,$code,$nameDe,$nameEn,isset($_POST['active'])?1:0,(int)($_POST['sort_order']??0)];
        if ($id) {
            $values[]=$id;
            db()->prepare('UPDATE catalog_classifications SET item_type=?,code=?,name_de=?,name_en=?,active=?,sort_order=? WHERE id=?')->execute($values);
        } else {
            db()->prepare('INSERT INTO catalog_classifications (item_type,code,name_de,name_en,active,sort_order) VALUES (?,?,?,?,?,?)')->execute($values);
        }
        flash('success','Klassifizierung wurde gespeichert.');
    } catch (Throwable $exception) {
        flash('error','Speichern fehlgeschlagen: '.$exception->getMessage());
    }
    redirect('admin/classifications.php');
}

$rows=db()->query('SELECT cc.*,(SELECT COUNT(*) FROM catalog_items c WHERE c.classification_id=cc.id) AS item_count FROM catalog_classifications cc ORDER BY FIELD(cc.item_type,\'accommodation\',\'sight\',\'activity\',\'restaurant\',\'spice_garden\',\'shop\',\'service\'),cc.sort_order,cc.name_de')->fetchAll();
$adminPage='classifications';$adminTitle='Kategorien & Klassifizierungen';require __DIR__.'/_header.php';
?>
<div class="admin-content">
    <a class="back-admin" href="catalog.php">← Reisebausteine</a>
    <div class="page-heading compact"><div><p>KATALOGSTRUKTUR</p><h1>Kategorien & Klassifizierungen</h1><span>Unterkunftsarten und Unterkategorien für Restaurants, Gewürzgärten, Aktivitäten, Shops und weitere Leistungen.</span></div></div>
    <div class="detail-grid">
        <form class="editor-form" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$classification['id'] ?>">
            <section class="admin-card form-card"><div class="card-head"><div><h2><?= $classification['id']?'Klassifizierung bearbeiten':'Neue Klassifizierung' ?></h2><p>Deutsch & Englisch</p></div></div><div class="form-grid">
                <label>Haupttyp<select name="item_type"><?php foreach($typeLabels as $value=>$label):?><option value="<?= e($value) ?>"<?= selected($value,$classification['item_type']) ?>><?= e($label) ?></option><?php endforeach;?></select></label>
                <label>Code<input name="code" required maxlength="80" value="<?= e($classification['code']) ?>" placeholder="z. B. boutique_hotel"><small>Kleinbuchstaben, Zahlen und Unterstriche.</small></label>
                <label>Name Deutsch<input name="name_de" required maxlength="160" value="<?= e($classification['name_de']) ?>"></label>
                <label>Name Englisch<input name="name_en" required maxlength="160" value="<?= e($classification['name_en']) ?>"></label>
                <label>Sortierung<input type="number" name="sort_order" value="<?= (int)$classification['sort_order'] ?>"></label>
                <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool)$classification['active']) ?>> Aktiv und auswählbar</label>
            </div></section>
            <div class="form-actions"><?php if($classification['id']):?><a href="classifications.php">Abbrechen</a><?php endif;?><button class="primary-button" type="submit">Klassifizierung speichern</button></div>
        </form>
        <section class="admin-card"><div class="card-head"><div><h2>Vorhandene Klassifizierungen</h2><p><?= count($rows) ?> Einträge · individuell erweiterbar</p></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Haupttyp</th><th>Name</th><th>Code</th><th>Verwendung</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach($rows as $row):?><tr><td><?= e($typeLabels[$row['item_type']]??$row['item_type']) ?></td><td><strong><?= e($row['name_de']) ?></strong><small><?= e($row['name_en']) ?></small></td><td><?= e($row['code']) ?></td><td><?= (int)$row['item_count'] ?></td><td><span class="status <?= $row['active']?'confirmed':'archived' ?>"><?= $row['active']?'Aktiv':'Inaktiv' ?></span></td><td><a href="?id=<?= (int)$row['id'] ?>">Bearbeiten →</a></td></tr><?php endforeach;?>
        </tbody></table></div></section>
    </div>
</div>
<?php require __DIR__.'/_footer.php'; ?>
