#!/bin/bash

oxhome=$(pwd)

echo "Building IdentityService"
cd ${oxhome}/IdentityService
./gradlew build

mkdir -p dist

cp build/libs/identity_plugin-1.0.jar dist/identity_plugin.jar

echo "Building ProcessEngine"
cd ${oxhome}/ProcessEngine
./gradlew build --stacktrace -x test

mkdir -p dist

cp build/libs/processengine_plugin-1.0.jar dist/processengine_plugin.jar 
