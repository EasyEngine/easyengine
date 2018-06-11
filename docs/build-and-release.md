Build and Release Process
===

Currently EasyEngine v4 is in development stage. Hence the main branch for v4 is [master-v4](https://github.com/easyengine/easyengine/tree/master-v4) and the ongoing development branch is [develop-v4](https://github.com/easyengine/easyengine/tree/develop-v4).

The current build and release process uses [travis-ci](https://travis-ci.org/).

* Whenever any commit is pushed or merged into the `develop-v4` branch, it triggers the travis-ci to generate a `nightly phar` and commits it to the [easyengine-builds](https://github.com/easyengine/easyengine-builds) repository.

* Similarly, whenever any commit is pushed or merged into the `releas/v4` branch, it triggers the travis-ci to generate a `stable phar` and commits it to the [easyengine-builds](https://github.com/easyengine/easyengine-builds) repository.