<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_portal_user();
$user = portal_user();
$isAgent = $user['user_type'] === 'agent';
$requestWhere = $isAgent ? 'r.b2b_agent_id=?' : 'r.customer_user_id=?';
$ownerId = $isAgent ? (int)$user['b2b_agent_id'] : (int)$user['id'];
$bookingWhere = $isAgent ? 'b.b2b_agent_id=?' : 'b.customer_user_id=?';

$statsStmt = db()->prepare("SELECT COUNT(*) total, SUM(status='quoted') quoted, SUM(status='confirmed') confirmed FROM tour_requests r WHERE $requestWhere");
$statsStmt->execute([$ownerId]);
$stats = $statsStmt->fetch() ?: ['total'=>0,'quoted'=>0,'confirmed'=>0];
$bookingStats = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM(total_amount),0) value, COALESCE(SUM(paid_amount),0) paid FROM bookings b WHERE $bookingWhere AND status<>'cancelled'");
$bookingStats->execute([$ownerId]);
$bookingSummary = $bookingStats->fetch() ?: ['total'=>0,'value'=>0,'paid'=>0];

$requestStmt = db()->prepare(
    "SELECT r.*,b.id AS booking_id,b.booking_reference,b.status AS booking_status,b.payment_status,b.total_amount AS booking_total
     FROM tour_requests r LEFT JOIN bookings b ON b.request_id=r.id
     WHERE $requestWhere ORDER BY r.created_at DESC LIMIT 100"
);
$requestStmt->execute([$ownerId]);
$requests = $requestStmt->fetchAll();
$requestTypeLabels=['tour'=>t('Rundreise','Tour'),'beach'=>t('Strandurlaub','Beach vacation'),'ayurveda'=>t('Ayurveda','Ayurveda'),'maldives'=>t('Malediven','Maldives')];
$requestStatusLabels=['new'=>t('Neu','New'),'contacted'=>t('In Bearbeitung','In progress'),'quoted'=>t('Angebot','Proposal'),'confirmed'=>t('Bestätigt','Confirmed'),'cancelled'=>t('Storniert','Cancelled'),'archived'=>t('Archiviert','Archived')];
$bookingStatusLabels=['provisional'=>t('Vorläufig gebucht','Provisionally booked'),'confirmed'=>t('Bestätigt gebucht','Booking confirmed'),'in_progress'=>t('Reise läuft','Trip in progress'),'completed'=>t('Abgeschlossen','Completed'),'cancelled'=>t('Buchung storniert','Booking cancelled')];
$pageTitle = $isAgent ? t('Agentur-Portal','Agent portal') : t('Mein Reisekonto','My travel account');
$bodyClass = 'account-page';
$extraStyles = ['css/account.css'];
require dirname(__DIR__) . '/includes/public-header.php';
$accountPage='dashboard'; require __DIR__ . '/_nav.php';
?>
<main class="account-main">
    <section class="account-heading"><div><p><?= e($isAgent?t('B2B-AGENTUR','B2B AGENCY'):t('KUNDENKONTO','CUSTOMER ACCOUNT')) ?></p><h1><?= e(t('Willkommen, ','Welcome, ').explode(' ',$user['name'])[0]) ?>.</h1><span><?= e($isAgent?($user['company_name']??''):t('Hier finden Sie Ihre Anfragen, Buchungen und Reisedokumente.','Find your enquiries, bookings and travel documents here.')) ?></span></div><a class="account-primary inline" href="<?= e(url('plan.php?lang='.lang())) ?>"><?= e(t('Neue Reise planen','Plan a new trip')) ?> →</a></section>
    <section class="account-stat-grid">
        <article><small><?= e(t('Anfragen','Enquiries')) ?></small><strong><?= (int)$stats['total'] ?></strong><span><?= e(t('alle Reiseideen','all travel ideas')) ?></span></article>
        <article><small><?= e(t('Angebote','Proposals')) ?></small><strong><?= (int)$stats['quoted'] ?></strong><span><?= e(t('zur Prüfung','to review')) ?></span></article>
        <article><small><?= e(t('Buchungen','Bookings')) ?></small><strong><?= (int)$bookingSummary['total'] ?></strong><span><?= e(t('aktiv und abgeschlossen','active and completed')) ?></span></article>
        <article><small><?= e(t('Offener Betrag','Outstanding')) ?></small><strong><?= e(money_precise(max(0,(float)$bookingSummary['value']-(float)$bookingSummary['paid']))) ?></strong><span><?= e(t('gemäß Buchungsstand','according to bookings')) ?></span></article>
    </section>
    <section class="account-card">
        <header><div><h2><?= e($isAgent?t('Kundenreisen & Buchungen','Client journeys & bookings'):t('Meine Reisen & Buchungen','My journeys & bookings')) ?></h2><p><?= e(t('Aktueller Bearbeitungs-, Buchungs- und Zahlungsstand.','Current enquiry, booking and payment status.')) ?></p></div></header>
        <?php if (!$requests): ?><div class="account-empty"><span>◇</span><h3><?= e(t('Noch keine Reisen','No journeys yet')) ?></h3><p><?= e(t('Planen Sie Ihre erste Reise oder wählen Sie einen Hotelaufenthalt.','Plan your first trip or choose a hotel stay.')) ?></p></div>
        <?php else: ?><div class="account-table-wrap"><table class="account-table"><thead><tr><th><?= e(t('Kunde / Reise','Customer / trip')) ?></th><th><?= e(t('Referenz','Reference')) ?></th><th><?= e(t('Termin','Dates')) ?></th><th><?= e(t('Wert','Value')) ?></th><th><?= e(t('Status','Status')) ?></th><th></th></tr></thead><tbody>
        <?php foreach($requests as $request): $statusClass=$request['booking_reference']?match($request['booking_status']){'cancelled'=>'cancelled','provisional'=>'quoted','confirmed'=>'confirmed','in_progress'=>'contacted',default=>'archived'}:$request['status']; ?><tr><td><strong><?= e($isAgent?$request['customer_name']:($requestTypeLabels[$request['request_type']]??'Reise')) ?></strong><small><?= e($isAgent?($requestTypeLabels[$request['request_type']]??'Reise'):$request['customer_name']) ?> · <?= (int)$request['duration'] ?> <?= e(t('Nächte','nights')) ?></small></td><td><strong><?= e($request['booking_reference']?:$request['reference']) ?></strong><small><?= e($request['booking_reference']?t('Buchung','Booking'):t('Anfrage','Enquiry')) ?></small></td><td><?= $request['start_date']?e(date('d.m.Y',strtotime($request['start_date']))):e(t('Flexibel','Flexible')) ?></td><td><strong><?= e(money_precise($request['booking_reference']?$request['booking_total']:$request['estimate'])) ?></strong></td><td><span class="account-status <?= e($statusClass) ?>"><?= e($request['booking_reference']?($bookingStatusLabels[$request['booking_status']]??$request['booking_status']):($requestStatusLabels[$request['status']]??$request['status'])) ?></span></td><td><a href="<?= e(url($request['booking_id']?'account/booking.php?id='.(int)$request['booking_id']:'account/request.php?id='.(int)$request['id'])) ?>"><?= e(t('Öffnen','Open')) ?> →</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__) . '/includes/public-footer.php'; ?>
