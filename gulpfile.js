import gulp from "gulp";
import concat from "gulp-concat";
import minify from "gulp-minify";
import cleanCss from "gulp-clean-css";
import rev from "gulp-rev";
import { deleteSync } from "del";

gulp.task(
  "pack-js",
  gulp.series(function () {
    deleteSync(["asset/public/*.js"]);
    return gulp
      .src(["asset/js/autocomplete.js", "asset/js/search.js"])
      .pipe(concat({ path: "bundle.js", cwd: "" }))
      .pipe(
        minify({
          ext: {
            min: ".js",
          },
          noSource: true,
        })
      )
      .pipe(rev())
      .pipe(gulp.dest("asset/public"))
      .pipe(
        rev.manifest({
          base: "asset/public",
          merge: true, // Merge with the existing manifest if one exists
        })
      )
      .pipe(gulp.dest("asset/public"));
  })
);

gulp.task(
  "pack-css",
  gulp.series(function () {
    deleteSync(["asset/public/*.css"]);
    return gulp
      .src(["asset/css/flex-kebab.css", "asset/css/search.css"])
      .pipe(concat({ path: "stylesheet.css", cwd: "" }))
      .pipe(cleanCss())
      .pipe(rev())
      .pipe(gulp.dest("asset/public"))
      .pipe(
        rev.manifest({
          base: "asset/public",
          merge: true, // Merge with the existing manifest if one exists
        })
      )
      .pipe(gulp.dest("asset/public"));
  })
);

gulp.task("watch", function () {
  gulp.watch(
    ["asset/js/*.js", "asset/css/*.css"],
    gulp.series("pack-js", "pack-css")
  );
});

gulp.task("default", gulp.series("pack-js", "pack-css", "watch"));
