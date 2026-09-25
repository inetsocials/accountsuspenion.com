<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Request;
use DR\Core\Settings;
use DR\Service\Markdown;

/**
 * Website CMS: blog posts, verified testimonials and URL redirects.
 * Public rendering happens in public_html/cms.php, which reads published rows only.
 *
 * Testimonials: every entry needs a consent record and is published only after an admin
 * other than its author approves it (a master may self-approve when working alone).
 * Invented or misattributed reviews are prohibited by the FTC rule on fake reviews (16 CFR Part 465).
 */
final class CmsController extends Controller
{
    private const RESERVED = ['portal', 'api', 'assets', 'services', 'blog', 'tools', 'contact-us', 'thank-you', 'about-us', 'faq', 'pricing', 'ethics'];

    // ------------------------------------------------------------------ posts
    public function posts(array $p, ?array $u): void
    {
        $status = Request::oneOf('status', ['all' => 1] + Labels::POST_STATUS, 'all');
        $w = $status === 'all' ? '1=1' : 'p.status = :st';
        $params = $status === 'all' ? [] : ['st' => $status];
        $pg = $this->paginate("SELECT COUNT(*) FROM posts p WHERE $w", $params, 30);
        $rows = Db::all("SELECT p.*, u.name AS author FROM posts p LEFT JOIN users u ON u.id = p.author_id WHERE $w
            ORDER BY CASE p.status WHEN 'draft' THEN 0 ELSE 1 END, COALESCE(p.published_at, p.updated_at) DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
        $this->view('cms/posts', ['title' => 'Blog posts', 'rows' => $rows, 'pg' => $pg, 'status' => $status], 'app');
    }

    public function postForm(array $p, ?array $u): void
    {
        $post = isset($p['id']) ? Db::one('SELECT * FROM posts WHERE id = ?', [$this->id($p)]) : null;
        if (isset($p['id']) && !$post) {
            throw new HttpError('Post not found.', 404);
        }
        $this->view('cms/post_edit', ['title' => $post ? 'Edit post' : 'New post', 'post' => $post], 'app');
    }

    public function savePost(array $p, ?array $u): void
    {
        $id = isset($p['id']) ? $this->id($p) : 0;
        $prev = $id ? Db::one('SELECT * FROM posts WHERE id = ?', [$id]) : null;
        if ($id && !$prev) {
            throw new HttpError('Post not found.', 404);
        }
        $back = $id ? '/cms/posts/' . $id : '/cms/posts/new';
        $title = Request::str('title', 190);
        $body = Request::text('body', 60000);
        $slug = strtolower(trim(Request::str('slug', 160) ?: $this->slugify($title), '-'));
        if (mb_strlen($title) < 10) {
            $this->fail('Give the post a descriptive title of at least 10 characters.', $back);
        }
        if (mb_strlen($body) < 200) {
            $this->fail('The post body is too short to publish usefully (at least 200 characters).', $back);
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) < 3 || in_array($slug, self::RESERVED, true)) {
            $this->fail('Use a URL slug of lowercase letters, numbers and hyphens, for example how-to-appeal-a-walmart-suspension.', $back);
        }
        if (Db::value('SELECT id FROM posts WHERE slug = ? AND id != ?', [$slug, $id])) {
            $this->fail('Another post already uses that URL.', $back);
        }
        $site = dirname(App::publicDir());
        if ($site !== '' && is_dir($site . '/blog/' . $slug)) {
            $this->fail('A built-in guide already uses /blog/' . $slug . '/. Choose another slug.', $back);
        }
        $status = Request::oneOf('status', Labels::POST_STATUS, 'draft');
        $platforms = array_values(array_intersect(Request::arr('platforms'), array_keys(Labels::PLATFORMS)));
        $metaDesc = Request::str('meta_desc', 170) ?: Markdown::excerpt($body, 155);
        $data = [
            'slug' => $slug, 'title' => $title, 'body' => $body,
            'excerpt' => Request::str('excerpt', 300) ?: Markdown::excerpt($body, 240),
            'meta_title' => Request::str('meta_title', 70) ?: mb_substr($title, 0, 70),
            'meta_desc' => $metaDesc,
            'category' => Request::oneOf('category', Labels::POST_CATEGORIES, 'general'),
            'platforms' => $platforms ? implode(',', $platforms) : null,
            'status' => $status, 'updated_at' => now(),
        ];
        if ($status === 'published' && (!$prev || !$prev['published_at'])) {
            $data['published_at'] = now();
        }
        if ($prev) {
            Db::update('posts', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = Db::insert('posts', $data + ['author_id' => (int) $u['id'], 'created_at' => now()]);
        }
        Audit::log('post_saved', 'post', $id, ['status' => $status], $u);
        $this->flash('ok', $status === 'published' ? 'Post published at /blog/' . $slug . '/.' : 'Draft saved.');
        App::redirect('/cms/posts/' . $id);
    }

    public function deletePost(array $p, ?array $u): void
    {
        $id = $this->id($p);
        Db::run('DELETE FROM posts WHERE id = ?', [$id]);
        Audit::log('post_deleted', 'post', $id, [], $u);
        $this->flash('ok', 'Post deleted. Add a redirect if it was published and linked from elsewhere.');
        App::redirect('/cms/posts');
    }

    // ------------------------------------------------------------------ testimonials
    public function testimonials(array $p, ?array $u): void
    {
        $rows = Db::all("SELECT t.*, c.name AS creator, a.name AS approver FROM testimonials t
            LEFT JOIN users c ON c.id = t.created_by LEFT JOIN users a ON a.id = t.approved_by
            ORDER BY CASE t.status WHEN 'pending' THEN 0 WHEN 'published' THEN 1 ELSE 2 END, t.given_on DESC");
        $pub = array_filter($rows, fn($r) => $r['status'] === 'published');
        $avg = $pub ? round(array_sum(array_column($pub, 'rating')) / count($pub), 1) : null;
        $this->view('cms/testimonials', ['title' => 'Testimonials', 'rows' => $rows, 'published' => count($pub), 'average' => $avg], 'app');
    }

    public function saveTestimonial(array $p, ?array $u): void
    {
        $back = '/cms/testimonials';
        $name = Request::str('name', 120);
        $body = Request::text('body', 1500);
        $consent = Request::str('consent_ref', 200);
        $given = Request::date('given_on');
        $rating = Request::int('rating');
        if ($name === '' || mb_strlen($body) < 20) {
            $this->fail('Enter the client name as they agreed to be shown and their exact words (at least 20 characters).', $back);
        }
        if (mb_strlen($consent) < 6) {
            $this->fail('Record where the written consent is kept (for example the signed form reference and date). Testimonials without consent cannot be saved.', $back);
        }
        if (!$given || $given > today()) {
            $this->fail('Enter the date the client gave the testimonial.', $back);
        }
        if ($rating < 1 || $rating > 5) {
            $this->fail('Enter the rating the client actually gave, from 1 to 5.', $back);
        }
        $data = [
            'name' => $name, 'role' => Request::str('role', 120) ?: null,
            'platform' => Request::oneOf('platform', Labels::PLATFORMS) ?: null,
            'service' => Request::oneOf('service', Labels::SERVICES) ?: null,
            'rating' => $rating, 'body' => $body, 'given_on' => $given, 'consent_ref' => $consent,
            'status' => 'pending', 'approved_by' => null, 'approved_at' => null, 'updated_at' => now(),
        ];
        $id = Request::int('id');
        if ($id && Db::value('SELECT id FROM testimonials WHERE id = ?', [$id])) {
            Db::update('testimonials', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = Db::insert('testimonials', $data + ['created_by' => (int) $u['id'], 'created_at' => now()]);
        }
        Audit::log('testimonial_saved', 'testimonial', $id, [], $u);
        $this->flash('ok', 'Saved. An admin must approve it before it appears on the website.');
        App::redirect($back);
    }

    public function approveTestimonial(array $p, ?array $u): void
    {
        $t = Db::one('SELECT * FROM testimonials WHERE id = ?', [$this->id($p)]);
        if (!$t) {
            throw new HttpError('Testimonial not found.', 404);
        }
        if ((int) $t['created_by'] === (int) $u['id'] && $u['role'] !== 'master') {
            $this->fail('A different admin must approve a testimonial you entered.', '/cms/testimonials');
        }
        if (trim((string) $t['consent_ref']) === '') {
            $this->fail('Add the consent record before approving.', '/cms/testimonials');
        }
        Db::update('testimonials', ['status' => 'published', 'approved_by' => (int) $u['id'], 'approved_at' => now(), 'updated_at' => now()], 'id = :id', ['id' => (int) $t['id']]);
        Audit::log('testimonial_published', 'testimonial', (int) $t['id'], [], $u);
        $this->flash('ok', 'Testimonial published on the website.');
        App::redirect('/cms/testimonials');
    }

    public function hideTestimonial(array $p, ?array $u): void
    {
        $id = $this->id($p);
        Db::update('testimonials', ['status' => 'hidden', 'updated_at' => now()], 'id = :id', ['id' => $id]);
        Audit::log('testimonial_hidden', 'testimonial', $id, [], $u);
        $this->flash('ok', 'Testimonial hidden from the website.');
        App::redirect('/cms/testimonials');
    }

    public function deleteTestimonial(array $p, ?array $u): void
    {
        $id = $this->id($p);
        Db::run('DELETE FROM testimonials WHERE id = ?', [$id]);
        Audit::log('testimonial_deleted', 'testimonial', $id, [], $u);
        $this->flash('ok', 'Testimonial deleted.');
        App::redirect('/cms/testimonials');
    }

    // ------------------------------------------------------------------ redirects
    public function redirects(array $p, ?array $u): void
    {
        $rows = Db::all('SELECT * FROM redirects ORDER BY from_path');
        $this->view('cms/redirects', ['title' => 'Redirects', 'rows' => $rows, 'site' => Settings::get('website_url')], 'app');
    }

    public function saveRedirect(array $p, ?array $u): void
    {
        $back = '/cms/redirects';
        $from = self::normalizePath(Request::str('from_path', 255));
        $to = trim(Request::str('to_path', 255));
        if ($from === '' || $from === '/' || str_starts_with($from, '/portal') || str_starts_with($from, '/api/')) {
            $this->fail('Enter an old path such as /old-page/ (not the home page, /portal or /api).', $back);
        }
        $site = rtrim(Settings::get('website_url'), '/');
        if (str_starts_with($to, $site . '/')) {
            $to = substr($to, strlen($site));
        }
        if (!preg_match('~^/[A-Za-z0-9/_\-.%]*$~', $to) && !preg_match('~^https://[^\s"<>]+$~', $to)) {
            $this->fail('Enter the new address as a path such as /blog/new-page/ or a full https:// address.', $back);
        }
        if (self::normalizePath($to) === $from) {
            $this->fail('The new address cannot be the same as the old one.', $back);
        }
        if (Db::value('SELECT id FROM redirects WHERE from_path = ?', [$from])) {
            Db::update('redirects', ['to_path' => $to], 'from_path = :f', ['f' => $from]);
        } else {
            Db::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'hits' => 0, 'created_by' => (int) $u['id'], 'created_at' => now()]);
        }
        Audit::log('redirect_saved', 'redirect', null, ['from' => $from], $u);
        $this->flash('ok', 'Redirect saved. It applies immediately.');
        App::redirect($back);
    }

    public function deleteRedirect(array $p, ?array $u): void
    {
        Db::run('DELETE FROM redirects WHERE id = ?', [$this->id($p)]);
        Audit::log('redirect_deleted', 'redirect', $this->id($p), [], $u);
        $this->flash('ok', 'Redirect removed.');
        App::redirect('/cms/redirects');
    }

    /** Lower-case, strip query, collapse slashes, drop trailing slash (except root). */
    public static function normalizePath(string $path): string
    {
        $path = (string) parse_url(trim($path), PHP_URL_PATH);
        if ($path === '' || $path[0] !== '/' || str_contains($path, '..')) {
            return '';
        }
        $path = strtolower(preg_replace('~/+~', '/', $path) ?? $path);
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim(mb_substr($s, 0, 80), '-');
    }
}
