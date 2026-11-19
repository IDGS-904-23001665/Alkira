// Ruta en ALKIRA: AWP/vite.config.js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

// Se usa MySQL local por defecto a través del backend PHP local.
// Para probar contra Railway, definir ALKIRA_API_PROXY_TARGET al iniciar Vite.
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
        name: 'ALKIRA',
        short_name: 'ALKIRA',
        description: 'Administración inmobiliaria y condominial',
        theme_color: '#0b142e',
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,svg,png,ico}'],
        cleanupOutdatedCaches: true,
        clientsClaim: true,
      },
      devOptions: {
        enabled: false,
        navigateFallback: 'index.html',
        suppressWarnings: true,
        type: 'module',
      },
    }),
  ],
})
