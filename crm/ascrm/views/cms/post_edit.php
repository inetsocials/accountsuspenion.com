<?php
use DR\Core\Auth;
use DR\Core\Labels;
use DR\Core\Settings;
use DR\Service\Markdown;
$v = static fn(string $k, string $d = '') => (string) (old($k) ?: ($post[$k] ?? $d));
$chosen = array_filter(explode(',', (string) ($post['platforms'] ?? '')));
$site = rtrim(Settings::get('website_url'), '/');
?>
<div class="page-head">
  <div><div class="eyebrow"><a href="<?= h(url('/cms/posts')) ?>">Blog posts</a></div><h1><?= $post ? h($post['title']) : 'New post' ?></h1>
    <?php if ($post): ?><p class="sub row"><span class="pill mono">/blog/<?= h($post['slug']) ?>/</span><?php if ($post['status'] === 'published'): ?><a href="<?= h($site . '/blog/' . $post['slug'] . '/') ?>" target="_blank" rel="noopener"><?= icon('external') ?>View on website</a><?php endif; ?></p><?php endif; ?></div>
  <?php if ($post && Auth::atLeast($user, 'admin')): ?>
  <div class="actions"><form method="post" action="<?= h(url('/cms/posts/' . $post['id'] . '/delete')) ?>" data-confirm="Delete this post? If it was published, add a redirect so links keep working." data-danger><?= csrf_field() ?><button class="btn btn-soft-danger" type="submit"><?= icon('trash') ?>Delete</button></form></div>
  <?php endif; ?>
</div>
<form class="grid g-main" id="post-form" method="post" action="<?= h(url($post ? '/cms/posts/' . $post['id'] : '/cms/posts')) ?>">
  <?= csrf_field() ?>
  <div class="stack">
    <div class="card"><div class="card-b">
      <label class="field"><span>Title</span><input type="text" name="title" required minlength="10" maxlength="190" value="<?= h($v('title')) ?>" placeholder="For example: How to appeal a Walmart seller suspension"></label>
      <label class="field"><span>URL slug</span><input type="text" name="slug" maxlength="160" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="mono" value="<?= h($v('slug')) ?>" placeholder="Created from the title if left blank"><span class="hint">Changing the slug of a published post breaks old links. Add a redirect if you do.</span></label>
      <label class="field"><span>Body</span><textarea name="body" rows="22" required class="mono"><?= h($v('body')) ?></textarea>
        <span class="hint">Formatting: ## Heading, ### Subheading, - bullet, 1. numbered, &gt; quote, **bold**, *italic*, [link text](https://example.com or /services/). Start with a direct two or three sentence answer to the question in the title.</span></label>
    </div></div>
    <?php if ($post): ?>
    <details class="card"><summary class="card-h"><h2>Preview</h2><span class="muted small">As rendered on the website</span></summary>
      <div class="card-b prose"><?= Markdown::toHtml((string) $post['body']) ?></div></details>
    <?php endif; ?>
  </div>
  <div class="stack">
    <div class="card"><div class="card-h"><h2>Publishing</h2></div><div class="card-b">
      <label class="field"><span>Status</span><select name="status"><?= options(Labels::POST_STATUS, $v('status', 'draft')) ?></select></label>
      <label class="field"><span>Category</span><select name="category"><?= options(Labels::POST_CATEGORIES, $v('category', 'general')) ?></select></label>
      <label class="field"><span>Platforms covered</span><select name="platforms[]" multiple size="8"><?php foreach (Labels::PLATFORMS as $k => $n): if ($k === 'other') { continue; } ?><option value="<?= h($k) ?>"<?= selected(in_array($k, $chosen, true)) ?>><?= h($n) ?></option><?php endforeach; ?></select><span class="hint">Links the post to those platform pages.</span></label>
      <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?>Save</button>
    </div></div>
    <div class="card"><div class="card-h"><h2>Search listing</h2></div><div class="card-b">
      <label class="field"><span>Meta title (up to 62 characters shows in full)</span><input type="text" name="meta_title" maxlength="70" value="<?= h($v('meta_title')) ?>"></label>
      <label class="field"><span>Meta description (110 to 160 characters)</span><textarea name="meta_desc" rows="3" maxlength="170"><?= h($v('meta_desc')) ?></textarea></label>
      <label class="field"><span>Excerpt for the blog listing</span><textarea name="excerpt" rows="3" maxlength="300"><?= h($v('excerpt')) ?></textarea><span class="hint">Created from the body when left blank.</span></label>
    </div></div>
  </div>
</form>
