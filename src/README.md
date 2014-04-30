### Source-code layout

lib/ 			=> 		easyengine command function lib
core/ 			=> 		easyengine core logic (framework)
commands/ 		=> 		easyengine commands and subcommands


## File-system location

easyengien installer moves everything in `/src/` folder to `/usr/local/src/easyengine/` using command:

````
cp -r src/*	/usr/local/src/easyengine/
````