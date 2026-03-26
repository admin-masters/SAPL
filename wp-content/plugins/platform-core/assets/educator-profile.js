(function () {
  if (!window.pcoreEducatorProfile) return;

  var cfg  = window.pcoreEducatorProfile;
  var root = document.getElementById('pc-educatorprofile-root');
  if (!root) return;

  // -- Remove duplicate .dates / .slots blocks injected by template -------------
  (function normalizeBaseTemplate(container) {
    var datesLists   = container.querySelectorAll('.calendar .dates');
    var primaryDates = datesLists.length ? datesLists[0] : null;
    for (var i = 1; i < datesLists.length; i++) {
      if (datesLists[i].parentNode) datesLists[i].parentNode.removeChild(datesLists[i]);
    }
    var strayDates = container.querySelectorAll('.calendar .date');
    for (var j = 0; j < strayDates.length; j++) {
      var holder = strayDates[j].closest('.dates');
      if (!primaryDates || holder !== primaryDates) {
        if (strayDates[j].parentNode) strayDates[j].parentNode.removeChild(strayDates[j]);
      }
    }
    var slotsLists   = container.querySelectorAll('.slots');
    var primarySlots = slotsLists.length ? slotsLists[0] : null;
    for (var k = 1; k < slotsLists.length; k++) {
      if (slotsLists[k].parentNode) slotsLists[k].parentNode.removeChild(slotsLists[k]);
    }
    var straySlots = container.querySelectorAll('.slot');
    for (var m = 0; m < straySlots.length; m++) {
      var sholder = straySlots[m].closest('.slots');
      if (!primarySlots || sholder !== primarySlots) {
        if (straySlots[m].parentNode) straySlots[m].parentNode.removeChild(straySlots[m]);
      }
    }
  })(root);

  var datesEl     = root.querySelector('.dates');
  var slotsEl     = root.querySelector('.slots');
  var durationEls = root.querySelectorAll('.duration div');
  var priceEl     = root.querySelector('.price span:last-child');
  var bookBtn     = root.querySelector('.book-btn');

  var classTitleEl   = document.getElementById('pc-class-title');
  var classDescEl    = document.getElementById('pc-class-description');
  var offeredPriceEl = document.getElementById('pc-offered-price');

  if (offeredPriceEl) {
    offeredPriceEl.placeholder = 'Enter offered price - optional';
  }

  if (!datesEl || !slotsEl || !priceEl || !bookBtn) return;

  // -- State ---------------------------------------------------------------------
  var selectedDate     = null;   // no date pre-selected
  var selectedTime     = null;
  var selectedDuration = null;   // no duration pre-selected

  var expertPrices = cfg.prices || {};

  function priceForDuration(mins) {
    return expertPrices[mins] || 0;
  }

  // -- Duration buttons: compact, same line --------------------------------------
  var durationWrap = durationEls.length ? durationEls[0].parentElement : null;
  if (durationWrap) {
    durationWrap.style.cssText =
      'display:flex;flex-wrap:nowrap;gap:6px;align-items:center;';
    for (var di = 0; di < durationEls.length; di++) {
      durationEls[di].style.cssText =
        'padding:4px 8px;font-size:12px;border:1px solid #cbd5e1;border-radius:5px;' +
        'cursor:pointer;white-space:nowrap;flex:1 1 0;min-width:0;text-align:center;background:#fff;color:#0f172a;';
    }
  }

  // -- Helpers -------------------------------------------------------------------
  function pad2(n) { return n < 10 ? '0' + n : String(n); }

  function ymd(d) {
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  function parseYMD(s) {
    var rx = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(String(s || ''));
    if (!rx) return null;
    var dt = new Date(Number(rx[1]), Number(rx[2]) - 1, Number(rx[3]));
    return ymd(dt) === s ? dt : null;
  }

  var todayDateObj = parseYMD(cfg.today);

  function isPastDate(dateStr) {
    if (!todayDateObj) return false;
    var d = parseYMD(dateStr);
    if (!d) return false;
    return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime() <
           new Date(todayDateObj.getFullYear(), todayDateObj.getMonth(), todayDateObj.getDate()).getTime();
  }

  // -- Price display -------------------------------------------------------------
  function setPrice() {
    if (!selectedDuration) {
      priceEl.innerHTML = '&#8212;'; // em-dash until duration chosen
    } else {
      var price = priceForDuration(selectedDuration);
      priceEl.innerHTML = price ? '&#8377;' + price : '&#8212;';
    }
  }

  // -- Month navigation state ----------------------------------------------------
  var monthState = (function () {
    var d = new Date();
    return { year: d.getFullYear(), month: d.getMonth() };
  })();

  // -- Calendar ------------------------------------------------------------------
  function renderCalendar(year, month) {
    datesEl.innerHTML = '';

    var oldNav = root.querySelector('.pc-month-nav');
    if (oldNav) oldNav.parentNode.removeChild(oldNav);

    var nav = document.createElement('div');
    nav.className = 'pc-month-nav';
    nav.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin:10px 0 6px;';

    var prev = document.createElement('button');
    prev.type = 'button';
    prev.style.cssText = 'padding:4px 12px;font-size:18px;line-height:1;cursor:pointer;background:#0f172a;color:#fff;border:none;border-radius:6px;';
    prev.innerHTML = '&#8592;';

    var next = document.createElement('button');
    next.type = 'button';
    next.style.cssText = 'padding:4px 12px;font-size:18px;line-height:1;cursor:pointer;background:#0f172a;color:#fff;border:none;border-radius:6px;';
    next.innerHTML = '&#8594;';

    var title = document.createElement('div');
    title.style.cssText = 'font-weight:600;font-size:14px;color:#0f172a;';
    title.textContent = new Date(year, month, 1).toLocaleString(undefined, { month: 'long', year: 'numeric' });

    nav.appendChild(prev);
    nav.appendChild(title);
    nav.appendChild(next);

    var calendarEl = root.querySelector('.calendar');
    if (calendarEl) calendarEl.insertBefore(nav, calendarEl.firstChild);

    prev.addEventListener('click', function () {
      var m = monthState.month - 1, y = monthState.year;
      if (m < 0) { m = 11; y--; }
      monthState.month = m; monthState.year = y;
      renderCalendar(y, m);
    });

    next.addEventListener('click', function () {
      var m = monthState.month + 1, y = monthState.year;
      if (m > 11) { m = 0; y++; }
      monthState.month = m; monthState.year = y;
      renderCalendar(y, m);
    });

    var firstDow    = new Date(year, month, 1).getDay();
    var daysInMonth = new Date(year, month + 1, 0).getDate();

    for (var sp = 0; sp < firstDow; sp++) {
      var spacer = document.createElement('div');
      spacer.className = 'pc-cal-spacer';
      spacer.style.cssText = 'visibility:hidden;pointer-events:none;padding:8px;border:none;background:none;';
      datesEl.appendChild(spacer);
    }

    for (var day = 1; day <= daysInMonth; day++) {
      var d  = new Date(year, month, day);
      var ds = ymd(d);

      var cell = document.createElement('div');
      cell.className    = 'date';
      cell.textContent  = String(day);
      cell.dataset.date = ds;

      if (isPastDate(ds)) {
        cell.classList.add('pc-disabled');
      } else {
        cell.addEventListener('click', (function (dateStr) {
          return function () { selectDate(dateStr); };
        })(ds));
      }

      if (ds === selectedDate) cell.classList.add('pc-active');
      datesEl.appendChild(cell);
    }
  }

  function setActiveDateCell(dateStr) {
    var cells = datesEl.querySelectorAll('.date');
    for (var i = 0; i < cells.length; i++) {
      cells[i].classList.toggle('pc-active', !!(cells[i].dataset && cells[i].dataset.date === dateStr));
    }
  }

  // -- Duration ------------------------------------------------------------------
  function clearDurationActive() {
    for (var i = 0; i < durationEls.length; i++) {
      durationEls[i].classList.remove('pc-active');
      durationEls[i].style.background = '#fff';
      durationEls[i].style.color      = '#0f172a';
    }
  }

  function setDurationActive(mins) {
    selectedDuration = mins;
    clearDurationActive();
    for (var i = 0; i < durationEls.length; i++) {
      var txt = (durationEls[i].textContent || '').trim();
      var isActive = txt.indexOf(String(mins)) === 0;
      durationEls[i].classList.toggle('pc-active', isActive);
      // Mirror the black active style used by dates/slots
      durationEls[i].style.background = isActive ? '#000' : '#fff';
      durationEls[i].style.color      = isActive ? '#fff' : '#0f172a';
    }
    setPrice();

    // Fetch slots only if a date is already chosen
    if (selectedDate) {
      selectedTime      = null;
      slotsEl.innerHTML = '<div style="padding:6px 0;font-size:13px;grid-column:1/-1;">Loading slots&#8230;</div>';
      renderSlots(selectedDate);
    }
  }

  for (var di = 0; di < durationEls.length; di++) {
    (function (el) {
      el.addEventListener('click', function () {
        var txt  = (el.textContent || '').trim();
        var rx   = /^([0-9]{1,3})/.exec(txt);
        if (!rx) return;
        var mins = Number(rx[1]);
        if (mins !== 30 && mins !== 60 && mins !== 90) return;
        setDurationActive(mins);
      });
    })(durationEls[di]);
  }

  // -- Slot time label -----------------------------------------------------------
  function fmtMins(totalMins) {
    var hh   = Math.floor(totalMins / 60) % 24;
    var mm   = totalMins % 60;
    var ampm = hh >= 12 ? 'PM' : 'AM';
    var h12  = hh % 12 || 12;
    return h12 + ':' + (mm < 10 ? '0' + mm : mm) + ' ' + ampm;
  }

  function slotLabel(timeStr, durMins) {
    var dur       = Math.floor(Number(durMins));
    if (!dur || dur < 1 || dur > 480) dur = 60;
    var parts     = String(timeStr).split(':');
    var startMins = Math.floor(Number(parts[0])) * 60 + Math.floor(Number(parts[1] || 0));
    return fmtMins(startMins) + ' \u2013 ' + fmtMins(startMins + dur);
  }

  // -- Slots ---------------------------------------------------------------------
  async function fetchSlots(dateStr, durationMins) {
    var url = cfg.slotsEndpoint
      + '?expert_id='        + encodeURIComponent(cfg.expertId)
      + '&date='             + encodeURIComponent(dateStr)
      + '&duration_minutes=' + encodeURIComponent(durationMins);

    var res  = await fetch(url, { method: 'GET', headers: { 'X-WP-Nonce': cfg.nonce } });
    var json = await res.json();
    if (!res.ok) throw new Error((json && json.message) ? json.message : 'Failed to load slots.');

    // Update price map from live API response
    if (json && json.prices) {
      expertPrices = json.prices;
      setPrice();
    }

    return (json && json.slots) ? json.slots : [];
  }

  async function renderSlots(dateStr) {
    var snapDate     = dateStr;
    var snapDuration = Math.floor(Number(selectedDuration));
    if (!snapDuration || snapDuration < 1) snapDuration = 60;

    slotsEl.innerHTML = '<div style="padding:6px 0;font-size:13px;grid-column:1/-1;">Loading slots&#8230;</div>';

    try {
      var slots = await fetchSlots(snapDate, snapDuration);

      // Stale-response guard
      if (selectedDate !== snapDate || selectedDuration !== snapDuration) return;

      if (!slots.length) {
        slotsEl.innerHTML = '<div style="padding:6px 0;font-size:13px;grid-column:1/-1;">No slots available for this date.</div>';
        return;
      }

      slotsEl.innerHTML = '';
      slots.forEach(function (s) {
        var div          = document.createElement('div');
        div.className    = 'slot';
        div.textContent  = slotLabel(s.time, snapDuration);
        div.dataset.time = s.time;
        div.addEventListener('click', function () {
          slotsEl.querySelectorAll('.slot').forEach(function (n) { n.classList.remove('pc-active'); });
          div.classList.add('pc-active');
          selectedTime = div.dataset.time || null;
        });
        slotsEl.appendChild(div);
      });

    } catch (e) {
      if (selectedDate !== snapDate || selectedDuration !== snapDuration) return;
      slotsEl.innerHTML = '<div style="padding:6px 0;color:#b91c1c;font-size:13px;grid-column:1/-1;">' + e.message + '</div>';
    }
  }

  // -- Select date ---------------------------------------------------------------
  function selectDate(dateStr) {
    selectedDate     = dateStr;
    selectedTime     = null;
    selectedDuration = null;   // reset duration on every date change
    clearDurationActive();
    setPrice();
    setActiveDateCell(dateStr);
    // Show prompt — slots load only after duration is picked
    slotsEl.innerHTML = '<div style="padding:6px 0;font-size:13px;color:#64748b;grid-column:1/-1;">Please select a session duration above.</div>';
  }

  // -- Book ----------------------------------------------------------------------
  async function book() {
    if (!selectedDate)     { alert('Please select a date.');             return; }
    if (!selectedDuration) { alert('Please select a session duration.'); return; }
    if (!selectedTime)     { alert('Please select a time slot.');        return; }

    var classTitle   = classTitleEl   ? classTitleEl.value.trim()   : '';
    var classDesc    = classDescEl    ? classDescEl.value.trim()    : '';
    var offeredPrice = offeredPriceEl ? offeredPriceEl.value.trim() : '';

    if (!classTitle) { alert('Please enter class title.');       return; }
    if (!classDesc)  { alert('Please enter class description.'); return; }

    var finalPrice = (offeredPrice && Number(offeredPrice) > 0)
      ? Number(offeredPrice)
      : priceForDuration(selectedDuration);

    bookBtn.disabled    = true;
    var oldText         = bookBtn.textContent;
    bookBtn.textContent = 'Booking\u2026';

    try {
      var res = await fetch(cfg.bookEndpoint, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
        body: JSON.stringify({
          expert_id:        cfg.expertId,
          date:             selectedDate,
          time:             selectedTime,
          duration_minutes: selectedDuration,
          offered_price:    finalPrice,
          class_title:      classTitle,
          description:      classDesc
        })
      });

      var json = await res.json();

      if (!res.ok || !json || !json.success) {
        alert((json && json.message) ? json.message : 'Booking failed.');
        bookBtn.disabled    = false;
        bookBtn.textContent = oldText;
        return;
      }

      alert('Session booked successfully!');
      window.location.reload();

    } catch (e) {
      alert('Network error while booking.');
      bookBtn.disabled    = false;
      bookBtn.textContent = oldText;
    }
  }

  bookBtn.addEventListener('click', function (e) { e.preventDefault(); book(); });

  // -- Init ----------------------------------------------------------------------
  // No default duration or date — start with a clean slate.
  setPrice();
  renderCalendar(monthState.year, monthState.month);
  slotsEl.innerHTML = '<div style="padding:6px 0;font-size:13px;color:#64748b;grid-column:1/-1;">Please select a date to continue.</div>';

})();