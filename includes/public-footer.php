<?php
$footerBrand = site_setting('brand_name', 'Sri Lanka Expert');
$footerByline = site_setting('brand_byline', 'by Raonex GmbH');
$companyName = site_setting('company_name', 'Raonex GmbH');
$companyAddress = site_setting('company_address', 'August-Bebel-Strasse 26c');
$companyCity = site_setting('company_postcode_city', '97297 Waldbüttelbrunn');
$companyPhone = site_setting('company_phone', '+49 931 80472297');
$companyEmail = site_setting('company_email', 'info@raonex.de');
$companyWeb = site_setting('company_web', 'https://www.srilankaexpert.de');
$socials = [
    'Instagram' => site_setting('social_instagram'),
    'Facebook' => site_setting('social_facebook'),
    'TikTok' => site_setting('social_tiktok'),
    'YouTube' => site_setting('social_youtube'),
];
?>
<footer class="public-footer" id="about">
    <div class="footer-brand-block">
        <a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">SL</span><span><strong><?= e(strtoupper($footerBrand)) ?></strong><small><?= e(strtoupper($footerByline)) ?></small></span></a>
        <p><?= e(t('Individuelle Sri-Lanka-Reisen, Ayurveda und ausgewählte Malediven-Resorts – persönlich geplant.', 'Tailor-made Sri Lanka journeys, Ayurveda and selected Maldives resorts – personally planned.')) ?></p>
    </div>
    <div class="footer-company">
        <strong><?= e($companyName) ?></strong>
        <address><?= e($companyAddress) ?><br><?= e($companyCity) ?></address>
        <a href="tel:<?= e(preg_replace('/[^+0-9]/', '', $companyPhone)) ?>"><?= e($companyPhone) ?></a>
        <a href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?></a>
        <a href="<?= e($companyWeb) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $companyWeb)) ?></a>
    </div>
    <div class="footer-links">
        <nav>
            <a href="<?= e(url('tours.php?lang='.lang())) ?>"><?= e(t('Reiseideen', 'Tour ideas')) ?></a>
            <a href="<?= e(url('plan.php?lang='.lang())) ?>"><?= e(t('Rundreise planen', 'Plan a tour')) ?></a>
            <a href="<?= e(url('stay.php?type=beach&lang='.lang())) ?>"><?= e(t('Strandurlaub','Beach vacation')) ?></a>
            <a href="<?= e(url('stay.php?type=ayurveda&lang='.lang())) ?>"><?= e(t('Ayurveda','Ayurveda')) ?></a>
            <a href="<?= e(url('maldives.php?lang='.lang())) ?>"><?= e(t('Malediven','Maldives')) ?></a>
            <a href="<?= e(url('guide-register.php?lang='.lang())) ?>"><?= e(t('Reiseleiter registrieren','Guide registration')) ?></a>
            <a href="<?= e(url((portal_user()?'account/index.php':'account/login.php').'?lang='.lang())) ?>"><?= e(t('Kundenkonto','Customer account')) ?></a>
            <a href="<?= e(url('admin/login.php')) ?>">Admin</a>
        </nav>
        <div class="social-links">
            <?php foreach ($socials as $label => $link): if ($link === '') continue; ?>
                <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <span>© <?= date('Y') ?> <?= e($companyName) ?></span>
    </div>
</footer>
<script src="<?= e(asset('js/site.js')) ?>"></script>
</body>
</html>
