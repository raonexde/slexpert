<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$reference = trim((string)($_GET['ref'] ?? ''));
if ($reference === '' || !hash_equals((string)($_SESSION['submitted_reference'] ?? ''), $reference)) {
    redirect('index.php');
}
$requestStmt = db()->prepare('SELECT request_type FROM tour_requests WHERE reference=?');
$requestStmt->execute([$reference]);
$requestType = (string)($requestStmt->fetchColumn() ?: 'tour');
$isHotelStay = in_array($requestType,['beach','ayurveda','maldives'],true);
$portalSuccessUser = portal_user();
$pageTitle = t('Anfrage erhalten', 'Enquiry received');
require __DIR__ . '/includes/public-header.php';
?>
<main class="success-page">
    <section class="success-card">
        <span class="success-check">✓</span>
        <p class="eyebrow dark"><?= e(t('VIELEN DANK', 'THANK YOU')) ?></p>
        <h1><?= e($isHotelStay?t('Ihre Buchungsanfrage ist angekommen.','Your booking request has arrived.'):t('Ihre Reiseidee ist angekommen.', 'Your journey idea has arrived.')) ?></h1>
        <p><?= e($isHotelStay?t('Wir prüfen das Hotel, die Verfügbarkeit und alle gewählten Zusatzleistungen persönlich und melden uns mit der Bestätigung.','We will personally check the hotel, availability and every selected additional service and contact you with confirmation.'):t('Wir prüfen Ihre Route, Unterkünfte und Erlebnisse persönlich und melden uns mit einem passenden Vorschlag.', 'We will personally review your route, stays and experiences and contact you with a tailored proposal.')) ?></p>
        <span class="success-ref"><?= e($reference) ?></span>
        <div class="success-actions"><a class="button button-gold" href="<?= e(url(($isHotelStay?'stay-proposal.php':'itinerary.php').'?ref=' . urlencode($reference) . '&lang=' . lang())) ?>"><?= e($isHotelStay?t('Aufenthalt anzeigen & drucken','View & print stay'):t('Route anzeigen & drucken', 'View & print route')) ?> <span>⌁</span></a><a class="button button-dark" href="<?= e(url(($portalSuccessUser?'account/index.php':'account/register.php').'?lang=' . lang())) ?>"><?= e($portalSuccessUser?t('Zum Reisekonto','Open travel account'):t('Konto erstellen & Anfrage speichern','Create account & save enquiry')) ?> <span>→</span></a></div>
    </section>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
