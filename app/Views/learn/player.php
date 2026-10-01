<?php
/** @var array $prod, $lessons, $progress; @var ?array $current; @var bool $open, $bunny_ok; @var int $done, $total */
$payload = array_map(fn($l) => [
    'id' => (int)$l['id'], 'title' => $l['title'],
    'watched' => !empty($progress[(int)$l['id']]['watched']),
    'locked' => !$open && empty($l['free_preview']),
], $lessons);
$pct = $total ? (int)round($done / $total * 100) : 0;
?>
<link rel="stylesheet" href="<?= e(asset_v('css/learn.css')) ?>">
<section class="ln" id="learn"
         data-csrf="<?= e(Csrf::token()) ?>"
         data-lesson-url="<?= e(url('/learn/lesson')) ?>"
         data-progress-url="<?= e(url('/learn/progress')) ?>"
         data-watched-url="<?= e(url('/learn/watched')) ?>"
         data-note-url="<?= e(url('/learn/note')) ?>"
         data-current="<?= (int)($current['id'] ?? 0) ?>"
         data-lessons="<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="ln-top">
    <a class="ln-back" href="<?= e(url('/my-account/my-course')) ?>">← Мої курси</a>
    <h1 class="ln-course"><?= e($prod['name']) ?></h1>
    <div class="ln-progress" title="Переглянуто відео">
      <div class="ln-bar"><i id="lnBar" style="width:<?= $pct ?>%"></i></div>
      <span><b id="lnDone"><?= (int)$done ?></b> з <?= (int)$total ?> переглянуто</span>
    </div>
  </div>

  <?php if (!$bunny_ok && Auth::isAdmin()): ?>
    <div class="ln-warn">Адміністраторові: ключ підписаних посилань Bunny ще не задано (Налаштування → Bunny). Відео відкриваються без підпису, а це безпечно лише доки в бібліотеці не ввімкнено Token Authentication.</div>
  <?php endif; ?>

  <div class="ln-grid">
    <div class="ln-main">
      <div class="ln-stage" id="lnStage">
        <iframe id="lnFrame" title="Відео курсу" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
        <div class="ln-resume" id="lnResume" hidden>
          <div class="ln-resume-box">
            <p>Ви зупинилися на <b id="lnResumeTime">0:00</b></p>
            <div>
              <button type="button" class="ln-btn ln-btn-gold" id="lnResumeYes">Продовжити</button>
              <button type="button" class="ln-btn" id="lnResumeNo">Спочатку</button>
            </div>
          </div>
        </div>
        <div class="ln-empty" id="lnEmpty">Оберіть урок зі списку</div>
      </div>

      <div class="ln-bar2">
        <h2 id="lnTitle"><?= e($current['title'] ?? '') ?></h2>
        <div class="ln-ctrl">
          <button type="button" class="ln-btn" id="lnPrev">← Попередній</button>
          <button type="button" class="ln-btn ln-mark" id="lnMark" <?= $open ? '' : 'disabled' ?>>Позначити вивченим</button>
          <button type="button" class="ln-btn ln-btn-gold" id="lnNext">Наступний →</button>
        </div>
      </div>

      <?php if ($open): ?>
      <div class="ln-notes">
        <label for="lnNote">Мої нотатки до цього відео <span class="ln-saved" id="lnSaved">збережено ✓</span></label>
        <textarea id="lnNote" rows="6" placeholder="Записуйте тут думки, терміни, часові мітки — бачите їх лише ви. Нотатки зберігаються самі."></textarea>
      </div>
      <?php endif; ?>
    </div>

    <aside class="ln-list" aria-label="Відео курсу">
      <ol id="lnList">
        <?php foreach ($lessons as $i => $l): $id = (int)$l['id']; $w = !empty($progress[$id]['watched']); $locked = !$open && empty($l['free_preview']); ?>
          <li>
            <button type="button" class="ln-item<?= $w ? ' is-done' : '' ?><?= $locked ? ' is-locked' : '' ?>" data-id="<?= $id ?>">
              <span class="ln-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <span class="ln-name"><?= e($l['title']) ?></span>
              <span class="ln-state" aria-hidden="true"><?= $locked ? '🔒' : ($w ? '✓' : '') ?></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ol>
    </aside>
  </div>
</section>
<script src="<?= e(asset_v('js/learn.js')) ?>" defer></script>
