import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig({
	root: resolve('theme'),
	// Assets are enqueued by PHP reading the manifest, so nothing is served from a dev
	// server and every URL must be relative to the theme directory.
	base: './',
	build: {
		outDir: resolve('theme/assets/dist'),
		emptyOutDir: true,
		manifest: true,
		rollupOptions: {
			input: {
				main: resolve('theme/src/js/main.js'),
				style: resolve('theme/src/css/main.css'),
			},
		},
	},
});
