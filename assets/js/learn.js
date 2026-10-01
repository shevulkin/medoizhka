/*
 * Плеєр курсу: вибір уроку, продовження з місця зупинки, «вивчено», особисті нотатки.
 * Відео — Bunny Stream в iframe; позицію беремо з подій плеєра (postMessage, Player.js).
 * Позиція йде на сервер щоп'ять секунд і при виході зі сторінки, тож на іншому пристрої
 * людина продовжить з того ж місця.
 */
(function () {
  'use strict';
  var root = document.getElementById('learn');
  if (!root) return;

  var csrf = root.dataset.csrf;
  var lessons = JSON.parse(root.dataset.lessons || '[]');
  var frame = document.getElementById('lnFrame');
  var stage = document.getElementById('lnStage');
  var empty = document.getElementById('lnEmpty');
  var resume = document.getElementById('lnResume');
  var resumeTime = document.getElementById('lnResumeTime');
  var titleEl = document.getElementById('lnTitle');
  var markBtn = document.getElementById('lnMark');
  var prevBtn = document.getElementById('lnPrev');
  var nextBtn = document.getElementById('lnNext');
  var noteEl = document.getElementById('lnNote');
  var savedEl = document.getElementById('lnSaved');
  var doneEl = document.getElementById('lnDone');
  var barEl = document.getElementById('lnBar');
  var items = root.querySelectorAll('.ln-item');

  var cur = null;          // поточний урок (обʼєкт із lessons)
  var pendingSeek = 0;     // секунда, з якої пропонуємо продовжити
  var lastSec = 0, lastSaved = 0;
  var noteTimer = null, noteDirty = false;

  function fmt(s) {
    var h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), x = s % 60;
    return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(x).padStart(2, '0');
  }
  function post(url, data, beacon) {
    var body = new URLSearchParams(data); body.set('_csrf', csrf);
    if (beacon && navigator.sendBeacon) { navigator.sendBeacon(url, body); return Promise.resolve(); }
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true })
      .then(function (r) { return r.json(); });
  }
  function byId(id) { for (var i = 0; i < lessons.length; i++) if (lessons[i].id === id) return lessons[i]; return null; }
  function idx(id) { for (var i = 0; i < lessons.length; i++) if (lessons[i].id === id) return i; return -1; }

  function send(msg) { try { frame.contentWindow.postMessage(JSON.stringify(msg), '*'); } catch (e) {} }
  function playerJs(method, value) { send({ context: 'player.js', version: '0.0.11', method: method, value: value }); }

  function saveNow(beacon) {
    if (cur && lastSec > 0 && lastSec !== lastSaved) {
      lastSaved = lastSec;
      post(root.dataset.progressUrl, { lesson: cur.id, position: lastSec }, beacon);
    }
  }
  function flushNote(beacon) {
    if (!noteEl || !cur || !noteDirty) return;
    noteDirty = false;
    post(root.dataset.noteUrl, { lesson: cur.id, note: noteEl.value }, beacon).then(function () {
      if (savedEl) { savedEl.classList.add('on'); setTimeout(function () { savedEl.classList.remove('on'); }, 1800); }
    });
  }

  function paintMark(watched) {
    if (!markBtn) return;
    markBtn.textContent = watched ? '✓ Вивчено' : 'Позначити вивченим';
    markBtn.classList.toggle('on', watched);
  }
  function paintList() {
    items.forEach(function (b) {
      var l = byId(+b.dataset.id);
      b.classList.toggle('is-active', cur && l.id === cur.id);
      b.classList.toggle('is-done', !!l.watched);
      var st = b.querySelector('.ln-state');
      if (st && !l.locked) st.textContent = l.watched ? '✓' : '';
    });
  }
  function paintProgress(done, total) {
    if (doneEl) doneEl.textContent = done;
    if (barEl) barEl.style.width = (total ? Math.round(done / total * 100) : 0) + '%';
  }
  function paintNav() {
    var i = idx(cur.id);
    prevBtn.disabled = i <= 0;
    nextBtn.disabled = i >= lessons.length - 1;
  }

  function open(id, push) {
    var l = byId(id);
    if (!l) return;
    if (l.locked) { alert('Цей урок відкривається після придбання курсу.'); return; }
    saveNow(true); flushNote(true);
    cur = l; lastSec = 0; lastSaved = 0;
    titleEl.textContent = l.title;
    if (push !== false) { try { history.replaceState(null, '', location.pathname + '?l=' + l.id); } catch (e) {} }
    paintList(); paintNav();
    empty.style.display = 'none';
    resume.hidden = true;

    fetch(root.dataset.lessonUrl + '?id=' + l.id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!cur || cur.id !== d.id) return;
        if (d.error) { empty.style.display = 'flex'; empty.textContent = d.error; return; }
        frame.src = d.embed;
        l.watched = d.watched; paintMark(d.watched); paintList();
        if (noteEl) { noteEl.value = d.note || ''; noteDirty = false; }
        if (d.position > 10) {
          pendingSeek = d.position;
          resumeTime.textContent = fmt(d.position);
          resume.hidden = false;
        }
      })
      .catch(function () { empty.style.display = 'flex'; empty.textContent = 'Не вдалося завантажити урок. Оновіть сторінку.'; });
  }

  // Слухаємо плеєр. Підтримуємо обидва формати подій: Player.js і власний Bunny.
  frame.addEventListener('load', function () {
    playerJs('addEventListener', 'timeupdate');
    playerJs('addEventListener', 'ended');
  });
  window.addEventListener('message', function (e) {
    if (e.origin !== 'https://iframe.mediadelivery.net') return;
    var d = e.data;
    try { if (typeof d === 'string') d = JSON.parse(d); } catch (err) { return; }
    if (!d || !cur) return;
    var ev = String(d.event || '').replace('player:', '');
    if (ev === 'timeupdate') {
      var v = d.value;
      var sec = Math.floor(typeof v === 'object' && v ? (v.seconds || 0) : (v || 0));
      if (sec > 0) {
        lastSec = sec;
        if (sec - lastSaved >= 5 || sec < lastSaved) saveNow(false);
        // дивиться до кінця — зараховуємо: понад 92% тривалості
        if (v && v.duration && sec / v.duration > 0.92 && !cur.watched) setWatched(true);
      }
    } else if (ev === 'ended') {
      if (!cur.watched) setWatched(true);
      saveNow(false);
    }
  });

  function setWatched(state) {
    var l = cur; if (!l) return;
    post(root.dataset.watchedUrl, { lesson: l.id, state: state ? 1 : 0 }).then(function (r) {
      if (!r || r.error) return;
      l.watched = r.watched; if (cur && cur.id === l.id) paintMark(r.watched);
      paintList(); paintProgress(r.done, r.total);
    });
  }

  document.getElementById('lnResumeYes').addEventListener('click', function () {
    resume.hidden = true;
    playerJs('setCurrentTime', pendingSeek);
    send({ event: 'command:seek', value: pendingSeek });
    playerJs('play'); send({ event: 'command:play' });
  });
  document.getElementById('lnResumeNo').addEventListener('click', function () {
    resume.hidden = true; playerJs('play'); send({ event: 'command:play' });
  });

  items.forEach(function (b) { b.addEventListener('click', function () { open(+b.dataset.id); }); });
  prevBtn.addEventListener('click', function () { var i = idx(cur.id); if (i > 0) open(lessons[i - 1].id); });
  nextBtn.addEventListener('click', function () { var i = idx(cur.id); if (i < lessons.length - 1) open(lessons[i + 1].id); });
  if (markBtn) markBtn.addEventListener('click', function () { if (cur) setWatched(!cur.watched); });
  if (noteEl) {
    noteEl.addEventListener('input', function () {
      noteDirty = true; clearTimeout(noteTimer); noteTimer = setTimeout(function () { flushNote(false); }, 1000);
    });
  }
  window.addEventListener('pagehide', function () { saveNow(true); flushNote(true); });
  document.addEventListener('visibilitychange', function () { if (document.hidden) { saveNow(true); flushNote(true); } });

  var start = byId(+root.dataset.current);
  if (start) open(start.id, false);
})();
