<?php
/**
 * Сітка товарів для каталогу, тем і брендів. Те, чого немає, не стоїть урозкид
 * між тим, що є: спершу все, що можна купити, а нижче окремим тихим блоком —
 * «зараз немає», з пропозицією повідомити, щойно зʼявиться.
 * @var array $products; @var string $cols ('' або 'pgrid-3')
 */
[$buy, $out] = Catalog::splitByAvail($products);
$cols = $cols ?? '';
?>
<?php if ($buy): ?>
  <div class="pgrid <?= e($cols) ?>"><?php foreach ($buy as $prod) echo View::partial('partials/m_card', ['prod' => $prod]); ?></div>
<?php endif; ?>
<?php if ($out): ?>
  <?php $onlyServices = !array_filter($out, fn($p) => !Catalog::isService($p)); ?>
  <div class="mz-out-head<?= $buy ? '' : ' is-first' ?>">
    <?php if ($onlyServices): ?>
      <h2>Тимчасово недоступно</h2>
      <p>Відкрийте послугу й натисніть «Повідомити» — напишемо, щойно відновимо.</p>
    <?php else: ?>
      <h2>Зараз немає в наявності</h2>
      <p>Готуємо нову партію. Відкрийте товар і натисніть «Повідомити» — напишемо, щойно зʼявиться.</p>
    <?php endif; ?>
  </div>
  <div class="pgrid <?= e($cols) ?> pgrid-out"><?php foreach ($out as $prod) echo View::partial('partials/m_card', ['prod' => $prod]); ?></div>
<?php endif; ?>
