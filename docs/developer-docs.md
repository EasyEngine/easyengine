EasyEngine Developer Docs
=========================

## Requirements

* Git
* PHP
* Composer

Before starting refer the [core repository structure](docs/core-repo-structure.md) and the [structure for multiple repositories](docs/structure-for-multiple-repos.md).

## Steps for working on core repository

1. Fork the EasyEngine core repository, clone it locally and checkout the development branch.
```bash
git clone git@github.com:your-username/easyengine.git && git checkout develop-v4 
```
2. Run `composer install` in the core repository after this.  

3. Make required changes and check them by running it locally using the following from the easyengine repository root:

```bash
$ ./bin/ee command
```

## Steps for working on existing commands

1. Clone the EasyEngine core repository locally and checkout the development branch.
```bash
git clone git@github.com:EasyEngine/easyengine.git && git checkout develop-v4 
```

2. Fork the command you want to work on.

3. Update the `composer.json` in the EasyEngine core repository, replace that command from `require` block to have your command name. For example lets replace site command: 

~~```"easyengine/site-command": "dev-master"```~~

```"your-username/site-command": "dev-master"```

4. Append the following section in the `composer.json` for development:
```
"repositories": {
    "author/command-name": {
        "type": "path",
        "url": "path/to/your/forked/repository"
    }
}
```

5. Run `composer update` in the core repository after this.

6. Then, run your commands locally using the following from the easyengine repository root:
```bash
$ ./bin/ee command
```

## Steps for creating a new command

1. Fork the [command template repository](https://github.com/EasyEngine/command-template) and rename it to the command you want to create. This will now look like `author/command-name` in your github.

2. Update the `name` and `homepage` in the `composer.json` of the  cloned repository. If the name is not updated properly then composer update/install with it will fail. 

3. Clone the EasyEngine core repository locally and checkout the development branch.
```bash
git clone git@github.com:EasyEngine/easyengine.git && git checkout develop-v4 
```
4. Update the `composer.json` in the EasyEngine core repository, add the following in `require`:
```
"author/command-name": "dev-master"
```
Also, append the following section in the `composer.json` for development:
```
"repositories": {
    "author/command-name": {
        "type": "path",
        "url": "path/to/your/forked/repository"
    }
}
```

Or, you can add your repository to packagist and run `composer reqiure author/name`.

5. Run `composer update` in the core repository after this.
6. After that, try running the default command `hello-world` given in the template, it should give a success message as below by running it from the easyengine repository root:
```bash
$ ./bin/ee hello-world
Success: Hello world.
```
7. Your repository will be in the vendor directory. 
    * Go to your repository directory: `cd vendor/author/command-name`.
    * Try `git remote -v`, if you have already put the proper url and used `--prefer-source` during composer install then the `remote origin` will have your github url.
    * In case that is missing, do `git remote add origin git@github.com:author/command-name.git`.
    * Make changes inside the vendor directory itself to view your changes directly and keep committing them regularly.

Note: We are working on scaffold command and these manual steps will be replaced very soon.