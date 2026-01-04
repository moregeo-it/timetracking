import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [
    vue({
      template: {
        compilerOptions: {
          isCustomElement: (tag) => tag.startsWith('nc-')
        }
      }
    })
  ],
  build: {
    outDir: 'js',
    sourcemap: false,
    minify: 'terser',
    terserOptions: {
      ecma: 2020,
      compress: {
        drop_console: false,
        pure_funcs: [],
        passes: 2
      },
      mangle: true,
      format: {
        comments: false
      }
    },
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'src/main.js')
      },
      output: {
        entryFileNames: 'timetracking-main.js',
        assetFileNames: 'timetracking-[name].[ext]',
        manualChunks: undefined,
        format: 'iife',
        strict: false,
        generatedCode: {
          constBindings: true
        }
      }
    },
    target: 'es2020',
    cssCodeSplit: false,
    commonjsOptions: {
      transformMixedEsModules: true
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
      'vue': 'vue/dist/vue.runtime.esm-bundler.js'
    }
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    '__VUE_OPTIONS_API__': true,
    '__VUE_PROD_DEVTOOLS__': false,
    '__VUE_PROD_HYDRATION_MISMATCH_DETAILS__': false
  }
})
