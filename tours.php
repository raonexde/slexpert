<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$category = preg_replace('/[^a-z_]/', '', (string)($_GET['category'] ?? '')) ?: '';
$allowedCategories = ['winter','summer','sport','culture','discovery','backpacker','ayurveda','family','luxury'];
if (!in_array($category, $allowedCategories, true)) $category = '';
$sql = 'SELECT * FROM tour_templates WHERE active=1';
$params = [];
if ($category !== '') { $sql .= ' AND category=?'; $params[] = $category; }
$sql .= ' ORDER BY featured DESC,sort_order,title_de';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll();

$stopsByTour = [];
if ($tours) {
    $ids = array_column($tours, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stopStmt = db()->prepare("SELECT s.tour_template_id,s.nights,d.name_de,d.name_en FROM tour_template_stops s JOIN destinations d ON d.id=s.destination_id WHERE s.tour_template_id IN ($placeholders) ORDER BY s.tour_template_id,s.sort_order,s.id");
    $stopStmt->execute($ids);
    foreach ($stopStmt->fetchAll() as $stop) $stopsByTour[(int)$stop['tour_template_id']][] = $stop;
}
$categoryLabels = [
    'winter'=>t('Winterurlaub','Winter holiday'),'summer'=>t('Sommerurlaub','Summer holiday'),
    'sport'=>t('Sport & Abenteuer','Sport & adventure'),'culture'=>t('Kultur','Culture'),
    'discovery'=>t('Kennenlernen','Discovery'),'backpacker'=>t('Backpacker','Backpacker'),
    'ayurveda'=>t('Ayurveda & Rundreise','Ayurveda & touring'),'family'=>t('Familie','Family'),'luxury'=>t('Luxus','Luxury'),
];
$pageTitle = t('Fertige Reisen – individuell anpassbar', 'Ready-made tours – fully customisable');
$bodyClass = 'tours-page';
$extraStyles = ['css/tours.css'];
require __DIR__ . '/includes/public-header.php';
?>
<main>
    <section class="tour-catalog-hero">
        <p class="eyebrow"><?= e(t('Fertige Idee · Ihre persönliche Reise', 'A ready idea · your personal journey')) ?></p>
        <h1><?= e(t('Starten Sie mit einer Reise, die bereits gut geplant ist.', 'Start with a journey that is already thoughtfully planned.')) ?></h1>
        <p><?= e(t('Jede Route ist ein Vorschlag. Nächte, Hotels, Verpflegung, Erlebnisse und Fahrzeug können Sie anschließend vollständig verändern.', 'Every route is a starting point. You can then change nights, hotels, meals, experiences and vehicle completely.')) ?></p>
    </section>
    <nav class="tour-filter" aria-label="<?= e(t('Reisearten', 'Tour categories')) ?>">
        <a class="<?= $category===''?'active':'' ?>" href="<?= e(url('tours.php?lang='.lang())) ?>"><?= e(t('Alle Reisen','All tours')) ?></a>
        <?php foreach ($categoryLabels as $key=>$label): ?><a class="<?= $category===$key?'active':'' ?>" href="<?= e(url('tours.php?category='.$key.'&lang='.lang())) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <section class="tour-catalog-section">
        <?php if (!$tours): ?><div class="tour-empty"><h2><?= e(t('Noch keine Reise veröffentlicht.', 'No tour has been published yet.')) ?></h2></div><?php endif; ?>
        <div class="tour-card-grid">
            <?php foreach ($tours as $tour): $stops=$stopsByTour[(int)$tour['id']]??[]; ?>
                <article class="tour-card">
                    <a class="tour-card-image" href="<?= e(url('tour.php?slug='.urlencode($tour['slug']).'&lang='.lang())) ?>"<?= $tour['image_path']?' style="background-image:linear-gradient(180deg,rgba(8,39,31,.08),rgba(8,39,31,.7)),url('.e(url($tour['image_path'])).')"':'' ?>>
                        <span><?= e($categoryLabels[$tour['category']]??$tour['category']) ?></span>
                        <?php if ($tour['featured']): ?><b><?= e(t('EMPFOHLEN','FEATURED')) ?></b><?php endif; ?>
                    </a>
                    <div class="tour-card-copy">
                        <p><?= e($tour['eyebrow_'.lang()]) ?></p>
                        <h2><a href="<?= e(url('tour.php?slug='.urlencode($tour['slug']).'&lang='.lang())) ?>"><?= e($tour['title_'.lang()]) ?></a></h2>
                        <span><?= e($tour['intro_'.lang()]) ?></span>
                        <ul class="tour-route-preview">
                            <?php foreach (array_slice($stops,0,4) as $stop): ?><li><?= e($stop['name_'.lang()]) ?></li><?php endforeach; ?>
                            <?php if (count($stops)>4): ?><li>+<?= count($stops)-4 ?> <?= e(t('weitere','more')) ?></li><?php endif; ?>
                        </ul>
                        <div class="tour-card-meta"><span><strong><?= (int)$tour['duration_nights'] ?></strong> <?= e(t('Nächte','nights')) ?></span><span><?= e(t('ab','from')) ?> <strong><?= e(money(price_with_markup($tour['price_from']))) ?></strong> <?= e(t('p. P.','p.p.')) ?></span></div>
                        <a class="tour-card-link" href="<?= e(url('tour.php?slug='.urlencode($tour['slug']).'&lang='.lang())) ?>"><?= e(t('Reise ansehen','View tour')) ?> <b>→</b></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
