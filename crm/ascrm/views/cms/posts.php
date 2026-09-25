<?php use DR\Core\Auth; use DR\Core\Labels; use DR\Core\Settings; use DR\Core\View; $site = rtrim(Settings::get('website_url'), '/'); ?>
<div class="page-head">
  <div><div class="eyebrow">Website</div><h1>Blog posts</h1><p class="sub">Published posts appear at /blog/your-slug/ on the website, in the blog listing and in the blog sitemap.</p></div>
  <div class="actions"><a class="btn btn-primary" href="<?= h(url('/cms/posts/new')) ?>"><?= icon('plus') ?>New post</a></div>
</div>
<div class="card">
  <form class="filters" method="get" action="<?= h(url('/cms/posts')) ?>">
    <select name="status" data-autosubmit aria-label="Status"><?= options(['all' => 'All posts'] + Labels::POST_STATUS, $status) ?></select>
    <noscript><button class="btn btn-ghost btn-sm" type="submit">Filter</button></noscript>
  </form>
  <?php if (!$rows): ?>
    <?php $icon = 'file'; $heading = 'No posts yet'; $text = 'Write platform guides that answer the questions clients ask. Drafts stay private until you publish.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Post</th><th>Category</th><th>Status</th><th>Published</th><th>Author</th><th class="right"></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td data-l="Post" class="title-cell"><a class="row-link" href="<?= h(url('/cms/posts/' . $r['id'])) ?>"><?= h($r['title']) ?></a><small class="mono">/blog/<?= h($r['slug']) ?>/</small></td>
        <td data-l="Category"><?= h(Labels::POST_CATEGORIES[$r['category'] ?? ''] ?? '') ?></td>
        <td data-l="Status"><?= View::badge($r['status']) ?></td>
        <td data-l="Published" class="nowrap"><?= h(fdate($r['published_at'])) ?></td>
        <td data-l="Author"><?= h($r['author'] ?? '') ?></td>
        <td data-l="" class="right nowrap"><?php if ($r['status'] === 'published'): ?><a class="btn btn-ghost btn-xs" href="<?= h($site . '/blog/' . $r['slug'] . '/') ?>" target="_blank" rel="noopener"><?= icon('external') ?>View</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= pager($pg, '/cms/posts', $status !== 'all' ? ['status' => $status] : []) ?>
  <?php endif; ?>
</div>
