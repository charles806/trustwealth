#!/usr/bin/env bash
set -e

SRC="$HOME/Documents/CODE/project_bit"
DEST="/Applications/XAMPP/xamppfiles/htdocs/project_bit"

# Clear the old broken symlink if it's still there
if [ -L "$DEST" ]; then
    rm "$DEST"
fi

mkdir -p "$DEST"

rsync -a --delete \
    --exclude '.git' \
    --exclude 'sync.sh' \
    --exclude 'database/install.php' \
    --exclude '.DS_Store' \
    --exclude '.vscode' \
    "$SRC"/ "$DEST"/

echo "Synced -> $DEST"