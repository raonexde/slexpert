<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$atollId = max(0,(int)($_GET['atoll']??0));
$sql = "SELECT c.*,d.name_de AS atoll_de,d.name_en AS atoll_en,d.intro_de AS atoll_intro_de,d.intro_en AS atoll_intro_en,d.code AS atoll_code,d.accent
    FROM catalog_items c JOIN destinations d ON d.id=c.destination_id
    WHERE c.type='accommodation' AND c.active=1 AND c.maldives_available=1 AND d.active=1 AND d.country_code='MV'";
$params=[];
if($atollId>0){$sql.=' AND d.id=?';$params[]=$atollId;}
$sql.=' ORDER BY c.featured DESC,d.sort_order,c.sort_order,c.name_en';
$stmt=db()->prepare($sql);$stmt->execute($params);$resorts=$stmt->fetchAll();
$atolls=db()->query("SELECT d.id,d.name_de,d.name_en,d.intro_de,d.intro_en,d.image_path,COUNT(c.id) resort_count FROM destinations d JOIN catalog_items c ON c.destination_id=d.id AND c.type='accommodation' AND c.active=1 AND c.maldives_available=1 WHERE d.country_code='MV' AND d.active=1 GROUP BY d.id ORDER BY d.sort_order")->fetchAll();
$totalResorts=array_sum(array_map(static fn(array $atoll):int=>(int)$atoll['resort_count'],$atolls));

$pageTitle=t('Malediven-Resorts','Maldives resorts');
$bodyClass='maldives-page';
$extraStyles=['css/maldives.css'];
require __DIR__.'/includes/public-header.php';
?>
<main>
    <section class="maldives-hero"><div><p class="eyebrow"><?= e(t('MALEDIVEN','MALDIVES')) ?> · <?= (int)$totalResorts ?> <?= e(t('EDITIERBARE RESORTS','EDITABLE RESORTS')) ?></p><h1><?= e(t('Ihre Insel. Ihr Rhythmus.','Your island. Your pace.')) ?></h1><p><?= e(t('Wählen Sie ein Resort, Zimmer und Verpflegung. Transfers, Schnorcheln und Ausflüge können separat ergänzt werden.','Choose one resort, room and meal plan. Add transfers, snorkelling and excursions separately.')) ?></p><a class="button button-gold" href="<?= e(url('stay.php?type=maldives&lang='.lang())) ?>"><?= e(t('Malediven-Urlaub planen','Plan a Maldives holiday')) ?> <span>→</span></a></div></section>
    <nav class="maldives-filter" aria-label="<?= e(t('Atolle','Atolls')) ?>"><a class="<?= $atollId===0?'active':'' ?>" href="<?= e(url('maldives.php?lang='.lang())) ?>"><?= e(t('Alle Resorts','All resorts')) ?></a><?php foreach($atolls as $atoll):?><a class="<?= $atollId===(int)$atoll['id']?'active':'' ?>" href="<?= e(url('maldives.php?atoll='.(int)$atoll['id'].'&lang='.lang())) ?>"><?= e($atoll['name_'.lang()]) ?> <b><?= (int)$atoll['resort_count'] ?></b></a><?php endforeach;?></nav>
    <?php if($atollId===0):?><section class="atoll-visuals"><div class="section-heading"><div><p class="eyebrow dark"><?= e(t('ATOLLE ENTDECKEN','DISCOVER THE ATOLLS')) ?></p><h2><?= e(t('Neun Inselwelten zur Auswahl.','Nine island worlds to choose from.')) ?></h2></div></div><div class="atoll-visual-grid"><?php foreach($atolls as $atoll):?><a href="<?= e(url('maldives.php?atoll='.(int)$atoll['id'].'&lang='.lang())) ?>"<?= $atoll['image_path']?' style="background-image:linear-gradient(rgba(5,45,50,.12),rgba(5,45,50,.78)),url('.e(url($atoll['image_path'])).')"':'' ?>><small><?= (int)$atoll['resort_count'] ?> <?= e(t('Resorts','resorts')) ?></small><strong><?= e($atoll['name_'.lang()]) ?></strong><span><?= e($atoll['intro_'.lang()]) ?></span></a><?php endforeach;?></div></section><?php endif;?>
    <section class="maldives-catalog"><div class="section-heading"><div><p class="eyebrow dark"><?= e(t('INSELRESORTS','ISLAND RESORTS')) ?></p><h2><?= e(t('Von entspannt bis außergewöhnlich.','From relaxed to exceptional.')) ?></h2></div><p><?= e(t('Alle Preise sind editierbare Richtwerte pro Doppelzimmer und Nacht. Die endgültige Rate wird nach Verfügbarkeit bestätigt.','All prices are editable guide rates per double room and night. The final rate is confirmed after checking availability.')) ?></p></div>
        <?php if(!$resorts):?><div class="maldives-empty"><?= e(t('In diesem Atoll sind noch keine Resorts veröffentlicht.','No resorts are published in this atoll yet.')) ?></div><?php endif;?>
        <div class="resort-grid"><?php foreach($resorts as $resort):?><article class="resort-card"><a class="resort-image accent-<?= e($resort['accent']) ?>" href="<?= e(url('resort.php?id='.(int)$resort['id'].'&lang='.lang())) ?>"<?= $resort['image_path']?' style="background-image:linear-gradient(rgba(8,43,48,.08),rgba(8,43,48,.67)),url('.e(url($resort['image_path'])).')"':'' ?>><span><?= e($resort['atoll_code']) ?></span><?php if($resort['featured']):?><b><?= e(t('EMPFOHLEN','FEATURED')) ?></b><?php endif;?></a><div><p><?= e($resort['atoll_'.lang()]) ?></p><h2><a href="<?= e(url('resort.php?id='.(int)$resort['id'].'&lang='.lang())) ?>"><?= e($resort['name_'.lang()]) ?></a></h2><span><?= e($resort['description_'.lang()]) ?></span><small><?= e($resort['meta_'.lang()]) ?></small><footer><strong><?= e(t('ab','from')) ?> <?= e(money(price_with_markup($resort['price_per_person']))) ?></strong><em><?= e(t('Zimmer / Nacht','room / night')) ?></em><a href="<?= e(url('resort.php?id='.(int)$resort['id'].'&lang='.lang())) ?>"><?= e(t('Details','Details')) ?> →</a></footer></div></article><?php endforeach;?></div>
    </section>
</main>
<?php require __DIR__.'/includes/public-footer.php';?>
