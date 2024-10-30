import sass from 'sass';
import path from 'node:path';
import { writeFileSync, copyFileSync } from 'node:fs';

const vendorCss = [
    './node_modules/dragula/dist/dragula.min.css',
    './node_modules/cropperjs/dist/cropper.min.css',
    './node_modules/tributejs/dist/tribute.css',
    './node_modules/trix/dist/trix.css',
    './node_modules/select2/dist/css/select2.min.css',
];

vendorCss.forEach((i) => {
    const destination = `./public/css/${path.basename(i)}`;
    copyFileSync(i, destination)
});

const sassOutput = sass.compile('./resources/sass/main.scss');

writeFileSync('./public/css/main.css', sassOutput.css);

console.log('done building scripts');
