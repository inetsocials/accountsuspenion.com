<?php
/**
 * AccountSuspension.com public CMS endpoint (public_html/cms.php).
 *
 *   /cms.php?feed=posts                          published posts (JSON) for the blog listing
 *   /cms.php?feed=testimonials[&platform=][&service=][&home=1]   verified testimonials (JSON)
 *   /blog-sitemap.xml  (rewritten to /cms.php?sitemap=1)          published posts sitemap
 *   any URL that is not a file or folder (rewritten by .htaccess): redirects, CMS posts, else 404
 *
 * Reads published rows only, through AS Case Vault (outside public_html). Never writes content,
 * only redirect hit counters. Falls back to a plain 404 or empty feeds if the CRM is unavailable.
 */
declare(strict_types=1);

require __DIR__ . '/api/config.php';

header('X-Content-Type-Options: nosniff');

$site = __DIR__;

function not_found(string $site): never
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $f = $site . '/404.html';
    echo is_file($f) ? file_get_contents($f) : '<!doctype html><title>Not found</title><h1>Page not found</h1>';
    exit;
}

function json_out(array $data): never
{
    // Small feeds: always revalidate so an approval or new post shows immediately.
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$feed = isset($_GET['feed']) && is_string($_GET['feed']) ? $_GET['feed'] : '';
$sitemap = isset($_GET['sitemap']);
$path = isset($_GET['path']) && is_string($_GET['path']) ? $_GET['path'] : (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

/* Connect to the CRM (read-only use). */
$crm = ASCRM_PATH !== '' ? rtrim(ASCRM_PATH, '/') : dirname(__DIR__) . '/ascrm';
$ready = false;
if (is_file($crm . '/bootstrap.php')) {
    require $crm . '/bootstrap.php';
    restore_exception_handler();
    restore_error_handler();
    $ready = DR\Core\Config::installed();
}

use DR\Core\Db;
use DR\Core\Labels;
use DR\Core\Settings;
use DR\Http\CmsController;
use DR\Service\Markdown;

try {
    if ($feed === 'posts') {
        if (!$ready) {
            json_out(['items' => []]);
        }
        $rows = Db::all("SELECT slug, title, excerpt, category, published_at FROM posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 60");
        json_out(['items' => array_map(static fn($r) => [
            'url' => '/blog/' . $r['slug'] . '/', 'title' => $r['title'], 'excerpt' => (string) $r['excerpt'],
            'category' => Labels::POST_CATEGORIES[$r['category'] ?? ''] ?? 'All platforms', 'published' => fdate($r['published_at']),
        ], $rows)]);
    }

    if ($feed === 'testimonials') {
        if (!$ready) {
            json_out(['items' => [], 'count' => 0, 'average' => null]);
        }
        $all = Db::all("SELECT name, role, platform, service, rating, body, given_on FROM testimonials
            WHERE status = 'published' AND consent_ref IS NOT NULL AND consent_ref != '' ORDER BY given_on DESC");
        $platform = is_string($_GET['platform'] ?? null) ? $_GET['platform'] : '';
        $service = is_string($_GET['service'] ?? null) ? $_GET['service'] : '';
        $items = array_values(array_filter($all, static function (array $t) use ($platform, $service): bool {
            if ($platform !== '') {
                return $t['platform'] === $platform;
            }
            if ($service !== '') {
                return $t['service'] === $service;
            }
            return true;
        }));
        $count = count($all);
        json_out([
            'items' => array_map(static fn($t) => [
                'name' => $t['name'], 'role' => (string) $t['role'], 'platform' => Labels::PLATFORMS[$t['platform'] ?? ''] ?? '',
                'rating' => (int) $t['rating'], 'text' => $t['body'], 'given' => fdate($t['given_on']),
            ], array_slice($items, 0, 6)),
            'count' => $count,
            'average' => $count ? round(array_sum(array_column($all, 'rating')) / $count, 1) : null,
        ]);
    }

    if ($sitemap) {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $base = $ready ? rtrim(Settings::get('website_url'), '/') : '';
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        if ($ready) {
            foreach (Db::all("SELECT slug, updated_at FROM posts WHERE status = 'published' ORDER BY published_at DESC") as $r) {
                echo '<url><loc>' . htmlspecialchars($base . '/blog/' . $r['slug'] . '/', ENT_XML1) . '</loc><lastmod>' . substr((string) $r['updated_at'], 0, 10) . "</lastmod><priority>0.7</priority></url>\n";
            }
        }
        echo "</urlset>\n";
        exit;
    }

    /* /page.html, /page.htm, /page.php -> /page/ when the static page exists (also covered by .htaccess). */
    if (preg_match('~^/([a-z0-9][a-z0-9/-]*?)(?:/index)?\.(?:html?|php)$~i', $path, $hm) && is_file($site . '/' . strtolower($hm[1]) . '/index.html')) {
        header('Cache-Control: public, max-age=3600');
        header('Location: /' . strtolower($hm[1]) . '/', true, 301);
        exit;
    }

    /* Unknown URL: redirect table, then CMS post, else 404. */
    if (!$ready) {
        not_found($site);
    }
    $norm = CmsController::normalizePath($path);
    if ($norm === '') {
        not_found($site);
    }
    $r = Db::one('SELECT id, to_path FROM redirects WHERE from_path = ?', [$norm]);
    if ($r) {
        Db::run('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [(int) $r['id']]);
        header('Cache-Control: public, max-age=3600');
        header('Location: ' . $r['to_path'], true, 301);
        exit;
    }
    if (preg_match('~^/blog/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $norm, $m)) {
        $post = Db::one("SELECT * FROM posts WHERE slug = ? AND status = 'published'", [$m[1]]);
        if ($post) {
            if (!str_ends_with($path, '/')) {
                header('Location: /blog/' . $post['slug'] . '/', true, 301);
                exit;
            }
            render_post($post, $site);
        }
    }
    not_found($site);
} catch (Throwable $e) {
    if (class_exists('DR\Core\Log')) {
        DR\Core\Log::error($e);
    }
    $feed !== '' ? json_out(['items' => []]) : not_found($site);
}

function render_post(array $post, string $site): never
{
    $shellFile = $site . '/_theme/post.html';
    if (!is_file($shellFile)) {
        not_found($site);
    }
    $base = rtrim(Settings::get('website_url'), '/');
    $url = $base . '/blog/' . $post['slug'] . '/';
    $e = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = (string) ($post['meta_title'] ?: $post['title']);
    $desc = (string) ($post['meta_desc'] ?: Markdown::excerpt((string) $post['body'], 155));
    $platforms = array_values(array_filter(explode(',', (string) $post['platforms']), static fn($p) => isset(Labels::PLATFORMS[$p]) && $p !== 'other'));
    $side = '';
    if ($platforms) {
        $side = '<p class="f-h">Platforms in this guide</p><ul class="side-list">' . implode('', array_map(
            static fn($p) => '<li><a href="/' . $e($p) . '-reinstatement/">' . $e(Labels::PLATFORMS[$p]) . ' reinstatement</a></li>', $platforms)) . '</ul>';
    }
    $updated = fdate($post['updated_at']);
    $body = '<section class="phero"><div class="wrap"><nav class="crumbs" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li><a href="/blog/">Blog</a></li><li aria-current="page">'
        . $e($post['title']) . '</li></ol></nav><span class="pill">Guide</span><h1>' . $e($post['title']) . '</h1><p class="lede">' . $e($desc) . '</p></div></section>'
        . '<section class="sec"><div class="wrap layout"><article class="main prose-wrap">'
        . ($post['excerpt'] ? '<p class="answer"><strong>Short answer.</strong> ' . $e($post['excerpt']) . '</p>' : '')
        . Markdown::toHtml((string) $post['body'])
        . '</article><aside class="side"><div class="side-card">' . $side
        . '<a class="btn btn-primary btn-block" href="/contact-us/?source=guide">Start a confidential case</a><p class="small">Updated ' . $e($updated) . '</p></div></aside></div></section>'
        . '<section class="cta-band"><div class="wrap cta-in"><div><h2>Start with the notice.</h2><p>Send us what the platform sent you. We reply in writing with what we see, what we would do, and whether we think the case is worth pursuing.</p></div>'
        . '<div class="ctas"><a class="btn btn-primary" href="/contact-us/?source=guide">Start a confidential case</a><a class="btn btn-ghost" href="/tools/reinstatement-readiness-score/">Get your Readiness Score</a></div></div></section>';

    $html = (string) file_get_contents($shellFile);
    // JSON-LD: keep the site's Organization and WebSite nodes, add this article.
    $html = preg_replace_callback('~<script type="application/ld\+json">(.*?)</script>~s', static function (array $m) use ($post, $url, $title, $desc, $base): string {
        $g = json_decode(str_replace('<\/', '</', $m[1]), true) ?: ['@context' => 'https://schema.org', '@graph' => []];
        $nodes = array_values(array_filter($g['@graph'] ?? [], static fn($n) => in_array($n['@type'] ?? '', ['Organization', 'WebSite'], true)));
        $org = ['@id' => $base . '/#organization'];
        $nodes[] = ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'description' => $desc, 'isPartOf' => ['@id' => $base . '/#website'], 'inLanguage' => 'en-US'];
        $nodes[] = ['@type' => 'Article', 'headline' => $post['title'], 'description' => $desc, 'author' => $org, 'publisher' => $org,
            'datePublished' => date('c', strtotime((string) $post['published_at'])), 'dateModified' => date('c', strtotime((string) $post['updated_at'])),
            'mainEntityOfPage' => $url, 'image' => $base . '/assets/img/og.png', 'inLanguage' => 'en-US'];
        $nodes[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $base . '/blog/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $url],
        ]];
        $g['@graph'] = $nodes;
        return '<script type="application/ld+json">' . str_replace('</', '<\/', (string) json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</script>';
    }, $html, 1) ?? $html;
    $html = strtr($html, [
        '%%TITLE%%' => $e($title), '%%DESC%%' => $e($desc), '%%SLUG%%' => $e((string) $post['slug']), '<main id="main">%%BODY%%</main>' => '<main id="main">' . $body . '</main>',
    ]);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=600');
    echo $html;
    exit;
}
