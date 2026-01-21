import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import { copyFileSync, readdirSync, mkdirSync, existsSync, rmSync } from 'fs'
import { join } from 'path'

// Plugin to copy dist contents to parent folder
function copyDistToParent() {
  return {
    name: 'copy-dist-to-parent',
    closeBundle() {
      const distDir = resolve(__dirname, 'dist')
      const parentDir = resolve(__dirname)
      
      function copyRecursive(src, dest) {
        if (!existsSync(src)) return
        
        const entries = readdirSync(src, { withFileTypes: true })
        
        for (const entry of entries) {
          const srcPath = join(src, entry.name)
          const destPath = join(dest, entry.name)
          
          if (entry.isDirectory()) {
            if (!existsSync(destPath)) {
              mkdirSync(destPath, { recursive: true })
            }
            copyRecursive(srcPath, destPath)
          } else {
            copyFileSync(srcPath, destPath)
            console.log(`Copied: ${entry.name} to parent folder`)
          }
        }
      }
      
      copyRecursive(distDir, parentDir)
      console.log('✓ Dist contents copied to parent folder')
      
      // Remove dist directory after copying
      if (existsSync(distDir)) {
        rmSync(distDir, { recursive: true, force: true })
        console.log('✓ Dist directory removed')
      }
    }
  }
}

export default defineConfig({
  plugins: [vue(), copyDistToParent()],
  define: {
    'process.env': {},
    '__VUE_OPTIONS_API__': true,
    '__VUE_PROD_DEVTOOLS__': false,
    '__VUE_PROD_HYDRATION_MISMATCH_DETAILS__': false,
    'appName': JSON.stringify('timetracking'),
    'appVersion': JSON.stringify('1.0.0'),
  },
  resolve: {
    alias: {
      // FORCE absolute path to the bundler version of Vue.
      // This is required for "import Vue" to work correctly.
      vue: resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
    },
    dedupe: ['vue'] 
  },
  build: {
    // Fix: Build to 'dist' to avoid "outDir must not be root" warning
    outDir: 'dist',
    emptyOutDir: true,
    
    lib: {
      entry: resolve(__dirname, 'src/main.js'),
      name: 'timetracking',
      formats: ['iife'],
      fileName: (format) => 'js/timetracking-main.js',
    },
    
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/timetracking-style.css'
          }
          return 'css/[name][extname]'
        },
        globals: {
          vue: 'Vue',
        },
      },
    },
  },
})
