<?php


define('_PS_ROOT_DIR_', realpath(__DIR__));
$shop_root   = _PS_ROOT_DIR_ . '/';
$image_folder = 'img/p/';
$scan_dir    = $shop_root . $image_folder;

include($shop_root . 'config/config.inc.php');
include($shop_root . 'init.php');

$mode = in_array($_GET['mode'] ?? '', ['soft', 'clean']) ? $_GET['mode'] : 'soft';
$is_clean = ($mode === 'clean');

// ── Queries ────────────────────────────────────────────────────────────────

$total_db = (int) Db::getInstance()->getValue(
    'SELECT COUNT(id_image) FROM ' . _DB_PREFIX_ . 'image'
);

$sql = 'SELECT DISTINCT i.id_image, i.id_product
        FROM ' . _DB_PREFIX_ . 'image i
        LEFT JOIN ' . _DB_PREFIX_ . 'image_shop ish
            ON ish.id_image = i.id_image AND ish.id_shop = 1
        LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_image pai
            ON pai.id_image = i.id_image
        WHERE (ish.cover = 0 OR ish.cover IS NULL)
          AND ish.id_shop = 1
          AND pai.id_product_attribute IS NULL';

$unused_images = Db::getInstance()->executeS($sql);
$unused_count  = count($unused_images);

// ── Process ────────────────────────────────────────────────────────────────

$results        = [];
$files_affected = 0;
$total_bytes    = 0;

foreach ($unused_images as $img) {
    $id       = (int) $img['id_image'];
    $id_prod  = (int) $img['id_product'];
    $dir_path = $scan_dir . implode('/', str_split($id));
    $files    = glob($dir_path . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];

    $size = array_sum(array_map('filesize', array_filter($files, 'is_file')));
    $total_bytes += $size;

    if (!imageExistsInDB($id)) {
        if ($files) {
            if ($is_clean) deleteFiles($files);
            $files_affected++;
            $results[] = ['type' => 'file_only', 'id' => $id, 'product' => $id_prod, 'files' => count($files), 'size' => $size, 'path' => $dir_path];
        }
    } else {
        if ($is_clean) {
            deleteSql($id);
            deleteFiles($files);
        }
        $files_affected++;
        $results[] = ['type' => 'full', 'id' => $id, 'product' => $id_prod, 'files' => count($files), 'size' => $size, 'path' => $dir_path];
    }
}

// ── Functions ──────────────────────────────────────────────────────────────

function formatBytes(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2)    . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2)       . ' KB';
    return $bytes . ' B';
}

function imageExistsInDB(int $id_image): bool
{
    return (bool) Db::getInstance()->getValue(
        'SELECT id_image FROM ' . _DB_PREFIX_ . 'image WHERE id_image = ' . $id_image
    );
}

function deleteFiles(array $files): void
{
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

function deleteSql(int $id_image): void
{
    $tables = ['image', 'image_shop', 'image_lang'];
    foreach ($tables as $table) {
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . $table . ' WHERE id_image = ' . $id_image
        );
    }
}

// ── Output ─────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PS Image Cleaner</title>
<style>

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0d0f14;
    --surface: #151820;
    --border: #252a35;
    --accent-soft: #4ade80;
    --accent-clean: #f87171;
    --accent-neutral: #60a5fa;
    --text: #e2e8f0;
    --muted: #64748b;
    --radius: 10px;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    min-height: 100vh;
    padding: 2rem 1.5rem;
  }

  .container { max-width: 860px; margin: 0 auto; }

  header { margin-bottom: 2rem; }
  header h1 { sans-serif; font-weight: 800; font-size: 2rem; letter-spacing: -0.03em; }
  header span { color: var(--accent-neutral); }
  .subtitle { color: var(--muted); margin-top: .35rem; font-size: .85rem; }

  .mode-bar {
    display: flex;
    gap: .75rem;
    margin-bottom: 1.75rem;
  }
  .mode-btn {
    padding: .55rem 1.25rem;
    border-radius: 999px;
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    text-decoration: none;
    border: 1.5px solid var(--border);
    color: var(--muted);
    transition: all .2s;
  }
  .mode-btn.soft.active  { border-color: var(--accent-soft);  color: var(--accent-soft);  background: rgba(74,222,128,.08); }
  .mode-btn.clean.active { border-color: var(--accent-clean); color: #fff; background: rgba(248,113,113,.25); box-shadow: 0 0 14px rgba(248,113,113,.4); }
  .mode-btn.clean { border-color: var(--accent-clean); color: #fff; background: rgba(248,113,113,.25); box-shadow: 0 0 14px rgba(248,113,113,.4); }

  .mode-btn:hover:not(.active) { border-color: #3d4655; color: var(--text); }

  .stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.75rem;
  }
  .stat {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.1rem 1.25rem;
  }
  .stat-value {  sans-serif; font-size: 2rem; font-weight: 800; line-height: 1; }
  .stat-label { color: var(--muted); font-size: .75rem; margin-top: .4rem; text-transform: uppercase; letter-spacing: .06em; }
  .stat-value.warn  { color: var(--accent-clean); }
  .stat-value.ok    { color: var(--accent-soft); }
  .stat-value.info  { color: var(--accent-neutral); }

  .mode-badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .35rem .85rem;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: 1rem;
  }
  .mode-badge.soft  { background: rgba(74,222,128,.12);  color: var(--accent-soft);  border: 1px solid rgba(74,222,128,.3); }
  .mode-badge.clean { background: rgba(248,113,113,.18); color: #fff; border: 2px solid var(--accent-clean); font-size: .85rem; padding: .5rem 1.2rem; box-shadow: 0 0 18px rgba(248,113,113,.35); animation: pulse-red 1.8s ease-in-out infinite; }
  @keyframes pulse-red {
    0%, 100% { box-shadow: 0 0 12px rgba(248,113,113,.3); }
    50%       { box-shadow: 0 0 28px rgba(248,113,113,.7); }
  }

  table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    font-size: .82rem;
  }
  thead th {
    text-align: left;
    padding: .75rem 1rem;
    background: #1c2030;
    color: var(--muted);
    font-weight: 500;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .07em;
    border-bottom: 1px solid var(--border);
  }
  tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(255,255,255,.02); }
  td { padding: .65rem 1rem; color: var(--text); }
  .tag {
    display: inline-block;
    padding: .2rem .55rem;
    border-radius: 4px;
    font-size: .7rem;
    font-weight: 600;
    letter-spacing: .05em;
  }
  .tag-db   { background: rgba(248,113,113,.15); color: var(--accent-clean); }
  .tag-file { background: rgba(96,165,250,.15);  color: var(--accent-neutral); }
  .path { color: var(--muted); font-family: monospace; font-size: .72rem; word-break: break-all; }

  .empty {
    text-align: center;
    padding: 3rem;
    color: var(--muted);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }
  .empty .icon { font-size: 2.5rem; margin-bottom: .75rem; }
  .empty strong { display: block; color: var(--accent-soft);font-size: 1.1rem; }
</style>
</head>
<body>
<div class="container">

  <header>
    <h1>PS Image <span>Cleaner</span></h1>
    <p class="subtitle">PrestaShop 1.7 / 8.x — unused image detector &amp; remover</p>
  </header>

  <div class="mode-bar">
    <a href="?mode=soft"  class="mode-btn soft  <?= $mode === 'soft'  ? 'active' : '' ?>">🔍 Soft mode</a>
    <a href="?mode=clean" class="mode-btn clean <?= $mode === 'clean' ? 'active' : '' ?>">🗑️ Clean mode</a>
  </div>

  <div class="stats">
    <div class="stat">
      <div class="stat-value info"><?= $total_db ?></div>
      <div class="stat-label">Images in DB</div>
    </div>
    <div class="stat">
      <div class="stat-value <?= $unused_count > 0 ? 'warn' : 'ok' ?>"><?= $unused_count ?></div>
      <div class="stat-label">Unused images</div>
    </div>
    <div class="stat">
      <div class="stat-value <?= $files_affected > 0 ? 'warn' : 'ok' ?>"><?= $files_affected ?></div>
      <div class="stat-label"><?= $is_clean ? 'Folders deleted' : 'Folders to delete' ?></div>
    </div>
    <div class="stat">
      <div class="stat-value <?= $total_bytes > 0 ? 'warn' : 'ok' ?>"><?= formatBytes($total_bytes) ?></div>
      <div class="stat-label"><?= $is_clean ? 'Space freed' : 'Space to free' ?></div>
    </div>
  </div>

  <div class="mode-badge <?= $mode ?>">
    <?= $is_clean ? '🗑️ Clean mode — changes applied' : '🔍 Soft mode — preview only, no changes made' ?>
  </div>

  <?php if (empty($results)): ?>
    <div class="empty">
      <div class="icon">✅</div>
      <strong>All clean!</strong>
      No unused images found. Nothing to remove.
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>ID Image</th>
          <th>ID Product</th>
          <th>Type</th>
          <th>Files</th>
          <th>Size</th>
          <th>Path</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= $r['product'] ?></td>
          <td>
            <?php if ($r['type'] === 'full'): ?>
              <span class="tag tag-db">DB + Files</span>
            <?php else: ?>
              <span class="tag tag-file">Files only</span>
            <?php endif; ?>
          </td>
          <td><?= $r['files'] ?></td>
          <td><?= formatBytes($r['size']) ?></td>
          <td class="path"><?= htmlspecialchars($r['path']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>
</body>
</html>
