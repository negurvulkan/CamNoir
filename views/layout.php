<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_text($title ?? 'NRW Noir Cam') ?></title>
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#050509">
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
</head>
<body>
    <div class="container">
        <?= $content ?>
    </div>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= base_url('service-worker.js') ?>').catch(console.error);
    }
    </script>
</body>
</html>
