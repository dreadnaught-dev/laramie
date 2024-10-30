import sass from 'sass';
import path from 'node:path';
import { writeFileSync, copyFileSync } from 'node:fs';

var vendorJs = [
  './node_modules/cropperjs/dist/cropper.min.js',
  './node_modules/dragula/dist/dragula.min.js',
  './node_modules/handlebars/dist/handlebars.min.js',
  './node_modules/jquery/dist/jquery.min.js',
  './node_modules/tributejs/dist/tribute.min.js',
  './node_modules/trix/dist/trix.esm.js',
  './node_modules/@fortawesome/fontawesome-free/js/all.js',
  './node_modules/select2/dist/js/select2.min.js',

  './resources/js/edit.js',
  './resources/js/jquery.conditionally-hide.js',
  './resources/js/list.js',
  './resources/js/main.js',
];

vendorJs.forEach((i) => {
    const destination = './public/js/' + (/fontawesome/i.test(i) ? 'fontawesome/' : '') + path.basename(i);
    copyFileSync(i, destination)
});

console.log('done building scripts');
