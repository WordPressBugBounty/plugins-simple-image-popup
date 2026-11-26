import { defineConfig, normalizePath } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

const frontendEntry = normalizePath(path.resolve(__dirname, 'resources/js/frontend.js'));

export default defineConfig({
  plugins: [
    vue(),
    {
      name: 'classic-frontend-script',
      renderChunk(code, chunk) {
        if (chunk.facadeModuleId !== frontendEntry) {
          return null;
        }

        if (chunk.imports.length || chunk.dynamicImports.length || chunk.exports.length) {
          this.error('The popup frontend must build as a standalone classic script.');
        }

        // Isolate the classic script so its private state stays off the global scope.
        return { code: ["(function () {", "'use strict';", code, "})();"].join('\n'), map: null };
      },
    },
  ],
  build: {
    manifest: 'manifest.json',
    outDir: path.resolve(__dirname, 'assets/dist'),
    emptyOutDir: true,
    // Terser (not esbuild) so `/* translators: … */` comments survive minification and reach
    // translate.wordpress.org alongside the strings they describe.
    minify: 'terser',
    terserOptions: {
      // Vite runs terser in module mode, which would strip the classic frontend wrapper's 'use strict'.
      compress: {
        directives: false,
      },
      format: {
        comments: /translators:/i,
      },
    },
    rollupOptions: {
      input: {
        admin: path.resolve(__dirname, 'resources/js/admin/main.js'),
        frontend: frontendEntry,
      },
    },
  },
});
