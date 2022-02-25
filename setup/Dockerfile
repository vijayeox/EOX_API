FROM phusion/baseimage:bionic-1.0.0
# SETUP Docker Arguments
ARG VERSION=7.9.0
ARG DISTRO=tomcat
ARG CAMUNDA_DB
ARG TOMCAT_PORT=8090
ARG DB_HOST
ARG DB_USERNAME
ARG DB_PASSWORD
ARG API_DB
ARG TEST_API_DB
ARG QUARTZ_DB
ARG HOST

ENV DOCKER_USER_ID 501 
ENV DOCKER_USER_GID 20

ENV BOOT2DOCKER_ID 1000
ENV BOOT2DOCKER_GID 50
ENV PHPMYADMIN_VERSION=5.0.2
ENV SUPERVISOR_VERSION=4.2.0

RUN apt-get update

# Tweaks to give Apache/PHP write permissions to the app
RUN usermod -u ${BOOT2DOCKER_ID} www-data && \
    usermod -G staff www-data && \
    useradd -r mysql && \
    usermod -G staff mysql

RUN groupmod -g $(($BOOT2DOCKER_GID + 10000)) $(getent group $BOOT2DOCKER_GID | cut -d: -f1)
RUN groupmod -g ${BOOT2DOCKER_GID} staff

ENV DEBIAN_FRONTEND noninteractive

# Setup Nodejs
RUN rm -rf /var/lib/apt/lists/* && curl -sL https://deb.nodesource.com/setup_14.x | bash - 

RUN add-apt-repository -y ppa:ondrej/php && \
  apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C && \
  apt-get update --fix-missing 

RUN apt-get -o Dpkg::Options::="--force-confnew" --force-yes -fuy upgrade
RUN apt-get -y install postfix python3-setuptools wget git apache2 php-xdebug libapache2-mod-php7.2 php7.2-fpm mysql-server zip unzip curl php7.2-cli php7.2-dev 
RUN apt-get -y install php7.2-mysql php7.2-gd php7.2-imagick php7.2-recode php7.2-tidy php7.2-xmlrpc php7.2-curl php7.2-json php7.2-msgpack php7.2-memcached php7.2-intl 
RUN apt-get -y install php7.2-sqlite3 php7.2-gmp php7.2-geoip php7.2-mbstring php7.2-xml php7.2-zip php7.2-imap php7.2-soap supervisor php7.2-imagick 
RUN apt-get -y install libapache2-mod-xsendfile dos2unix xmlstarlet tar tzdata ca-certificates nodejs zlib1g-dev libglib2.0-dev rsync

COPY pdftk_installer.sh /

RUN chmod +x pdftk_installer.sh && bash /pdftk_installer.sh

RUN apt-get -y autoremove

# Apache2 conf
RUN echo "# Include vhost conf" >> /etc/apache2/apache2.conf 
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf 
RUN echo "<Directory /var/www/public> " >> /etc/apache2/apache2.conf 
RUN echo "    Options Indexes FollowSymLinks Includes ExecCGI" >> /etc/apache2/apache2.conf 
RUN echo "    AllowOverride None" >> /etc/apache2/apache2.conf 
RUN echo "    Require all granted" >> /etc/apache2/apache2.conf 
RUN echo "</Directory>" >> /etc/apache2/apache2.conf 

# Timezone settings
ENV TIMEZONE="UTC"
RUN echo "date.timezone = '${TIMEZONE}'" >> /etc/php/7.2/apache2/php.ini && \
  echo "${TIMEZONE}" > /etc/timezone && dpkg-reconfigure --frontend=noninteractive tzdata

RUN sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen && \
    echo 'LANG="en_US.UTF-8"'>/etc/default/locale && \
    dpkg-reconfigure --frontend=noninteractive locales && \
    update-locale LANG=en_US.UTF-8
ENV LANG en_US.UTF-8 
ENV LC_ALL en_US.UTF-8  

ADD ./configs/my.cnf /etc/mysql/conf.d/my.cnf

# Remove pre-installed database
RUN rm -rf /var/lib/mysql/*

# Add MySQL utils
ADD create_mysql_admin_user.sh /create_mysql_admin_user.sh
RUN chmod 755 /*.sh

# config Apache
RUN a2enmod actions ssl rewrite headers

# Environment variables to configure php
ENV PHP_UPLOAD_MAX_FILESIZE 200M
ENV PHP_POST_MAX_SIZE 200M
ENV PHP_MEMORY_LIMIT 1024M

RUN chown -R mysql:mysql /var/lib/mysql

# Add alias
RUN echo "alias node='nodejs'" >> ~/.bashrc

# Phpmyadmin
RUN wget https://files.phpmyadmin.net/phpMyAdmin/4.8.5/phpMyAdmin-4.8.5-all-languages.zip -P /tmp/phpmyadmin/
RUN unzip /tmp/phpmyadmin/phpMyAdmin-4.8.5-all-languages.zip -d /tmp/phpmyadmin/
RUN mkdir -p /var/www/phpmyadmin
RUN mv /tmp/phpmyadmin/phpMyAdmin-4.8.5-all-languages/* /var/www/phpmyadmin/
ADD configs/phpmyadmin/config.inc.php /var/www/phpmyadmin/config.inc.php
RUN chmod 755 /var/www/phpmyadmin/config.inc.php

# Symfony 2 pre requisted
RUN curl -sS https://getcomposer.org/installer | php -- --version=1.10.7
RUN mv composer.phar /usr/local/bin/composer

RUN chmod 755 /*.sh
RUN mkdir -p /app/api
RUN mkdir -p /app/view
RUN mkdir -p /app/camel
RUN mkdir -p /app/smtp
RUN mkdir -p /app/workflow
RUN mkdir -p /app/clients

# Temp Fix for Issues in missing repositories
RUN touch /etc/apt/apt.conf.d/99fixbadproxy \
    && echo "Acquire::http::Pipeline-Depth 0;" >> /etc/apt/apt.conf.d/99fixbadproxy \
    && echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99fixbadproxy \
    && echo "Acquire::BrokenProxy true;" >> /etc/apt/apt.conf.d/99fixbadproxy \
    && apt-get update -o Acquire::CompressionTypes::Order::=gz \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get update -y
RUN apt-get install -y --fix-missing default-jre

ENV ACTIVEMQ_VERSION 5.15.6
ENV ACTIVEMQ apache-activemq-$ACTIVEMQ_VERSION
ENV ACTIVEMQ_TCP=61616 ACTIVEMQ_AMQP=5672 ACTIVEMQ_STOMP=61613 ACTIVEMQ_MQTT=1883 ACTIVEMQ_WS=61614 ACTIVEMQ_UI=8161
ENV SHA512_VAL=a1b931a25c513f83f4f712cc126ee67a2b196ea23a243aa6cafe357ea03f721fba6cb566701e5c0e1f2f7ad8954807361364635c45d5069ec2dbf0ba5c6b588b

ENV ACTIVEMQ_HOME /app/activemq

RUN curl "https://archive.apache.org/dist/activemq/$ACTIVEMQ_VERSION/$ACTIVEMQ-bin.tar.gz" -o $ACTIVEMQ-bin.tar.gz

# Validate checksum
RUN if [ "$SHA512_VAL" != "$(sha512sum $ACTIVEMQ-bin.tar.gz | awk '{print($1)}')" ];\
    then \
        echo "sha512 values doesn't match! exiting."  && \
        exit 1; \
    fi;

RUN tar xzf $ACTIVEMQ-bin.tar.gz -C  /opt && \
    ln -s /opt/$ACTIVEMQ $ACTIVEMQ_HOME && \
    useradd -r -M -d $ACTIVEMQ_HOME activemq && \
    chown -R activemq:activemq /opt/$ACTIVEMQ && \
    chown -h activemq:activemq $ACTIVEMQ_HOME && \
    mkdir -p /app 
RUN wget -O /usr/local/bin/wait-for-it.sh \ 
    "https://raw.githubusercontent.com/vishnubob/wait-for-it/db049716e42767d39961e95dd9696103dca813f1/wait-for-it.sh" && \
    chmod +x /usr/local/bin/wait-for-it.sh

COPY camunda-tomcat.sh /tmp/

ARG CAMUNDA_REPO="https://camunda.org/release/camunda-bpm/tomcat/7.9/"

RUN wget --progress=bar:force:noscroll -O /tmp/camunda.tar.gz "${CAMUNDA_REPO}camunda-bpm-${DISTRO}-${VERSION}.tar.gz"

# Unpack distro to /camunda directory
RUN mkdir -p /camunda \
    && tar xzf /tmp/camunda.tar.gz -C /camunda server --strip 2 \
    && cp /tmp/camunda-${DISTRO}.sh /camunda/camunda.sh \
    && chmod +x /camunda/camunda.sh


RUN MYSQL_VERSION=5.1.21 \
    && wget -O /tmp/mysql-connector-java-${MYSQL_VERSION}.jar "https://repo1.maven.org/maven2/mysql/mysql-connector-java/${MYSQL_VERSION}/mysql-connector-java-${MYSQL_VERSION}.jar" \ 
    && cp /tmp/mysql-connector-java-${MYSQL_VERSION}.jar /camunda/lib 

# remove default CATALINA_OPTS from environment settings
RUN echo "" > /camunda/bin/setenv.sh

COPY bpm-platform.xml /camunda/conf/

RUN xmlstarlet ed --inplace -u "//Connector[@protocol='HTTP/1.1']/@port" -v ${TOMCAT_PORT} /camunda/conf/server.xml
RUN rm -f /tmp/camunda-${DISTRO}.sh \
    && rm -f /tmp/camunda.tar.gz

ADD apache_default /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite ssl headers

# Set Docker Environment variables
ENV HOST=${HOST}
ENV API_DB_USERNAME=${DB_USERNAME}
ENV API_DB_PASSWORD=${DB_PASSWORD}
ENV DB_USERNAME=${DB_USERNAME}
ENV DB_PASSWORD=${DB_PASSWORD}
ENV CAMUNDA_VERSION=${VERSION}
ENV DB_DRIVER=com.mysql.jdbc.Driver
ENV DB_URL=jdbc:mysql://${DB_HOST}/${CAMUNDA_DB}
ENV API_DB_URL=jdbc:mysql://${DB_HOST}/${API_DB}
ENV API_DB=${API_DB}
ENV QUARTZ_DB=${QUARTZ_DB}
ENV TEST_API_DB=${TEST_API_DB}
ENV DB_HOST=${DB_HOST}
ENV ELASTIC_CLUSTER=${ELASTIC_CLUSTER}
ENV DB_CONN_MAXACTIVE=20
ENV DB_CONN_MINIDLE=5
ENV DB_CONN_MAXIDLE=20
ENV SKIP_DB_CONFIG=
ENV WAIT_FOR=
ENV WAIT_FOR_TIMEOUT=30
ENV TZ=UTC
ENV DEBUG=false

RUN mkdir /services

RUN sed -i "s/export APACHE_RUN_GROUP=www-data/export APACHE_RUN_GROUP=staff/" /etc/apache2/envvars

RUN sed -ri -e "s/^upload_max_filesize.*/upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE}/" \
    -e "s/^post_max_size.*/post_max_size = ${PHP_POST_MAX_SIZE}/" \
    -e "s/^memory_limit.*/memory_limit = ${PHP_MEMORY_LIMIT}/" /etc/php/7.2/apache2/php.ini
RUN sed -i "s/.*bind-address.*/bind-address = 0.0.0.0/" /etc/mysql/my.cnf
RUN sed -i "s/.*Listen 80*/Listen 8080/" /etc/apache2/ports.conf
RUN sed -i "s/.*Listen 808080*/Listen 8080/" /etc/apache2/ports.conf
RUN sed -i "s/user.*/user = www-data/" /etc/mysql/mysql.conf.d/mysqld.cnf
RUN sed -i "s/.*bind-address.*/bind-address = 0.0.0.0/" /etc/mysql/mysql.conf.d/mysqld.cnf
RUN mkdir -p /var/run/mysqld \
    && chmod 755 /etc/mysql/conf.d/my.cnf \
    && chmod -R 770 /var/lib/mysql \
    && chmod -R 770 /var/run/mysqld \
    && chown -R www-data:staff /var/lib/mysql \
    && chown -R www-data:staff /var/run/mysqld \
    && chown -R www-data:staff /var/log/mysql
RUN npm install -g npm
RUN update-alternatives --set php /usr/bin/php7.2 && update-alternatives --set phar /usr/bin/phar7.2 && update-alternatives --set phar.phar /usr/bin/phar.phar7.2 && update-alternatives --set phpize /usr/bin/phpize7.2 && update-alternatives --set php-config /usr/bin/php-config7.2

CMD ["/services/run.sh"]
