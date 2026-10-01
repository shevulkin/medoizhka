<?php /** @var array $p */ ?>
<a class="pp-card" href="<?= e(url('/pasiky/' . slug_enc($p['slug']) . '/')) ?>">
  <div class="ph"<?= $p['photo'] ? ' style="background-image:url(' . e(asset($p['photo'])) . ')"' : '' ?>></div>
  <div class="bd">
    <span class="mh-badge" style="align-self:flex-start"><?= e(Hub::kindLabel($p['kind'])) ?></span>
    <h3><?= e($p['name']) ?></h3>
    <div class="pp-meta"><?php if ($p['city']): ?><span><?= e($p['city']) ?><?= $p['region'] ? ', ' . e($p['region']) : '' ?></span><?php endif; ?><?php if ($p['duration']): ?><span><?= e($p['duration']) ?></span><?php endif; ?></div>
    <?php if ($p['summary']): ?><p class="dim" style="font-size:14px"><?= e($p['summary']) ?></p><?php endif; ?>
    <?php if ($p['price']): ?><div class="pp-price">від <?= (int)$p['price'] ?> ₴<?= $p['price_note'] ? ' <small style="font-weight:600;color:var(--dim)">' . e($p['price_note']) . '</small>' : '' ?></div><?php endif; ?>
  </div>
</a>
