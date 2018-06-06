Docker Files
============

1. traefik: EasyEngine traefik uses [traefik](https://github.com/containous/traefik) for load-balancing. Træfik is a modern HTTP reverse proxy and load balancer that makes deploying microservices easy.

2. nginx: EasyEngine nginx image uses [openresty](https://github.com/openresty/docker-openresty) as the base. It is a full-fledged web application nginx server. Multiple additional modules have been added to make it similar to the EasyEngine version 3 nginx server as well as to add EasyEngine's custom configuration on top of it.

3. php: EasyEngine php image uses [wordpress](https://github.com/docker-library/wordpress) as the base image. Additional php extension that may be required have been added on top of it, also the permissions of the site data on the host machine is given to the user creating the site in this image.

4. redis: EasyEngine redis image uses [redis](https://github.com/docker-library/redis) image.

5. mail: EasyEngine mail image uses [mailhog](https://github.com/mailhog/MailHog) image.

6. db: EasyEngine db image uses [mariadb](https://github.com/docker-library/mariadb/) image.

7. phpmyadmin: EasyEngine db image uses [phpmyadmin](https://github.com/phpmyadmin/docker) image.