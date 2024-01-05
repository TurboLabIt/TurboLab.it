📂 The physical folder is located at [src/Forum/ext-turbolabit](https://github.com/TurboLabIt/TurboLab.it/tree/main/src/Forum/ext-turbolabit) and it's managed by Git.

While TLI1 remains live on production, this folder is a mirror of the production directory,
managed by [scripts/forum-download.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/forum-download.sh).

🔗 A symlink, located at `public/forum/ext/turbolabit`, allows phpBB to load it.

Such symlink is created by [scripts/cache-clear.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/cache-clear.sh).
