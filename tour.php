<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$slug = substr(trim((string)($_GET['slug'] ?? '')), 0, 160);
$stmt = db()->prepare('SELECT * FROM tour_templates WHERE slug=? AND (active=1 OR ?=1) LIMIT 1');
$stmt->execute([$slug, admin_user() ? 1 : 0]);
$tour = $stmt->fetch();
if (!$tour) { http_response_code(404); $pageTitle=t('Reise nicht gefunden','Tour not found'); require __DIR__.'/includes/public-header.php'; echo '<main class="success-page"><section class="success-card"><h1>'.e($pageTitle).'</h1><a class="button button-dark" href="'.e(url('tours.php?lang='.lang())).'">'.e(t('Zu den Reisen','View tours')).'</a></section></main>'; require __DIR__.'/includes/public-footer.php'; exit; }

$stopStmt = db()->prepare('SELECT s.*,d.name_de AS destination_name_de,d.name_en AS destination_name_en,d.region_de,d.region_en,d.latitude,d.longitude,d.accent,d.image_path AS destination_image FROM tour_template_stops s JOIN destinations d ON d.id=s.destination_id WHERE s.tour_template_id=? ORDER BY s.sort_order,s.id');
$stopStmt->execute([$tour['id']]);
$stops = $stopStmt->fetchAll();
$itemsByStop = [];
if ($stops) {
    $stopIds = array_column($stops,'id');
    $placeholders = implode(',',array_fill(0,count($stopIds),'?'));
    $itemStmt = db()->prepare("SELECT tsi.tour_template_stop_id,tsi.included,c.id,c.type,c.name_de,c.name_en,c.description_de,c.description_en FROM tour_template_stop_items tsi JOIN catalog_items c ON c.id=tsi.catalog_item_id WHERE tsi.tour_template_stop_id IN ($placeholders) AND c.active=1 ORDER BY tsi.sort_order,tsi.id");
    $itemStmt->execute($stopIds);
    foreach ($itemStmt->fetchAll() as $item) $itemsByStop[(int)$item['tour_template_stop_id']][]=$item;
}
$priceStmt=db()->prepare('SELECT * FROM tour_template_prices WHERE tour_template_id=? AND active=1 ORDER BY sort_order,id');
$priceStmt->execute([$tour['id']]);
$prices=$priceStmt->fetchAll();
$settings = db()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('google_maps_api_key','google_maps_map_id')")->fetchAll(PDO::FETCH_KEY_PAIR);
$categoryLabels=['winter'=>t('Winterurlaub','Winter holiday'),'summer'=>t('Sommerurlaub','Summer holiday'),'sport'=>t('Sport & Abenteuer','Sport & adventure'),'culture'=>t('Kultur','Culture'),'discovery'=>t('Kennenlernen','Discovery'),'backpacker'=>t('Backpacker','Backpacker'),'ayurveda'=>t('Ayurveda & Rundreise','Ayurveda & touring'),'family'=>t('Familie','Family'),'luxury'=>t('Luxus','Luxury')];
$typeLabels=['accommodation'=>t('Unterkunft','Accommodation'),'sight'=>t('Sehenswürdigkeit','Sight'),'activity'=>t('Aktivität','Activity'),'shop'=>t('Lokaler Shop','Local shop'),'service'=>t('Zusatzleistung','Additional service')];
$pageTitle=$tour['title_'.lang()];
$bodyClass='tour-detail-page';
$extraStyles=['css/tours.css'];
require __DIR__.'/includes/public-header.php';
$heroImage=$tour['image_path']?url($tour['image_path']):asset('images/hero.jpg');
?>
<main>
    <section class="tour-detail-hero" style="background-image:linear-gradient(90deg,rgba(7,35,27,.94),rgba(7,35,27,.4)),url('<?= e($heroImage) ?>')">
        <div><p class="eyebrow"><?= e($categoryLabels[$tour['category']]??$tour['category']) ?> · <?= e($tour['eyebrow_'.lang()]) ?></p><h1><?= e($tour['title_'.lang()]) ?></h1><span><?= e($tour['intro_'.lang()]) ?></span>
        <div class="tour-hero-actions"><a class="button button-gold" href="<?= e(url('plan.php?tour='.(int)$tour['id'].'&lang='.lang())) ?>"><?= e(t('Diese Reise anpassen','Customise this tour')) ?> <b>→</b></a><button class="quiet-print" type="button" onclick="window.print()"><?= e(t('Reise drucken','Print tour')) ?> ⌁</button></div></div>
        <aside><div><small><?= e(t('Dauer','Duration')) ?></small><strong><?= (int)$tour['duration_nights'] ?> <?= e(t('Nächte','nights')) ?></strong></div><div><small><?= e(t('Saison','Season')) ?></small><strong><?= e($tour['season_'.lang()]) ?></strong></div><div><small><?= e(t('Reisestil','Travel style')) ?></small><strong><?= e($tour['difficulty_'.lang()]) ?></strong></div><div><small><?= e(t('Richtpreis ab','Guide price from')) ?></small><strong><?= e(money(price_with_markup($tour['price_from']))) ?> <?= e(t('p. P.','p.p.')) ?></strong></div></aside>
    </section>
    <section class="tour-detail-intro"><p class="eyebrow dark"><?= e(t('Fertige Route · vollständig anpassbar','Ready route · fully customisable')) ?></p><div><h2><?= e(t('Ein guter Ausgangspunkt für Ihre persönliche Reise.','A strong starting point for your personal journey.')) ?></h2><div><?= nl2br(e($tour['description_'.lang()])) ?></div></div></section>
    <section class="tour-detail-layout">
        <div class="tour-itinerary">
            <div class="tour-section-heading"><p class="eyebrow dark"><?= e(t('Reiseverlauf','Journey')) ?></p><h2><?= e(t('Ihre Route Tag für Tag.','Your route, stage by stage.')) ?></h2></div>
            <ol>
                <?php foreach ($stops as $index=>$stop): ?>
                    <li><span class="tour-stop-number"><?= $index+1 ?></span><div class="tour-stop-copy"><p><?= e(t('Tag','Day')) ?> <?= (int)$stop['day_start'] ?><?= (int)$stop['day_end']>(int)$stop['day_start']?'–'.(int)$stop['day_end']:'' ?> · <?= (int)$stop['nights'] ?> <?= e((int)$stop['nights']===1?t('Nacht','night'):t('Nächte','nights')) ?></p><h3><?= e($stop['title_'.lang()]?:$stop['destination_name_'.lang()]) ?></h3><span><?= e($stop['description_'.lang()]) ?></span>
                    <?php if (!empty($itemsByStop[(int)$stop['id']])): ?><ul><?php foreach ($itemsByStop[(int)$stop['id']] as $item): ?><li><small><?= e($typeLabels[$item['type']]??$item['type']) ?></small><strong><?= e($item['name_'.lang()]) ?></strong></li><?php endforeach; ?></ul><?php endif; ?>
                    </div><b class="tour-stop-code"><?= e($stop['destination_name_'.lang()]) ?></b></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <aside class="tour-route-side">
            <section class="tour-map-card"><div><p>GOOGLE MAPS</p><h2><?= e(t('Die vorgeschlagene Route','The suggested route')) ?></h2></div><div class="tour-map-canvas" data-tour-map></div><p data-tour-map-status><?= e(t('Karte wird vorbereitet …','Preparing map…')) ?></p></section>
            <?php if ($prices): ?><section class="tour-prices"><h3><?= e(t('Saisonpreise','Seasonal prices')) ?></h3><?php foreach($prices as $price): ?><div><span><?= e($price['label_'.lang()]) ?><small><?= (int)$price['min_travelers'] ?>–<?= (int)$price['max_travelers'] ?> <?= e(t('Reisende','travellers')) ?></small></span><strong><?= e(money(price_with_markup($price['price_per_person']))) ?> <?= e(t('p. P.','p.p.')) ?></strong></div><?php endforeach; ?></section><?php endif; ?>
        </aside>
    </section>
    <section class="tour-inclusions"><article><p class="eyebrow dark"><?= e(t('Enthalten','Included')) ?></p><h2><?= e(t('Was bereits vorgesehen ist.','What is already planned.')) ?></h2><ul><?php foreach(text_lines($tour['includes_'.lang()]) as $line): ?><li>✓ <?= e($line) ?></li><?php endforeach; ?></ul></article><article><p class="eyebrow dark"><?= e(t('Nicht enthalten','Not included')) ?></p><h2><?= e(t('Was separat bleibt.','What remains separate.')) ?></h2><ul><?php foreach(text_lines($tour['excludes_'.lang()]) as $line): ?><li>— <?= e($line) ?></li><?php endforeach; ?></ul></article></section>
    <section class="tour-customise-cta"><div><p class="eyebrow"><?= e(t('Ihre Reise beginnt hier','Your journey begins here')) ?></p><h2><?= e(t('Übernehmen Sie die Route und ändern Sie alles, was zu Ihnen passen soll.','Use this route and change everything that should suit you.')) ?></h2></div><a class="button button-gold" href="<?= e(url('plan.php?tour='.(int)$tour['id'].'&lang='.lang())) ?>"><?= e(t('Tour jetzt anpassen','Customise tour now')) ?> <b>→</b></a></section>
</main>
<script>window.TOUR_MAP_DATA=<?= json_encode(['language'=>lang(),'apiKey'=>trim((string)($settings['google_maps_api_key']??'')),'mapId'=>trim((string)($settings['google_maps_map_id']??''))?:'DEMO_MAP_ID','points'=>array_map(static fn(array $stop):array=>['name'=>$stop['destination_name_'.lang()],'lat'=>$stop['latitude']!==null?(float)$stop['latitude']:null,'lng'=>$stop['longitude']!==null?(float)$stop['longitude']:null],$stops),'labels'=>['missing'=>t('Google Maps ist noch nicht eingerichtet.','Google Maps is not configured yet.'),'error'=>t('Die Route konnte nicht geladen werden.','The route could not be loaded.')]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('js/tour-map.js')) ?>"></script>
<?php require __DIR__.'/includes/public-footer.php'; ?>
