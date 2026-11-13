Karena working tree lokal masih punya `Welcome.vue` yang belum di-commit dan di luar scope perubahan ini, saya akan build image dari `git archive` commit tersebut saja agar file itu tidak ikut terkirim.

rm -rf $S/buildctx && mkdir -p $S/buildctx && git archive HEAD | tar -x -C $S/buildctx && ls $S/buildctx | head -30 && ls $S/buildctx/Dockerfile