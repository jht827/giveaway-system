<?php
$policy_links = [
    ['href' => '/用户协议.html', 'label' => '用户协议'],
    ['href' => '/隐私政策.html', 'label' => '隐私政策'],
];
?>
<style>
    .site-footer {
        margin-top: 24px;
        text-align: center;
        font-size: 0.9em;
        opacity: 0.8;
    }
    .site-footer a {
        color: inherit;
    }
    .site-footer .divider {
        margin: 0 8px;
        opacity: 0.6;
    }
</style>
<footer class="site-footer">
    <?php foreach ($policy_links as $index => $link): ?>
        <?php if ($index > 0): ?><span class="divider">|</span><?php endif; ?>
        <a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
            <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endforeach; ?>
</footer>
