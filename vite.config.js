import { defineConfig } from 'vite'
import { viteCopyAssetFiles, viteEmptyDirs, viteWPConfig } from '@wp-strap/vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [
    vue(),
    viteWPConfig(),
    viteCopyAssetFiles(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
});