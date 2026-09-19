<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$destinations = db()->query("SELECT * FROM destinations WHERE active = 1 AND country_code='LK' ORDER BY sort_order, name_de")->fetchAll();
$featuredTours = db()->query('SELECT * FROM tour_templates WHERE active=1 AND featured=1 ORDER BY sort_order,title_de LIMIT 3')->fetchAll();
$featuredTourStops = [];
if ($featuredTours) {
    $tourIds = array_column($featuredTours, 'id');
    $tourPlaceholders = implode(',', array_fill(0, count($tourIds), '?'));
    $tourStopStmt = db()->prepare("SELECT s.tour_template_id,d.name_de,d.name_en FROM tour_template_stops s JOIN destinations d ON d.id=s.destination_id WHERE s.tour_template_id IN ($tourPlaceholders) ORDER BY s.tour_template_id,s.sort_order,s.id");
    $tourStopStmt->execute($tourIds);
    foreach ($tourStopStmt->fetchAll() as $tourStop) $featuredTourStops[(int)$tourStop['tour_template_id']][] = $tourStop;
}
$featuredCodes = ['NEG', 'SIG', 'KAN', 'ELA', 'YAL', 'MIR', 'GAL', 'TRI'];
$destinationGroups = [
    'culture' => ['CMB','ANU','SIG','POL','KAN','JAF','MAN'],
    'nature' => ['WIL','MTL','HAT','NUE','ELA','HAP','KIT','SIN','UDA','YAL','GOY'],
    'beach' => ['NEG','KAL','WAD','BEN','HIK','GAL','KOG','MIR','MAT','TAN','ARU','BAT','PAS','TRI','MAN'],
    'ayurveda' => ['WAD','BEN','GAL','SIG','KOG'],
];
$pageTitle = t('Sri Lanka, genau nach Ihrem Rhythmus', 'Sri Lanka, entirely at your pace');
$bodyClass = 'home-page';
require __DIR__ . '/includes/public-header.php';
?>
<main>
    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="hero-copy">
            <p class="eyebrow"><?= e(t('Sri Lanka Expert · by Raonex GmbH', 'Sri Lanka Expert · by Raonex GmbH')) ?></p>
            <h1><?= e(t('Ihre Reise.', 'Your journey.')) ?><br><em><?= e(t('Ihr Sri Lanka.', 'Your Sri Lanka.')) ?></em></h1>
            <p><?= e(t(
                'Sri-Lanka-Rundreise, Strand, Ayurveda oder eine Malediven-Insel: Wir planen persönlich, transparent und passend zu Ihnen.',
                'Sri Lanka tour, beach or Ayurveda stay, or a Maldives island: choose what suits you. We plan personally and transparently.'
            )) ?></p>
            <div class="hero-actions">
                <a class="button button-gold" href="<?= e(url('plan.php?lang=' . lang())) ?>"><?= e(t('Reise zusammenstellen', 'Build your journey')) ?> <span>→</span></a>
                <a class="quiet-link" href="#places"><?= e(t('Inspiration entdecken', 'Explore inspiration')) ?> <span>↓</span></a>
            </div>
        </div>
        <form class="quick-planner" action="<?= e(url('plan.php')) ?>" method="get">
            <input type="hidden" name="lang" value="<?= e(lang()) ?>">
            <p class="mini-label">TAILOR-MADE TOUR</p>
            <h2><?= e(t('Starten Sie Ihre Rundreise.', 'Start your private tour.')) ?></h2>
            <p><?= e(t('Der erste Entwurf dauert nur wenige Minuten.', 'Your first outline takes only a few minutes.')) ?></p>
            <label>
                <span><?= e(t('Erster Reiseort', 'First destination')) ?></span>
                <select name="destination">
                    <?php foreach ($destinations as $destination): ?><option value="<?= (int)$destination['id'] ?>"><?= e($destination['name_' . lang()]) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?= e(t('Reisende', 'Travellers')) ?></span>
                <select name="travelers">
                    <?php for ($i = 1; $i <= 50; $i++): ?><option value="<?= $i ?>"<?= selected($i, 2) ?>><?= $i ?> <?= e(t('Personen', 'people')) ?></option><?php endfor; ?>
                </select>
            </label>
            <label>
                <span><?= e(t('Reisestil', 'Travel style')) ?></span>
                <select name="style">
                    <option value="culture"><?= e(t('Kultur & Genuss', 'Culture & food')) ?></option>
                    <option value="nature"><?= e(t('Natur & Safari', 'Nature & safari')) ?></option>
                    <option value="ayurveda"><?= e(t('Ayurveda & Ruhe', 'Ayurveda & calm')) ?></option>
                    <option value="family"><?= e(t('Familienreise', 'Family journey')) ?></option>
                </select>
            </label>
            <button class="button button-dark" type="submit"><?= e(t('Reise starten', 'Start planning')) ?> <span>→</span></button>
        </form>
    </section>

    <section class="trust-strip">
        <span>✓ <?= e(t('Persönliche Beratung aus Deutschland', 'Personal advice from Germany')) ?></span>
        <span>✓ <?= e(t('Lokales Team in Sri Lanka', 'Local team in Sri Lanka')) ?></span>
        <span>✓ <?= e(t('Preise transparent berechnet', 'Transparent pricing')) ?></span>
        <span>✓ <?= e(t('Route als PDF druckbar', 'Printable PDF route')) ?></span>
    </section>

    <section class="stay-choice-section" id="stays">
        <div class="section-heading"><div><p class="eyebrow dark"><?= e(t('Vier Wege zu Ihrer Reise','Four ways to travel')) ?></p><h2><?= e(t('Was möchten Sie erleben?','How would you like to travel?')) ?></h2></div><p><?= e(t('Planen Sie eine Sri-Lanka-Route oder wählen Sie einen Strand-, Ayurveda- oder Malediven-Aufenthalt.','Build a Sri Lanka route or choose a beach, Ayurveda or Maldives stay.')) ?></p></div>
        <div class="stay-choice-grid four">
            <article class="stay-choice-card tour"><div><p>TAILOR-MADE TOUR</p><h3><?= e(t('Private Rundreise','Private tour')) ?></h3><span><?= e(t('Mehrere Orte, frei wählbare Nächte, Hotels, Erlebnisse und ein passendes Fahrzeug.','Multiple destinations, flexible nights, hotels, experiences and the right vehicle.')) ?></span></div><a href="<?= e(url('plan.php?lang='.lang())) ?>"><?= e(t('Rundreise planen','Plan a tour')) ?> <b>→</b></a></article>
            <article class="stay-choice-card beach"><div><p>BEACH VACATION</p><h3><?= e(t('Strandurlaub','Beach vacation')) ?></h3><span><?= e(t('Ein Strandhotel, flexible Nächte, Zimmer, Verpflegung, Transfers und Ausflüge.','One beach hotel, flexible nights, rooms, meals, transfers and excursions.')) ?></span></div><a href="<?= e(url('stay.php?type=beach&lang='.lang())) ?>"><?= e(t('Strandhotel wählen','Choose a beach hotel')) ?> <b>→</b></a></article>
            <article class="stay-choice-card ayurveda"><div><p>AYURVEDA RETREAT</p><h3><?= e(t('Ayurveda-Aufenthalt','Ayurveda retreat')) ?></h3><span><?= e(t('Ein Ayurveda-Hotel, 7, 14, 21 oder 28 Nächte, All-inclusive und separat wählbare Ayurveda-Pakete.','One Ayurveda hotel, 7, 14, 21 or 28 nights, all-inclusive and separately selectable Ayurveda packages.')) ?></span></div><a href="<?= e(url('stay.php?type=ayurveda&lang='.lang())) ?>"><?= e(t('Ayurveda-Hotel wählen','Choose an Ayurveda hotel')) ?> <b>→</b></a></article>
            <article class="stay-choice-card maldives"><div><p>MALDIVES ISLANDS</p><h3><?= e(t('Malediven-Urlaub','Maldives holiday')) ?></h3><span><?= e(t('25 editierbare Resorts in neun Atollen mit Zimmer, Verpflegung, Transfer und Inselerlebnissen.','25 editable resorts across nine atolls with rooms, meals, transfers and island experiences.')) ?></span></div><a href="<?= e(url('maldives.php?lang='.lang())) ?>"><?= e(t('Resorts entdecken','Explore resorts')) ?> <b>→</b></a></article>
        </div>
    </section>

    <?php if ($featuredTours): ?><section class="home-tours">
        <div class="section-heading"><div><p class="eyebrow dark"><?= e(t('Fertige Reiseideen','Ready-made tour ideas')) ?></p><h2><?= e(t('Gut geplant. Frei veränderbar.','Well planned. Fully flexible.')) ?></h2></div><p><?= e(t('Beginnen Sie mit einer bewährten Route und passen Sie anschließend Orte, Nächte, Hotels und Erlebnisse an.','Start with a proven route, then change destinations, nights, hotels and experiences.')) ?></p></div>
        <div class="home-tour-grid">
            <?php foreach ($featuredTours as $featuredTour): $route=$featuredTourStops[(int)$featuredTour['id']]??[]; ?>
                <article><a class="home-tour-image" href="<?= e(url('tour.php?slug='.urlencode($featuredTour['slug']).'&lang='.lang())) ?>"<?= $featuredTour['image_path']?' style="background-image:linear-gradient(rgba(9,37,30,.15),rgba(9,37,30,.68)),url('.e(url($featuredTour['image_path'])).')"':'' ?>><span><?= (int)$featuredTour['duration_nights'] ?> <?= e(t('Nächte','nights')) ?></span></a><div><p><?= e($featuredTour['eyebrow_'.lang()]) ?></p><h3><?= e($featuredTour['title_'.lang()]) ?></h3><span><?= e($featuredTour['intro_'.lang()]) ?></span><small><?= e(implode(' · ',array_map(static fn(array $stop):string=>$stop['name_'.lang()],array_slice($route,0,4)))) ?><?= count($route)>4?' …':'' ?></small><a href="<?= e(url('tour.php?slug='.urlencode($featuredTour['slug']).'&lang='.lang())) ?>"><?= e(t('Reise ansehen','View tour')) ?> <b>→</b></a></div></article>
            <?php endforeach; ?>
        </div>
        <div class="home-tour-more"><a class="button button-dark" href="<?= e(url('tours.php?lang='.lang())) ?>"><?= e(t('Alle Reiseideen entdecken','Explore all tour ideas')) ?> <span>→</span></a></div>
    </section><?php endif; ?>

    <section class="places" id="places">
        <div class="section-heading">
            <div><p class="eyebrow dark"><?= e(t('Inspiration für Ihre Route', 'Inspiration for your route')) ?></p><h2><?= e(t('Entdecken Sie Sri Lanka.', 'Discover Sri Lanka.')) ?></h2></div>
            <p><?= e(t(
                'Beginnen Sie mit einer Region. Im Planer bestimmen Sie anschließend Unterkunft, Sehenswürdigkeiten, Aktivitäten und lokale Einkaufsstopps.',
                'Start with a region. Then choose your accommodation, sights, activities and local shopping stops in the planner.'
            )) ?></p>
        </div>
        <div class="destination-explorer">
            <label class="destination-search"><span><?= e(t('Reiseziel suchen', 'Search destinations')) ?></span><input type="search" data-place-search placeholder="<?= e(t('z. B. Sigiriya, Strand oder Nationalpark', 'e.g. Sigiriya, beach or national park')) ?>"></label>
            <div class="destination-filters" aria-label="<?= e(t('Reiseziele filtern', 'Filter destinations')) ?>">
                <button type="button" class="active" data-place-filter="all"><?= e(t('Empfohlen', 'Featured')) ?></button>
                <button type="button" data-place-filter="culture"><?= e(t('Kultur', 'Culture')) ?></button>
                <button type="button" data-place-filter="nature"><?= e(t('Natur & Safari', 'Nature & safari')) ?></button>
                <button type="button" data-place-filter="beach"><?= e(t('Strände', 'Beaches')) ?></button>
                <button type="button" data-place-filter="ayurveda">Ayurveda</button>
                <button type="button" data-place-filter="catalog"><?= e(t('Alle 32 Orte', 'All 32 places')) ?></button>
            </div>
        </div>
        <div class="destination-grid">
            <?php foreach ($destinations as $index => $destination): ?>
                <?php
                $categories = [];
                foreach ($destinationGroups as $group => $codes) if (in_array($destination['code'], $codes, true)) $categories[] = $group;
                $isFeatured = in_array($destination['code'], $featuredCodes, true);
                ?>
                <article class="destination-card<?= $isFeatured ? ' featured' : ' destination-hidden' ?>" data-place-card data-featured="<?= $isFeatured ? '1' : '0' ?>" data-groups="<?= e(implode(' ', $categories)) ?>" data-search="<?= e(strtolower($destination['code'].' '.$destination['name_de'].' '.$destination['name_en'].' '.$destination['region_de'].' '.$destination['region_en'].' '.$destination['intro_de'].' '.$destination['intro_en'])) ?>">
                    <div class="destination-image accent-<?= e($destination['accent']) ?>"<?= $destination['image_path'] ? ' style="background-image:linear-gradient(rgba(10,40,32,.15),rgba(10,40,32,.55)),url(' . e(url($destination['image_path'])) . ')"' : '' ?>>
                        <span><?= e(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span><strong><?= e($destination['code']) ?></strong>
                    </div>
                    <div class="destination-content">
                        <p class="card-region"><?= e($destination['region_' . lang()]) ?></p>
                        <h3><?= e($destination['name_' . lang()]) ?></h3>
                        <i></i>
                        <p><?= e($destination['intro_' . lang()]) ?></p>
                        <div class="card-meta"><span><b><?= (int)$destination['default_nights'] ?></b> <?= e(t('Nächte', 'nights')) ?></span><span><?= e(t('ab', 'from')) ?> <b><?= e(money(price_with_markup($destination['price_from']))) ?></b></span></div>
                        <a href="<?= e(url('plan.php?lang=' . lang() . '&destination=' . (int)$destination['id'])) ?>"><?= e(t('Zur Reise hinzufügen', 'Add to journey')) ?> <span>＋</span></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="destination-empty" data-place-empty hidden><?= e(t('Kein Reiseziel passt zu Ihrer Suche.', 'No destination matches your search.')) ?></p>
    </section>

    <section class="how" id="how">
        <div><p class="eyebrow"><?= e(t('Einfach persönlich', 'Simple and personal')) ?></p><h2><?= e(t('Vom Wunsch zur fertigen Reise.', 'From first wish to final journey.')) ?></h2></div>
        <div class="steps">
            <article><span>01</span><h3><?= e(t('Reise gestalten', 'Shape your trip')) ?></h3><p><?= e(t('Orte, Hotels und Erlebnisse ohne starres Paket wählen.', 'Choose places, hotels and experiences without a fixed package.')) ?></p></article>
            <article><span>02</span><h3><?= e(t('Lokal verfeinern', 'Refined locally')) ?></h3><p><?= e(t('Wir prüfen Wege, Saison, Verfügbarkeit und Ihre Wünsche.', 'We check routes, seasons, availability and your wishes.')) ?></p></article>
            <article><span>03</span><h3><?= e(t('Entspannt reisen', 'Travel at ease')) ?></h3><p><?= e(t('Sie erhalten Route, Fahrer, Kontakte und alle Bestätigungen.', 'Receive your route, driver, contacts and all confirmations.')) ?></p></article>
        </div>
    </section>
    <section class="home-cta">
        <p class="eyebrow"><?= e(t('Bereit für den ersten Entwurf?', 'Ready for your first outline?')) ?></p>
        <h2><?= e(t('Planen Sie Ihre Route – wir machen daraus eine Reise.', 'Build your route – we turn it into a journey.')) ?></h2>
        <a class="button button-gold" href="<?= e(url('plan.php?lang='.lang())) ?>"><?= e(t('Jetzt Reise planen', 'Start planning now')) ?> <span>→</span></a>
    </section>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
