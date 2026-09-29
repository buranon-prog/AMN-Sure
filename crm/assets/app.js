/* AMN Sure CRM — ตัวช่วยฝั่งหน้าเว็บ (ไม่มี inline script เพราะ CSP อนุญาตเฉพาะไฟล์นี้) */
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

  // จัดรูปแบบช่องเงินเมื่อออกจากช่อง: 1250000 → 1,250,000.00
  function fmtMoney(v) {
    var n = parseFloat(String(v).replace(/[^0-9.]/g, ''));
    if (isNaN(n)) return '';
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function num(v) {
    var n = parseFloat(String(v || '').replace(/[^0-9.]/g, ''));
    return isNaN(n) ? 0 : n;
  }
  document.addEventListener('blur', function (ev) {
    var el = ev.target;
    if (el.matches && el.matches('input[data-money]') && el.value.trim() !== '') el.value = fmtMoney(el.value);
  }, true);

  // select ที่ค้นหาได้: เพิ่มช่องพิมพ์กรองตัวเลือก
  document.querySelectorAll('select[data-search]').forEach(function (sel) {
    var box = document.createElement('input');
    box.type = 'search';
    box.className = 'select-search';
    box.placeholder = 'พิมพ์เพื่อค้นหา…';
    sel.parentNode.insertBefore(box, sel);
    var all = Array.prototype.map.call(sel.options, function (o) { return { value: o.value, text: o.text }; });
    box.addEventListener('input', function () {
      var q = box.value.toLowerCase();
      var current = sel.value;
      sel.innerHTML = '';
      all.forEach(function (o) {
        if (o.value === '' || o.text.toLowerCase().indexOf(q) !== -1 || o.value === current) {
          var opt = document.createElement('option');
          opt.value = o.value; opt.text = o.text;
          if (o.value === current) opt.selected = true;
          sel.appendChild(opt);
        }
      });
    });
  });

  // ตารางรายการแบบเพิ่ม/ลบแถวได้ (cost sheet, ใบเสนอราคา)
  document.querySelectorAll('[data-rows]').forEach(function (table) {
    var tbody = table.querySelector('tbody');
    var tpl = table.querySelector('template');
    var addBtn = document.querySelector('[data-add-row="' + table.id + '"]');
    function recalc() {
      var total = 0;
      tbody.querySelectorAll('tr').forEach(function (tr) {
        var qty = tr.querySelector('[data-qty]');
        var amt = tr.querySelector('[data-amount]');
        var line = num(amt ? amt.value : 0) * (qty ? num(qty.value) || 0 : 1);
        var cell = tr.querySelector('[data-line-total]');
        if (cell) cell.textContent = fmtMoney(line);
        total += line;
      });
      var out = document.querySelectorAll('[data-total-for="' + table.id + '"]');
      out.forEach(function (o) {
        var vatRate = o.getAttribute('data-vat');
        if (vatRate !== null) {
          var rateInput = document.getElementById(vatRate);
          var rate = rateInput ? num(rateInput.value) : 0;
          var mode = o.getAttribute('data-mode');
          var vat = Math.round(total * rate) / 100;
          o.textContent = fmtMoney(mode === 'vat' ? vat : total + vat);
        } else {
          o.textContent = fmtMoney(total);
        }
      });
    }
    var counter = 1000;
    if (addBtn && tpl) {
      addBtn.addEventListener('click', function () {
        var frag = tpl.content.cloneNode(true);
        var idx = String(counter++);
        frag.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace('__i', idx); });
        tbody.appendChild(frag);
        recalc();
      });
    }
    table.addEventListener('click', function (ev) {
      if (ev.target.closest('.remove-row')) {
        ev.preventDefault();
        var tr = ev.target.closest('tr');
        if (tbody.querySelectorAll('tr').length > 1) tr.remove();
        else tr.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        recalc();
      }
    });
    table.addEventListener('input', recalc);
    document.addEventListener('input', function (ev) { if (ev.target.hasAttribute && ev.target.hasAttribute('data-vat-rate')) recalc(); });
    recalc();
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

  // ปุ่มพิมพ์
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function (ev) { ev.preventDefault(); window.print(); });
  });
})();
