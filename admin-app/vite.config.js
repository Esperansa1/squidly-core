import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from 'tailwindcss'
import autoprefixer from 'autoprefixer'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@shared-ui': path.resolve(__dirname, '../shared-ui')
    }
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    chunkSizeWarningLimit: 300,
    rollupOptions: {
      input: './src/main.jsx',
      output: {
        entryFileNames: 'assets/main-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'assets/main-[hash].css';
          }
          return 'assets/[name]-[hash].[ext]';
        },
        manualChunks: {
          vendor: ['react', 'react-dom'],
          recharts: ['recharts'],
          icons: ['@heroicons/react']
        }
      }
    },
    target: 'es2015',
    minify: 'esbuild',
    sourcemap: true
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV)
  },
  css: {
    postcss: {
      plugins: [
        tailwindcss,
        autoprefixer,
      ],
    },
  },
  server: {
    port: 3000,
    open: false,
    cors: true
  }
})