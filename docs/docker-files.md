Docker Files
============

1. nginx-proxy: EasyEngine nginx-proxy uses [jwilder/nginx-proxy](https://github.com/jwilder/nginx-proxy) as the base. Nginx-proxy sets up a container running nginx and docker-gen. docker-gen generates reverse proxy configs for nginx and reloads nginx when containers are started and stopped. The root permission in this image have been changed with user permission in the EasyEngine image.

2. nginx: EasyEngine nginx image uses [openresty](https://github.com/openresty/docker-openresty) as the base. It is a full-fledged web application nginx server. Multiple additional modules have been added to make it similar to the EasyEngine version 3 nginx server as well as add things on top of it.

3. php: EasyEngine php image uses [wordpress](https://github.com/docker-library/wordpress) as the base image. Additional php extension that may be required have been added on top of it, also the permissions of the site data on the host machine is given to the user creating the site in this image.

4. redis: EasyEngine redis image uses [redis](https://github.com/docker-library/redis) image.

5. mail: EasyEngine mail image uses [mailhog](https://github.com/mailhog/MailHog) image.

6. db: EasyEngine db image uses [mariadb](https://github.com/docker-library/mariadb/) image.

7. phpmyadmin: EasyEngine db image uses [phpmyadmin](https://github.com/phpmyadmin/docker) image.

8. base: EasyEngine base image has been created by EasyEngine to facilitate the usage of EasyEngine v4 without any software dependency. Pulling and using this image will create the enviournment required to run EasyEngine and create the sites from this container with the help of the wrapper-script. The wrapper-script simply passes the parameters/arguments/flags given to it directly to the easyengine phar inside the easyengine base container for execution and running of EasyEngine. 