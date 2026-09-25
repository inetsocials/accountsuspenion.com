<?php
declare(strict_types=1);

namespace DR\Service;

/**
 * Minimal, safe Markdown for CMS posts.
 * Supported: ## and ### headings, paragraphs, - and 1. lists, > quotes, **bold**, *italic*, [text](url).
 * Everything is HTML-escaped first; links accept only https://, http:// or site-relative /paths.
 * No raw HTML, images, scripts or inline styles can be produced.
 */
final class Markdown
{
    public static function toHtml(string $md): string
    {
        $lines = preg_split('/\R/', str_replace("\t", '    ', $md)) ?: [];
        $out = [];
        $para = [];
        $list = null;
        $items = [];
        $flushPara = static function () use (&$para, &$out): void {
            if ($para) {
                $out[] = '<p>' . self::inline(implode(' ', $para)) . '</p>';
                $para = [];
            }
        };
        $flushList = static function () use (&$list, &$items, &$out): void {
            if ($list) {
                $out[] = '<' . $list . '>' . implode('', array_map(static fn($i) => '<li>' . self::inline($i) . '</li>', $items)) . '</' . $list . '>';
                $list = null;
                $items = [];
            }
        };
        foreach ($lines as $raw) {
            $line = rtrim($raw);
            if (trim($line) === '') {
                $flushPara();
                $flushList();
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/', $line, $m)) {
                $flushPara();
                $flushList();
                $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                $out[] = "<$tag>" . self::inline($m[2]) . "</$tag>";
                continue;
            }
            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($list !== 'ul') {
                    $flushList();
                    $list = 'ul';
                }
                $items[] = $m[1];
                continue;
            }
            if (preg_match('/^\s*\d+[.)]\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($list !== 'ol') {
                    $flushList();
                    $list = 'ol';
                }
                $items[] = $m[1];
                continue;
            }
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $flushPara();
                $flushList();
                $out[] = '<blockquote><p>' . self::inline($m[1]) . '</p></blockquote>';
                continue;
            }
            $flushList();
            $para[] = trim($line);
        }
        $flushPara();
        $flushList();
        return implode("\n", $out);
    }

    public static function inline(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace_callback('/\[([^\]]{1,200})\]\(([^)\s]{1,500})\)/', static function (array $m): string {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (!preg_match('~^(https?://[^\s"<>]+|/[A-Za-z0-9/_\-.#?=&%]*)$~', $url)) {
                return $m[1];
            }
            $ext = str_starts_with($url, 'http');
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . ($ext ? ' rel="noopener nofollow" target="_blank"' : '') . '>' . $m[1] . '</a>';
        }, $s) ?? $s;
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $s) ?? $s;
        return $s;
    }

    /** Plain-text excerpt for listings and meta descriptions. */
    public static function excerpt(string $md, int $len = 160): string
    {
        $t = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $md) ?? $md;
        $t = preg_replace('/[#>*_`]+/', '', $t) ?? $t;
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        if (mb_strlen($t) <= $len) {
            return $t;
        }
        $cut = mb_substr($t, 0, $len);
        $sp = mb_strrpos($cut, ' ');
        return rtrim($sp ? mb_substr($cut, 0, $sp) : $cut, ' ,.;:') . '...';
    }
}
