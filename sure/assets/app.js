/* AMN SURE — ตัวช่วยฝั่งหน้าเว็บ (ไม่มี inline script เพราะ CSP อนุญาตเฉพาะไฟล์นี้) */
(function () {
  'use strict';

  // ยืนยันก่อนส่งฟอร์มที่มี data-confirm
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var msg = f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }
    // กันกดส่งซ้ำ
    if (f.dataset.submitted === '1') { ev.preventDefault(); return; }
    f.dataset.submitted = '1';
    window.setTimeout(function () { f.dataset.submitted = '0'; }, 4000);
    f.removeAttribute('data-dirty');
  }, true);

  // ยืนยันก่อนกดปุ่มที่มี data-confirm-click (ใช้กับปุ่ม submit บางปุ่มในฟอร์มเดียวกัน)
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-confirm-click]');
    if (b && !window.confirm(b.getAttribute('data-confirm-click'))) { ev.preventDefault(); ev.stopPropagation(); }
  }, true);

  // ปุ่มย้อนกลับ
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-back]');
    if (a && window.history.length > 1) { ev.preventDefault(); window.history.back(); }
  });

  // จัดรูปแบบช่องเงินเมื่อออกจากช่อง: 1250000 → 1,250,000.00 (data-money="signed" ใส่ติดลบได้)
  function fmtMoney(v, signed) {
    var s = String(v).replace(/[^0-9.\-]/g, '');
    var neg = signed && s.charAt(0) === '-';
    var n = parseFloat(s.replace(/-/g, ''));
    if (isNaN(n)) return '';
    return (neg ? '-' : '') + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  document.addEventListener('blur', function (ev) {
    var el = ev.target;
    if (el.matches && el.matches('input[data-money]') && el.value.trim() !== '') el.value = fmtMoney(el.value, el.getAttribute('data-money') === 'signed');
  }, true);

  // select ที่ค้นหาได้ (data-search) + กรองตัวเลือกตาม select อื่น (data-filter-by="id" เทียบกับ data-parent ของตัวเลือก)
  function setupSelect(sel) {
    var all = Array.prototype.map.call(sel.options, function (o) {
      return { value: o.value, text: o.text, parent: o.getAttribute('data-parent') };
    });
    var box = null;
    var parentSel = sel.getAttribute('data-filter-by') ? document.getElementById(sel.getAttribute('data-filter-by')) : null;
    function rebuild() {
      var q = box ? box.value.toLowerCase() : '';
      var p = parentSel ? parentSel.value : '';
      var current = sel.value;
      sel.innerHTML = '';
      all.forEach(function (o) {
        var okParent = !parentSel || o.value === '' || p === '' || o.parent === p;
        var okText = o.value === '' || q === '' || o.text.toLowerCase().indexOf(q) !== -1 || o.value === current;
        if (okParent && okText) {
          var opt = document.createElement('option');
          opt.value = o.value; opt.text = o.text;
          if (o.parent !== null) opt.setAttribute('data-parent', o.parent);
          if (o.value === current) opt.selected = true;
          sel.appendChild(opt);
        }
      });
    }
    if (sel.hasAttribute('data-search')) {
      box = document.createElement('input');
      box.type = 'search';
      box.className = 'select-search';
      box.placeholder = 'พิมพ์เพื่อค้นหา…';
      box.setAttribute('aria-label', 'ค้นหาในรายการ');
      sel.parentNode.insertBefore(box, sel);
      box.addEventListener('input', rebuild);
    }
    if (parentSel) {
      parentSel.addEventListener('change', function () {
        var keep = all.some(function (o) { return o.value === sel.value && (o.parent === parentSel.value || sel.value === ''); });
        if (!keep) sel.value = '';
        rebuild();
      });
      rebuild();
    }
  }
  document.querySelectorAll('select[data-search], select[data-filter-by]').forEach(setupSelect);

  // ฟอร์มสาขา: เลือกรหัสลูกค้าแล้วเติม "รหัสถัดไป" ให้ (data-next-codes = {รหัสลูกค้า: รหัสสาขาถัดไป})
  document.querySelectorAll('select[data-next-codes]').forEach(function (sel) {
    var codes = {};
    try { codes = JSON.parse(sel.getAttribute('data-next-codes')) || {}; } catch (e) { codes = {}; }
    var target = document.getElementById(sel.getAttribute('data-next-target'));
    var label = document.querySelector('[data-next-label]');
    var suggested = target ? target.value : '';
    sel.addEventListener('change', function () {
      var code = codes[sel.value] || '';
      if (target && (target.value === '' || target.value === suggested)) { target.value = code; suggested = code; }
      if (label) label.textContent = code || 'เลือกรหัสลูกค้าก่อน';
    });
  });

  // ช่องวันที่: แสดงค่าที่เลือกเป็น วว/ดด/ปปปป (ปี ค.ศ.) เสมอ ไม่ว่าเบราว์เซอร์จะตั้งภาษาอะไร
  document.querySelectorAll('input[type=date]').forEach(function (inp) {
    var out = document.createElement('div');
    out.className = 'date-th';
    inp.insertAdjacentElement('afterend', out);
    function show() {
      var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(inp.value);
      out.textContent = m ? 'วว/ดด/ปปปป: ' + m[3] + '/' + m[2] + '/' + m[1] : '';
    }
    inp.addEventListener('change', show);
    inp.addEventListener('input', show);
    show();
  });

  // เตือนเมื่อออกจากหน้าที่กรอกฟอร์มค้างไว้ (ฟอร์มยาว เช่น ผลตรวจเครื่อง)
  document.querySelectorAll('form[data-warn-unsaved]').forEach(function (f) {
    f.addEventListener('input', function () { f.setAttribute('data-dirty', '1'); });
  });
  window.addEventListener('beforeunload', function (ev) {
    if (document.querySelector('form[data-warn-unsaved][data-dirty]')) { ev.preventDefault(); ev.returnValue = ''; }
  });

  // แสดง/ซ่อนส่วนของฟอร์มตามตัวเลือก: data-show-when="field_id=value"
  function toggleConditional() {
    document.querySelectorAll('[data-show-when]').forEach(function (el) {
      var parts = el.getAttribute('data-show-when').split('=');
      var src = document.getElementById(parts[0]);
      if (!src) return;
      var val = src.type === 'checkbox' ? (src.checked ? '1' : '0') : src.value;
      var show = parts[1].split('|').indexOf(val) !== -1;
      el.hidden = !show;
    });
  }
  document.addEventListener('change', toggleConditional);
  toggleConditional();

  // select ที่ส่งฟอร์มทันทีเมื่อเปลี่ยนค่า
  document.addEventListener('change', function (ev) {
    if (ev.target.matches && ev.target.matches('select[data-autosubmit]')) ev.target.form.submit();
  });

  // รีเฟรชหน้าเอง (data-autorefresh="วินาที") — เว้นตอนแท็บไม่ได้เปิดดูอยู่ หรือกำลังกดยืนยันอะไรค้างไว้
  var auto = document.querySelector('[data-autorefresh]');
  if (auto) {
    var sec = parseInt(auto.getAttribute('data-autorefresh'), 10) || 30;
    window.setInterval(function () {
      if (document.visibilityState === 'visible' && !document.querySelector('form[data-submitted="1"]')) window.location.reload();
    }, sec * 1000);
  }

  // ปุ่มพิมพ์
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function (ev) { ev.preventDefault(); window.print(); });
  });
})();
