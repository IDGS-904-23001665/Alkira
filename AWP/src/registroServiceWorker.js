export function registrarServiceWorker() {
  if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
    return
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .catch((error) => {
        console.error('No fue posible registrar el Service Worker de ALKIRA.', error)
      })
  }, { once: true })
}
