## File-system location

easyengien installer moves `easyengine` in `bin/` folder to `/usr/local/sbin/` using command:

````
cp -r bin/easyengine	/usr/local/sbin/easyengine
````

Then it creates symbolic link so that you get `ee comamnd`.

````
ln -s /usr/local/sbin/easyengine /usr/local/sbin/ee
````
