server{
	server_name	www.example.com	example.com;
 
	access_log       /var/log/nginx/example.com.access.log ;
	error_log       /var/log/nginx/example.com.error.log;

    root   /var/www/example.com/htdocs;
    index  index.php index.html index.htm;                                           

    ## PHP with FATSCGI
    location ~ \.php$ {
            include /etc/nginx/fastcgi_params;
            fastcgi_pass  127.0.0.1:9000;
            fastcgi_index index.php;
    }
                          
	#root dir
	location / {
			autoindex on;
			try_files $uri $uri/ /index.php;
	}    
} 
