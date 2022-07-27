#!/bin/bash

if [ ! -d ./IdentityService/build ]; then

    echo "Building IdentityService..."
    cd ./IdentityService
    chmod 777 ./gradlew
    ./gradlew build

    chmod 777 .
    mkdir -p dist
    cp ./build/libs/identity_plugin-1.0.jar dist/identity_plugin.jar
    cd ..

fi

echo "Building ProcessEngine..."
cd ./ProcessEngine
chmod 777 ./gradlew
./gradlew build

chmod 777 .
mkdir -p dist
cp ./build/libs/processengine_plugin-1.0.jar dist/processengine_plugin.jar
cd ..
