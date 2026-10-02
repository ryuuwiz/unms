{{-- Tanpa UI. Poll lewat setInterval: tetap berjalan saat tab di latar belakang, justru saat notifikasi browser
     paling berguna. Izin diminta pada klik pertama karena Firefox/Safari menolak requestPermission() tanpa
     interaksi user; bila ditolak, notifikasi browser diam. --}}
<div
    x-data="{
        timer: null,
        init() {
            if (! ('Notification' in window)) return
            if (Notification.permission === 'default') {
                document.addEventListener('click', () => Notification.requestPermission(), { once: true })
            }
            this.timer = setInterval(() => $wire.periksaBaru(), 10000)
        },
        destroy() { clearInterval(this.timer) },
        tampilkan(daftar) {
            if (Notification.permission !== 'granted') return
            daftar.forEach(n => {
                // tag = id: beberapa tab terbuka tidak menggandakan notifikasi yang sama.
                const notif = new Notification(n.title, { body: n.body, tag: n.id })
                notif.onclick = () => { window.focus(); $wire.tandaiDibaca(n.id); if (n.url) window.location.href = n.url; notif.close() }
            })
        },
    }"
    x-on:notifikasi-browser.window="tampilkan($event.detail.notifikasi)"
    hidden></div>
