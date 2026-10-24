#!/bin/sh
set -eu

project_root=$(git rev-parse --show-toplevel)
output_path=${1:-"$project_root/waterboom-upload.zip"}

case "$output_path" in
    /*) ;;
    *) output_path="$project_root/$output_path" ;;
esac

cd "$project_root"
git archive \
    --format=zip \
    --output="$output_path" \
    HEAD \
    .htaccess \
    .env.example \
    admin \
    app \
    assets \
    config \
    public \
    resources \
    ':(exclude)assets/.DS_Store' \
    ':(exclude)public/assets/action_shot.png' \
    ':(exclude)public/assets/bca_logo.png' \
    ':(exclude)public/assets/hero.png' \
    ':(exclude)public/assets/pool2.png' \
    ':(exclude)public/assets/qrs_payment-compressed.webp'

printf 'Paket upload dibuat: %s\n' "$output_path"
du -h "$output_path"
