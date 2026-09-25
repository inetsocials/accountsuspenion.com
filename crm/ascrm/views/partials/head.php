<?php
use DR\Core\Config;
use DR\Core\Session;
use DR\Core\Settings;
$portalName = Config::installed() ? Settings::get('portal_name') : 'AS Case Vault';
?><!doctype html>
<html lang="en-US" data-theme="auto">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="csrf-token" content="<?= h(Session::csrf()) ?>">
<meta name="ascv-base" content="<?= h(\DR\Core\App::basePath()) ?>">
<?php if (!empty($user)): ?><meta name="ascv-idle" content="<?= (int) Settings::int('session_idle_minutes') ?>"><?php endif; ?>
<title><?= h(($title ?? '') !== '' ? $title . ' | ' . $portalName : $portalName) ?></title>
<link rel="icon" href="<?= h(asset('img/favicon.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
<script src="<?= h(asset('js/theme.js')) ?>"></script>
<script src="<?= h(asset('js/app.js')) ?>" defer></script>
</head>
