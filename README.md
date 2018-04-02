EEv4
======

## Requirements

* Docker
* Docker-Compose
* PHP

## Installing

Once you've verified requirements, download the [ee4.phar](https://raw.githubusercontent.com/mrrobot47/ee4-builds/master/ee4.phar) file using `wget` or `curl`:

```bash
curl -O https://raw.githubusercontent.com/mrrobot47/ee4-builds/master/ee4.phar
```

Next, check the Phar file to verify that it's working:

```bash
php ee4.phar cli info
```

To use EEv4 from the command line by typing `ee4`, make the file executable and move it to somewhere in your PATH. For example:

```bash
chmod +x ee4.phar
sudo mv ee4.phar /usr/local/bin/ee4
```

If EE was installed successfully, you should see something like this when you run `ee4 cli version`:

```bash
$ ee4 cli version
EE 0.0.1
```
