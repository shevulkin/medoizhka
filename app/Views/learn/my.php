<?php /** @var array $items, $diplomas */ ?>
<link rel="stylesheet" href="<?= e(asset_v('css/learn.css')) ?>">
<section class="section">
  <div class="container">
    <div class="kicker">Кабінет</div>
    <h1 class="h-display">Мої відеокурси</h1>
    <?php if (!$items): ?>
      <p class="dim" style="margin-top:14px">Придбаних відеокурсів поки немає.</p>
    <?php else: ?>
      <div class="mc-grid">
        <?php foreach ($items as $c): $p = $c['product']; $pct = $c['total'] ? (int)round($c['done'] / $c['total'] * 100) : 0; ?>
          <article class="mc-card">
            <?php $photo = Catalog::photo($p); ?>
            <div class="mc-cover"><span class="mc-rh"></span><b><?= $pct ?>%</b></div>
            <div class="mc-body">
              <h2><?= e($p['name']) ?></h2>
              <div class="ln-bar"><i style="width:<?= $pct ?>%"></i></div>
              <p class="dim"><?= (int)$c['done'] ?> з <?= (int)$c['total'] ?> відео переглянуто
                <?php if ($c['expires_at'] !== null): ?> · доступ до <?= e(date('d.m.Y', strtotime((string)$c['expires_at']))) ?><?php endif; ?></p>
              <?php if ($c['expired']): ?>
                <span class="status-pill st-canceled">Строк доступу вийшов</span>
              <?php elseif ($c['next']): ?>
                <a class="ln-btn ln-btn-gold" href="<?= e(url('/' . $p['slug'] . '/?l=' . (int)$c['next']['id'])) ?>">
                  <?= $c['started'] ? 'Продовжити' : 'Почати навчання' ?>
                </a>
                <?php if ($c['started']): ?><div class="dim mc-next">Далі: <?= e($c['next']['title']) ?></div><?php endif; ?>
              <?php else: ?>
                <span class="dim">Відео ще готуються</span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($diplomas): ?>
      <h2 class="h-serif" style="margin-top:44px">Сертифікати</h2>
      <?php foreach ($diplomas as $d): ?>
        <p>№ <?= e($d['number']) ?> · <?= e(Diplomas::courseLabel($d)) ?>
           · <a href="<?= e(url('/diploma?number=' . urlencode((string)$d['number']))) ?>">перевірити</a></p>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
