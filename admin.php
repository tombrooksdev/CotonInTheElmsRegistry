<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https','samesite' => 'Strict']);
session_start();

$e = fn(?string $s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

$error = '';
if (($_POST['action'] ?? '') === 'login') {
    usleep(500000);
    if (password_verify((string)($_POST['password'] ?? ''), cfg()['admin_password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php'); exit;
    }
    $error = 'Wrong password.';
}

if (empty($_SESSION['admin'])) {
    ?><!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
    <title>Admin</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="min-h-screen bg-slate-100 flex items-center justify-center">
    <form method="post" class="bg-white p-8 rounded-2xl shadow w-80 space-y-4">
      <h1 class="text-xl font-semibold">Register admin</h1>
      <?php if ($error): ?><p class="text-red-600 text-sm"><?= $e($error) ?></p><?php endif; ?>
      <input type="hidden" name="action" value="login">
      <input type="password" name="password" required autofocus class="w-full border rounded-lg px-3 py-2" placeholder="Password">
      <button class="w-full bg-emerald-700 text-white rounded-lg py-2">Log in</button>
    </form></body></html><?php
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$where = match ($filter) {
    'confirmed' => 'WHERE confirmed_at IS NOT NULL',
    'marketing' => 'WHERE confirmed_at IS NOT NULL AND marketing_consent = 1',
    default => '',
};
$rows = db()->query("SELECT id, name, email, business, description, marketing_consent, confirmed_at, created_at
                     FROM signups $where ORDER BY created_at DESC")->fetchAll();

if (isset($_GET['csv'])) {
    // Stop spreadsheet formula injection from user-supplied cells
    $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="coton-register-' . $filter . '-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, ['Name', 'Email', 'Business', 'Description', 'Marketing consent', 'Confirmed', 'Signed up']);
    foreach ($rows as $r) {
        fputcsv($f, [$safe($r['name']), $safe($r['email']), $safe($r['business']), $safe($r['description']),
            $r['marketing_consent'] ? 'Yes' : 'No', $r['confirmed_at'] ?? '', $r['created_at']]);
    }
    exit;
}

$total = count($rows);
?><!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Register admin</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-100 p-4 md:p-8">
<div class="max-w-6xl mx-auto">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-2xl font-semibold">Signups (<?= $total ?>)</h1>
    <div class="flex gap-2 text-sm">
      <?php foreach (['all' => 'All', 'confirmed' => 'Confirmed', 'marketing' => 'Confirmed + marketing'] as $k => $label): ?>
        <a href="?filter=<?= $k ?>" class="px-3 py-1.5 rounded-lg <?= $filter === $k ? 'bg-emerald-700 text-white' : 'bg-white' ?>"><?= $label ?></a>
      <?php endforeach; ?>
      <a href="?csv=1&filter=<?= $e($filter) ?>" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white">Download CSV</a>
      <a href="?logout=1" class="px-3 py-1.5 rounded-lg bg-white">Log out</a>
    </div>
  </div>
  <div class="bg-white rounded-2xl shadow overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left"><tr>
        <th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Business</th><th class="p-3">Description</th>
        <th class="p-3">Marketing</th><th class="p-3">Confirmed</th><th class="p-3">Signed up</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t align-top">
          <td class="p-3"><?= $e($r['name']) ?></td><td class="p-3"><?= $e($r['email']) ?></td>
          <td class="p-3"><?= $e($r['business']) ?></td><td class="p-3 max-w-xs"><?= $e($r['description']) ?></td>
          <td class="p-3"><?= $r['marketing_consent'] ? 'Yes' : 'No' ?></td>
          <td class="p-3"><?= $r['confirmed_at'] ? 'Yes' : 'No' ?></td>
          <td class="p-3 whitespace-nowrap"><?= $e($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></body></html>
