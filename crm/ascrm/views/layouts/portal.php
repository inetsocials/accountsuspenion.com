<?php
use DR\Core\App;
use DR\Core\Db;
use DR\Core\Settings;

$path = substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(App::basePath())) ?: '/';
$is = static fn(string $p, bool $exact = false): string => ($exact ? $path === $p : str_starts_with($path, $p)) ? ' class="active" aria-current="page"' : '';
$notes = (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [(int) $user['id']]);
include DR_ROOT . '/views/partials/head.php';
?>
<body>
<?php include DR_ROOT . '/views/partials/icons.php'; ?>
<a class="sr-only" href="#main">Skip to content</a>
<header class="portal-top">
  <div class="inner">
    <a href="<?= h(url('/client')) ?>"><img src="<?= h(asset('img/logo-light.png')) ?>" alt="<?= h(Settings::get('org_name')) ?>" width="236" height="26"></a>
    <nav class="portal-nav" aria-label="Portal">
      <a href="<?= h(url('/client')) ?>"<?= $is('/client', true) ?: $is('/client/cases') ?>><?= icon('home') ?>My cases</a>
      <a href="<?= h(url('/client/documents')) ?>"<?= $is('/client/documents') ?>><?= icon('folder') ?>Documents</a>
      <a href="<?= h(url('/client/invoices')) ?>"<?= $is('/client/invoices') ?>><?= icon('receipt') ?>Invoices</a>
      <a href="<?= h(url('/account')) ?>"<?= $is('/account') ?>><?= icon('shield') ?>Security</a>
    </nav>
    <div class="spacer"></div>
    <button type="button" class="icon-btn" data-palette-open aria-label="Search"><?= icon('search') ?></button>
    <a class="icon-btn" href="<?= h(url('/notifications')) ?>" aria-label="Notifications"><?= icon('bell') ?><?php if ($notes): ?><span class="dot"><?= $notes > 99 ? '99+' : $notes ?></span><?php endif; ?></a>
    <?php include DR_ROOT . '/views/partials/usermenu.php'; ?>
  </div>
</header>
<main id="main" class="portal-main">
<?= $content ?>
  <div class="privacy-strip mt-3">
    <span><?= icon('lock') ?>Your files are encrypted with a key unique to you</span>
    <span><?= icon('shield') ?>Two-step sign-in on every account</span>
    <span><?= icon('lock') ?>We never ask for your passwords or codes</span>
    <span><?= icon('pulse') ?>Every access to your file is logged</span>
  </div>
</main>
<?php include DR_ROOT . '/views/partials/flashes.php'; include DR_ROOT . '/views/partials/dialogs.php'; ?>
</body>
</html>
