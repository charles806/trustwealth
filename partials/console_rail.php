<?php
declare(strict_types=1);

$nav ??= [];
$activeNav ??= '';
$admin = $admin ?? false;
$base ??= str_repeat('../', count(array_filter(explode('/', trim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/')))));
?>
<aside class="rail">
    <span class="rail-brand"><span class="mark"></span>Trust&thinsp;Wealth</span>

    <nav class="rail-nav" aria-label="Console sections">
        <?php foreach ($nav as [$href, $icon, $label, $slug]) : ?>
            <a class="rail-link<?= $slug === $activeNav ? ' is-active' : '' ?>" href="<?= $base ?><?= e($href) ?>">
                <i class="fa-solid <?= e($icon) ?>"></i><?= e($label) ?>
            </a>
        <?php endforeach; ?>
        <?php if ($admin) : ?>
            <a class="rail-link<?= $activeNav === 'admin' ? ' is-active' : '' ?>" href="<?= $base ?>admin/index.php">
                <i class="fa-solid fa-shield-halved"></i>Admin
            </a>
        <?php endif; ?>
    </nav>

    <div class="rail-foot">
        <div class="rail-user">
            <span class="avatar"><?= e(strtoupper(substr($user['fullname'], 0, 1))) ?></span>
            <div class="rail-user-meta">
                <strong><?= e($user['fullname']) ?></strong>
                <span>@<?= e($user['username']) ?><?= (int) ($user['is_admin'] ?? 0) === 1 ? ' · admin' : '' ?></span>
            </div>
        </div>
        <a href="<?= $base ?>logout.php" class="btn btn-ghost rail-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i>Log out</a>
    </div>
</aside>