FROM phusion/baseimage
CMD ["/sbin/my_init"]
RUN apt-get update && apt-get install -y mysql-server php5-common php5-mysql php5-cli
COPY ./php/ /tmp/php
RUN chmod +x /tmp/php/startup.sh
RUN ["/tmp/php/startup.sh", "4", "1", "100"]
