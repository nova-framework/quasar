<div class="row page-header">
    <h1>Whoops!</h1>
</div>

<div class="row">
    <p>
        <?= $exception->getMessage(); ?> in <?= $exception->getFile(); ?> on line <?= $exception->getLine(); ?>
    </p>
    <br>
    <pre><?= $exception->getTraceAsString(); ?></pre>
</div>
