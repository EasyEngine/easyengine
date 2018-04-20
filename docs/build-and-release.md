Build and Release Process
===

Currently EasyEngine v4 is in development stage. Hence the main branch for v4 is [release/v4](https://github.com/easyengine/easyengine/tree/release/v4) and the ongoing development branch is [develop-v4](https://github.com/easyengine/easyengine/tree/develop-v4).

The current build and release process uses [travis-ci](https://travis-ci.org/) and docker build triggers.

* Whenever any commit is pushed or merged into the `develop-v4` branch, it trigggers the travis-ci to generate a `nightly phar` and commits it to the [easyengine-builds](https://github.com/easyengine/easyengine-builds) repository, after that the docker build trigger for the `latest` tag is invoked and it takes the newly committed `nightly phar` and builds the `base image` with it.

* Simillarly, whenever any commit is pushed or merged into the `releas/v4` branch, it trigggers the travis-ci to generate a `stable phar` and commits it to the [easyengine-builds](https://github.com/easyengine/easyengine-builds) repository, after that the docker build trigger for the `stable` tag is invoked and it takes the newly committed `stable phar` and builds the `base image` with it.

As there are a few issues with docker build triggers and the build fails at times, we may shift to builduing and pushing the docker image from travis-ci itself in the near future.