/* ZT Continental Trans — JavaScript (vanilla) */
(function () {
  'use strict';

  // --- Menu mobile ---
  document.querySelectorAll('[data-nav-toggle]').forEach(function (el) {
    el.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
  });
  document.querySelectorAll('#sidebar a').forEach(function (a) {
    a.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
  });

  // --- Onglets (sans rechargement), mémorisés dans l'URL (#tab) ---
  var tabs = document.querySelectorAll('[data-tab]');
  function showTab(name) {
    var found = false;
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.classList.toggle('active', on);
      if (on) found = true;
    });
    document.querySelectorAll('[data-panel]').forEach(function (p) {
      p.classList.toggle('active', p.getAttribute('data-panel') === name);
    });
    return found;
  }
  if (tabs.length) {
    tabs.forEach(function (t) {
      t.addEventListener('click', function (ev) {
        ev.preventDefault();
        var n = t.getAttribute('data-tab');
        showTab(n);
        if (history.replaceState) history.replaceState(null, '', '#' + n);
      });
    });
    var initial = (location.hash || '').replace('#', '') || (document.querySelector('[data-default-tab]') || document.body).getAttribute('data-default-tab') || tabs[0].getAttribute('data-tab');
    if (!showTab(initial)) showTab(tabs[0].getAttribute('data-tab'));
  }

  // --- Formulaire camion : champs de location affichés seulement si « Location » ---
  var own = document.querySelector('[data-rental-toggle]');
  if (own) {
    var box = document.querySelector('[data-rental-fields]');
    var sync = function () { box.classList.toggle('hidden', own.value !== 'rented'); };
    own.addEventListener('change', sync); sync();
  }

  // --- Confirmation avant action sensible ---
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });

  // --- Empêche le double envoi ---
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) return;
      var btn = f.querySelector('button[type=submit][data-loading]');
      if (btn) { btn.disabled = true; btn.setAttribute('data-label', btn.textContent); btn.textContent = btn.getAttribute('data-loading'); }
    });
  });

  // --- Sélecteur de photos : appareil photo (capture) ou galerie, avec aperçus ---
  document.querySelectorAll('[data-photo-picker]').forEach(function (root) {
    var max = parseInt(root.getAttribute('data-max') || '10', 10);
    var maxBytes = parseInt(root.getAttribute('data-max-bytes') || '0', 10);
    var target = root.querySelector('input[type=file][name]');      // champ réellement envoyé
    var camera = root.querySelector('[data-input=camera]');
    var gallery = root.querySelector('[data-input=gallery]');
    var previews = root.querySelector('.photo-previews');
    var count = root.querySelector('.photo-count');
    var errBox = root.querySelector('.photo-error');
    var files = [];

    function error(msg) { if (errBox) { errBox.textContent = msg; errBox.classList.toggle('hidden', !msg); } }

    function sync() {
      // Reconstruit la liste de fichiers du champ envoyé au serveur
      var dt = new DataTransfer();
      files.forEach(function (f) { dt.items.add(f); });
      target.files = dt.files;
      previews.innerHTML = '';
      files.forEach(function (f, i) {
        var d = document.createElement('div');
        d.className = 'preview';
        var img = document.createElement('img');
        img.alt = 'Photo ' + (i + 1);
        img.src = URL.createObjectURL(f);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        var b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', 'Retirer la photo ' + (i + 1));
        b.innerHTML = '&times;';
        b.addEventListener('click', function () { files.splice(i, 1); sync(); });
        d.appendChild(img); d.appendChild(b);
        previews.appendChild(d);
      });
      if (count) count.textContent = files.length + ' photo' + (files.length > 1 ? 's' : '') + ' sur ' + max + ' maximum';
    }

    function add(list) {
      error('');
      Array.prototype.forEach.call(list, function (f) {
        if (!/^image\//.test(f.type)) { error('« ' + f.name + ' » n\'est pas une image.'); return; }
        if (maxBytes && f.size > maxBytes) { error('« ' + f.name + ' » est trop volumineuse.'); return; }
        if (files.length >= max) { error('Maximum ' + max + ' photos.'); return; }
        files.push(f);
      });
      sync();
    }

    [camera, gallery].forEach(function (inp) {
      if (!inp) return;
      inp.addEventListener('change', function () { add(inp.files); inp.value = ''; });
    });
    root.querySelectorAll('[data-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var inp = root.querySelector('[data-input=' + btn.getAttribute('data-open') + ']');
        if (inp) inp.click();
      });
    });
    sync();
  });


  // --- Smartphone : tableaux en cartes (étiquettes issues des en-têtes) ---
  document.querySelectorAll('.table-wrap table').forEach(function (table) {
    var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    table.classList.add('responsive');
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var i = 0;
      Array.prototype.forEach.call(tr.children, function (td) {
        if (td.colSpan > 1) return;
        td.setAttribute('data-label', heads[i] || '');
        i++;
      });
    });
  });

  // --- Smartphone : filtres repliables ---
  document.querySelectorAll('form.card.filters').forEach(function (form) {
    if (form.hasAttribute('data-keep')) return;
    var params = new URLSearchParams(location.search), n = 0;
    params.forEach(function (v, k) { if (v !== '' && ['r', 'page', 'type', 'format'].indexOf(k) === -1) n++; });
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-light filters-toggle';
    btn.innerHTML = '<span>Filtres et recherche' + (n ? '<span class="count">' + n + '</span>' : '') + '</span>';
    btn.setAttribute('aria-expanded', 'false');
    form.parentNode.insertBefore(btn, form);
    form.classList.add('collapsed');
    btn.addEventListener('click', function () {
      var open = form.classList.toggle('collapsed') === false;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // --- Planning des tournées : lignes de missions et camion proposé ---
  var tf = document.querySelector('[data-tours-form]');
  if (tf) {
    var list = tf.querySelector('[data-tours]'), tpl = tf.querySelector('[data-tour-template]');
    var max = parseInt(tf.getAttribute('data-max'), 10) || 6, counter = list.children.length + 100;
    var refresh = function () {
      var rows = list.querySelectorAll('[data-tour-row]');
      rows.forEach(function (r) { r.querySelector('[data-tour-remove]').hidden = rows.length < 2; });
      tf.querySelector('[data-tour-add]').hidden = rows.length >= max;
    };
    tf.querySelector('[data-tour-add]').addEventListener('click', function () {
      var div = document.createElement('div');
      div.innerHTML = tpl.innerHTML.replace(/__i__/g, String(counter++));
      list.appendChild(div.firstElementChild);
      refresh();
    });
    list.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-tour-remove]');
      if (b) { b.closest('[data-tour-row]').remove(); refresh(); }
    });
    var ds = tf.querySelector('[data-driver-select]'), ts = tf.querySelector('[data-truck-select]');
    ds.addEventListener('change', function () {
      var id = ds.options[ds.selectedIndex].getAttribute('data-truck');
      if (id && id !== '0' && !ts.value) ts.value = id;
    });
    refresh();
  }

  // --- Service worker (PWA) ---
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', function () {
      var base = document.querySelector('link[rel=manifest]').getAttribute('href').replace(/manifest\.webmanifest$/, '');
      navigator.serviceWorker.register(base + 'sw.js').catch(function () {});
    });
  }
})();
