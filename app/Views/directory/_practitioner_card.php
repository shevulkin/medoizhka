<?php /** @var array $p */ $tags = Hub::lines($p['specialties']); ?>
<a class="pp-card" href="<?= e(url('/apiterapevty/' . slug_enc($p['slug']) . '/')) ?>">
  <div class="ph"<?= $p['photo'] ? ' style="background-image:url(' . e(asset($p['photo'])) . ')"' : '' ?>></div>
  <div class="bd">
    <?php if ($p['verified']): ?><span class="verified">Перевірено</span><?php endif; ?>
    <h3><?= e($p['name']) ?></h3>
    <div class="pp-meta"><?php if ($p['title']): ?><span><?= e($p['title']) ?></span><?php endif; ?><?php if ($p['city']): ?><span><?= e($p['city']) ?></span><?php endif; ?><?php if ($p['online']): ?><span>онлайн</span><?php endif; ?></div>
    <?php if ($tags): ?><div class="pp-tags"><?php foreach (array_slice($tags, 0, 4) as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
    <?php if ($p['price_from']): ?><div class="pp-price">від <?= (int)$p['price_from'] ?> ₴</div><?php endif; ?>
  </div>
</a>
