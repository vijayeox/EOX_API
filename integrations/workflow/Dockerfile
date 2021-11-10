FROM openjdk:8u181-jre-alpine3.8

ARG DISTRO=tomcat
ARG VERSION=7.9.0
ARG TOMCAT_PORT=8090
ARG CAMUNDA_REPO="https://camunda.org/release/camunda-bpm/tomcat/7.9/"

RUN apk add --no-cache \
        bash \
        ca-certificates \
        tar \
        wget \
        tzdata \
        tini \
        xmlstarlet \
    && wget -O /usr/local/bin/wait-for-it.sh "https://raw.githubusercontent.com/vishnubob/wait-for-it/db049716e42767d39961e95dd9696103dca813f1/wait-for-it.sh" \
    && chmod +x /usr/local/bin/wait-for-it.sh

COPY ./camunda-tomcat.sh /tmp/

COPY ./IdentityService/dist/identity_plugin.jar /camunda/lib/

COPY ./ProcessEngine/dist/processengine_plugin.jar /camunda/lib/

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

COPY ./bpm-platform.xml /camunda/conf/

RUN xmlstarlet ed --inplace -u "//Connector[@protocol='HTTP/1.1']/@port" -v ${TOMCAT_PORT} /camunda/conf/server.xml
RUN rm -f /tmp/camunda-${DISTRO}.sh \
    && rm -f /tmp/camunda.tar.gz

ARG DB_HOST
ARG API_DB
ARG CAMUNDA_DB

ENV CAMUNDA_VERSION=${VERSION}
ENV DB_DRIVER=com.mysql.jdbc.Driver
ENV DB_URL=jdbc:mysql://${DB_HOST}/${CAMUNDA_DB}
ENV API_DB_URL=jdbc:mysql://${DB_HOST}/${API_DB}
ENV DB_CONN_MAXACTIVE=20
ENV DB_CONN_MINIDLE=5
ENV DB_CONN_MAXIDLE=20
ENV SKIP_DB_CONFIG=
ENV WAIT_FOR=
ENV WAIT_FOR_TIMEOUT=30
ENV TZ=UTC
ENV DEBUG=false
ENV JAVA_OPTS="-Xmx768m -XX:MaxMetaspaceSize=256m"

EXPOSE 8090 8000

RUN addgroup -g 1000 -S camunda && \
adduser -u 1000 -S camunda -G camunda -h /camunda -s /bin/bash -D camunda

# RUN chown camunda:camunda -R /camunda

WORKDIR /camunda

# USER camunda
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["./camunda.sh"]
