#!/usr/bin/env php
<?php
/**
 * DEV COPIES ONLY, read-only. What this copy's database holds for some articles, as JSON on stdout: the body as
 * stored (placeholders and all, NOT what the editor gets) and the two dates the article page turns into
 * article:modified_time (Article::getModifiedAt(): the later of the two), as the database writes them.
 *
 *   php stored-body.php ID [ID...]
 *
 * downloads.py compares the stored body with the editor's copy of the same article, to find text that the page
 * never shows and that a save would therefore delete. It works on the database of this checkout only: run against
 * production pages, it tells something only while the copy is at least as recent as each article's last change.
 */
declare(strict_types=1);

// this checkout's kernel and database, once it's certain this is a dev copy: PROJECT_DIR, fail(), $db, $site, $host
require __DIR__ . '/../../tli-logged-in-check/scripts/dev-copy.php';

$ids = array_slice($argv, 1);
if (!$ids || array_filter($ids, fn($id) => !ctype_digit((string) $id) || (int) $id < 1)) {
    fail("usage: php stored-body.php ID [ID...] (article IDs)", 2);
}

$articles = [];
foreach (array_unique(array_map('intval', $ids)) as $id) {
    $row = $db->fetchAssociative('SELECT body, updated_at, published_at FROM article WHERE id = ?', [$id]);
    $articles[$id] = $row === false ? null : [
        'body'          => (string) $row['body'],
        'updated_at'    => $row['updated_at'],
        'published_at'  => $row['published_at'],
    ];
}

echo json_encode(['site' => $site, 'articles' => $articles], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
