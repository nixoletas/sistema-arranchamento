// Service worker do app do arranchamento. Só funciona por HTTPS (endereço do app).
// Páginas e consultas sempre vêm do servidor (o arranchamento precisa estar atualizado);
// sem conexão, mostra uma página avisando. CSS, JS e imagens ficam guardados para abrir mais rápido.
const CACHE = 'arranchamento-v1';
const OFFLINE = '/offline.html';
const PRE = [OFFLINE, '/assets/img/app-192.png', '/assets/img/logo.png', '/assets/css/bootstrap.css'];

self.addEventListener('install', (ev) => {
	ev.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (ev) => {
	ev.waitUntil(
		caches.keys()
			.then((nomes) => Promise.all(nomes.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
			.then(() => self.clients.claim())
	);
});

self.addEventListener('fetch', (ev) => {
	const req = ev.request;
	if (req.method !== 'GET') { return; }
	const url = new URL(req.url);

	// Navegação (abrir páginas): servidor; sem rede, a página de aviso.
	if (req.mode === 'navigate') {
		ev.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
		return;
	}

	// Arquivos estáticos do próprio site: usa o guardado e atualiza em segundo plano.
	if (url.origin === self.location.origin && url.pathname.startsWith('/assets/')) {
		ev.respondWith(caches.open(CACHE).then((c) =>
			c.match(req).then((guardado) => {
				const rede = fetch(req).then((resp) => {
					if (resp.ok) { c.put(req, resp.clone()); }
					return resp;
				}).catch(() => guardado);
				return guardado || rede;
			})
		));
	}
	// O resto (controllers, consultas) passa direto pelo servidor.
});
