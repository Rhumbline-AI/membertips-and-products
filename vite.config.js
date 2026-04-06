import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => {
  const isWp = mode === 'wordpress'
  return {
    plugins: [react()],
    base: isWp ? './' : '/',
    build: {
      outDir: isWp ? 'wordpress/fsf-product-grid/app' : 'dist',
      emptyOutDir: true,
      rollupOptions: isWp
        ? {
            output: {
              entryFileNames: 'product-grid.js',
              chunkFileNames: 'product-grid.js',
              assetFileNames: 'product-grid.[ext]',
            },
          }
        : undefined,
    },
  }
})
