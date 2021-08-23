#!/bin/bash

if [ ! -e ./IdentityService/build ]; then

    echo "Building IdentityService..."
    cd ./IdentityService
    ./gradlew build

    mkdir -p dist
    cp ./build/libs/identity_plugin-1.0.jar dist/identity_plugin.jar
    cd ..

fi

echo "Building ProcessEngine..."
cd ./ProcessEngine
./gradlew build

mkdir -p dist
cp ./build/libs/processengine_plugin-1.0.jar dist/processengine_plugin.jar
cd ..
