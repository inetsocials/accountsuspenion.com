<?php
use DR\Core\Access;
use DR\Core\App;
use DR\Core\Auth;
use DR\Core\Db;
use DR\Core\Settings;

$path = substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(App::basePath())) ?: '/';
$is = static fn(string $p): string => ($p === '/' ? $path === '/' : str_starts_with($path, $p)) ? ' class="active" aria-current="page"' : '';
[$scope, $sp] = Access::caseScope($user);
$leadCount = (int) Db::value("SELECT COUNT(*) FROM cases c WHERE $scope AND c.status = 'lead'", $sp);
$emerg = (int) Db::value("SELECT COUNT(*) FROM cases c WHERE $scope AND c.status = 'lead' AND c.priority = 'emergency'", $sp);
$myTasks = (int) Db::value("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status = 'open'", [(int) $user['id']]);
$notes = (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [(int) $user['id']]);
$admin = Auth::atLeast($user, 'admin');
$lead = Auth::atLeast($user, 'lead');
include DR_ROOT . '/views/partials/head.php';
?>
<body>
<?php include DR_ROOT . '/views/partials/icons.php'; ?>
<a class="sr-only" href="#main">Skip to content</a>
<div class="shell">
  <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
  <aside class="sidebar" aria-label="Main navigation">
    <a class="brand" href="<?= h(url('/')) ?>"><span><img src="<?= h(asset('img/logo-light.png')) ?>" alt="<?= h(Settings::get('org_name')) ?>" width="236" height="26"><span class="brand-tag"><?= h(Settings::get('portal_name')) ?></span></span></a>
    <nav class="nav">
      <a href="<?= h(url('/')) ?>"<?= $is('/') ?>><?= icon('home') ?>Dashboard</a>
      <a href="<?= h(url('/leads')) ?>"<?= $is('/leads') ?>><?= icon('inbox') ?>Leads<?php if ($leadCount): ?><span class="count<?= $emerg ? ' hot' : '' ?>"><?= $leadCount ?></span><?php endif; ?></a>
      <a href="<?= h(url('/cases')) ?>"<?= $is('/cases') ?>><?= icon('briefcase') ?>Cases</a>
      <a href="<?= h(url('/clients')) ?>"<?= $is('/clients') ?>><?= icon('users') ?>Clients</a>
      <a href="<?= h(url('/tasks')) ?>"<?= $is('/tasks') ?>><?= icon('check-square') ?>Tasks<?php if ($myTasks): ?><span class="count"><?= $myTasks ?></span><?php endif; ?></a>
      <a href="<?= h(url('/calendar')) ?>"<?= $is('/calendar') ?>><?= icon('calendar') ?>Calendar</a>
      <a href="<?= h(url('/invoices')) ?>"<?= $is('/invoices') ?>><?= icon('receipt') ?>Invoices</a>
      <?php if ($lead): ?><a href="<?= h(url('/reports')) ?>"<?= $is('/reports') ?>><?= icon('chart') ?>Reports</a><?php endif; ?>
      <?php if ($lead): ?>
      <div class="nav-group">Website</div>
      <a href="<?= h(url('/cms/posts')) ?>"<?= $is('/cms/posts') ?>><?= icon('file') ?>Blog posts</a>
      <a href="<?= h(url('/cms/testimonials')) ?>"<?= $is('/cms/testimonials') ?>><?= icon('message') ?>Testimonials</a>
      <?php if ($admin): ?><a href="<?= h(url('/cms/redirects')) ?>"<?= $is('/cms/redirects') ?>><?= icon('link') ?>Redirects</a><?php endif; ?>
      <?php endif; ?>
      <?php if ($admin): ?>
      <div class="nav-group">Administration</div>
      <a href="<?= h(url('/admin/users')) ?>"<?= $is('/admin/users') ?>><?= icon('user') ?>Users and roles</a>
      <a href="<?= h(url('/admin/catalog')) ?>"<?= $is('/admin/catalog') ?>><?= icon('list') ?>Service catalog</a>
      <a href="<?= h(url('/admin/audit')) ?>"<?= $is('/admin/audit') ?>><?= icon('pulse') ?>Audit log</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'master'): ?>
      <a href="<?= h(url('/admin/settings')) ?>"<?= $is('/admin/settings') ?>><?= icon('sliders') ?>Settings</a>
      <a href="<?= h(url('/admin/security')) ?>"<?= $is('/admin/security') ?>><?= icon('shield') ?>Security and keys</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-foot"><?= icon('lock') ?> Encrypted per client. Every access is logged.<br>Version <?= h(DR_VERSION) ?></div>
  </aside>
  <label for="nav-toggle" class="scrim" aria-hidden="true"></label>
  <div class="main">
    <header class="topbar">
      <label for="nav-toggle" class="icon-btn hamburger" aria-label="Open menu"><?= icon('menu') ?></label>
      <div class="crumbs"><?= h($title ?? '') ?></div>
      <div class="spacer"></div>
      <button type="button" class="search-trigger" data-palette-open aria-label="Search"><?= icon('search') ?><span class="lbl">Search cases, clients, pages</span><kbd>Ctrl K</kbd></button>
      <a class="icon-btn" href="<?= h(url('/notifications')) ?>" aria-label="Notifications<?= $notes ? ' (' . $notes . ' unread)' : '' ?>"><?= icon('bell') ?><?php if ($notes): ?><span class="dot"><?= $notes > 99 ? '99+' : $notes ?></span><?php endif; ?></a>
      <button type="button" class="icon-btn" data-theme-toggle aria-label="Toggle dark mode"><?= icon('moon') ?></button>
      <?php include DR_ROOT . '/views/partials/usermenu.php'; ?>
    </header>
    <main id="main" class="content">
<?= $content ?>
    </main>
  </div>
</div>
<?php include DR_ROOT . '/views/partials/flashes.php'; include DR_ROOT . '/views/partials/dialogs.php'; ?>
</body>
</html>
