import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import {defineConfig} from 'vite';

export default defineConfig(() => {
  return {
    plugins: [
      react(),
      tailwindcss(),
      {
        name: "defer-css",
        enforce: "post",
        transformIndexHtml(html) {
          return html.replace(/<link rel="stylesheet"[^>]+>/g, (match) => {
            const href = match.match(/href="([^"]+)"/)?.[1];
            if (!href) return match;
            return `<link rel="preload" href="${href}" as="style" onload="this.onload=null;this.rel='stylesheet'"><noscript>${match}</noscript>`;
          });
        },
      },
    ],
    build: {
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes('node_modules/react-dom') || id.includes('node_modules/react/')) return 'vendor-react';
            if (id.includes('node_modules/motion')) return 'vendor-anim';
            if (id.includes('node_modules/lucide-react')) return 'vendor-icons';
            if (id.includes('node_modules/jspdf')) return 'vendor-pdf';
          },
        },
      },
    },
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    server: {
      // HMR is disabled in AI Studio via DISABLE_HMR env var.
      // Do not modifyâfile watching is disabled to prevent flickering during agent edits.
      hmr: process.env.DISABLE_HMR !== 'true',
      // Disable file watching when DISABLE_HMR is true to save CPU during agent edits.
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
    },
  };
});
