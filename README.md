EE
======

[EE](https://ee.org/) is the command-line interface for [WordPress](https://wordpress.org/). You can update plugins, configure multisite installs and much more, without using a web browser.

Ongoing maintenance is <a href="https://make.wordpress.org/cli/2017/04/03/new-co-maintainer-alain-thanks-2017-sponsors/#sponsors">made possible by</a>:

<a href="https://automattic.com/"><img src="https://make.wordpress.org/cli/files/2017/04/automattic-1.png" style="width:19%;height:auto;display:inline-block;vertical-align:middle;" alt="" width="160" height="35" class="aligncenter size-full wp-image-347" /></a> <a href="https://www.bluehost.com/"><img class="aligncenter size-full wp-image-335" style="width:19%;height:auto;display:inline-block;vertical-align:middle;" src="https://make.wordpress.org/cli/files/2017/04/bluehost.png" alt="" width="160" height="26" /></a> <a href="https://www.dreamhost.com/"><img class="aligncenter size-full wp-image-324" style="width:19%;height:auto;display:inline-block;vertical-align:middle;" src="https://make.wordpress.org/cli/files/2017/04/dreamhost.png" alt="" width="160" height="30" /></a> <a href="https://www.siteground.com/"><img class="aligncenter size-full wp-image-332" style="width:19%;height:auto;display:inline-block;vertical-align:middle;" src="https://make.wordpress.org/cli/files/2017/04/siteground.png" alt="" width="160" height="33" /></a> <a href="https://wpengine.com/"><img class="aligncenter size-full wp-image-333" style="width:19%;height:auto;display:inline-block;vertical-align:middle;" src="https://make.wordpress.org/cli/files/2017/04/wpengine.png" alt="" width="160" height="30" /></a>

The current stable release is [version 1.5.0](https://make.wordpress.org/cli/2018/01/31/version-1-5-0-released/). For announcements, follow [@wpcli on Twitter](https://twitter.com/wpcli) or [sign up for email updates](https://make.wordpress.org/cli/subscribe/). [Check out the roadmap](https://make.wordpress.org/cli/handbook/roadmap/) for an overview of what's planned for upcoming releases.

[![Build Status](https://travis-ci.org/ee/ee.svg?branch=master)](https://travis-ci.org/ee/ee) [![Dependency Status](https://gemnasium.com/badges/github.com/ee/ee.svg)](https://gemnasium.com/github.com/ee/ee) [![Average time to resolve an issue](https://isitmaintained.com/badge/resolution/ee/ee.svg)](https://isitmaintained.com/project/ee/ee "Average time to resolve an issue") [![Percentage of issues still open](https://isitmaintained.com/badge/open/ee/ee.svg)](https://isitmaintained.com/project/ee/ee "Percentage of issues still open")

Quick links: [Using](#using) &#124; [Installing](#installing) &#124; [Support](#support) &#124; [Extending](#extending) &#124; [Contributing](#contributing) &#124; [Credits](#credits)

## Using

EE provides a command-line interface for many actions you might perform in the WordPress admin. For instance, `wp plugin install --activate` ([doc](https://developer.wordpress.org/cli/commands/plugin/install/)) lets you install and activate a WordPress plugin:

```bash
$ wp plugin install user-switching --activate
Installing User Switching (1.0.9)
Downloading install package from https://downloads.wordpress.org/plugin/user-switching.1.0.9.zip...
Unpacking the package...
Installing the plugin...
Plugin installed successfully.
Activating 'user-switching'...
Plugin 'user-switching' activated.
Success: Installed 1 of 1 plugins.
```

EE also includes commands for many things you can't do in the WordPress admin. For example, `wp transient delete --all` ([doc](https://developer.wordpress.org/cli/commands/transient/delete/)) lets you delete one or all transients:

```bash
$ wp transient delete --all
Success: 34 transients deleted from the database.
```

For a more complete introduction to using EE, read the [Quick Start guide](https://make.wordpress.org/cli/handbook/quick-start/). Or, catch up with [shell friends](https://make.wordpress.org/cli/handbook/shell-friends/) to learn about helpful command line utilities.

Already feel comfortable with the basics? Jump into the [complete list of commands](https://developer.wordpress.org/cli/commands/) for detailed information on managing themes and plugins, importing and exporting data, performing database search-replace operations and more.

## Installing

Downloading the Phar file is our recommended installation method for most users. Should you need, see also our documentation on [alternative installation methods](https://make.wordpress.org/cli/handbook/installing/).

Before installing EE, please make sure your environment meets the minimum requirements:

- UNIX-like environment (OS X, Linux, FreeBSD, Cygwin); limited support in Windows environment
- PHP 5.3.29 or later
- WordPress 3.7 or later. Versions older than the latest WordPress release may have degraded functionality

Once you've verified requirements, download the [ee.phar](https://raw.github.com/ee/builds/gh-pages/phar/ee.phar) file using `wget` or `curl`:

```bash
curl -O https://raw.githubusercontent.com/ee/builds/gh-pages/phar/ee.phar
```

Next, check the Phar file to verify that it's working:

```bash
php ee.phar --info
```

To use EE from the command line by typing `wp`, make the file executable and move it to somewhere in your PATH. For example:

```bash
chmod +x ee.phar
sudo mv ee.phar /usr/local/bin/wp
```

If EE was installed successfully, you should see something like this when you run `wp --info`:

```bash
$ wp --info
OS:	Darwin 16.7.0 Darwin Kernel Version 16.7.0: Thu Jan 11 22:59:40 PST 2018; root:xnu-3789.73.8~1/RELEASE_X86_64 x86_64
Shell:	/bin/zsh
PHP binary:    /usr/local/bin/php
PHP version:    7.0.22
php.ini used:   /etc/local/etc/php/7.0/php.ini
EE root dir:        /home/ee/.ee
EE vendor dir:	    /home/ee/.ee/vendor
EE packages dir:    /home/ee/.ee/packages/
EE global config:   /home/ee/.ee/config.yml
EE project config:
EE version: 1.5.0
```

### Updating

You can update EE with `wp cli update` ([doc](https://developer.wordpress.org/cli/commands/cli/update/)), or by repeating the installation steps.

If EE is owned by root or another system user, you'll need to run `sudo wp cli update`.

Want to live life on the edge? Run `wp cli update --nightly` to use the latest nightly build of EE. The nightly build is more or less stable enough for you to use in your development environment, and always includes the latest and greatest EE features.

### Tab completions

EE also comes with a tab completion script for Bash and ZSH. Just download [wp-completion.bash](https://raw.githubusercontent.com/ee/ee/master/utils/wp-completion.bash) and source it from `~/.bash_profile`:

```bash
source /FULL/PATH/TO/wp-completion.bash
```

Don't forget to run `source ~/.bash_profile` afterwards.

If using zsh for your shell, you may need to load and start `bashcompinit` before sourcing. Put the following in your `.zshrc`:

```bash
autoload bashcompinit
bashcompinit
source /FULL/PATH/TO/wp-completion.bash
```

## Support

EE's maintainers and contributors have limited availability to address general support questions. The [current version of EE](https://make.wordpress.org/cli/handbook/roadmap/) is the only officially supported version.

When looking for support, please first search for your question in these venues:

* [Common issues and their fixes](https://make.wordpress.org/cli/handbook/common-issues/)
* [EE handbook](https://make.wordpress.org/cli/handbook/)
* [Open or closed issues in the EE GitHub organization](https://github.com/issues?utf8=%E2%9C%93&q=sort%3Aupdated-desc+org%3Aee+is%3Aissue)
* [Threads tagged 'EE' in the WordPress.org support forum](https://wordpress.org/support/topic-tag/ee/)
* [Questions tagged 'EE' in the WordPress StackExchange](https://wordpress.stackexchange.com/questions/tagged/ee)

If you didn't find an answer in one of the venues above, you can:

* Join the `#cli` channel in the [WordPress.org Slack](https://make.wordpress.org/chat/) to chat with whomever might be available at the time. This option is best for quick questions.
* [Post a new thread](https://wordpress.org/support/forum/wp-advanced/#new-post) in the WordPress.org support forum and tag it 'EE' so it's seen by the community.

GitHub issues are meant for tracking enhancements to and bugs of existing commands, not general support. Before submitting a bug report, please [review our best practices](https://make.wordpress.org/cli/handbook/bug-reports/) to help ensure your issue is addressed in a timely manner.

Please do not ask support questions on Twitter. Twitter isn't an acceptable venue for support because: 1) it's hard to hold conversations in under 140 characters, and 2) Twitter isn't a place where someone with your same question can search for an answer in a prior conversation.

Remember, libre != gratis; the open source license grants you the freedom to use and modify, but not commitments of other people's time. Please be respectful, and set your expectations accordingly.

## Extending

A **command** is the atomic unit of EE functionality. `wp plugin install` ([doc](https://developer.wordpress.org/cli/commands/plugin/install/)) is one command. `wp plugin activate` ([doc](https://developer.wordpress.org/cli/commands/plugin/activate/)) is another.

EE supports registering any callable class, function, or closure as a command. It reads usage details from the callback's PHPdoc. `EE::add_command()` ([doc](https://make.wordpress.org/cli/handbook/internal-api/ee-add-command/)) is used for both internal and third-party command registration.

```php
/**
 * Delete an option from the database.
 *
 * Returns an error if the option didn't exist.
 *
 * ## OPTIONS
 *
 * <key>
 * : Key for the option.
 *
 * ## EXAMPLES
 *
 *     $ wp option delete my_option
 *     Success: Deleted 'my_option' option.
 */
$delete_option_cmd = function( $args ) {
	list( $key ) = $args;

	if ( ! delete_option( $key ) ) {
		EE::error( "Could not delete '$key' option. Does it exist?" );
	} else {
		EE::success( "Deleted '$key' option." );
	}
};
EE::add_command( 'option delete', $delete_option_cmd );
```

EE comes with dozens of commands. It's easier than it looks to create a custom EE command. Read the [commands cookbook](https://make.wordpress.org/cli/handbook/commands-cookbook/) to learn more. Browse the [internal API docs](https://make.wordpress.org/cli/handbook/internal-api/) to discover a variety of helpful functions you can use in your custom EE command.

## Contributing

We appreciate you taking the initiative to contribute to EE. It’s because of you, and the community around you, that EE is such a great project.

**Contributing isn’t limited to just code.** We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

Read through our [contributing guidelines in the handbook](https://make.wordpress.org/cli/handbook/contributing/) for a thorough introduction to how you can get involved. Following these guidelines helps to communicate that you respect the time of other contributors on the project. In turn, they’ll do their best to reciprocate that respect when working with you, across timezones and around the world.

## Leadership

EE has two project maintainers: [danielbachhuber](https://github.com/danielbachhuber) and [schlessera](http://github.com/schlessera).

On occasion, we [grant write access to contributors](https://make.wordpress.org/cli/handbook/committers-credo/) who have demonstrated, over a period of time, that they are capable and invested in moving the project forward.

Read the [governance document in the handbook](https://make.wordpress.org/cli/handbook/governance/) for more operational details about the project.

## Credits

Besides the libraries defined in [composer.json](composer.json), we have used code or ideas from the following projects:

* [Drush](https://github.com/drush-ops/drush) for... a lot of things
* [wpshell](https://code.trac.wordpress.org/browser/wpshell) for `wp shell`
* [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/) for `wp media regenerate`
* [Search-Replace-DB](https://github.com/interconnectit/Search-Replace-DB) for `wp search-replace`
* [WordPress-CLI-Exporter](https://github.com/Automattic/WordPress-CLI-Exporter) for `wp export`
* [WordPress-CLI-Importer](https://github.com/Automattic/WordPress-CLI-Importer) for `wp import`
* [wordpress-plugin-tests](https://github.com/benbalter/wordpress-plugin-tests/) for `wp scaffold plugin-tests`
