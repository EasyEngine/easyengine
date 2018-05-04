EasyEngine Developer Docs
=========================

## Requirements

* Git
* PHP
* Composer

## Steps

1. Before starting refer the [core repository structure](https://github.com/EasyEngine/easyengine/blob/develop-v4/docs/core-repo-structure.md) and the [structure for multiple repositories](https://github.com/EasyEngine/easyengine/blob/develop-v4/docs/structure-for-multiple-repos.md).

2. Fork the [command template repository](https://github.com/EasyEngine/command-template) and rename it to the command you want to create. This will now look like `author/command-name` in your github.

3. Update the `name` and `homepage` in the `composer.json` of the  cloned repository. If the name is not updated properly then composer update/install with it will fail. 

3. Clone the EasyEngine core repository locally and checkout the development branch.
```bash
git clone git@github.com:EasyEngine/easyengine.git && git checkout develop-v4 
```
4. Update the `composer.json` in the core repository, add the following in `require`:
```
"author/command-name": "dev-master"
```
Also, append the following section in the `composer.json`:
```
"repositories": {
    "author/command-name": {
        "type": "vcs",
        "url": "git@github.com:author/command-name.git"
    }
}
```
Or, you can add your repository to packagist and run `composer reqiure author/name`.

5. Run `composer update --prefer-source` in the core repository after this.
7. After that, try running the default command `hello-world` given in the template, it should give a success message as below:
```bash
$ ./bin/ee hello-world
Success: Hello world.
```
6. Your repository will be in the vendor directory. 
    * Go to your repository directory: `cd vendor/author/command-name`.
    * Try `git remote -v`, if you have already put the proper url and used `--prefer-source` during composer install then the `remote origin` will have your github url.
    * In case that is missing, do `git remote add origin git@github.com:author/command-name.git`.
    * Make changes inside the vendor directory itself to view your changes directly and keep committing them regularly.

Note: We are working on scaffold command and these manual steps will be replaced very soon.