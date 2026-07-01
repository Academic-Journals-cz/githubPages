<?php

/**
 * @file classes/GithubMarkdownService.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubMarkdownService
 *
 * @brief Downloads a Markdown file from GitHub, renders it to HTML using the
 *   GitHub Markdown API, rewrites relative image/link URLs to absolute GitHub
 *   URLs and sanitizes the result.
 */

namespace APP\plugins\generic\githubPages\classes;

use APP\core\Application;

class GithubMarkdownService
{
    /** HTML elements that are always removed (defense in depth on top of GitHub's own sanitizing). */
    private const BANNED_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'select', 'textarea', 'link', 'meta', 'base', 'applet', 'frame', 'frameset',
        'noscript', 'svg', 'math',
    ];

    /** Attributes carrying a URL that we rewrite/clean. */
    private const URL_ATTRS = ['href', 'src', 'poster', 'longdesc', 'xlink:href'];

    /**
     * Parse a GitHub URL into its components.
     *
     * Accepts the common forms:
     *   https://github.com/{owner}/{repo}/blob/{branch}/{path}
     *   https://github.com/{owner}/{repo}/raw/{branch}/{path}
     *   https://raw.githubusercontent.com/{owner}/{repo}/{branch}/{path}
     *   https://raw.githubusercontent.com/{owner}/{repo}/refs/heads/{branch}/{path}
     *
     * @return ?array{owner:string,repo:string,branch:string,path:string,dir:string,ownerRepo:string,rawUrl:string,rawBase:string,blobBase:string}
     */
    public static function parseGithubUrl(string $url): ?array
    {
        $url = trim($url);
        $owner = $repo = $branch = $path = null;

        if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)/(?:blob|raw)/([^/]+)/(.+)$#', $url, $m)) {
            [, $owner, $repo, $branch, $path] = $m;
        } elseif (preg_match('#^https?://raw\.githubusercontent\.com/([^/]+)/([^/]+)/refs/heads/([^/]+)/(.+)$#', $url, $m)) {
            [, $owner, $repo, $branch, $path] = $m;
        } elseif (preg_match('#^https?://raw\.githubusercontent\.com/([^/]+)/([^/]+)/([^/]+)/(.+)$#', $url, $m)) {
            [, $owner, $repo, $branch, $path] = $m;
        } else {
            return null;
        }

        // Strip any query string or fragment from the file path.
        $path = preg_replace('#[?\#].*$#', '', $path);
        $dir = (strpos($path, '/') !== false) ? substr($path, 0, strrpos($path, '/')) : '';

        return [
            'owner' => $owner,
            'repo' => $repo,
            'branch' => $branch,
            'path' => $path,
            'dir' => $dir,
            'ownerRepo' => "{$owner}/{$repo}",
            'rawUrl' => "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/{$path}",
            'rawBase' => "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/",
            'blobBase' => "https://github.com/{$owner}/{$repo}/blob/{$branch}/",
        ];
    }

    /**
     * Fetch the Markdown for a URL, render it and return clean HTML.
     *
     * @throws \Exception on a bad URL or any network/parse failure.
     */
    public function fetchAndRender(string $url, ?string $token = null): string
    {
        $parts = self::parseGithubUrl($url);
        if (!$parts) {
            throw new \Exception(__('plugins.generic.githubPages.error.badUrl'));
        }

        $markdown = $this->httpGetRaw($parts['rawUrl'], $token);
        $html = $this->renderMarkdown($markdown, $parts['ownerRepo'], $token);

        return self::processHtml($html, $parts);
    }

    /**
     * Re-fetch every locale of a page and store the rendered HTML on it.
     *
     * @return string[] List of error messages (empty means full success).
     */
    public function refreshPage(GithubPage $githubPage, ?string $token = null): array
    {
        $errors = [];
        $sourceUrls = (array) $githubPage->getSourceUrl(null);

        foreach ($sourceUrls as $locale => $sourceUrl) {
            $sourceUrl = trim((string) $sourceUrl);
            if ($sourceUrl === '') {
                continue;
            }
            try {
                $html = $this->fetchAndRender($sourceUrl, $token);
                $githubPage->setContent($html, $locale);
                $githubPage->setFetchedAt(date('Y-m-d H:i'), $locale);
            } catch (\Throwable $e) {
                $errors[] = "{$locale}: " . $e->getMessage();
            }
        }
        return $errors;
    }

    /**
     * Download a raw file using OJS' (proxy-aware) HTTP client.
     *
     * @throws \Exception
     */
    private function httpGetRaw(string $rawUrl, ?string $token): string
    {
        $headers = [
            'User-Agent' => 'OJS-GithubPages-Plugin',
            'Accept' => 'application/vnd.github.raw',
        ];
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $response = Application::get()->getHttpClient()->request('GET', $rawUrl, [
                'headers' => $headers,
                'timeout' => 20,
                'http_errors' => true,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception(__('plugins.generic.githubPages.error.fetchFailed') . ' (' . $e->getMessage() . ')');
        }

        return (string) $response->getBody();
    }

    /**
     * Render Markdown to GitHub-flavored HTML via the GitHub Markdown API.
     *
     * @throws \Exception
     */
    private function renderMarkdown(string $markdown, string $ownerRepo, ?string $token): string
    {
        $headers = [
            'User-Agent' => 'OJS-GithubPages-Plugin',
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $response = Application::get()->getHttpClient()->request('POST', 'https://api.github.com/markdown', [
                'headers' => $headers,
                'json' => [
                    'text' => $markdown,
                    'mode' => 'gfm',
                    'context' => $ownerRepo,
                ],
                'timeout' => 30,
                'http_errors' => true,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception(__('plugins.generic.githubPages.error.renderFailed') . ' (' . $e->getMessage() . ')');
        }

        return (string) $response->getBody();
    }

    /**
     * Sanitize the rendered HTML and rewrite relative URLs in a single DOM pass.
     */
    private static function processHtml(string $html, array $parts): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // NOIMPLIED + NODEFDTD keep DOMDocument from wrapping the fragment in <html>/<body>.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__ghroot">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__ghroot');
        if (!$root) {
            return '';
        }

        self::cleanNode($root, $parts);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }
        return $out;
    }

    /**
     * Recursively remove unsafe elements/attributes and absolutize relative URLs.
     */
    private static function cleanNode(\DOMNode $node, array $parts): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::BANNED_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            if ($child->hasAttributes()) {
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->nodeName);
                    $value = trim((string) $attr->nodeValue);

                    // Drop inline event handlers.
                    if (str_starts_with($name, 'on')) {
                        $child->removeAttribute($attr->nodeName);
                        continue;
                    }

                    if (in_array($name, self::URL_ATTRS, true)) {
                        if (preg_match('#^\s*(javascript|vbscript)\s*:#i', $value)) {
                            $child->removeAttribute($attr->nodeName);
                            continue;
                        }
                        if ($value !== '' && !self::isAbsolute($value)) {
                            $child->setAttribute($attr->nodeName, self::resolveUrl($value, $parts, $tag));
                        }
                    }
                }
            }

            self::cleanNode($child, $parts);
        }
    }

    /**
     * Whether a URL is already absolute (or an in-page anchor / mail link / data URI).
     */
    private static function isAbsolute(string $url): bool
    {
        return (bool) preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)
            || str_starts_with($url, '//')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || str_starts_with($url, 'data:');
    }

    /**
     * Turn a relative URL into an absolute GitHub URL.
     * Images (and non-anchor assets) point at raw.githubusercontent.com;
     * links (<a>) point at the browsable github.com/blob view.
     */
    private static function resolveUrl(string $value, array $parts, string $tag): string
    {
        // Preserve any trailing query string / fragment.
        $suffix = '';
        if (preg_match('/[?\#].*$/', $value, $mm)) {
            $suffix = $mm[0];
            $value = substr($value, 0, strlen($value) - strlen($suffix));
        }

        if (str_starts_with($value, '/')) {
            // Repository-root-relative.
            $resolved = self::resolvePath('', ltrim($value, '/'));
        } else {
            $resolved = self::resolvePath($parts['dir'], $value);
        }

        $base = ($tag === 'a') ? $parts['blobBase'] : $parts['rawBase'];
        return $base . $resolved . $suffix;
    }

    /**
     * Resolve a relative path against a base directory, collapsing "." and "..".
     */
    private static function resolvePath(string $baseDir, string $relative): string
    {
        $stack = ($baseDir === '') ? [] : explode('/', $baseDir);
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($stack);
            } else {
                $stack[] = $segment;
            }
        }
        return implode('/', $stack);
    }
}
