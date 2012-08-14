server{
	server_name		example.com;
    listen          80;         
 
	access_log       /var/www/example.com/logs/access.log main ;
	error_log       /var/www/example.com/logs/error.log;

    root   /var/www/example.com/htdocs;
    index  index.php index.html index.htm;                                           

    ## PHP with FATSCGI
    location ~ \.php$ {
            include /etc/nginx/fastcgi_params;
            fastcgi_pass  127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
    }
                          
	#root dir
	location / {
			autoindex on;
			try_files $uri $uri/ /index.php?q=$uri&$args;
	}    
} 
