(function () {
    'use strict';

    /* Menu samping di layar kecil */
    var tombolMenu = document.getElementById('buka-menu');
    var scrim = document.getElementById('scrim');
    function tutupMenu() {
        document.body.classList.remove('menu-buka');
        if (tombolMenu) tombolMenu.setAttribute('aria-expanded', 'false');
    }
    if (tombolMenu) {
        tombolMenu.addEventListener('click', function () {
            var buka = document.body.classList.toggle('menu-buka');
            tombolMenu.setAttribute('aria-expanded', buka ? 'true' : 'false');
        });
    }
    if (scrim) scrim.addEventListener('click', tutupMenu);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupMenu(); });

    /* Konfirmasi untuk tombol berbahaya: <form data-konfirmasi="Pesan"> */
    document.addEventListener('submit', function (e) {
        var pesan = e.target.getAttribute && e.target.getAttribute('data-konfirmasi');
        if (pesan && !window.confirm(pesan)) e.preventDefault();
    });

    /* Kolom angka saja: <input data-angka> */
    document.querySelectorAll('input[data-angka]').forEach(function (el) {
        el.addEventListener('input', function () { el.value = el.value.replace(/\D+/g, ''); });
    });

    /* Resep obat dinamis (halaman pemeriksaan dokter) */
    var daftar = document.getElementById('resep-list');
    var tpl = document.getElementById('tpl-resep');
    var tambah = document.getElementById('tambah-resep');
    var perkiraan = document.getElementById('perkiraan-obat');

    function rupiah(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

    function hitung() {
        if (!daftar) return;
        var total = 0;
        daftar.querySelectorAll('.resep-baris').forEach(function (baris) {
            var sel = baris.querySelector('select');
            var jml = baris.querySelector('input[type=number]');
            var opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) { jml.removeAttribute('max'); return; }
            var stok = parseInt(opt.getAttribute('data-stok'), 10);
            jml.max = stok;
            var j = parseInt(jml.value, 10) || 0;
            jml.setCustomValidity(j > stok ? 'Stok hanya ' + stok : '');
            total += (parseInt(opt.getAttribute('data-harga'), 10) || 0) * j;
        });
        if (perkiraan) perkiraan.textContent = rupiah(total);
    }

    function pasangBaris(baris) {
        baris.querySelector('select').addEventListener('change', hitung);
        baris.querySelector('input[type=number]').addEventListener('input', hitung);
        baris.querySelector('.hapus-resep').addEventListener('click', function () {
            baris.remove();
            hitung();
        });
    }

    if (daftar && tpl) {
        daftar.querySelectorAll('.resep-baris').forEach(pasangBaris);
        if (tambah) {
            tambah.addEventListener('click', function () {
                var salinan = tpl.content.firstElementChild.cloneNode(true);
                daftar.appendChild(salinan);
                pasangBaris(salinan);
                salinan.querySelector('select').focus();
            });
        }
        hitung();
    }

    /* Hitung kembalian pada form pembayaran tunai */
    var uang = document.getElementById('uang-diterima');
    var kembalian = document.getElementById('kembalian');
    if (uang && kembalian) {
        var total = parseInt(uang.getAttribute('data-total'), 10) || 0;
        uang.addEventListener('input', function () {
            var diterima = parseInt(uang.value, 10) || 0;
            kembalian.textContent = diterima >= total ? rupiah(diterima - total) : 'Uang kurang ' + rupiah(total - diterima);
        });
    }
    var metode = document.getElementById('metode-bayar');
    var barisUang = document.getElementById('baris-uang');
    if (metode && barisUang) {
        var atur = function () {
            var tunai = metode.value === 'Tunai';
            barisUang.hidden = !tunai;
            if (uang) uang.required = tunai;
        };
        metode.addEventListener('change', atur);
        atur();
    }
})();
