EEv4
======

## Requirements

* Docker
* Docker-Compose
* PHP

## Installing

Once you've verified requirements, download the [ee.phar](https://raw.github.com/mbtamuli/ee4-builds/master/ee.phar) file using `wget` or `curl`:

```bash
curl -O https://raw.githubusercontent.com/mbtamuli/ee4-builds/master/ee.phar
```

Next, check the Phar file to verify that it's working:

```bash
php ee.phar cli info
```

To use EEv4 from the command line by typing `ee4`, make the file executable and move it to somewhere in your PATH. For example:

```bash
chmod +x ee.phar
sudo mv ee.phar /usr/local/bin/ee4
```

If EE was installed successfully, you should see something like this when you run `ee4 cli version`:

```bash
$ ee4 cli version
EE 0.0.1
```
