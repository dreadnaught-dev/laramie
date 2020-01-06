var babel    = require('gulp-babel');
var fixme    = require('fixme');
var fs       = require('fs');
var gulp     = require('gulp');
var noop     = require('gulp-noop');
var plumber  = require('gulp-plumber'); // better error handling; don't totally break on an error, but still show it
var prettier = require('gulp-prettier');
var sass     = require('gulp-dart-sass');
var uglify   = require('gulp-uglify');
var watch    = require('gulp-watch'); // more granular watch ability than gulp.watch. For a discussion on gulp.watch vs gulp-watch see http://stackoverflow.com/a/22391756

var vendorJs = [
  './node_modules/cropperjs/dist/cropper.min.js',
  './node_modules/dragula/dist/dragula.min.js',
  './node_modules/handlebars/dist/handlebars.min.js',
  './node_modules/jquery/dist/jquery.min.js',
  './node_modules/tributejs/dist/tribute.min.js',
  './node_modules/trix/dist/trix.js',
  './node_modules/@fortawesome/fontawesome-free/js/all.js',
  './node_modules/select2/dist/js/select2.min.js',
];

var vendorCss = [
  './node_modules/dragula/dist/dragula.min.css',
  './node_modules/cropper/dist/cropper.min.css',
  './node_modules/tributejs/dist/tribute.css',
  './node_modules/trix/dist/trix.css',
  './node_modules/select2/dist/css/select2.min.css',
];

/* META TASKS (TASKS THAT WRAP OTHERS) */
gulp.task('default', gulp.series(gulp.parallel(styles, scripts), watchTask));
function watchTask() {
  watch(['resources/sass/**/*.scss'], { ignoreInitial: false }, styles);
  watch(['resources/js/**/*.js'], { ignoreInitial: false }, scripts);
}

/* CONCRETE TASKS */
gulp.task('prettier', prettier);
function prettier() {
  return gulp.src('resources/js/**/*.js')
    .pipe(plumber())
    .pipe(prettier({printWidth: 120, tabWidth: 2, trailingComma: 'es5'}))
    .pipe(gulp.dest('./resources/js'))
};

gulp.task('copy-vendor', copyVendor);
function copyVendor() {
  for (var i = 0; i < vendorJs.length; i ++) {
    var dest = './public/js' + (/fontawesome/i.test(vendorJs[i]) ? '/fontawesome' : '');
    gulp.src(vendorJs[i])
      .pipe(gulp.dest(dest))
  }
  for (var i = 0; i < vendorCss.length; i ++) {
    gulp.src(vendorCss[i])
      .pipe(gulp.dest('./public/css'))
  }
};

gulp.task('sass', styles);
function styles() {
  return gulp.src(['resources/sass/main.scss'])
    .pipe(plumber())
    .pipe(sass({outputStyle: 'compressed'}).on('error', sass.logError))
    .pipe(gulp.dest('./public/css'))
    .pipe(process.env.LIVE_COPY ? gulp.dest('../../../../public/laramie/admin/css') : noop())
    .on('end', () => console.log('Regenerated styles...'));
};

gulp.task('scripts', scripts);
function scripts() {
  return gulp.src('resources/js/**/*.js')
    .pipe(plumber())
    .pipe(babel({presets: ["@babel/preset-env"]}))
    .pipe(uglify())
    .pipe(gulp.dest('./public/js'))
    .pipe(process.env.LIVE_COPY ? gulp.dest('../../../../public/laramie/admin/js') : noop())
    .on('end', () => console.log('Regenerated scripts...'));
};

gulp.task('notes', function() {
  fixme({
    path:                 process.cwd(),
    ignored_directories:  ['node_modules/**', 'vendor/**', '.git/**', 'public/**'],
    file_patterns:        ['**/*.js', '**/*.php'],
    file_encoding:        'utf8',
    line_length_limit:    1000
  });
});

