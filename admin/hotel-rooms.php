<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('hotels', request_is_post());
$hotelId=max(0,(int)($_GET['hotel_id']??$_POST['hotel_id']??0));
$hotelStmt=db()->prepare("SELECT c.*,d.name_de AS destination_name FROM catalog_items c JOIN destinations d ON d.id=c.destination_id WHERE c.id=? AND c.type='accommodation'");
$hotelStmt->execute([$hotelId]);$hotel=$hotelStmt->fetch();
if(!$hotel){flash('error','Hotel wurde nicht gefunden.');redirect('admin/hotels.php');}

if(request_is_post()){
    verify_csrf();$action=(string)($_POST['action']??'');
    if($action==='save_related'){
        $ids=array_values(array_unique(array_filter(array_map('intval',is_array($_POST['related_items']??null)?$_POST['related_items']:[]))));
        $pdo=db();$pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM hotel_related_items WHERE hotel_id=?')->execute([$hotelId]);
            $valid=[];
            if($ids){$placeholders=implode(',',array_fill(0,count($ids),'?'));$check=$pdo->prepare("SELECT id FROM catalog_items WHERE destination_id=? AND type<>'accommodation' AND id IN ($placeholders)");$check->execute(array_merge([(int)$hotel['destination_id']],$ids));$valid=array_map('intval',$check->fetchAll(PDO::FETCH_COLUMN));}
            $insert=$pdo->prepare('INSERT INTO hotel_related_items (hotel_id,catalog_item_id,sort_order) VALUES (?,?,?)');
            foreach($valid as $order=>$itemId)$insert->execute([$hotelId,$itemId,($order+1)*10]);
            $pdo->commit();flash('success','Zugehörige Leistungen wurden gespeichert.');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error','Leistungen konnten nicht gespeichert werden.');}
    }elseif(in_array($action,['toggle_room','delete_room'],true)){
        $roomId=max(0,(int)($_POST['room_id']??0));$roomStmt=db()->prepare('SELECT * FROM hotel_room_types WHERE id=? AND hotel_id=?');$roomStmt->execute([$roomId,$hotelId]);$room=$roomStmt->fetch();
        if($room){
            if($action==='toggle_room'){db()->prepare('UPDATE hotel_room_types SET active=? WHERE id=?')->execute([(int)$room['active']===1?0:1,$roomId]);flash('success',(int)$room['active']===1?'Zimmerkategorie wurde archiviert.':'Zimmerkategorie wurde aktiviert.');}
            else{$usedStmt=db()->prepare('SELECT COUNT(*) FROM tour_request_items WHERE room_type_id=?');$usedStmt->execute([$roomId]);if((int)$usedStmt->fetchColumn()>0){db()->prepare('UPDATE hotel_room_types SET active=0 WHERE id=?')->execute([$roomId]);flash('success','Die bereits gebuchte Zimmerkategorie wurde archiviert.');}else{db()->prepare('DELETE FROM hotel_room_types WHERE id=?')->execute([$roomId]);flash('success','Zimmerkategorie wurde gelöscht.');}}
        }
    }
    redirect('admin/hotel-rooms.php?hotel_id='.$hotelId);
}
$roomStmt=db()->prepare('SELECT r.*,COUNT(rr.id) AS seasonal_rates FROM hotel_room_types r LEFT JOIN hotel_room_rates rr ON rr.room_type_id=r.id WHERE r.hotel_id=? GROUP BY r.id ORDER BY r.sort_order,r.id');$roomStmt->execute([$hotelId]);$rooms=$roomStmt->fetchAll();
$serviceStmt=db()->prepare("SELECT c.id,c.type,c.name_de,c.name_en,c.price_per_person,c.active,cc.name_de AS classification_name FROM catalog_items c LEFT JOIN catalog_classifications cc ON cc.id=c.classification_id WHERE c.destination_id=? AND c.type<>'accommodation' ORDER BY c.type,c.sort_order,c.name_de");$serviceStmt->execute([(int)$hotel['destination_id']]);$services=$serviceStmt->fetchAll();
$selectedStmt=db()->prepare('SELECT catalog_item_id FROM hotel_related_items WHERE hotel_id=?');$selectedStmt->execute([$hotelId]);$selected=array_flip(array_map('intval',$selectedStmt->fetchAll(PDO::FETCH_COLUMN)));
$typeLabels=['sight'=>'Sehenswürdigkeit','activity'=>'Aktivität','restaurant'=>'Restaurant','spice_garden'=>'Gewürzgarten','shop'=>'Shop','service'=>'Zusatzleistung'];
$adminPage='hotels';$adminTitle='Zimmer & Leistungen';require __DIR__.'/_header.php';
?>
<div class="admin-content">
    <a class="back-admin" href="hotels.php">← Hotelverwaltung</a>
    <div class="page-heading compact"><div><p><?= e(strtoupper($hotel['destination_name'])) ?></p><h1><?= e($hotel['name_de']) ?></h1><span>Zimmerkategorien, Saisonpreise und Leistungen dieses Hotels verwalten.</span></div><div class="heading-actions"><a class="secondary-button" href="item-edit.php?id=<?= $hotelId ?>&hotel=1">Hotel bearbeiten</a><a class="primary-button" href="room-edit.php?hotel_id=<?= $hotelId ?>">＋ Neue Zimmerkategorie</a></div></div>
    <section class="admin-card"><div class="card-head"><div><h2>Zimmerkategorien</h2><p><?= count($rooms) ?> Kategorien · Preise immer pro Zimmer und Nacht</p></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Bild</th><th>Zimmer</th><th>Belegung</th><th>Basispreis</th><th>Saisonpreise</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
    <?php foreach($rooms as $room):?><tr><td><?php if($room['image_path']):?><img class="admin-table-thumb" src="<?= e(url($room['image_path'])) ?>" alt=""><?php else:?><span class="admin-table-placeholder">▥</span><?php endif;?></td><td><strong><?= e($room['name_de']) ?></strong><small><?= e($room['name_en']) ?> · <?= e($room['code']) ?></small></td><td><?= (int)$room['standard_guests'] ?> Standard<small>max. <?= (int)$room['max_guests'] ?> Gäste<?= $room['room_size_sqm']?' · '.(int)$room['room_size_sqm'].' m²':'' ?></small></td><td><strong><?= e(money($room['base_rate_per_room_night'])) ?></strong><small>pro Zimmer / Nacht</small></td><td><?= (int)$room['seasonal_rates'] ?> Preiszeiträume</td><td><span class="status <?= $room['active']?'confirmed':'archived' ?>"><?= $room['active']?'Aktiv':'Archiviert' ?></span></td><td><div class="hotel-row-actions"><a href="room-edit.php?id=<?= (int)$room['id'] ?>">Bearbeiten</a><form method="post"><?= csrf_field() ?><input type="hidden" name="hotel_id" value="<?= $hotelId ?>"><input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>"><button name="action" value="toggle_room"><?= $room['active']?'Archivieren':'Aktivieren' ?></button><button class="danger-link" name="action" value="delete_room" onclick="return confirm('Zimmerkategorie löschen?')">Löschen</button></form></div></td></tr><?php endforeach;?>
    <?php if(!$rooms):?><tr><td colspan="7"><div class="empty small">Noch keine Zimmerkategorie. Legen Sie den ersten Zimmertyp an.</div></td></tr><?php endif;?>
    </tbody></table></div></section>
    <form class="editor-form hotel-services-card" method="post"><?= csrf_field() ?><input type="hidden" name="hotel_id" value="<?= $hotelId ?>"><input type="hidden" name="action" value="save_related"><section class="admin-card form-card"><div class="card-head"><div><h2>Zugehörige Aktivitäten & Leistungen</h2><p>Nur ausgewählte Angebote werden bei diesem Hotel bevorzugt angeboten. Ohne Auswahl gelten weiterhin alle Angebote des Reiseziels.</p></div></div><div class="related-service-grid">
    <?php foreach($services as $service):?><label><input type="checkbox" name="related_items[]" value="<?= (int)$service['id'] ?>"<?= isset($selected[(int)$service['id']])?' checked':'' ?>><span><strong><?= e($service['name_de']) ?></strong><small><?= e($typeLabels[$service['type']]??$service['type']) ?><?= $service['classification_name']?' · '.e($service['classification_name']):'' ?> · <?= e(money($service['price_per_person'])) ?><?= !$service['active']?' · inaktiv':'' ?></small></span></label><?php endforeach;?>
    <?php if(!$services):?><p class="form-help">Für dieses Reiseziel sind noch keine Aktivitäten oder Zusatzleistungen angelegt.</p><?php endif;?>
    </div></section><div class="form-actions"><button class="primary-button" type="submit">Leistungen speichern</button></div></form>
</div>
<?php require __DIR__.'/_footer.php';?>
