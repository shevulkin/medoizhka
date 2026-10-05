/*
 * Редактор опису — замість HTML-коду звичайне поле з кнопками, як у листі чи Word.
 *
 * Вмикається на <textarea data-rich data-assets="…/assets/">. Сам textarea лишається
 * у формі (прихований) і отримує чистий HTML при кожній зміні — тож збереження,
 * смуга «Є незбережені зміни» й решта форми працюють як раніше.
 *
 * Що вміє: абзац / заголовок / підзаголовок, жирний, курсив, списки, посилання,
 * прибрати оформлення, скасувати / повторити. Текст, вставлений з Word, Google Docs
 * чи іншого сайту, очищається від чужих шрифтів, кольорів і класів — лишається
 * лише структура, яку сайт уміє показати (той самий перелік, що й rich() у PHP).
 *
 * Фото в описах зберігаються з маркером {assets}/ (див. bin/localize-images.php):
 * у редакторі він підміняється справжньою адресою, щоб фото було видно, а при
 * збереженні — повертається назад.
 */
(function () {
  'use strict';

  // Що лишаємо. Решту тегів розгортаємо (зберігаючи текст) або викидаємо разом із вмістом.
  var KEEP = { P: 1, BR: 1, STRONG: 1, EM: 1, U: 1, UL: 1, OL: 1, LI: 1, H2: 1, H3: 1, A: 1, BLOCKQUOTE: 1,
    IMG: 1, FIGURE: 1, FIGCAPTION: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TH: 1, TD: 1 };
  var RENAME = { B: 'STRONG', I: 'EM', H1: 'H2', H4: 'H3', H5: 'H3', H6: 'H3', DIV: 'P', SECTION: 'P', ARTICLE: 'P' };
  var DROP = { SCRIPT: 1, STYLE: 1, META: 1, LINK: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, SVG: 1, NOSCRIPT: 1, TEMPLATE: 1, TITLE: 1 };
  // Блок усередині такого батька не відкриває новий абзац — лише переносить вміст
  var NO_P_INSIDE = { P: 1, LI: 1, TD: 1, TH: 1, H2: 1, H3: 1, BLOCKQUOTE: 1, FIGCAPTION: 1, A: 1, STRONG: 1, EM: 1, U: 1 };
  var BLOCK = { P: 1, UL: 1, OL: 1, H2: 1, H3: 1, BLOCKQUOTE: 1, FIGURE: 1, TABLE: 1 };

  function safeHref(h) { return /^(https?:|\/|#|mailto:|tel:)/i.test(h || '') ? h : ''; }
  function safeSrc(s) { return /^(https?:|\/)/i.test(s || '') ? s : ''; }

  /** Чисті копії дочірніх вузлів src у dst */
  function cleanInto(src, dst, parentTag) {
    for (var n = src.firstChild; n; n = n.nextSibling) {
      if (n.nodeType === 3) { dst.appendChild(document.createTextNode(n.nodeValue)); continue; }
      if (n.nodeType !== 1) continue;
      var tag = n.tagName.toUpperCase();
      if (DROP[tag] || tag.indexOf(':') > -1) continue;            // <o:p> та інше з Word
      tag = RENAME[tag] || tag;
      if (tag === 'P' && NO_P_INSIDE[parentTag]) {                 // div у пункті списку тощо
        cleanInto(n, dst, parentTag);
        if (n.nextSibling) dst.appendChild(document.createElement('br'));
        continue;
      }
      if (!KEEP[tag]) { cleanInto(n, dst, parentTag); continue; }   // span, font… — лише вміст
      var el = document.createElement(tag);
      if (tag === 'A') {
        var href = safeHref(n.getAttribute('href'));
        if (!href) { cleanInto(n, dst, parentTag); continue; }
        el.setAttribute('href', href);
      } else if (tag === 'IMG') {
        var s = safeSrc(n.getAttribute('src'));
        if (!s) continue;                                           // data:-картинки з буфера не беремо
        el.setAttribute('src', s);
        if (n.getAttribute('alt')) el.setAttribute('alt', n.getAttribute('alt'));
      }
      if (tag !== 'IMG' && tag !== 'BR') cleanInto(n, el, tag);
      dst.appendChild(el);
    }
  }

  /** Чистий фрагмент: на верхньому рівні — лише блоки, порожні абзаци прибрано */
  function cleanHtml(html) {
    var doc = new DOMParser().parseFromString('<body>' + html + '</body>', 'text/html');
    var tmp = document.createElement('div');
    cleanInto(doc.body, tmp, 'ROOT');
    // Голий текст і рядкові теги на верхньому рівні — в абзаци
    var out = document.createElement('div'), p = null;
    while (tmp.firstChild) {
      var n = tmp.firstChild;
      if (n.nodeType === 1 && BLOCK[n.tagName]) { p = null; out.appendChild(n); continue; }
      if (n.nodeType === 3 && !n.nodeValue.trim() && !p) { tmp.removeChild(n); continue; }
      if (n.nodeType === 1 && n.tagName === 'BR' && !p) { tmp.removeChild(n); continue; }
      if (!p) { p = document.createElement('p'); out.appendChild(p); }
      p.appendChild(n);
    }
    // Порожні абзаци й заголовки (лише пробіли / <br>) — геть
    Array.prototype.slice.call(out.querySelectorAll('p,h2,h3,li,blockquote')).forEach(function (el) {
      if (!el.textContent.trim() && !el.querySelector('img')) el.parentNode.removeChild(el);
    });
    Array.prototype.slice.call(out.querySelectorAll('ul,ol')).forEach(function (el) {
      if (!el.querySelector('li')) el.parentNode.removeChild(el);
    });
    return out.innerHTML.trim();
  }

  /** Старий опис без тегів — абзаци за порожніми рядками, як і показує сайт */
  function textToHtml(t) {
    var esc = function (s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
    return t.split(/\n\s*\n/).map(function (b) { return b.trim() ? '<p>' + esc(b.trim()).replace(/\n/g, '<br>') + '</p>' : ''; }).join('');
  }

  var ICON = {
    bold: '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/>',
    italic: '<path d="M10 5h8M6 19h8M14 5l-4 14"/>',
    ul: '<path d="M10 6h10M10 12h10M10 18h10"/><circle cx="5" cy="6" r="1.2"/><circle cx="5" cy="12" r="1.2"/><circle cx="5" cy="18" r="1.2"/>',
    ol: '<path d="M10 6h10M10 12h10M10 18h10"/><path d="M4 5h1.5v4M4 9h3M4 15.5c.5-1 2.8-1 2.8.4 0 1.1-2.8 2-2.8 3.1h3"/>',
    link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    image: '<rect x="3.5" y="5" width="17" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
    clear: '<path d="M6 5h12M12 5l-3 14M4 20 20 4"/>',
    undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
    redo: '<path d="m15 14 5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/>'
  };
  function svg(k) { return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICON[k] + '</svg>'; }

  function enhance(ta) {
    var assets = ta.getAttribute('data-assets') || '';
    var toView = function (h) { return assets ? h.split('{assets}/').join(assets) : h; };
    var toStore = function (h) { return assets ? h.split(assets).join('{assets}/') : h; };

    var wrap = document.createElement('div');
    wrap.className = 'rte';
    var bar = document.createElement('div');
    bar.className = 'rte-bar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Оформлення тексту');
    bar.innerHTML =
      '<span class="rte-group">' +
        '<button type="button" data-block="p" title="Звичайний текст">Текст</button>' +
        '<button type="button" data-block="h2" title="Заголовок розділу">Заголовок</button>' +
        '<button type="button" data-block="h3" title="Підзаголовок">Підзаголовок</button>' +
      '</span><span class="rte-group">' +
        '<button type="button" data-cmd="bold" title="Жирний (Ctrl+B)">' + svg('bold') + '</button>' +
        '<button type="button" data-cmd="italic" title="Курсив (Ctrl+I)">' + svg('italic') + '</button>' +
      '</span><span class="rte-group">' +
        '<button type="button" data-cmd="insertUnorderedList" title="Список з крапками">' + svg('ul') + '</button>' +
        '<button type="button" data-cmd="insertOrderedList" title="Нумерований список">' + svg('ol') + '</button>' +
        '<button type="button" data-act="link" title="Посилання: виділіть слова й натисніть">' + svg('link') + '</button>' +
        '<button type="button" data-act="image" title="Вставити фото з медіатеки або з компʼютера">' + svg('image') + '</button>' +
        '<button type="button" data-act="clear" title="Прибрати оформлення з виділеного">' + svg('clear') + '</button>' +
      '</span><span class="rte-group">' +
        '<button type="button" data-cmd="undo" title="Скасувати (Ctrl+Z)">' + svg('undo') + '</button>' +
        '<button type="button" data-cmd="redo" title="Повторити (Ctrl+Y)">' + svg('redo') + '</button>' +
      '</span>' +
      '<button type="button" class="rte-code" data-act="code" title="Показати HTML-код — лише для досвідчених">&lt;/&gt;</button>';
    var area = document.createElement('div');
    area.className = 'rte-area prose';
    area.contentEditable = ta.disabled ? 'false' : 'true';
    area.setAttribute('role', 'textbox');
    area.setAttribute('aria-multiline', 'true');
    if (ta.getAttribute('placeholder')) area.setAttribute('data-placeholder', ta.getAttribute('placeholder'));

    var raw = ta.value.trim();
    var start = raw === '' ? '' : (/<[a-z][\s\S]*>/i.test(raw) ? cleanHtml(toView(raw)) : textToHtml(raw));
    area.innerHTML = start;
    ta.value = toStore(start);          // нормалізований вигляд — без події, тож форма не «брудна»

    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(bar);
    wrap.appendChild(area);
    wrap.appendChild(ta);
    ta.classList.add('rte-src');
    if (ta.disabled) bar.hidden = true;

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); document.execCommand('styleWithCSS', false, false); } catch (e) {}

    var codeMode = false;
    function sync() {
      if (codeMode) return;
      var html = cleanHtml(area.innerHTML);
      ta.value = toStore(html);
      wrap.classList.toggle('is-empty', html === '');
    }
    sync();

    function exec(cmd, val) { area.focus(); document.execCommand(cmd, false, val || null); sync(); state(); area.dispatchEvent(new Event('input', { bubbles: true })); }

    function state() {
      if (codeMode) return;
      var block = '';
      try { block = String(document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, ''); } catch (e) {}
      Array.prototype.forEach.call(bar.querySelectorAll('[data-block]'), function (b) {
        b.classList.toggle('on', b.getAttribute('data-block') === (block === 'div' || block === '' ? 'p' : block));
      });
      Array.prototype.forEach.call(bar.querySelectorAll('[data-cmd]'), function (b) {
        var c = b.getAttribute('data-cmd');
        if (c === 'undo' || c === 'redo') return;
        var on = false; try { on = document.queryCommandState(c); } catch (e) {}
        b.classList.toggle('on', on);
      });
    }

    bar.addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); }); // не губимо виділення
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      if (b.hasAttribute('data-block')) return exec('formatBlock', '<' + b.getAttribute('data-block') + '>');
      if (b.hasAttribute('data-cmd')) return exec(b.getAttribute('data-cmd'));
      var act = b.getAttribute('data-act');
      if (act === 'clear') { exec('removeFormat'); exec('unlink'); return exec('formatBlock', '<p>'); }
      if (act === 'image') {
        // Вікно вибору фото — те саме, що й у картці товару: обрати з медіатеки або завантажити з компʼютера
        if (!window.MediaPicker || !MediaPicker.open) { alert('Вибір фото тут недоступний.'); return; }
        var saved = null;   // позиція курсора, бо вікно вибору забирає фокус
        var selNow = window.getSelection();
        if (selNow.rangeCount && area.contains(selNow.anchorNode)) saved = selNow.getRangeAt(0).cloneRange();
        MediaPicker.open(function (path) {
          var base = assets || ((window.BOFU && BOFU.base ? BOFU.base : '/').replace(/\/$/, '') + '/assets/');
          if (base.slice(-1) !== '/') base += '/';
          var nameInput = document.querySelector('input[name="name"]');
          var alt = prompt('Короткий опис фото (для пошуку й для незрячих; можна лишити порожнім):', nameInput ? nameInput.value : '');
          if (alt === null) return;
          area.focus();
          if (saved) { var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(saved); }
          var esc = function (t) { return t.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); };
          exec('insertHTML', '<img src="' + esc(base + path) + '" alt="' + esc(alt) + '">');
        });
        return;
      }
      if (act === 'link') {
        var sel = window.getSelection();
        var inLink = sel.anchorNode && (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentNode).closest('a');
        if (inLink && area.contains(inLink)) return exec('unlink');
        if (!sel.toString().trim()) { alert('Спершу виділіть слова, які мають стати посиланням.'); return; }
        var href = prompt('Адреса посилання (наприклад, https://medoizhka.com/... або /product/...):', 'https://');
        if (!href || href === 'https://') return;
        href = href.trim();
        if (!safeHref(href)) href = 'https://' + href.replace(/^\/+/, '');
        return exec('createLink', href);
      }
      if (act === 'code') {
        if (!codeMode) sync();               // у код — актуальний вміст редактора
        codeMode = !codeMode;
        wrap.classList.toggle('is-code', codeMode);
        b.classList.toggle('on', codeMode);
        if (codeMode) { ta.focus(); return; }
        area.innerHTML = cleanHtml(toView(ta.value));   // назад — те, що наредагували в коді
        sync();
      }
    });

    area.addEventListener('input', function () { sync(); });
    area.addEventListener('keyup', state);
    area.addEventListener('mouseup', state);
    area.addEventListener('focus', state);

    // Вставка: з Word і сайтів — лише структура; простий текст — абзацами
    area.addEventListener('paste', function (e) {
      var cd = e.clipboardData; if (!cd) return;
      e.preventDefault();
      var html = cd.getData('text/html');
      var clean = html ? cleanHtml(html.replace(/^[\s\S]*<body[^>]*>|<\/body>[\s\S]*$/gi, '')) : textToHtml(cd.getData('text/plain') || '');
      if (clean) exec('insertHTML', clean);
    });
    // Перетягнуті файли редактор вбудовує як data:-картинки на мегабайти — не приймаємо
    area.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) e.preventDefault(); });

    var form = ta.form;
    if (form) form.addEventListener('submit', function () { if (!codeMode) sync(); });
  }

  document.querySelectorAll('textarea[data-rich]').forEach(enhance);
})();
