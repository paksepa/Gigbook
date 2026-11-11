/* Gigbook – kleines, abhängigkeitsfreies JavaScript */
(function () {
  'use strict';

  var basis = (document.querySelector('meta[name=basis]') || {}).content || '';
  var csrf = (document.querySelector('meta[name=csrf]') || {}).content || '';

  function api(pfad, daten) {
    var opt = {
      method: daten === undefined ? 'GET' : 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
    };
    if (daten !== undefined) {
      opt.headers['Content-Type'] = 'application/json';
      opt.body = JSON.stringify(daten);
    }
    return fetch(basis + pfad, opt).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (r.status === 401) { window.location.href = basis + '/login'; }
        if (!r.ok) { throw new Error(j.fehler || ('Fehler ' + r.status)); }
        return j;
      });
    });
  }

  var toastTimer;
  function meldung(text) {
    var t = document.querySelector('.toast');
    if (!t) { t = document.createElement('div'); t.className = 'toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
    t.textContent = text;
    t.classList.remove('versteckt');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.add('versteckt'); }, 3500);
  }

  function el(tag, klasse, text) {
    var e = document.createElement(tag);
    if (klasse) { e.className = klasse; }
    if (text !== undefined) { e.textContent = text; }
    return e;
  }

  // --- Sicherheitsabfragen & Auto-Submit ---------------------------------
  document.addEventListener('submit', function (ev) {
    var f = ev.target.closest('[data-bestaetigen]');
    if (f && !window.confirm(f.getAttribute('data-bestaetigen'))) { ev.preventDefault(); }
  });
  document.addEventListener('change', function (ev) {
    if (ev.target.matches('[data-auto-submit]')) { ev.target.form.submit(); }
  });

  // --- Bekannten Veranstalter gewählt: Kontakt übernehmen -----------------
  document.querySelectorAll('input[data-kontakt-ziel]').forEach(function (feld) {
    var ziel = feld.form.querySelector('[name="' + feld.getAttribute('data-kontakt-ziel') + '"]');
    var liste = document.getElementById(feld.getAttribute('list'));
    if (!ziel || !liste) { return; }
    var uebernommen = '';
    feld.addEventListener('input', function () {
      var name = feld.value.trim().toLowerCase();
      var kontakt = '';
      Array.prototype.forEach.call(liste.options, function (o) {
        if (o.value.trim().toLowerCase() === name) { kontakt = o.getAttribute('data-kontakt') || ''; }
      });
      // nur leere oder zuvor automatisch gefüllte Kontakte überschreiben
      if (kontakt && (ziel.value === '' || ziel.value === uebernommen)) {
        ziel.value = kontakt;
        uebernommen = kontakt;
      }
    });
  });

  // --- Abrechnungen: Balkenbreiten (CSP erlaubt keine Inline-Styles) & Drucken ---
  document.querySelectorAll('[data-breite]').forEach(function (b) {
    b.style.width = Math.max(0, Math.min(100, parseFloat(b.getAttribute('data-breite')) || 0)) + '%';
  });
  document.querySelectorAll('[data-drucken]').forEach(function (k) {
    k.addEventListener('click', function () { window.print(); });
  });

  // --- Mehrfachauswahl in der Werkliste -------------------------------------
  document.querySelectorAll('[data-alle-waehlen]').forEach(function (alle) {
    var form = alle.form;
    var name = alle.getAttribute('data-alle-waehlen');
    var anzeige = form.querySelector('[data-auswahl-anzahl]');
    function kaestchen() { return form.querySelectorAll('input[type=checkbox][name="' + name + '"]'); }
    // Ein Werk kann in mehreren Abschnitten stehen: gezählt wird jedes Werk einmal
    function zaehlen() {
      var gewaehlt = {}, alleWerte = {};
      kaestchen().forEach(function (k) { alleWerte[k.value] = 1; if (k.checked) { gewaehlt[k.value] = 1; } });
      var n = Object.keys(gewaehlt).length, gesamt = Object.keys(alleWerte).length;
      if (anzeige) { anzeige.textContent = n; }
      alle.checked = n > 0 && n === gesamt;
      alle.indeterminate = n > 0 && n < gesamt;
    }
    alle.addEventListener('change', function () {
      kaestchen().forEach(function (k) { k.checked = alle.checked; });
      zaehlen();
    });
    form.addEventListener('change', function (ev) {
      if (ev.target.name !== name) { return; }
      kaestchen().forEach(function (k) { if (k.value === ev.target.value) { k.checked = ev.target.checked; } });
      zaehlen();
    });
    form.addEventListener('submit', function (ev) {
      if (form.querySelectorAll('input[name="' + name + '"]:checked').length === 0) {
        ev.preventDefault();
        meldung('Bitte zuerst Werke auswählen.');
      }
    });
    zaehlen();
  });

  // --- Felder abhängig von Auswahl ein-/ausblenden -------------------------
  document.querySelectorAll('select[data-zeigt]').forEach(function (sel) {
    var teile = sel.getAttribute('data-zeigt').split(':');
    var werte = teile[0].split(',');
    var ziel = document.getElementById(teile[1]);
    function aktualisieren() { if (ziel) { ziel.classList.toggle('versteckt', werte.indexOf(sel.value) === -1); } }
    sel.addEventListener('change', aktualisieren);
    aktualisieren();
  });

  // --- Werk-Formular: Komponisten-Zeilen ----------------------------------
  var kListe = document.getElementById('komponisten-liste');
  var kVorlage = document.getElementById('komponist-vorlage');
  var kNeu = document.getElementById('komponist-hinzufuegen');
  if (kListe && kVorlage && kNeu) {
    var zaehler = kListe.children.length + 100;
    kNeu.addEventListener('click', function () {
      var html = kVorlage.innerHTML.replace(/__I__/g, String(zaehler++));
      var tmp = document.createElement('div');
      tmp.innerHTML = html.trim();
      var zeile = tmp.firstElementChild;
      kListe.appendChild(zeile);
      zeile.querySelector('input').focus();
    });
    kListe.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-komponist-entfernen]');
      if (!b) { return; }
      var zeile = b.closest('.komponist-zeile');
      if (kListe.children.length > 1) { zeile.remove(); }
      else { zeile.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
    });
  }

  // --- Optionale GEMA-Werksuche im Werk-Formular ---------------------------
  var gsStart = document.getElementById('gema-suche-start');
  if (gsStart) {
    var gsErgebnis = document.getElementById('gema-suche-ergebnis');
    gsStart.addEventListener('click', function () {
      var form = gsStart.closest('form');
      var titel = form.querySelector('[name=titel]').value.trim();
      var komp = form.querySelector('[name="komponisten[0][nachname]"]');
      gsErgebnis.textContent = 'Suche läuft …';
      var q = '?titel=' + encodeURIComponent(titel) + '&komponist=' + encodeURIComponent(komp ? komp.value.trim() : '');
      api('/api/werksuche' + q).then(function (d) {
        gsErgebnis.textContent = '';
        if (!d.treffer || !d.treffer.length) { gsErgebnis.textContent = 'Keine Treffer.'; return; }
        var ul = el('ul', 'treffer');
        d.treffer.forEach(function (t) {
          var li = el('li');
          var b = el('button');
          b.type = 'button';
          b.appendChild(el('span', '', t.titel + (t.werknummer ? ' · ' + t.werknummer : '')));
          b.appendChild(el('small', '', (t.komponisten || []).map(function (k) { return (k.vorname ? k.vorname + ' ' : '') + k.nachname; }).join(', ')));
          b.addEventListener('click', function () { werksucheUebernehmen(form, t); });
          li.appendChild(b);
          ul.appendChild(li);
        });
        gsErgebnis.appendChild(ul);
      }).catch(function (e) {
        gsErgebnis.textContent = e.message + ' – du kannst die Daten wie gewohnt von Hand eintragen.';
      });
    });
  }

  function werksucheUebernehmen(form, t) {
    if (t.werknummer) { form.querySelector('[name=gema_werknummer]').value = t.werknummer; }
    if (t.iswc) { form.querySelector('[name=iswc]').value = t.iswc; }
    if (t.komponisten && t.komponisten.length && kListe) {
      kListe.innerHTML = '';
      t.komponisten.forEach(function () { kNeu.click(); });
      var zeilen = kListe.querySelectorAll('.komponist-zeile');
      t.komponisten.forEach(function (k, i) {
        var inputs = zeilen[i].querySelectorAll('input');
        inputs[0].value = k.nachname || '';
        inputs[1].value = k.vorname || '';
      });
    }
    meldung('Übernommen – bitte prüfen und speichern.');
  }

  // --- Setlist-Editor -------------------------------------------------------
  var bereich = document.getElementById('setlist-bereich');
  if (!bereich) { return; }
  var auftrittId = bereich.getAttribute('data-auftritt');
  var container = document.getElementById('setlist');
  var zusammenfassung = document.getElementById('setlist-zusammenfassung');
  var suche = document.getElementById('werk-suche');
  var treffer = document.getElementById('werk-treffer');

  function neuZeichnen(d) {
    if (d.html !== undefined) { container.innerHTML = d.html; }
    if (d.zusammenfassung !== undefined) { zusammenfassung.textContent = d.zusammenfassung; }
  }
  function fehler(e) { meldung(e.message); }

  // Suche
  var suchTimer, letzteSuche = '';
  suche.addEventListener('input', function () {
    clearTimeout(suchTimer);
    suchTimer = setTimeout(sucheAusfuehren, 180);
  });
  suche.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      var erster = treffer.querySelector('button');
      if (erster) { erster.click(); }
    }
  });

  function sucheAusfuehren() {
    var q = suche.value.trim();
    letzteSuche = q;
    if (!q) { treffer.innerHTML = ''; return; }
    api('/api/werke?q=' + encodeURIComponent(q)).then(function (d) {
      if (q !== letzteSuche) { return; }
      treffer.innerHTML = '';
      d.werke.forEach(function (w) {
        var b = el('button');
        b.type = 'button';
        b.appendChild(el('span', '', w.titel + (w.eigen ? ' ★' : '')));
        b.appendChild(el('small', '', [w.komponisten || 'Komponist fehlt', w.dauer].filter(Boolean).join(' · ')));
        b.addEventListener('click', function () { hinzufuegen(w.id); });
        var li = el('li'); li.appendChild(b); treffer.appendChild(li);
      });
      var neu = el('button');
      neu.type = 'button';
      neu.appendChild(el('span', 'neu', '+ „' + q + '“ als neues Werk anlegen'));
      neu.appendChild(el('small', '', 'Komponist kannst du danach ergänzen'));
      neu.addEventListener('click', function () {
        api('/api/werke', { titel: q }).then(function (r) { hinzufuegen(r.id); }).catch(fehler);
      });
      var li = el('li'); li.appendChild(neu); treffer.appendChild(li);
    }).catch(fehler);
  }

  function hinzufuegen(werkId) {
    api('/api/auftritte/' + auftrittId + '/setlist', { werk_id: werkId }).then(function (d) {
      neuZeichnen(d);
      suche.value = '';
      treffer.innerHTML = '';
      suche.focus();
      var letzter = container.querySelector('li:last-child');
      if (letzter) { letzter.scrollIntoView({ block: 'nearest' }); }
    }).catch(fehler);
  }

  // Inline-Bearbeitung
  container.addEventListener('change', function (ev) {
    var feld = ev.target.closest('[data-feld]');
    if (!feld) { return; }
    var li = feld.closest('li');
    feld.classList.remove('gespeichert', 'fehlerhaft');
    api('/api/setlist/' + li.getAttribute('data-id'), { feld: feld.getAttribute('data-feld'), wert: feld.value }).then(function (d) {
      if (feld.getAttribute('data-feld') === 'dauer') { feld.value = d.dauer; }
      if (feld.getAttribute('data-feld') === 'abgerechnet') { li.className = 'abgerechnet-' + feld.value; }
      feld.classList.add('gespeichert');
      setTimeout(function () { feld.classList.remove('gespeichert'); }, 1200);
      neuZeichnen({ zusammenfassung: d.zusammenfassung });
    }).catch(function (e) { feld.classList.add('fehlerhaft'); fehler(e); });
  });

  // Entfernen
  container.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-entfernen]');
    if (!b) { return; }
    var li = b.closest('li');
    var titel = li.querySelector('.titel').textContent;
    if (!window.confirm('„' + titel + '“ aus der Setlist entfernen?')) { return; }
    api('/api/setlist/' + li.getAttribute('data-id') + '/loeschen', {}).then(neuZeichnen).catch(fehler);
  });

  // Alle Titel gleichzeitig als gefunden/fehlt markieren
  bereich.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-alle-abgerechnet]');
    if (!b) { return; }
    api('/api/auftritte/' + auftrittId + '/abgerechnet', { wert: b.getAttribute('data-alle-abgerechnet') }).then(neuZeichnen).catch(fehler);
  });

  // Drag & Drop (Pointer Events – funktioniert mit Maus und Touch)
  var zug = null;
  container.addEventListener('pointerdown', function (ev) {
    var griff = ev.target.closest('.griff');
    if (!griff) { return; }
    ev.preventDefault();
    var li = griff.closest('li');
    zug = { li: li, liste: li.parentNode, vorher: ordnung(li.parentNode) };
    li.classList.add('zieht');
    griff.setPointerCapture(ev.pointerId);
  });
  container.addEventListener('pointermove', function (ev) {
    if (!zug) { return; }
    ev.preventDefault();
    var y = ev.clientY;
    if (y < 70) { window.scrollBy(0, -12); }
    else if (y > window.innerHeight - 110) { window.scrollBy(0, 12); }
    var andere = Array.prototype.filter.call(zug.liste.children, function (c) { return c !== zug.li; });
    var davor = null;
    for (var i = 0; i < andere.length; i++) {
      var r = andere[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) { davor = andere[i]; break; }
    }
    if (davor) { zug.liste.insertBefore(zug.li, davor); } else { zug.liste.appendChild(zug.li); }
  });
  function zugEnde() {
    if (!zug) { return; }
    var z = zug;
    zug = null;
    z.li.classList.remove('zieht');
    var neu = ordnung(z.liste);
    if (neu.join(',') === z.vorher.join(',')) { return; }
    z.liste.querySelectorAll('.nr').forEach(function (n, i) { n.textContent = String(i + 1); });
    api('/api/auftritte/' + auftrittId + '/reihenfolge', { ids: neu }).then(neuZeichnen).catch(function (e) {
      fehler(e);
      window.setTimeout(function () { window.location.reload(); }, 1500);
    });
  }
  container.addEventListener('pointerup', zugEnde);
  container.addEventListener('pointercancel', zugEnde);

  function ordnung(liste) {
    return Array.prototype.map.call(liste.children, function (li) { return parseInt(li.getAttribute('data-id'), 10); });
  }
})();
