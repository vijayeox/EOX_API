#!/bin/bash

build_projects(){
    cd $1
    for D in $(find . -mindepth 1 -maxdepth 1 -type d) ; do
        build_project $D
    done
    cd ..
}
build_project() {
    echo "Building $1 ...";
    cd $1
    npm install
    npm run build
    cd ..
}
APPS="apps"
BOS="bos"
ICON_PACKS="iconpacks"
THEMES="themes"

build_projects $APPS
build_projects $ICON_PACKS
build_projects $THEMES
build_project $BOS
cd $BOS
    npm run package:discover
cd ..


