<?php
/** @var array $c  категорія */
$pub = url('/product-category/' . slug_enc((string)$c['slug']) . '/');
?>
<div class="admin-head">
  <h1 class="h-serif">Категорія «<?= e($c['name']) ?>»</h1>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/categories')) ?>">← До списку</a>
</div>
<form class="admin-card" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/categories')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="edit_save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
  <div style="display:grid;grid-template-columns:1fr 280px;gap:22px" data-rg>
    <div>
      <div class="field"><label>Назва</label><input type="text" name="name" value="<?= e($c['name']) ?>" required></div>
      <div class="field"><label>Адреса сторінки (латиницею)</label>
        <input type="text" name="slug" value="<?= e($c['slug']) ?>" autocomplete="off">
        <p class="dim" style="margin:6px 0 0;font-size:13px">Зараз: <a href="<?= e($pub) ?>" target="_blank" rel="noopener"><?= e($pub) ?></a><br>
          Можна вписати й українською, сайт сам зробить латиницю з дефісами. Стара адреса після зміни автоматично перенаправляє на нову (301), тож пошук і посилання не зламаються.</p></div>
      <div class="field"><label>Опис категорії</label><textarea name="description" rows="5"><?= e((string)($c['description'] ?? '')) ?></textarea>
        <p class="dim" style="margin:6px 0 0;font-size:13px">Використовується для пошуку, якщо SEO-опис нижче порожній.</p></div>
      <div class="field"><label>SEO-заголовок</label><input type="text" name="seo_title" value="<?= e((string)($c['seo_title'] ?? '')) ?>" maxlength="70" placeholder="Порожньо: «Назва - Медоїжка»"></div>
      <div class="field"><label>SEO-опис</label><textarea name="seo_desc" rows="3" maxlength="170" placeholder="До 160 символів, для видачі Google"><?= e((string)($c['seo_desc'] ?? '')) ?></textarea></div>
    </div>
    <div>
      <label style="display:block;margin-bottom:8px">Фото категорії</label>
      <?php if (!empty($c['image'])): ?>
        <div style="background:var(--bg3);border-radius:8px;padding:8px;text-align:center;margin-bottom:8px">
          <img src="<?= e(asset($c['image'])) ?>" alt="<?= e($c['name']) ?>" style="max-width:100%;max-height:200px;border-radius:6px">
        </div>
        <button class="btn btn-line btn-xs" type="submit" form="catImgRemove" style="width:100%;margin-bottom:8px">Прибрати фото</button>
      <?php else: ?>
        <p class="dim" style="margin:0 0 8px">Фото немає. Воно показується на головній і в списку розділів.</p>
      <?php endif; ?>
      <span class="file-field">
        <input type="file" name="image" accept="image/*">
        <button class="btn btn-line btn-xs" type="button" data-file-btn>Обрати фото з комп'ютера</button>
        <span class="file-name" data-empty="Файл не обрано">Файл не обрано</span>
      </span>
      <p class="dim" style="margin:8px 0 0;font-size:12.5px">JPEG, PNG, GIF або WebP до 15 МБ. Велике фото сайт зменшить сам.</p>
    </div>
  </div>
  <div class="admin-save"><button class="btn btn-gold" type="submit">💾 Зберегти</button></div>
</form>
<form id="catImgRemove" method="post" action="<?= e(url('/admin/categories')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="image_remove"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
</form>