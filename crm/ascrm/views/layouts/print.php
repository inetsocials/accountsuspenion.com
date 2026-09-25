<?php include DR_ROOT . '/views/partials/head.php'; ?>
<body class="print-body">
<?php include DR_ROOT . '/views/partials/icons.php'; ?>
<div class="print-bar no-print"><button type="button" class="btn btn-primary btn-sm" data-print><?= icon('printer') ?>Print or save as PDF</button></div>
<?= $content ?>
</body>
</html>
