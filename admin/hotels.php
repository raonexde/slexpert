<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('hotels', request_is_post());

if (request_is_post()) {
    verify_csrf();
    $hotelId = max(0, (int)($_POST['hotel_id'] ?? 0));
    $action = (string)($_POST['action'] ?? '');
    $hotelStmt = db()->prepare("SELECT id,name_de,active FROM catalog_items WHERE id=? AND type='accommodation'");
    $hotelStmt->execute([$hotelId]);
    $hotel = $hotelStmt->fetch();
    if ($hotel) {
        if ($action === 'toggle') {
            db()->prepare('UPDATE catalog_items SET active=? WHERE id=?')->execute([(int)$hotel['active'] === 1 ? 0 : 1,$hotelId]);
            flash('success', (int)$hotel['active'] === 1 ? 'Hotel wurde archiviert.' : 'Hotel wurde aktiviert.');
        } elseif ($action === 'delete') {
            $templateStmt = db()->prepare('SELECT COUNT(*) FROM tour_template_stop_items WHERE catalog_item_id=?');
            $templateStmt->execute([$hotelId]);
            $requestStmt = db()->prepare('SELECT COUNT(*) FROM tour_request_items WHERE item_id=?');
            $requestStmt->execute([$hotelId]);
            $used = (int)$templateStmt->fetchColumn() + (int)$requestStmt->fetchColumn();
            if ($used > 0) {
                db()->prepare('UPDATE catalog_items SET active=0 WHERE id=?')->execute([$hotelId]);
                flash('success', 'Dieses Hotel wird bereits in Reisen oder Buchungen verwendet und wurde deshalb sicher archiviert.');
            } else {
                db()->prepare("DELETE FROM catalog_items WHERE id=? AND type='accommodation'")->execute([$hotelId]);
                flash('success', 'Hotel und zugehörige Zimmerdaten wurden endgültig gelöscht.');
            }
        }
    }
    redirect('admin/hotels.php');
}

$search = substr(trim((string)($_GET['q'] ?? '')),0,120);
$country = in_array($_GET['country'] ?? '', ['LK','MV'], true) ? $_GET['country'] : '';
$status = in_array($_GET['status'] ?? '', ['active','archived'], true) ? $_GET['status'] : '';
$where = ["c.type='accommodation'"];
$params = [];
if ($search !== '') {
    $searchTerms=array_slice(preg_split('/\s+/u',$search,-1,PREG_SPLIT_NO_EMPTY)?:[],0,8);
    $searchIndex="CONCAT_WS(' ',
        CAST(c.id AS CHAR),c.name_de,c.name_en,d.name_de,d.name_en,d.region_de,d.region_en,d.country_code,
        CASE d.country_code WHEN 'LK' THEN 'Sri Lanka' WHEN 'MV' THEN 'Malediven Maldives' ELSE '' END,
        COALESCE(cc.name_de,''),COALESCE(cc.name_en,''),REPLACE(c.market_segment,'_',' '),
        c.supplier_name,c.sltda_registration_number,CAST(c.star_rating AS CHAR),
        CASE WHEN c.star_rating>0 THEN CONCAT(c.star_rating,' Sterne star') ELSE '' END,
        DATE_FORMAT(c.created_at,'%d.%m.%Y'),DATE_FORMAT(c.created_at,'%Y-%m-%d')
    )";
    foreach($searchTerms as $term){$where[]="$searchIndex LIKE ?";$params[]='%'.strtr($term,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']).'%';}
}
if ($country !== '') { $where[] = 'd.country_code=?'; $params[]=$country; }
if ($status !== '') $where[] = 'c.active=' . ($status === 'active' ? '1' : '0');
$sql = "SELECT c.*,d.name_de AS destination_name,d.country_code,cc.name_de AS classification_name,
        COUNT(DISTINCT r.id) AS room_types,
        MIN(CASE WHEN r.active=1 THEN r.base_rate_per_room_night END) AS room_rate_from
    FROM catalog_items c JOIN destinations d ON d.id=c.destination_id
    LEFT JOIN catalog_classifications cc ON cc.id=c.classification_id
    LEFT JOIN hotel_room_types r ON r.hotel_id=c.id
    WHERE ".implode(' AND ',$where)."
    GROUP BY c.id ORDER BY c.created_at DESC,c.id DESC";
$stmt=db()->prepare($sql);$stmt->execute($params);$hotels=$stmt->fetchAll();

$adminPage='hotels';$adminTitle='Hotelverwaltung';require __DIR__.'/_header.php';
?>
<div class="admin-content">
    <div class="page-heading compact"><div><p>UNTERKÜNFTE</p><h1>Hotelverwaltung</h1><span>Hotels, Resorts, Zimmerkategorien, Saisonpreise, Verpflegung und zugehörige Leistungen zentral verwalten.</span></div><a class="primary-button" href="item-edit.php?hotel=1">＋ Neues Hotel</a></div>
    <form class="table-toolbar hotel-filter" method="get"><input name="q" value="<?= e($search) ?>" placeholder="Name, Ort, Region, Kategorie, Lieferant oder ID …"><select name="country"><option value="">Alle Länder</option><option value="LK"<?= selected('LK',$country) ?>>Sri Lanka</option><option value="MV"<?= selected('MV',$country) ?>>Malediven</option></select><select name="status"><option value="">Alle Status</option><option value="active"<?= selected('active',$status) ?>>Aktiv</option><option value="archived"<?= selected('archived',$status) ?>>Archiviert</option></select><button type="submit">Suchen</button><?php if($search!==''||$country!==''||$status!==''):?><a class="filter-reset" href="hotels.php">Zurücksetzen</a><?php endif;?><span><?= count($hotels) ?> Hotels gefunden</span></form>
    <section class="admin-card"><div class="admin-table-wrap"><table class="admin-table hotel-admin-table"><thead><tr><th>Bild</th><th>Hotel</th><th>Ort & Kategorie</th><th>Zimmer</th><th>Preis ab</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
    <?php foreach($hotels as $hotel):?><tr>
        <td><?php if($hotel['image_path']):?><img class="admin-table-thumb" src="<?= e(url($hotel['image_path'])) ?>" alt=""><?php else:?><span class="admin-table-placeholder">⌂</span><?php endif;?></td>
        <td><strong><?= e($hotel['name_de']) ?></strong><small><?= e($hotel['name_en']) ?><?= $hotel['star_rating']?' · '.(int)$hotel['star_rating'].'★':'' ?></small></td>
        <td><?= e($hotel['country_code']==='MV'?'Malediven · ':'Sri Lanka · ') ?><?= e($hotel['destination_name']) ?><small><?= e($hotel['classification_name']?:($hotel['market_segment']?:'Nicht klassifiziert')) ?></small></td>
        <td><strong><?= (int)$hotel['room_types'] ?> Kategorien</strong><small>Zimmer & Saisonpreise</small></td>
        <td><strong><?= e(money($hotel['room_rate_from'] ?? $hotel['price_per_person'])) ?></strong><small>pro Zimmer / Nacht</small></td>
        <td><span class="status <?= $hotel['active']?'confirmed':'archived' ?>"><?= $hotel['active']?'Aktiv':'Archiviert' ?></span></td>
        <td><div class="hotel-row-actions"><a href="item-edit.php?id=<?= (int)$hotel['id'] ?>&hotel=1">Hotel bearbeiten</a><a href="hotel-rooms.php?hotel_id=<?= (int)$hotel['id'] ?>">Zimmer & Leistungen</a><form method="post"><?= csrf_field() ?><input type="hidden" name="hotel_id" value="<?= (int)$hotel['id'] ?>"><button name="action" value="toggle" type="submit"><?= $hotel['active']?'Archivieren':'Aktivieren' ?></button><button class="danger-link" name="action" value="delete" type="submit" onclick="return confirm('Hotel endgültig löschen? Bereits verwendete Hotels werden nur archiviert.')">Löschen</button></form></div></td>
    </tr><?php endforeach;?>
    <?php if(!$hotels):?><tr><td colspan="7"><div class="empty small">Keine Hotels für diesen Filter gefunden.</div></td></tr><?php endif;?>
    </tbody></table></div></section>
</div>
<?php require __DIR__.'/_footer.php';?>
