<?php
/** @var string $message */
use function InfoLoop\Core\e;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>403</title>
<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;height:100vh;margin:0;background:#0f172a;color:#fff}div{text-align:center}h1{font-size:4rem;margin:0}</style>
</head><body><div><h1>403</h1><p><?= e($message !== '' ? $message : 'Forbidden') ?></p><p><a href="/admin" style="color:#60a5fa">Back to admin</a></p></div></body></html>
