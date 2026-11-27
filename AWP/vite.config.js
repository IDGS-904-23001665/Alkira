import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

const destinoApi = process.env.ALKIRA_API_PROXY_TARGET || 'http://127.0.0.1:8000'

export default defineConfig({
  server: {
    proxy: {
      '/api': {
        target: destinoApi,
        changeOrigin: true,
      },
    },
  },
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      injectRegister: false,
      pwaAssets: {
        disabled: false,
        config: true,
      },
      manifest: {
        id: '/',
        name: 'ALKIRA',
        short_name: 'ALKIRA',
        description: 'Administración inmobiliaria y condominial',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        lang: 'es-MX',
        theme_color: '#0b142e',
        background_color: '#f4f7fc',
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,svg,png,ico,woff,woff2}'],
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api(?:\/|$)/],
        cleanupOutdatedCaches: true,
        clientsClaim: true,
        skipWaiting: true,
      },
      devOptions: {
        enabled: false,
      },
    }),
  ],
})