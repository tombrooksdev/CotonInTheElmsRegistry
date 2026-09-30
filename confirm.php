<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$t = (string)($_GET['t'] ?? '');
$ok = false;
if (preg_match('/^[a-f0-9]{64}$/', $t)) {
  $st = db()->prepare('UPDATE signups SET confirmed_at = COALESCE(confirmed_at, NOW()) WHERE token = ?');
  $st->execute([$t]);
  $chk = db()->prepare('SELECT 1 FROM signups WHERE token = ? AND confirmed_at IS NOT NULL');
  $chk->execute([$t]);
  $ok = (bool)$chk->fetchColumn();
}
?>
<!doctype html>
<html lang="en-GB">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Email confirmation</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-emerald-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow p-8 max-w-md text-center">
    <?php if ($ok): ?>
      <h1 class="text-2xl font-semibold text-emerald-800">You're confirmed</h1>
      <p class="mt-3 text-slate-600">Thanks for registering. We'll be in touch as the Coton in the Elms business register takes shape.</p>
    <?php else: ?>
      <h1 class="text-2xl font-semibold text-slate-800">Link not recognised</h1>
      <p class="mt-3 text-slate-600">That confirmation link is invalid. Please try signing up again.</p>
    <?php endif; ?>
  </div>
</body>

</html>