<?php
namespace AFOS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Service_Worker {
    public function hooks(): void {
        add_action( 'wp_head', [ $this, 'inject_sw_registration' ] );
        add_action( 'wp_ajax_afos_sw', [ $this, 'render_sw' ] );
        add_action( 'wp_ajax_nopriv_afos_sw', [ $this, 'render_sw' ] );
    }

    public function inject_sw_registration(): void {
        // Small inline registration for PWA
        echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register(' . wp_json_encode( admin_url( 'admin-ajax.php?action=afos_sw' ) ) . ').catch(function(e){console.warn("AFOS SW reg failed",e);});});}</script>';
    }

    public function render_sw(): void {
        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' );

        $cache_key = 'afos-cache-v' . sanitize_key( AFOS_VERSION );
        $pages     = get_option( 'afos_pages_to_cache', array() );
        $urls      = array();
        if ( is_array( $pages ) ) {
            foreach ( $pages as $page_id ) {
                $url = get_permalink( (int) $page_id );
                if ( $url ) {
                    $urls[] = esc_url_raw( $url );
                }
            }
        }
        // include plugin assets
        $urls[] = esc_url_raw( AFOS_PLUGIN_URL . 'assets/js/offline-forms.js' );

        $precache = wp_json_encode( array_values( array_unique( $urls ) ) );

        echo "const AFOS_CACHE='{$cache_key}';\n";
        echo "const AFOS_PRECACHE={$precache};\n";
        echo <<<'JS'
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(AFOS_CACHE).then((cache) => cache.addAll(AFOS_PRECACHE)).catch((e)=>{})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys)=>Promise.all(keys.map((k)=>{if(k!==AFOS_CACHE){return caches.delete(k)}})))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method === 'GET' && (url.origin === self.location.origin)) {
    // Cache-first for same-origin pages and assets
    event.respondWith(
      caches.match(req).then((cached)=>{
        if (cached) return cached;
        return fetch(req).then((res)=>{
          const resClone = res.clone();
          caches.open(AFOS_CACHE).then((cache)=>{ cache.put(req, resClone); });
          return res;
        }).catch(()=>{
          // Offline fallback for HTML
          if (req.headers.get('accept') && req.headers.get('accept').includes('text/html')) {
            return caches.match('/');
          }
        });
      })
    );
  }
});
JS;
        exit;
    }
}