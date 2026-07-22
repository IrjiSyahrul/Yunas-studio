// ═══════════════════════════════════════════════════════════════════
// Laravel PWA Service Worker
//
// PENTING: Setiap kali kamu mengubah file yang di-precache (terutama
// offline.html) ATAU mengubah strategi fetch di file ini, WAJIB naikkan
// versi CACHE_NAME di bawah ini. Tanpa itu, browser tidak akan
// mendeteksi ada perubahan dan cache lama akan terus dipakai selamanya.
//
// Format bebas, yang penting berubah setiap kali ada update:
//   "laravel-pwa-v2", "laravel-pwa-2026-07-16", dst.
// ═══════════════════════════════════════════════════════════════════
const CACHE_NAME = "laravel-pwa-v2-20260716";
const OFFLINE_URL = "/offline.html";

const FILES_TO_CACHE = [
    "/",
    OFFLINE_URL
];

// ── INSTALL: pre-cache resource kritis ──────────────────────────────
self.addEventListener("install", (event) => {
    console.log('[Laravel PWA] Service Worker installing... (' + CACHE_NAME + ')');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                // { cache: 'reload' } memaksa fetch dari network (bypass HTTP
                // cache browser) saat pre-caching, supaya offline.html yang
                // tersimpan benar-benar versi terbaru dari server, bukan
                // versi lama yang mungkin masih di-cache oleh browser.
                const requests = FILES_TO_CACHE.map(
                    url => new Request(url, { cache: 'reload' })
                );
                return cache.addAll(requests);
            })
    );
});

// ── ACTIVATE: hapus semua cache lama yang namanya beda dari sekarang ──
self.addEventListener("activate", (event) => {
    console.log('[Laravel PWA] Service Worker activated. (' + CACHE_NAME + ')');

    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.map(key => {
                    if (key !== CACHE_NAME) {
                        console.log('[Laravel PWA] Menghapus cache lama:', key);
                        return caches.delete(key);
                    }
                })
            )
        ).then(() => self.clients.claim())
    );
});

// ── Terima pesan SKIP_WAITING dari halaman (untuk update instan) ────
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// ── FETCH: strategi berbeda tergantung jenis request ─────────────────
self.addEventListener("fetch", (event) => {

    const request = event.request;

    // Jangan pernah cache request non-GET (hindari error Cache.put untuk POST)
    if (request.method !== 'GET') {
        event.respondWith(fetch(request));
        return;
    }

    // Navigasi halaman (buka URL langsung / klik link) → fallback ke offline.html
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request)
                .catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Asset statis (CSS/JS/gambar/font) → cache-first, fallback ke network
    if (
        request.destination === "style" ||
        request.destination === "script" ||
        request.destination === "image" ||
        request.destination === "font"
    ) {
        event.respondWith(
            caches.match(request)
                .then(cached => {
                    return cached || fetch(request).then(response => {
                        return caches.open(CACHE_NAME).then(cache => {
                            cache.put(request, response.clone());
                            return response;
                        });
                    });
                })
        );
        return;
    }

    // Default (API, dsb.) → network-first, fallback ke cache kalau offline
    event.respondWith(
        fetch(request)
            .then(response => {
                return caches.open(CACHE_NAME).then(cache => {
                    cache.put(request, response.clone());
                    return response;
                });
            })
            .catch(async (error) => {
                // Retry request POST yang gagal lewat Background Sync jika didukung
                if (request.method === 'POST' && 'SyncManager' in self) {
                    // Penanganan queue POST offline sudah ditangani di background-sync.js
                }
                return caches.match(request);
            })
    );
});

// ── BACKGROUND SYNC ───────────────────────────────────────────────────
self.addEventListener('sync', (event) => {
    if (event.tag === 'laravel-pwa-sync') {
        event.waitUntil(syncRequests());
    }
});

async function syncRequests() {
    const db = await openDB();
    const tx = db.transaction('offline-requests', 'readonly');
    const store = tx.objectStore('offline-requests');
    const requests = await getAllRequests(store);

    for (const req of requests) {
        try {
            const response = await fetch(req.url, {
                method: req.method,
                headers: req.headers,
                body: req.body
            });

            if (response.ok) {
                const deleteTx = db.transaction('offline-requests', 'readwrite');
                deleteTx.objectStore('offline-requests').delete(req.id);
            }
        } catch (err) {
            console.error('[Laravel PWA] Sync failed for:', req.url, err);
        }
    }
}

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('laravel-pwa-sync', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function getAllRequests(store) {
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}